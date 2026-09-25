<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use Nowo\WordTemplateBundle\Exception\InvalidContextValueException;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Processor\PhpWordHtmlState;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Html;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use ZipArchive;

/**
 * One processor instance serving consecutive requests in the same PHP process (FrankenPHP worker, no reset).
 */
final class WorkerModeIsolationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/nowo_word_tpl_worker_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir);
        Settings::setTempDir($this->tempDir);
    }

    protected function tearDown(): void
    {
        Settings::setTempDir('');
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }

    public function testStyleSheetFromOneRequestDoesNotStyleTheNext(): void
    {
        $tpl       = $this->createTemplate();
        $processor = new WordTemplateProcessor();

        // Request 1 (user A) ships a <style> block.
        $out1 = $processor->process($tpl, [
            'rich' => new HtmlContent('<style>.hl { color: #FF0000; }</style><p class="hl">Alpha</p>'),
        ]);
        // Request 2 (user B) uses the same class name without any stylesheet.
        $out2 = $processor->process($tpl, [
            'rich' => new HtmlContent('<p class="hl">Beta</p>'),
        ]);

        try {
            $xml1 = $this->readMainDocumentXml($out1->path());
            $xml2 = $this->readMainDocumentXml($out2->path());

            self::assertStringContainsString('Alpha', $xml1);
            self::assertStringContainsString('FF0000', $xml1);
            self::assertStringContainsString('Beta', $xml2);
            self::assertStringNotContainsString('FF0000', $xml2);
        } finally {
            $out1->dispose();
            $out2->dispose();
            @unlink($tpl);
        }

        self::assertNull((new ReflectionProperty(Html::class, 'css'))->getValue());
        self::assertNull((new ReflectionProperty(Html::class, 'xpath'))->getValue());
    }

    public function testApplicationHtmlStateIsRestoredAfterProcess(): void
    {
        $css = new ReflectionProperty(Html::class, 'css');
        $css->setValue(null, 'app-state');

        $tpl = $this->createTemplate();
        try {
            (new WordTemplateProcessor())->process($tpl, ['rich' => new HtmlContent('<p>x</p>')])->dispose();
        } finally {
            @unlink($tpl);
        }

        self::assertSame('app-state', $css->getValue());
        $css->setValue(null, null);
    }

    public function testIsolateSkipsPropertiesMissingInInstalledPhpWord(): void
    {
        self::assertSame([], PhpWordHtmlState::isolate(['doesNotExist']));
        PhpWordHtmlState::restore(['doesNotExist' => 'x']);
        $this->addToAssertionCount(1);
    }

    public function testNoPhpWordWorkingCopiesAreLeftBehind(): void
    {
        $tpl       = $this->createTemplate();
        $processor = new WordTemplateProcessor();

        try {
            self::assertContains('rich', $processor->listVariables($tpl));
            self::assertSame([], $processor->listConditionalBlocks($tpl));

            try {
                $processor->process($tpl, ['rows' => new TableRows('row', [])]);
                self::fail('Expected InvalidContextValueException');
            } catch (InvalidContextValueException) {
            }

            $processor->process($tpl, ['rich' => 'plain'])->dispose();
        } finally {
            @unlink($tpl);
        }

        self::assertSame([], glob($this->tempDir . '/PhpWord*') ?: []);
    }

    private function createTemplate(): string
    {
        $path = sys_get_temp_dir() . '/nowo_word_tpl_' . bin2hex(random_bytes(8)) . '.docx';
        $pw   = new PhpWord();
        $pw->addSection()->addText('${rich}');
        IOFactory::createWriter($pw, 'Word2007')->save($path);

        return $path;
    }

    private function readMainDocumentXml(string $docxPath): string
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($docxPath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        self::assertIsString($xml);

        return $xml;
    }
}
