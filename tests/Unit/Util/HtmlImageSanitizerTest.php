<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Util;

use Nowo\WordTemplateBundle\Util\HtmlImageSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function str_repeat;

final class HtmlImageSanitizerTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nowo_wt_html_root_' . bin2hex(random_bytes(4));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->root);
    }

    public function testHtmlWithoutImagesIsReturnedUnchanged(): void
    {
        $html = '<p>Hello &amp; <strong>world</strong></p>';

        self::assertSame($html, (new HtmlImageSanitizer([], 1024))->sanitize($html));
    }

    public function testUnparsableHtmlIsReturnedUnchanged(): void
    {
        $html = '<p><img src="http://example.test/a.png"></p';

        self::assertSame($html, (new HtmlImageSanitizer([], 1024))->sanitize($html));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRejectedSources(): iterable
    {
        yield 'remote http (SSRF)' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'remote https' => ['https://attacker.example/pixel.png'];
        yield 'protocol-relative' => ['//attacker.example/pixel.png'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'php filter' => ['php://filter/resource=/etc/passwd'];
        yield 'phar' => ['phar:///tmp/evil.phar/x.png'];
        yield 'absolute local path outside roots' => ['/etc/passwd'];
        yield 'traversal' => ['../../../../etc/passwd'];
        yield 'empty' => [''];
        yield 'data non-image' => ['data:text/html;base64,PHNjcmlwdD4='];
        yield 'data svg' => ['data:image/svg+xml;base64,PHN2Zy8+'];
        yield 'data not base64' => ['data:image/png;base64,%%%'];
        yield 'data not an image' => ['data:image/png;base64,' . base64_encode('not an image')];
        yield 'data embedded in url' => ['http://attacker.example/?data:image/png;base64,' . self::PNG_1X1];
    }

    #[DataProvider('provideRejectedSources')]
    public function testRejectedSourcesAreDropped(string $src): void
    {
        $sanitizer = new HtmlImageSanitizer([$this->root], 1024 * 1024);

        $out = $sanitizer->sanitize('<p>Before<img src="' . $src . '" width="10"/>After</p>');

        self::assertSame('<p>BeforeAfter</p>', $out);
        self::assertSame([], $sanitizer->tempFiles());
    }

    public function testImageWithoutSrcIsDropped(): void
    {
        self::assertSame('<p>x</p>', (new HtmlImageSanitizer([], 1024))->sanitize('<p>x<img/></p>'));
    }

    public function testDataImageIsWrittenToTrackedTempFileAndCleanedUp(): void
    {
        $sanitizer = new HtmlImageSanitizer([], 1024);

        $out   = $sanitizer->sanitize('<p><img src="data:image/png;base64,' . self::PNG_1X1 . '" width="5"/></p>');
        $files = $sanitizer->tempFiles();

        self::assertCount(1, $files);
        self::assertFileExists($files[0]);
        self::assertStringEndsWith('.png', $files[0]);
        self::assertSame('<p><img src="' . $files[0] . '" width="5"/></p>', $out);

        $sanitizer->cleanup();

        self::assertFileDoesNotExist($files[0]);
        self::assertSame([], $sanitizer->tempFiles());
    }

    public function testDataImageIsDroppedWhenTempFileCannotBeWritten(): void
    {
        $sanitizer = new HtmlImageSanitizer([], 1024, $this->root . '/missing-dir');

        self::assertSame('<p/>', $sanitizer->sanitize('<p><img src="data:image/png;base64,' . self::PNG_1X1 . '"/></p>'));
        self::assertSame([], $sanitizer->tempFiles());
        self::assertSame(1, $sanitizer->droppedImages());
    }

    public function testDroppedImagesCountsEveryRemovedImage(): void
    {
        $sanitizer = new HtmlImageSanitizer([], 1024, $this->root);

        $sanitizer->sanitize('<p><img src="https://example.invalid/a.png"/><img src="data:image/png;base64,' . self::PNG_1X1 . '"/><img/></p>');

        self::assertSame(2, $sanitizer->droppedImages());
        self::assertCount(1, $sanitizer->tempFiles());
        self::assertStringStartsWith($this->root . '/nowo_word_html_img_', $sanitizer->tempFiles()[0]);
        $sanitizer->cleanup();
    }

    public function testOversizedDataImageIsDroppedBeforeDecoding(): void
    {
        $sanitizer = new HtmlImageSanitizer([], 16);

        $out = $sanitizer->sanitize('<p><img src="data:image/png;base64,' . self::PNG_1X1 . '"/></p>');

        self::assertSame('<p/>', $out);
        self::assertSame([], $sanitizer->tempFiles());
    }

    public function testDataImageJustOverDecodedCapIsDropped(): void
    {
        // 70 decoded bytes; the cheap pre-check lets it through, the exact check rejects it.
        $sanitizer = new HtmlImageSanitizer([], 69);

        self::assertSame('<p/>', $sanitizer->sanitize('<p><img src="data:image/png;base64,' . self::PNG_1X1 . '"/></p>'));
    }

    public function testLocalPathsAreRejectedWithoutAllowedRoots(): void
    {
        $file = $this->root . '/pic.png';
        file_put_contents($file, base64_decode(self::PNG_1X1, true));

        self::assertSame('<p/>', (new HtmlImageSanitizer([], 1024))->sanitize('<p><img src="' . $file . '"/></p>'));
    }

    public function testLocalPathUnderAllowedRootIsKept(): void
    {
        $file = $this->root . '/pic.png';
        file_put_contents($file, base64_decode(self::PNG_1X1, true));
        $real = realpath($file);

        $out = (new HtmlImageSanitizer([$this->root], 1024))->sanitize('<p><img src="' . $this->root . '/./pic.png"/></p>');

        self::assertSame('<p><img src="' . $real . '"/></p>', $out);
    }

    public function testLocalPathUnderAllowedRootThatChangesWhenUrlDecodedIsDropped(): void
    {
        $file = $this->root . '/pic%2e.png';
        file_put_contents($file, base64_decode(self::PNG_1X1, true));

        self::assertSame('<p/>', (new HtmlImageSanitizer([$this->root], 1024))->sanitize('<p><img src="' . $file . '"/></p>'));
    }

    public function testDirectoryUnderAllowedRootIsDropped(): void
    {
        self::assertSame('<p/>', (new HtmlImageSanitizer([$this->root], 1024))->sanitize('<p><img src="' . $this->root . '"/></p>'));
    }

    public function testEntitiesAndTextSurviveRoundTrip(): void
    {
        $sanitizer = new HtmlImageSanitizer([], 1024);

        $out = $sanitizer->sanitize('<p>A &amp; B &lt;c&gt; &quot;q&quot; caf&eacute; ' . str_repeat('x', 3) . '<img src="https://x.test/a.png"/></p>');

        self::assertSame('<p>A &amp; B &lt;c&gt; "q" café xxx</p>', $out);
    }
}
