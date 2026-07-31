<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Util;

use PhpOffice\PhpWord\TemplateProcessor;

use function is_string;
use function strlen;

/**
 * Collapses Word-split placeholder / conditional markers so `${…}` (or configured
 * delimiters) become contiguous text again.
 *
 * Microsoft Word often stores a single visible macro across several {@code <w:r>/<w:t>}
 * runs (spell-check, partial formatting). PHPWord applies a similar heal on load
 * ({@see TemplateProcessor}); this util mirrors that behaviour for
 * conditional discovery/application so the bundle does not depend solely on PHPWord’s
 * internal heal, and works when {@see ConditionalBlockApplicator} is used on raw XML.
 */
final readonly class BrokenMacroNormalizer
{
    public function __construct(
        private string $openingChars,
        private string $closingChars,
    ) {
    }

    /**
     * Builds a normalizer from conditional opening/closing delimiters.
     *
     * Uses the brace prefix of {@code $ifOpening} (characters before {@code #}, e.g.
     * {@code ${} from {@code ${#if}) and {@code $ifClosing} (e.g. {@code }}).
     */
    public static function forConditionalDelimiters(string $ifOpening, string $ifClosing): self
    {
        $hashPos = strpos($ifOpening, '#');
        $opening = ($hashPos !== false && $hashPos > 0)
            ? substr($ifOpening, 0, $hashPos)
            : $ifOpening;

        return new self($opening, $ifClosing);
    }

    public function normalize(string $documentPart): string
    {
        if ($this->openingChars === '' || $this->closingChars === '') {
            return $documentPart;
        }

        // PHPWord-compatible path: multi-char opening (e.g. `${`) + single-char closing (`}`).
        if (strlen($this->openingChars) >= 2 && strlen($this->closingChars) === 1) {
            return $this->normalizePhpWordStyle($documentPart);
        }

        return $this->normalizeLoose($documentPart);
    }

    /**
     * Same shape as PHPWord {@code TemplateProcessor::fixBrokenMacros} for `${` / `}`.
     */
    private function normalizePhpWordStyle(string $documentPart): string
    {
        $firstOpen    = preg_quote($this->openingChars[0], '/');
        $restOpen     = substr($this->openingChars, 1);
        $restQuoted   = preg_quote($restOpen, '/');
        $restFirst    = preg_quote($restOpen[0], '/');
        $closeQuoted  = preg_quote($this->closingChars, '/');
        $closeInClass = preg_quote($this->closingChars, '/');

        $pattern = '/' . $firstOpen . '(?:' . $restQuoted . '|[^{$]*\>' . $restFirst . ')[^' . $closeInClass . '$]*' . $closeQuoted . '/U';

        return $this->replaceWithStrippedTags($pattern, $documentPart);
    }

    /**
     * Looser heal for atypical delimiters: strip XML tags between the opening sequence
     * (possibly interrupted by tags after the first character) and the closing sequence.
     */
    private function normalizeLoose(string $documentPart): string
    {
        $first       = preg_quote($this->openingChars[0], '/');
        $rest        = substr($this->openingChars, 1);
        $closeQuoted = preg_quote($this->closingChars, '/');

        if ($rest === '') {
            $pattern = '/' . $first . '.*?' . $closeQuoted . '/s';
        } else {
            $restQuoted = preg_quote($rest, '/');
            $second     = preg_quote($rest[0], '/');
            $pattern    = '/' . $first . '(?:' . $restQuoted . '|.*?\>' . $second . ').*?' . $closeQuoted . '/s';
        }

        return $this->replaceWithStrippedTags($pattern, $documentPart);
    }

    private function replaceWithStrippedTags(string $pattern, string $documentPart): string
    {
        $normalized = preg_replace_callback(
            $pattern,
            static fn (array $match): string => strip_tags($match[0]),
            $documentPart,
        );

        return is_string($normalized) ? $normalized : $documentPart;
    }
}
