<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Expression\BuiltinExpressionFunctions;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use Nowo\WordTemplateBundle\Result\ProcessedDocument;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use ZipArchive;

use function array_map;
use function array_unique;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function html_entity_decode;
use function implode;
use function is_array;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_split;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strip_tags;
use function strpos;
use function substr;
use function trim;

use const ENT_QUOTES;
use const ENT_XML1;
use const PREG_SET_ORDER;

/**
 * Executable companion of docs/USAGE.md "Expression conditionals" and "Loops".
 *
 * Every table row with an "In Word" marker, a PHP context and a result (kept / removed / throws) is rendered
 * through a real .docx; every "In Word / In PHP / Result" example marked with {@code <!-- expr-example -->}
 * is run as written. Now is frozen at 2026-10-30 12:00 Europe/Madrid, as the docs state.
 */
final class ExpressionConditionalsUsageDocTest extends TestCase
{
    private const PRELUDE = [
        'use Nowo\WordTemplateBundle\Model\ConditionalBlock;',
        'use Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals\InvoiceStatus;',
    ];

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function documentedRows(): iterable
    {
        foreach (explode("\n", self::section()) as $number => $line) {
            if (!str_starts_with($line, '|')) {
                continue;
            }

            $cells = array_map(trim(...), preg_split('/(?<!\\\\)\|/', trim($line, '|')) ?: []);
            foreach ($cells as $i => $cell) {
                if (preg_match('/^`\$\{#if (.+)\}`$/', $cell, $marker) !== 1 || preg_match('/^`(\[.*\])`$/', $cells[$i + 1] ?? '', $context) !== 1) {
                    continue;
                }

                $expression = str_replace('\|', '|', $marker[1]);
                yield 'line ' . ($number + 1) . ': ' . $expression => [$expression, $context[1], (string) $cells[count($cells) - 1]];

                break;
            }
        }
    }

    public function testEnoughRowsAndExamplesAreDocumented(): void
    {
        self::assertGreaterThanOrEqual(80, count(iterator_to_array(self::documentedRows())));
        self::assertGreaterThanOrEqual(10, count(self::examples()));
    }

    #[DataProvider('documentedRows')]
    public function testDocumentedRow(string $expression, string $contextCode, string $result): void
    {
        $context  = $this->evaluate('return ' . $contextCode . ';');
        $template = $this->template(['Before', '${#if ' . $expression . '}', 'Shown', '${#endif}', 'After']);
        self::assertTrue(is_array($context));

        if (preg_match('/^throws `(.+)`$/', $result, $error) === 1) {
            try {
                $this->render($template, $context);
                self::fail('ConditionalExpressionException expected for ' . $expression);
            } catch (ConditionalExpressionException $e) {
                self::assertStringContainsString($error[1], $e->getMessage());
            }

            return;
        }

        self::assertContains($result, ['✅ kept', '❌ removed'], 'Unknown result cell for ' . $expression);
        self::assertSame($result === '✅ kept' ? 'Before Shown After' : 'Before After', $this->render($template, $context), $expression);
    }

    /**
     * @return list<array{string, string, string}>
     */
    private static function examples(): array
    {
        $section  = self::section() . "\n" . self::section('Loops');
        $examples = [];
        $offset   = 0;

        while (($pos = strpos($section, '<!-- expr-example -->', $offset)) !== false) {
            preg_match_all('/```(?:text|php)\n(.*?)\n```/s', substr($section, $pos), $blocks, PREG_SET_ORDER);
            $examples[] = [$blocks[0][1], $blocks[1][1], $blocks[2][1]];
            $offset     = $pos + 1;
        }

        return $examples;
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function documentedExamples(): iterable
    {
        foreach (self::examples() as $i => [$word, $php, $result]) {
            yield 'example ' . ($i + 1) . ': ' . explode("\n", $word)[0] => [$word, $php, $result];
        }
    }

    #[DataProvider('documentedExamples')]
    public function testDocumentedExample(string $word, string $php, string $result): void
    {
        $templatePath = $this->template(explode("\n", $word));
        $processor    = $this->processor();

        $uses = self::PRELUDE;
        $code = [];
        foreach (explode("\n", $php) as $line) {
            if (str_starts_with($line, 'use ')) {
                $uses[] = $line;
            } else {
                $code[] = $line;
            }
        }

        $document = $this->evaluate(implode("\n", array_unique($uses)) . "\n" . implode("\n", $code) . "\nreturn \$result;", $processor, $templatePath);
        self::assertInstanceOf(ProcessedDocument::class, $document);

        try {
            self::assertSame($this->flatten(implode(' ', explode("\n", $result))), $this->flatten($this->text($document->path())));
        } finally {
            $document->dispose();
            @unlink($templatePath);
        }
    }

    /**
     * eval() keeps the documented PHP verbatim; $processor and $templatePath are in scope for the examples.
     */
    private function evaluate(string $code, ?WordTemplateProcessor $processor = null, ?string $templatePath = null): mixed
    {
        if (!str_contains($code, 'use ')) {
            $code = implode("\n", self::PRELUDE) . "\n" . $code;
        }

        $run = (static fn (string $code, ?WordTemplateProcessor $processor, ?string $templatePath): mixed => eval($code));

        return $run($code, $processor, $templatePath);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $templatePath, array $context): string
    {
        try {
            $document = $this->processor()->process($templatePath, $context);
            $text     = $this->text($document->path());
            $document->dispose();

            return $text;
        } finally {
            @unlink($templatePath);
        }
    }

    /**
     * Table rows are written "| a | b |" in the docs: compare without the pipes.
     */
    private function flatten(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace('|', ' ', $text)));
    }

    private function processor(): WordTemplateProcessor
    {
        $clock = new MockClock('2026-10-30 12:00:00', 'Europe/Madrid');

        return new WordTemplateProcessor(
            conditionalExpressionsEnabled: true,
            conditionalExpressionEvaluator: new ConditionalExpressionEvaluator(functionProviders: [new BuiltinExpressionFunctions($clock, 'Europe/Madrid')]),
            loopsEnabled: true,
        );
    }

    /**
     * @param list<string> $paragraphs
     */
    private function template(array $paragraphs): string
    {
        $path    = sys_get_temp_dir() . '/nowo_word_exprdoc_' . bin2hex(random_bytes(8)) . '.docx';
        $escaped = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);

        try {
            $pw      = new PhpWord();
            $section = $pw->addSection();
            $table   = null;
            foreach ($paragraphs as $paragraph) {
                if (!str_starts_with($paragraph, '|')) {
                    $table = null;
                    $section->addText($paragraph);

                    continue;
                }

                // "| a | b |" lines are the rows of one table.
                $table ??= $section->addTable();
                $table->addRow();
                foreach (explode('|', substr(trim($paragraph), 1, -1)) as $cell) {
                    $table->addCell(3000)->addText(trim($cell));
                }
            }
            IOFactory::createWriter($pw, 'Word2007')->save($path);
        } finally {
            Settings::setOutputEscapingEnabled($escaped);
        }

        return $path;
    }

    private function text(string $docxPath): string
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($docxPath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        self::assertTrue(is_string($xml));

        $text = strip_tags(str_replace('</w:p>', ' </w:p>', $xml));

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($text, ENT_QUOTES | ENT_XML1)));
    }

    private static function section(string $title = 'Expression conditionals'): string
    {
        $usage = file_get_contents(dirname(__DIR__, 2) . '/docs/USAGE.md');
        if (!is_string($usage) || preg_match('/\n## ' . $title . '\n(.*?)\n## /s', $usage, $section) !== 1) {
            throw new RuntimeException('docs/USAGE.md has no "' . $title . '" section.');
        }

        return $section[1];
    }
}
