<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use Nowo\WordTemplateBundle\Tests\Fixtures\RecordingLogger;
use PhpOffice\PhpWord\Exception\Exception as PhpWordException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

use function bin2hex;
use function html_entity_decode;
use function preg_replace;
use function random_bytes;
use function strip_tags;
use function sys_get_temp_dir;
use function trim;
use function unlink;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * A TableRows whose row variable sits inside a false conditional block is skipped, not a PHPWord cloneRow error.
 */
final class TableRowsInHiddenBlockTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function quotes(): iterable
    {
        yield 'straight quotes' => ['"', '"'];
        yield 'typographic quotes' => ["\u{201C}", "\u{201D}"];
    }

    #[DataProvider('quotes')]
    public function testExpressionBlockShownAndHidden(string $open, string $close): void
    {
        $template  = $this->template('${#if HAY_TOMADORSOLIDARIO == ' . $open . 'Sí' . $close . '}', '${#endif}');
        $processor = new WordTemplateProcessor(conditionalExpressionsEnabled: true);

        self::assertSame('Ana X', $this->render($processor, $template, $this->context('Sí')));

        $logger    = new RecordingLogger();
        $processor = new WordTemplateProcessor(conditionalExpressionsEnabled: true, logger: $logger);
        self::assertSame('', $this->render($processor, $template, $this->context('No')));
        self::assertSame('debug', $logger->records[0]['level']);
        self::assertSame('TABLE_3_NOMBRE', $logger->records[0]['context']['row_variable']);
    }

    public function testNamedConditionalBlockShownAndHidden(): void
    {
        $template  = $this->template('${#if solidario}', '${#endif solidario}');
        $processor = new WordTemplateProcessor();

        $shown  = $this->context('Sí') + ['solidario' => new ConditionalBlock('solidario', true)];
        $hidden = $this->context('No') + ['solidario' => new ConditionalBlock('solidario', false)];

        self::assertSame('Ana X', $this->render($processor, $template, $shown));
        self::assertSame('', $this->render($processor, $template, $hidden));
    }

    public function testRowVariableNeverInTemplateStillThrows(): void
    {
        $template = $this->template('${#if solidario}', '${#endif solidario}');

        $this->expectException(PhpWordException::class);
        $this->expectExceptionMessage('Can not clone row, template variable not found or variable contains markup.');
        $this->render(new WordTemplateProcessor(), $template, [
            'solidario' => new ConditionalBlock('solidario', true),
            'TABLE_9_X' => new TableRows('TABLE_9_X', [['TABLE_9_X' => 'Ana']]),
        ]);
    }

    public function testDuplicateRowVariableStillThrows(): void
    {
        $template = $this->template('${#if solidario}', '${#endif solidario}');
        $rows     = new TableRows('TABLE_3_NOMBRE', [['TABLE_3_NOMBRE' => 'Ana', 'TABLE_3_NIF' => 'X']]);

        $this->expectException(PhpWordException::class);
        $this->expectExceptionMessage('Can not clone row, template variable not found or variable contains markup.');
        $this->render(new WordTemplateProcessor(), $template, [
            'solidario' => new ConditionalBlock('solidario', true),
            'first'     => $rows,
            'second'    => $rows,
        ]);
    }

    public function testEmptyLoopHidesTableRowsWithoutError(): void
    {
        $template = $this->template('${#for x in items}', '${#endfor}');
        $out      = $this->render(new WordTemplateProcessor(loopsEnabled: true), $template, ['items' => []] + $this->context('No'));

        self::assertSame('', $out);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(string $answer): array
    {
        return [
            'HAY_TOMADORSOLIDARIO' => $answer,
            'TABLE_3_NOMBRE'       => new TableRows('TABLE_3_NOMBRE', [['TABLE_3_NOMBRE' => 'Ana', 'TABLE_3_NIF' => 'X']]),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(WordTemplateProcessor $processor, string $template, array $context): string
    {
        $out = $processor->process($template, $context);

        try {
            $zip = new ZipArchive();
            self::assertTrue($zip->open($out->path()));
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();
            $xml = (string) preg_replace('#</w:p>#', ' ', $xml);

            return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8')));
        } finally {
            $out->dispose();
        }
    }

    private function template(string $open, string $close): string
    {
        $path    = sys_get_temp_dir() . '/nowo_word_hidden_' . bin2hex(random_bytes(8)) . '.docx';
        $escaped = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);

        try {
            $pw      = new PhpWord();
            $section = $pw->addSection();
            $section->addText($open);
            $table = $section->addTable();
            $row   = $table->addRow();
            $row->addCell(3000)->addText('${TABLE_3_NOMBRE}');
            $row->addCell(3000)->addText('${TABLE_3_NIF}');
            $section->addText($close);
            IOFactory::createWriter($pw, 'Word2007')->save($path);
        } finally {
            Settings::setOutputEscapingEnabled($escaped);
        }

        $this->files[] = $path;

        return $path;
    }
}
