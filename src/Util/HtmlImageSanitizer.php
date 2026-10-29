<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Util;

use DOMDocument;
use DOMElement;
use Nowo\WordTemplateBundle\Exception\PathNotAllowedException;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use PhpOffice\PhpWord\Shared\Html;

use function base64_decode;
use function bin2hex;
use function chmod;
use function file_put_contents;
use function getimagesizefromstring;
use function html_entity_decode;
use function intdiv;
use function is_array;
use function is_file;
use function iterator_to_array;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function realpath;
use function rtrim;
use function str_replace;
use function stripos;
use function strlen;
use function sys_get_temp_dir;
use function trim;
use function unlink;
use function urldecode;

use const DIRECTORY_SEPARATOR;
use const ENT_QUOTES;
use const IMAGETYPE_BMP;
use const IMAGETYPE_GIF;
use const IMAGETYPE_JPEG;
use const IMAGETYPE_PNG;
use const LIBXML_NONET;

/**
 * Rewrites {@code <img src>} in an {@see HtmlContent} fragment before it
 * reaches PHPWord {@see Html::addHtml()}.
 *
 * PHPWord's {@code parseImage()} calls {@code file_get_contents()} on any {@code src} (remote URLs → SSRF,
 * stream wrappers, local files outside {@code allowed_roots}) and writes {@code data:} images to temp files
 * it never deletes. This class keeps only:
 *
 * - {@code data:image/(png|jpeg|gif|bmp);base64,…} payloads up to {@code $maxImageBytes}, decoded here into
 *   temp files tracked for {@see cleanup()};
 * - local filesystem paths that exist and pass {@see PathAllowlist} — only when {@code allowed_roots} is
 *   configured (an empty allowlist rejects local paths from HTML).
 *
 * Every other {@code <img>} element is dropped. One instance per merge (holds the temp file list).
 *
 * @internal
 */
final class HtmlImageSanitizer
{
    private const IMAGE_TYPES = [
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_BMP  => 'bmp',
    ];

    /** @var list<string> */
    private array $tempFiles = [];

    private int $droppedImages = 0;

    /**
     * @param list<string> $allowedRoots same roots as {@code nowo_word_template.allowed_roots}
     * @param int $maxImageBytes maximum decoded size of one {@code data:} image
     * @param string|null $tempDir directory for decoded {@code data:} images; null uses {@see sys_get_temp_dir()}
     */
    public function __construct(
        private readonly array $allowedRoots,
        private readonly int $maxImageBytes,
        private readonly ?string $tempDir = null,
    ) {
    }

    /**
     * Returns the fragment with every {@code <img>} either rewritten to a safe local file or removed.
     *
     * The fragment is pre-processed exactly like {@see Html::addHtml()} does (entity decoding included) before
     * looking for images, so `&#60;img …&#62;` cannot slip past. Fragments that contain no {@code <img} after
     * that step are returned unchanged. When the parse fails the fragment is dropped (fail closed): PHPWord
     * parses the same decoded XML and would render nothing safe from it.
     */
    public function sanitize(string $html): string
    {
        $xml = $this->preprocessLikePhpWord($html);
        if (stripos($xml, '<img') === false) {
            return $html;
        }

        $dom            = new DOMDocument();
        $previousLibxml = libxml_use_internal_errors(true);
        $loaded         = $dom->loadXML('<body>' . $xml . '</body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxml);

        $body = $dom->documentElement;
        if (!$loaded || !$body instanceof DOMElement) {
            // @igor-ignore - Per-merge instance created inside process(), never a shared service.
            ++$this->droppedImages;

            return '';
        }

        /** @var list<DOMElement> $images */
        $images = iterator_to_array($dom->getElementsByTagName('img'), false);
        foreach ($images as $img) {
            $safe = $this->safeSource($img->getAttribute('src'));
            if ($safe === null) {
                $img->parentNode?->removeChild($img);
                // @igor-ignore - Per-merge instance created inside process(), never a shared service.
                ++$this->droppedImages;

                continue;
            }
            $img->setAttribute('src', $safe);
        }

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $dom->saveXML($child);
        }

        return $out;
    }

    /**
     * Deletes temp files written for {@code data:} images. Call after the document has been saved.
     */
    public function cleanup(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        // @igor-ignore - Per-merge instance created inside process(), never a shared service.
        $this->tempFiles = [];
    }

    /**
     * Number of {@code <img>} elements removed by {@see self::sanitize()} since this instance was created.
     */
    public function droppedImages(): int
    {
        return $this->droppedImages;
    }

    /**
     * Returns the temp files written for {@code data:} images and not yet removed by {@see self::cleanup()}.
     *
     * @return list<string>
     */
    public function tempFiles(): array
    {
        return $this->tempFiles;
    }

    private function safeSource(string $src): ?string
    {
        $src = trim($src);
        if ($src === '') {
            return null;
        }

        if (preg_match('#^data:#i', $src) === 1) {
            return $this->materializeDataUri($src);
        }

        return $this->allowedLocalPath($src);
    }

    private function materializeDataUri(string $src): ?string
    {
        if (preg_match('#^data:image/(png|jpe?g|gif|bmp);base64,(.*)$#is', $src, $match) !== 1) {
            return null;
        }

        $payload = (string) preg_replace('/\s+/', '', $match[2]);
        // Reject oversized payloads before decoding (4 base64 chars → 3 bytes).
        if (intdiv(strlen($payload), 4) * 3 > $this->maxImageBytes + 3) {
            return null;
        }

        $bytes = base64_decode($payload, true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > $this->maxImageBytes) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        if (!is_array($info) || !isset(self::IMAGE_TYPES[$info[2]])) {
            return null;
        }

        $path = rtrim($this->tempDir ?? sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'nowo_word_html_img_' . bin2hex(random_bytes(8)) . '.' . self::IMAGE_TYPES[$info[2]];
        if (urldecode($path) !== $path || @file_put_contents($path, $bytes) !== strlen($bytes)) {
            @unlink($path);

            return null;
        }
        chmod($path, 0o600);
        // @igor-ignore - Per-merge instance created inside process(), never a shared service.
        $this->tempFiles[] = $path;

        return $path;
    }

    private function allowedLocalPath(string $src): ?string
    {
        if ($this->allowedRoots === []) {
            return null;
        }

        // Any URI scheme (http:, ftp:, file:, php:, phar:, …) is refused; a Windows drive letter is not a scheme.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $src) === 1 && preg_match('#^[a-z]:[\\\\/]#i', $src) !== 1) {
            return null;
        }
        try {
            PathAllowlist::assertUnderRoots($src, $this->allowedRoots, true);
        } catch (PathNotAllowedException) {
            return null;
        }

        $real = realpath($src);
        // PHPWord url-decodes the src again; only hand over paths that survive that unchanged.
        if ($real === false || !is_file($real) || urldecode($real) !== $real) {
            return null;
        }

        return $real;
    }

    /**
     * Same string pre-processing as {@see Html::addHtml()} (PHPWord 1.x) so the DOM seen here matches PHPWord's.
     */
    private function preprocessLikePhpWord(string $html): string
    {
        $html = str_replace(["\n", "\r"], '', $html);
        $html = str_replace(['&lt;', '&gt;', '&amp;', '&quot;'], ['_lt_', '_gt_', '_amp_', '_quot_'], $html);
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        $html = str_replace('&', '&amp;', $html);

        return str_replace(['_lt_', '_gt_', '_amp_', '_quot_'], ['&lt;', '&gt;', '&amp;', '&quot;'], $html);
    }
}
