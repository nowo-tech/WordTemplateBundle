<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use Nowo\WordTemplateBundle\Tests\Fixtures\RecordingLogger;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PHPUnit\Framework\TestCase;
use ZipArchive;

use function array_map;
use function bin2hex;
use function html_entity_decode;
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
 * Loops on real .docx files: paragraphs, table rows, table cells, headers, nesting with conditionals, listing / linting.
 */
final class LoopsIntegrationTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testInvoiceWithRowLoopParagraphLoopHeaderAndConditions(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $header  = $section->addHeader();
            $header->addText('${#for tag in tags}');
            $header->addText('[${tag}]');
            $header->addText('${#endfor}');

            $section->addText('Invoice ${number} for ${client.name}');
            $table = $section->addTable();
            $table->addRow();
            $table->addCell(2000)->addText('#');
            $table->addCell(3000)->addText('Item');
            $table->addCell(2000)->addText('Amount');
            $table->addRow();
            $table->addCell(2000)->addText('${#for line in lines}');
            $table->addRow();
            $table->addCell(2000)->addText('${loop.index}');
            $table->addCell(3000)->addText('${line.name}');
            $cell = $table->addCell(2000);
            $cell->addText('${line.amount}');
            $cell->addText('${#if line.amount > 100}');
            $cell->addText('(large)');
            $cell->addText('${#endif}');
            $table->addRow();
            $table->addCell(2000)->addText('${#endfor}');
            $table->addRow();
            $table->addCell(2000)->addText('Total');
            $table->addCell(3000)->addText('${total}');

            $section->addText('${#for note in notes}');
            $section->addText('Note ${loop.index} of ${loop.length}: ${note}');
            $section->addText('${#endfor}');
            $section->addText('${#if length(notes) == 0}');
            $section->addText('No notes.');
            $section->addText('${#endif}');
        });

        $lines = [['name' => 'Bolts & nuts', 'amount' => 40], ['name' => 'Crane', 'amount' => 900]];
        $out   = $this->processor()->process($template, [
            'number' => 'F-1',
            'client' => ['name' => 'ACME'],
            'lines'  => $lines,
            'total'  => 940,
            'notes'  => ['Fragile', 'Deliver Monday'],
            'tags'   => ['urgent', 'export'],
        ]);

        try {
            self::assertSame(
                'Invoice F-1 for ACME # Item Amount 1 Bolts & nuts 40 2 Crane 900 (large) Total 940 Note 1 of 2: Fragile Note 2 of 2: Deliver Monday',
                $this->text($out->path(), 'word/document.xml'),
            );
            self::assertSame('[urgent] [export]', $this->text($out->path(), 'word/header1.xml'));
        } finally {
            $out->dispose();
        }

        $out = $this->processor()->process($template, ['number' => 'F-2', 'client' => ['name' => 'B'], 'lines' => [], 'total' => 0, 'notes' => [], 'tags' => []]);

        try {
            self::assertSame('Invoice F-2 for B # Item Amount Total 0 No notes.', $this->text($out->path(), 'word/document.xml'));
        } finally {
            $out->dispose();
        }
    }

    public function testLoopsWorkWithoutConditionalExpressionsAndBesideTableRows(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#for c in covers}');
            $section->addText('Cover ${c}');
            $section->addText('${#endfor}');
            $section->addText('${#if covers}');
            $section->addText('${#endif}');
            $table = $section->addTable();
            $table->addRow();
            $table->addCell(2000)->addText('${code}');
        });

        $processor = new WordTemplateProcessor(loopsEnabled: true);
        $out       = $processor->process($template, ['covers' => ['fire', 'theft'], 'rows' => new TableRows('code', [['code' => 'A'], ['code' => 'B']])]);

        try {
            self::assertSame('Cover fire Cover theft ${#if covers} ${#endif} A B', $this->text($out->path(), 'word/document.xml'));
        } finally {
            $out->dispose();
        }

        $disabled = (new WordTemplateProcessor())->process($template, ['covers' => ['fire'], 'rows' => new TableRows('code', [['code' => 'A']])]);

        try {
            self::assertStringContainsString('${#for c in covers} Cover ${c} ${#endfor}', $this->text($disabled->path(), 'word/document.xml'));
        } finally {
            $disabled->dispose();
        }
    }

    public function testErrorsAndLogging(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('Start');
            $section->addText('${#for x in total}');
            $section->addText('${x}');
            $section->addText('${#endfor}');
        });

        try {
            $this->processor()->process($template, ['total' => 5]);
            self::fail('ConditionalExpressionException expected');
        } catch (ConditionalExpressionException $e) {
            self::assertStringContainsString('must iterate over a list or a map, got int', $e->getMessage());
        }

        $logger = new RecordingLogger();
        $out    = (new WordTemplateProcessor(logger: $logger, conditionalExpressionsOnError: 'hide', loopsEnabled: true))->process($template, ['total' => 5]);

        try {
            self::assertSame('Start', $this->text($out->path(), 'word/document.xml'));
            self::assertSame(1, $logger->recordsWithLevel('warning')[0]['context']['errors']);
        } finally {
            $out->dispose();
        }

        $limited = new WordTemplateProcessor(loopsEnabled: true, loopMaxIterations: 2);

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage('has 3 items; the limit is 2');
        $limited->process($template, ['total' => [1, 2, 3]]);
    }

    public function testConditionOnWholeTableRows(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $table = $pw->addSection()->addTable();
            foreach ([['Item', 'Amount'], ['${#if show_discount}'], ['Discount', '-${discount}'], ['${#endif}'], ['Total', '${total}']] as $cells) {
                $table->addRow();
                foreach ($cells as $cell) {
                    $table->addCell(3000)->addText($cell);
                }
            }
        });

        foreach ([[true, 'Item Amount Discount -5 Total 95'], [false, 'Item Amount Total 95']] as [$show, $expected]) {
            $out = (new WordTemplateProcessor(conditionalExpressionsEnabled: true))->process($template, ['show_discount' => $show, 'discount' => 5, 'total' => 95]);

            try {
                self::assertSame($expected, $this->text($out->path(), 'word/document.xml'));
            } finally {
                $out->dispose();
            }
        }
    }

    public function testListingAndLinting(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${title}');
            $section->addText('${#for key, line in order.lines}');
            $section->addText('${key}: ${line.name} ${loop.index} ${client.name}');
            $section->addText('${#if line.qty > limit}');
            $section->addText('${#endif}');
            $section->addText('${#endfor}');
        });

        $processor = $this->processor();

        self::assertSame(['title', 'client.name'], $processor->listVariables($template));
        self::assertSame(['key, line in order.lines'], $processor->listLoops($template));
        self::assertSame(['line.qty > limit'], $processor->listConditionalExpressions($template));
        self::assertSame([], $processor->lintConditionalExpressions($template, ['order', 'limit']));

        $messages = array_map(static fn (ConditionalExpressionException $e): string => $e->getMessage(), $processor->lintConditionalExpressions($template, ['limit']));
        self::assertSame(['Conditional expression "order.lines" uses the unknown variable "order" (not in the template context).'], $messages);

        $withoutLoops = new WordTemplateProcessor(conditionalExpressionsEnabled: true);
        self::assertContains('line.name', $withoutLoops->listVariables($template));
        self::assertSame(['key, line in order.lines'], $withoutLoops->listLoops($template));
    }

    private function processor(): WordTemplateProcessor
    {
        return new WordTemplateProcessor(conditionalExpressionsEnabled: true, loopsEnabled: true);
    }

    private function template(callable $build): string
    {
        $path    = sys_get_temp_dir() . '/nowo_word_loop_' . bin2hex(random_bytes(8)) . '.docx';
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

        $text = strip_tags(str_replace(['</w:p>', '</w:tc>'], [' </w:p>', ' </w:tc>'], $xml));

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($text, ENT_QUOTES | ENT_XML1)));
    }
}
