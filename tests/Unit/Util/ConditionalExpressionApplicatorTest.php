<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Util;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionErrorMode;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionReport;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Util\ConditionalBlockApplicator;
use Nowo\WordTemplateBundle\Util\ConditionalExpressionApplicator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function html_entity_decode;
use function implode;
use function preg_replace;
use function str_replace;
use function strip_tags;
use function trim;

use const ENT_QUOTES;
use const ENT_XML1;

final class ConditionalExpressionApplicatorTest extends TestCase
{
    public function testTrueExpressionKeepsContentAndDropsMarkerParagraphs(): void
    {
        $xml = self::doc('Before', '${#if total &gt; 100}', 'Big order', '${#endif}', 'After');

        self::assertSame('Before | Big order | After', $this->merge($xml, ['total' => 150]));
        self::assertSame('Before | After', $this->merge($xml, ['total' => 50]));
    }

    public function testDocumentWithoutExpressionBlocksIsReturnedUnchanged(): void
    {
        $xml = '<w:body><w:p><w:r><w:t>${na</w:t></w:r><w:r><w:t>me}</w:t></w:r></w:p></w:body>';

        self::assertSame($xml, $this->applicator()->apply($xml, [], new ConditionalExpressionReport()));
    }

    public function testIdentifierOnlyExpressionClosedByNamelessEndif(): void
    {
        $xml = self::doc('${#if is_vip}', 'VIP', '${#endif}', 'End');

        self::assertSame('VIP | End', $this->merge($xml, ['is_vip' => true]));
        self::assertSame('End', $this->merge($xml, ['is_vip' => false]));
    }

    public function testNamedBlocksAreLeftToTheLegacyApplicator(): void
    {
        $xml = self::doc('${#if annex}', 'Annex', '${#endif annex}', '${#if a == 1}', 'One', '${#endif}');

        self::assertSame('${#if annex} | Annex | ${#endif annex} | One', $this->merge($xml, ['a' => 1]));
        self::assertSame(['annex'], $this->applicator()->discoverBlockNames($xml));
        self::assertSame(['a == 1'], $this->applicator()->discoverExpressions($xml));
    }

    public function testMixedNestingWithLegacyBlocks(): void
    {
        $xml = self::doc(
            '${#if annex}',
            'Annex',
            '${#if total &gt;= 10}',
            'Big',
            '${#if Big}',
            'legacy inner',
            '${#endif big}',
            '${#endif}',
            '${#endif annex}',
        );

        $afterExpressions = $this->applicator()->apply($xml, ['total' => 20], new ConditionalExpressionReport());
        $legacy           = new ConditionalBlockApplicator('${#if', '}', '${#endif', '}');
        $final            = $legacy->applyAll($afterExpressions, [new ConditionalBlock('annex', true), new ConditionalBlock('Big', false)]);

        self::assertSame('Annex | Big', $this->text($final));
    }

    public function testOuterHiddenBlockSkipsInnerEvaluation(): void
    {
        $xml = self::doc('${#if client != null}', '${#if client.vip}', 'VIP', '${#endif}', '${#endif}', 'End');

        self::assertSame('End', $this->merge($xml, ['client' => null]));
    }

    public function testNestedExpressionsResolveOutsideIn(): void
    {
        $xml = self::doc('${#if a}', 'A', '${#if b}', 'B', '${#endif}', 'A2', '${#endif}', '${#if not a}', 'Not A', '${#endif}');

        self::assertSame('A | B | A2', $this->merge($xml, ['a' => true, 'b' => true]));
        self::assertSame('A | A2', $this->merge($xml, ['a' => true, 'b' => false]));
        self::assertSame('Not A', $this->merge($xml, ['a' => false, 'b' => true]));
    }

    public function testWordSplitRunsAndSmartQuotesAreHealed(): void
    {
        $xml = '<w:body>'
            . '<w:p><w:r><w:t>${#if country</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve"> == ‘ES’</w:t></w:r><w:r><w:t>}</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>Domestic</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>${#end</w:t></w:r><w:r><w:t>if}</w:t></w:r></w:p>'
            . '</w:body>';

        self::assertSame('Domestic', $this->merge($xml, ['country' => 'ES']));
        self::assertSame('', $this->merge($xml, ['country' => 'PT']));
    }

    public function testTableCellBlockAndWholeTableBlock(): void
    {
        $cell = '<w:tbl><w:tr><w:tc>' . self::paragraphs('${#if paid}', 'Paid') . '<w:tc/>' . self::paragraphs('${#endif}', 'Cell end') . '</w:tc></w:tr></w:tbl>';
        $xml  = '<w:body>' . $cell . self::paragraphs('${#if paid}') . '<w:tbl><w:tr><w:tc>' . self::paragraphs('Row') . '</w:tc></w:tr></w:tbl>' . self::paragraphs('${#endif}') . '</w:body>';

        $shown = $this->applicator()->apply($xml, ['paid' => true], new ConditionalExpressionReport());
        $gone  = $this->applicator()->apply($xml, ['paid' => false], new ConditionalExpressionReport());

        self::assertSame('Paid | Cell end | Row', $this->text($shown));
        self::assertSame('Cell end', $this->text($gone));
        self::assertSame(1, substr_count($gone, '<w:tbl>'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function layoutProblems(): iterable
    {
        yield 'stray nameless endif' => [self::doc('Text', '${#endif}'), 'there is no open block to close'];
        yield 'unclosed expression' => [self::doc('${#if a &gt; 1}', 'Text'), 'never closed'];
        yield 'unclosed expression before a named endif' => [self::doc('${#if x}', '${#if a &gt; 1}', '${#endif x}'), 'never closed'];
        yield 'same paragraph' => ['<w:body><w:p><w:r><w:t>${#if a}x${#endif}</w:t></w:r></w:p></w:body>', 'different paragraphs'];
        yield 'crossing cells' => [
            '<w:body><w:tbl><w:tr><w:tc>' . self::paragraphs('${#if a}') . '</w:tc><w:tc>' . self::paragraphs('${#endif}') . '</w:tc></w:tr></w:tbl></w:body>',
            'crosses table cells',
        ];
        yield 'opening outside a table, closing inside a cell' => [
            '<w:body>' . self::paragraphs('${#if a}') . '<w:tbl><w:tr><w:tc>' . self::paragraphs('${#endif}') . '</w:tc></w:tr></w:tbl></w:body>',
            'crosses table cells',
        ];
        yield 'nested marker shares a paragraph with the outer one' => [
            '<w:body><w:p><w:r><w:t>${#if a}${#if b}</w:t></w:r></w:p>' . self::paragraphs('x', '${#endif}', '${#endif}') . '</w:body>',
            'shares a paragraph',
        ];
        yield 'sibling marker shares a paragraph' => [
            '<w:body>' . self::paragraphs('${#if a}', 'x') . '<w:p><w:r><w:t>${#endif}${#if b}</w:t></w:r></w:p>' . self::paragraphs('y', '${#endif}') . '</w:body>',
            'shares a paragraph',
        ];
        yield 'marker outside a paragraph' => ['<w:body><w:t>${#if a}</w:t>' . self::paragraphs('x', '${#endif}') . '</w:body>', 'inside a paragraph'];
    }

    #[DataProvider('layoutProblems')]
    public function testLayoutProblemsThrowInExceptionMode(string $xml, string $reason): void
    {
        self::assertNotSame([], $this->applicator()->findProblems($xml));

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage($reason);
        $this->applicator()->apply($xml, ['a' => true, 'b' => true, 'x' => true], new ConditionalExpressionReport());
    }

    #[DataProvider('layoutProblems')]
    public function testLayoutProblemsAreReportedAndLeftAsTextInOtherModes(string $xml, string $reason): void
    {
        foreach ([ConditionalExpressionErrorMode::Hide, ConditionalExpressionErrorMode::Keep] as $mode) {
            $report = new ConditionalExpressionReport();
            $out    = $this->applicator($mode)->apply($xml, ['a' => true, 'b' => true, 'x' => true], $report);

            self::assertNotSame([], $report->errors());
            self::assertStringContainsString($reason, $report->errors()[0]->getMessage());
            self::assertStringContainsString('${#', $out);
        }
    }

    public function testUnclosedPlainNameIsIgnoredSilently(): void
    {
        $xml = self::doc('${#if annex}', 'Text');

        self::assertSame([], $this->applicator()->findProblems($xml));
        self::assertSame($xml, $this->applicator()->apply($xml, [], new ConditionalExpressionReport()));
        self::assertSame(['annex'], $this->applicator()->discoverBlockNames($xml));
    }

    public function testStrayNamedEndifIsIgnored(): void
    {
        $xml = self::doc('${#if a}', 'A', '${#endif other}', '${#endif}');

        self::assertSame('A | ${#endif other}', $this->merge($xml, ['a' => true]));
    }

    public function testEvaluationErrorModes(): void
    {
        $xml = self::doc('Before', '${#if missing &gt; 1}', '${#if b}', 'Inner', '${#endif}', '${#endif}', 'After');

        $report = new ConditionalExpressionReport();
        self::assertSame('Before | After', $this->text($this->applicator(ConditionalExpressionErrorMode::Hide)->apply($xml, ['b' => true], $report)));
        self::assertCount(1, $report->errors());

        $report = new ConditionalExpressionReport();
        self::assertSame('Before | ${#if missing > 1} | Inner | ${#endif} | After', $this->text($this->applicator(ConditionalExpressionErrorMode::Keep)->apply($xml, ['b' => true], $report)));
        self::assertSame('missing > 1', $report->errors()[0]->expression);

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage('Conditional expression "missing > 1" uses the unknown variable "missing"');
        $this->applicator()->apply($xml, ['b' => true], new ConditionalExpressionReport());
    }

    public function testCustomDelimiters(): void
    {
        $applicator = new ConditionalExpressionApplicator('{{#if', '}}', '{{#endif', '}}', new ConditionalExpressionEvaluator());
        $xml        = self::doc('{{#if n &gt; 1}}', 'Many', '{{#endif}}', '{{#if n == 1}}', 'One', '{{#endif }}');

        self::assertSame('Many', $this->text($applicator->apply($xml, ['n' => 2], new ConditionalExpressionReport())));
        self::assertSame(['n > 1', 'n == 1'], $applicator->discoverExpressions($xml));
    }

    public function testDiscoverExpressionsIncludesNestedOnesOnce(): void
    {
        $xml = self::doc('${#if a}', '${#if b}', '${#endif}', '${#endif}', '${#if a}', '${#endif}');

        self::assertSame(['a', 'b'], $this->applicator()->discoverExpressions($xml));
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function merge(string $xml, array $variables): string
    {
        return $this->text($this->applicator()->apply($xml, $variables, new ConditionalExpressionReport()));
    }

    private function applicator(ConditionalExpressionErrorMode $mode = ConditionalExpressionErrorMode::Exception): ConditionalExpressionApplicator
    {
        return new ConditionalExpressionApplicator('${#if', '}', '${#endif', '}', new ConditionalExpressionEvaluator(), $mode);
    }

    private static function doc(string ...$paragraphs): string
    {
        return '<w:body>' . self::paragraphs(...$paragraphs) . '</w:body>';
    }

    private static function paragraphs(string ...$paragraphs): string
    {
        return implode('', array_map(static fn (string $text): string => '<w:p w:rsidR="1"><w:r><w:t>' . $text . '</w:t></w:r></w:p>', $paragraphs));
    }

    private function text(string $xml): string
    {
        $text = strip_tags(str_replace('</w:p>', '|</w:p>', $xml));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1);

        return trim((string) preg_replace(['/\s*\|\s*/', '/^\|\s*|\s*\|$/'], [' | ', ''], trim($text, ' |')), ' |');
    }
}
