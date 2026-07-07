<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use Nowo\WordTemplateBundle\Exception\InvalidContextValueException;
use Nowo\WordTemplateBundle\Exception\TemplateNotFoundException;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\ImageSource;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;
use ZipArchive;

use function is_string;

final class WordTemplateProcessorIntegrationTest extends TestCase
{
    public function testReplacesBooleanAndNullScalars(): void
    {
        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $pw->addSection()->addText('Flag=${flag} Empty=${empty}');
        });

        $processor = new WordTemplateProcessor();
        $out       = $processor->process($tpl, [
            'flag'  => true,
            'empty' => null,
        ]);

        try {
            $xml = $this->readMainDocumentXml($out->path());
            self::assertStringContainsString('Flag=1', $xml);
            self::assertStringContainsString('Empty=', $xml);
            self::assertStringNotContainsString('${flag}', $xml);
        } finally {
            $out->dispose();
            @unlink($tpl);
        }
    }

    public function testReplacesScalarPlaceholders(): void
    {
        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('Hello ${name}, today is ${day}.');
        });

        $processor = new WordTemplateProcessor();
        $out       = $processor->process($tpl, [
            'name' => 'María',
            'day'  => 'Thursday',
        ]);

        try {
            $xml = $this->readMainDocumentXml($out->path());
            self::assertStringContainsString('María', $xml);
            self::assertStringContainsString('Thursday', $xml);
            self::assertStringNotContainsString('${name}', $xml);
        } finally {
            $out->dispose();
            @unlink($tpl);
        }
    }

    public function testNestedContextKeysBecomeDotPlaceholders(): void
    {
        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('City: ${client.city}');
        });

        $processor = new WordTemplateProcessor();
        $out       = $processor->process($tpl, [
            'client' => ['city' => 'Sevilla'],
        ]);

        try {
            $xml = $this->readMainDocumentXml($out->path());
            self::assertStringContainsString('Sevilla', $xml);
        } finally {
            $out->dispose();
            @unlink($tpl);
        }
    }

    public function testHtmlContentInsertsRichMarkup(): void
    {
        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('Before ${rich} After');
        });

        $processor = new WordTemplateProcessor();
        $out       = $processor->process($tpl, [
            'rich' => new HtmlContent('<p><strong>Bold</strong> and <em>italic</em></p><p>Second paragraph.</p>'),
        ]);

        try {
            $xml = $this->readMainDocumentXml($out->path());
            self::assertStringContainsString('Bold', $xml);
            self::assertStringContainsString('italic', $xml);
            self::assertStringContainsString('Second paragraph.', $xml);
        } finally {
            $out->dispose();
            @unlink($tpl);
        }
    }

    public function testTableRowsCloneAndFill(): void
    {
        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $table   = $section->addTable();
            $table->addRow();
            $table->addCell(1200)->addText('${rid}');
            $table->addCell(2400)->addText('${label}');
        });

        $processor = new WordTemplateProcessor();
        $out       = $processor->process($tpl, [
            'lines' => new TableRows('rid', [
                ['rid' => '10', 'label' => 'Alpha'],
                ['rid' => '20', 'label' => 'Beta'],
            ]),
        ]);

        try {
            $xml = $this->readMainDocumentXml($out->path());
            self::assertStringContainsString('Alpha', $xml);
            self::assertStringContainsString('Beta', $xml);
            self::assertStringContainsString('10', $xml);
            self::assertStringContainsString('20', $xml);
        } finally {
            $out->dispose();
            @unlink($tpl);
        }
    }

    public function testWritesToExplicitOutputPath(): void
    {
        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $pw->addSection()->addText('X=${v}');
        });

        $target = sys_get_temp_dir() . '/nowo_word_out_' . bin2hex(random_bytes(6)) . '.docx';

        $processor = new WordTemplateProcessor();
        $out       = $processor->process($tpl, ['v' => 'ok'], $target);

        try {
            self::assertFalse($out->isTemporary());
            self::assertSame($target, $out->path());
            self::assertFileExists($target);
            self::assertStringContainsString('ok', $this->readMainDocumentXml($target));
        } finally {
            @unlink($target);
            @unlink($tpl);
        }
    }

    public function testImagePlaceholderIsEmbedded(): void
    {
        $pngPath = sys_get_temp_dir() . '/nowo_word_1x1_' . bin2hex(random_bytes(4)) . '.png';
        file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $pw->addSection()->addText('${photo}');
        });

        $processor = new WordTemplateProcessor();
        $out       = $processor->process($tpl, [
            'photo' => new ImageSource($pngPath, 40, 20),
        ]);

        try {
            self::assertGreaterThan(5000, filesize($out->path()) ?: 0);
            $zip = new ZipArchive();
            self::assertTrue($zip->open($out->path()));
            $hasMedia = false;
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = $zip->getNameIndex($i);
                if (is_string($name) && str_starts_with($name, 'word/media/')) {
                    $hasMedia = true;

                    break;
                }
            }

            $zip->close();
            self::assertTrue($hasMedia, 'DOCX should contain word/media after image merge.');
        } finally {
            $out->dispose();
            @unlink($tpl);
            @unlink($pngPath);
        }
    }

    public function testImagePlaceholderAcceptsFilesystemPathOnly(): void
    {
        $pngPath = sys_get_temp_dir() . '/nowo_word_1x1b_' . bin2hex(random_bytes(4)) . '.png';
        file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $pw->addSection()->addText('${pic}');
        });

        $out = (new WordTemplateProcessor())->process($tpl, [
            'pic' => new ImageSource($pngPath),
        ]);

        try {
            self::assertGreaterThan(4000, filesize($out->path()) ?: 0);
        } finally {
            $out->dispose();
            @unlink($tpl);
            @unlink($pngPath);
        }
    }

    public function testThrowsWhenTemplateMissing(): void
    {
        $this->expectException(TemplateNotFoundException::class);

        (new WordTemplateProcessor())->process('/nonexistent/path/template.docx', []);
    }

    public function testListVariablesReturnsUniquePlaceholderNames(): void
    {
        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('Hello ${name}, city ${client.city}, again ${name}.');
        });

        try {
            $variables = (new WordTemplateProcessor())->listVariables($tpl);
            sort($variables);

            self::assertSame(['client.city', 'name'], $variables);
        } finally {
            @unlink($tpl);
        }
    }

    public function testListVariablesRespectsCustomMacroDelimiters(): void
    {
        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $pw->addSection()->addText('{{company}} and ${ignored}');
        });

        try {
            $variables = (new WordTemplateProcessor('{{', '}}'))->listVariables($tpl);

            self::assertSame(['company'], $variables);
        } finally {
            @unlink($tpl);
        }
    }

    public function testListVariablesThrowsWhenTemplateMissing(): void
    {
        $this->expectException(TemplateNotFoundException::class);

        (new WordTemplateProcessor())->listVariables('/nonexistent/path/template.docx');
    }

    public function testThrowsWhenTableRowsEmpty(): void
    {
        $tpl = $this->createTemplate(static function (PhpWord $pw): void {
            $pw->addSection()->addText('${x}');
        });

        $this->expectException(InvalidContextValueException::class);

        try {
            (new WordTemplateProcessor())->process($tpl, [
                't' => new TableRows('x', []),
            ]);
        } finally {
            @unlink($tpl);
        }
    }

    /**
     * @param callable(PhpWord): void $build
     */
    private function createTemplate(callable $build): string
    {
        $path = sys_get_temp_dir() . '/nowo_word_tpl_' . bin2hex(random_bytes(8)) . '.docx';
        $pw   = new PhpWord();
        $build($pw);
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
