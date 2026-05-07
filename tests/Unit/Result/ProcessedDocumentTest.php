<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Result;

use Nowo\WordTemplateBundle\Result\ProcessedDocument;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProcessedDocumentTest extends TestCase
{
    public function testReadContentsAndDispose(): void
    {
        $path = sys_get_temp_dir() . '/nowo_word_tpl_doc_test_' . bin2hex(random_bytes(4)) . '.bin';
        file_put_contents($path, 'hello-doc');

        $doc = new ProcessedDocument($path, true);

        self::assertSame($path, $doc->path());
        self::assertTrue($doc->isTemporary());
        self::assertSame('hello-doc', $doc->readContents());

        $doc->dispose();

        self::assertFileDoesNotExist($path);
    }

    public function testReadContentsThrowsWhenFileDoesNotExist(): void
    {
        $doc = new ProcessedDocument(sys_get_temp_dir() . '/nowo_word_missing_' . bin2hex(random_bytes(8)) . '.docx', false);

        $this->expectException(RuntimeException::class);
        $doc->readContents();
    }

    public function testDisposeKeepsNonTemporaryFile(): void
    {
        $path = sys_get_temp_dir() . '/nowo_word_tpl_keep_' . bin2hex(random_bytes(4)) . '.bin';
        file_put_contents($path, 'x');

        $doc = new ProcessedDocument($path, false);
        $doc->dispose();

        self::assertFileExists($path);
        @unlink($path);
    }
}
