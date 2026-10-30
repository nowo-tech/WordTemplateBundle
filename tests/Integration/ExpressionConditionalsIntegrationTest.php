<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use DateTimeImmutable;
use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\InvoiceStatus;
use Nowo\WordTemplateBundle\Tests\Fixtures\RecordingLogger;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PHPUnit\Framework\TestCase;
use ZipArchive;

use function array_map;
use function bin2hex;
use function html_entity_decode;
use function implode;
use function is_string;
use function preg_replace;
use function random_bytes;
use function str_replace;
use function strip_tags;
use function sys_get_temp_dir;
use function trim;
use function unlink;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * Expression conditionals on real .docx files: body, table cells, headers and footers, mixed with named blocks.
 */
final class ExpressionConditionalsIntegrationTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testBodyTableCellHeaderAndFooter(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $header  = $section->addHeader();
            $header->addText('${#if status == "draft"}');
            $header->addText('DRAFT');
            $header->addText('${#endif}');
            $header->addText('Header');
            $footer = $section->addFooter();
            $footer->addText('${#if total > 1000}');
            $footer->addText('Large order');
            $footer->addText('${#endif}');
            $footer->addText('Footer');

            $section->addText('Invoice ${number}');
            $section->addText('${#if client.country != "ES" and is_company}');
            $section->addText('Reverse charge for ${client.name}.');
            $section->addText('${#endif}');
            $table = $section->addTable();
            $table->addRow();
            $cell = $table->addCell(4000);
            $cell->addText('${#if due_date < today}');
            $cell->addText('Overdue since ${due_date}');
            $cell->addText('${#endif}');
            $cell->addText('Amount ${total}');
        });

        $context = [
            'number'     => 'F-1',
            'status'     => InvoiceStatus::Draft,
            'total'      => 1500,
            'is_company' => true,
            'client'     => ['name' => 'ACME', 'country' => 'PT'],
            'due_date'   => new DateTimeImmutable('2026-10-01'),
            'today'      => new DateTimeImmutable('2026-10-30'),
        ];

        $out = $this->processor()->process($template, $context);

        try {
            self::assertSame('Invoice F-1 Reverse charge for ACME. Overdue since 2026-10-01 Amount 1500', $this->text($out->path(), 'word/document.xml'));
            self::assertSame('DRAFT Header', $this->text($out->path(), 'word/header1.xml'));
            self::assertSame('Large order Footer', $this->text($out->path(), 'word/footer1.xml'));
        } finally {
            $out->dispose();
        }

        $out = $this->processor()->process($template, ['status' => InvoiceStatus::Paid, 'total' => 10, 'client' => ['name' => 'ACME', 'country' => 'ES'], 'due_date' => new DateTimeImmutable('2026-11-01')] + $context);

        try {
            self::assertSame('Invoice F-1 Amount 10', $this->text($out->path(), 'word/document.xml'));
            self::assertSame('Header', $this->text($out->path(), 'word/header1.xml'));
            self::assertSame('Footer', $this->text($out->path(), 'word/footer1.xml'));
        } finally {
            $out->dispose();
        }
    }

    public function testNamedAndExpressionBlocksTogether(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if annex}');
            $section->addText('Annex');
            $section->addText('${#if lines_count > 0}');
            $section->addText('Lines:');
            $section->addText('${code}');
            $section->addText('${#endif}');
            $section->addText('${#if lines_count == 0}');
            $section->addText('No lines');
            $section->addText('${#endif}');
            $section->addText('${#endif annex}');
            $section->addText('${#if annex and vip}');
            $section->addText('Annex for a VIP');
            $section->addText('${#endif}');
        });

        $text = $this->render($template, [
            'blocks'      => [new ConditionalBlock('annex', true), new ConditionalBlock('vip', true)],
            'lines_count' => 0,
        ]);
        self::assertSame('Annex No lines Annex for a VIP', $text);

        $text = $this->render($template, [
            'blocks'      => [new ConditionalBlock('annex', false), new ConditionalBlock('vip', true)],
            'lines_count' => 'not evaluated: the named block is removed first',
        ]);
        self::assertSame('', $text);
    }

    public function testDisabledFlagLeavesExpressionMarkersUntouched(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if total > 0}');
            $section->addText('Due');
            $section->addText('${#endif}');
            $section->addText('${#if annex}');
            $section->addText('Annex');
            $section->addText('${#endif annex}');
        });

        $out = (new WordTemplateProcessor())->process($template, ['total' => 5, 'annex' => new ConditionalBlock('annex', false)]);

        try {
            self::assertSame('${#if total > 0} Due ${#endif}', $this->text($out->path(), 'word/document.xml'));
        } finally {
            $out->dispose();
        }
    }

    public function testErrorModes(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('Start');
            $section->addText('${#if totl > 0}');
            $section->addText('Due');
            $section->addText('${#endif}');
        });

        try {
            $this->processor()->process($template, ['total' => 1]);
            self::fail('ConditionalExpressionException expected');
        } catch (ConditionalExpressionException $e) {
            self::assertSame('totl > 0', $e->expression);
            self::assertStringContainsString('unknown variable "totl"', $e->getMessage());
        }

        $logger = new RecordingLogger();
        self::assertSame('Start', $this->render($template, ['total' => 1], new WordTemplateProcessor(logger: $logger, conditionalExpressionsEnabled: true, conditionalExpressionsOnError: 'hide')));
        $warnings = $logger->recordsWithLevel('warning');
        self::assertCount(1, $warnings);
        self::assertSame(['bundle' => 'word_template', 'action' => 'conditional_expressions', 'template' => basename($template), 'on_error' => 'hide', 'errors' => 1], $warnings[0]['context']);

        self::assertSame('Start ${#if totl > 0} Due ${#endif}', $this->render($template, ['total' => 1], new WordTemplateProcessor(conditionalExpressionsEnabled: true, conditionalExpressionsOnError: 'keep')));
    }

    public function testFunctionsInADocument(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if upper(client.country) == “ES” and length(lines) > 1}');
            $section->addText('Domestic order with several lines');
            $section->addText('${#endif}');
            $section->addText('${#if date_diff_days(today, due_date) <= 7}');
            $section->addText('Due within a week');
            $section->addText('${#endif}');
            $section->addText('${#if round(total, 2) > 0 and number_format(total, 2) == “1,234.50”}');
            $section->addText('Total 1,234.50');
            $section->addText('${#endif}');
            $section->addText('${#if upper(lines) == ""}');
            $section->addText('Bad input');
            $section->addText('${#endif}');
        });

        $context = [
            'client'   => ['country' => 'es'],
            'lines'    => ['A', 'B'],
            'today'    => new DateTimeImmutable('2026-10-30'),
            'due_date' => new DateTimeImmutable('2026-11-03'),
            'total'    => 1234.5,
        ];

        self::assertSame(
            'Domestic order with several lines Due within a week Total 1,234.50',
            $this->render($template, $context, new WordTemplateProcessor(conditionalExpressionsEnabled: true, conditionalExpressionsOnError: 'hide')),
        );

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage('upper() expects a string, got array');
        $this->render($template, $context);
    }

    public function testTableShownOnlyWhenThereAreRows(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if lines != []}');
            $table = $section->addTable();
            $table->addRow();
            $table->addCell(2000)->addText('${line_code}');
            $section->addText('${#endif}');
            $section->addText('${#if lines == []}');
            $section->addText('No items.');
            $section->addText('${#endif}');
        });

        $lines = [['line_code' => 'A-1'], ['line_code' => 'B-2']];
        self::assertSame('A-1 B-2', $this->render($template, ['lines' => $lines, 'rows' => new TableRows('line_code', $lines)]));
        self::assertSame('No items.', $this->render($template, ['lines' => []]));
    }

    public function testListingAndLinting(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addHeader()->addText('${#if status == “paid”}');
            $section->addText('${#if annex}');
            $section->addText('${#endif annex}');
            $section->addText('${#if total > 0 and client.country == ‘ES’}');
            $section->addText('${name}');
            $section->addText('${#endif}');
            $section->addText('${#if missing >}');
            $section->addText('${#endif}');
            $section->addText('${#endif}');
        });

        $enabled  = $this->processor();
        $disabled = new WordTemplateProcessor();

        self::assertSame(['name'], $enabled->listVariables($template));
        self::assertSame(['annex', 'status == “paid”'], $enabled->listConditionalBlocks($template));
        self::assertSame(['annex', 'total &gt; 0 and client.country == ‘ES’', 'missing &gt;', 'status == “paid”'], $disabled->listConditionalBlocks($template));
        self::assertSame(['total > 0 and client.country == \'ES\'', 'missing >'], $enabled->listConditionalExpressions($template));
        self::assertSame($enabled->listConditionalExpressions($template), $disabled->listConditionalExpressions($template));

        $problems = array_map(static fn (ConditionalExpressionException $e): string => $e->getMessage(), $enabled->lintConditionalExpressions($template, ['total']));
        self::assertCount(4, $problems);
        $all = implode("\n", $problems);
        self::assertStringContainsString('"${#endif}": there is no open block to close', $all);
        self::assertStringContainsString('"${#if status == “paid”}": the block is never closed', $all);
        self::assertStringContainsString('unknown variable "client"', $all);
        self::assertStringContainsString('"missing >" is invalid', $all);

        self::assertCount(3, $enabled->lintConditionalExpressions($template, ['total', 'client']));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context, ?WordTemplateProcessor $processor = null): string
    {
        $out = ($processor ?? $this->processor())->process($template, $context);

        try {
            return $this->text($out->path(), 'word/document.xml');
        } finally {
            $out->dispose();
        }
    }

    private function processor(): WordTemplateProcessor
    {
        return new WordTemplateProcessor(conditionalExpressionsEnabled: true);
    }

    private function template(callable $build): string
    {
        $path    = sys_get_temp_dir() . '/nowo_word_expr_' . bin2hex(random_bytes(8)) . '.docx';
        $escaped = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);

        try {
            $pw = new PhpWord();
            $build($pw);
            IOFactory::createWriter($pw, 'Word2007')->save($path);
        } finally {
            Settings::setOutputEscapingEnabled($escaped);
        }

        $this->files[] = $path;

        return $path;
    }

    private function text(string $docxPath, string $part): string
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($docxPath));
        $xml = $zip->getFromName($part);
        $zip->close();
        self::assertTrue(is_string($xml), $part . ' missing');

        $text = strip_tags(str_replace('</w:p>', ' </w:p>', $xml));

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($text, ENT_QUOTES | ENT_XML1)));
    }
}
