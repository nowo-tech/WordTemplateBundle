<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Util;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Exception\TemplateLimitExceededException;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionErrorMode;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionReport;
use Nowo\WordTemplateBundle\Expression\ExpressionLimits;
use Nowo\WordTemplateBundle\Expression\ExpressionVariables;
use Nowo\WordTemplateBundle\Expression\LoopSettings;
use Nowo\WordTemplateBundle\Util\ConditionalExpressionApplicator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function html_entity_decode;
use function implode;
use function preg_replace;
use function str_replace;
use function strip_tags;
use function substr_count;
use function trim;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * {@code ${#elseif …}} / {@code ${#else}} branches of expression conditionals (spec 004-else-branches).
 */
final class ElseBranchesApplicatorTest extends TestCase
{
    public function testFirstTrueBranchWinsAndElseIsTheFallback(): void
    {
        $xml = self::doc('Before', '${#if total &gt; 5000}', 'Director', '${#elseif total &gt; 1000}', 'Manager', '${#else}', 'Clerk', '${#endif}', 'After');

        self::assertSame('Before | Director | After', $this->merge($xml, ['total' => 9000]));
        self::assertSame('Before | Manager | After', $this->merge($xml, ['total' => 2000]));
        self::assertSame('Before | Clerk | After', $this->merge($xml, ['total' => 10]));
    }

    public function testElseifWithoutElseRendersNothingWhenNoBranchMatches(): void
    {
        $xml = self::doc('${#if a == 1}', 'One', '${#elseif a == 2}', 'Two', '${#elseif a == 3}', 'Three', '${#endif}', 'End');

        self::assertSame('Three | End', $this->merge($xml, ['a' => 3]));
        self::assertSame('End', $this->merge($xml, ['a' => 4]));
    }

    public function testIfElse(): void
    {
        $xml = self::doc('${#if is_vip}', 'VIP', '${#else }', 'Regular', '${#endif}');

        self::assertSame('VIP', $this->merge($xml, ['is_vip' => true]));
        self::assertSame('Regular', $this->merge($xml, ['is_vip' => false]));
    }

    public function testLaterBranchesAreNotEvaluated(): void
    {
        $xml    = self::doc('${#if true}', 'First', '${#elseif missing &gt; 1}', 'Second', '${#endif}');
        $report = new ConditionalExpressionReport();

        self::assertSame('First', $this->text($this->applicator()->apply($xml, [], $report)));
        self::assertSame(1, $report->countEvaluation() - 1);
    }

    public function testNestedBlocksInsideBranches(): void
    {
        $xml = self::doc(
            '${#if a}',
            'A',
            '${#if b}',
            'AB',
            '${#else}',
            'A not B',
            '${#endif}',
            '${#else}',
            'not A',
            '${#if b}',
            'B only',
            '${#endif}',
            '${#endif}',
        );

        self::assertSame('A | AB', $this->merge($xml, ['a' => true, 'b' => true]));
        self::assertSame('A | A not B', $this->merge($xml, ['a' => true, 'b' => false]));
        self::assertSame('not A | B only', $this->merge($xml, ['a' => false, 'b' => true]));
        self::assertSame('not A', $this->merge($xml, ['a' => false, 'b' => false]));
        self::assertSame(['a', 'b'], $this->applicator()->discoverExpressions($xml));
    }

    public function testBranchesInsideLoopsSeeTheLoopVariables(): void
    {
        $xml = self::doc('${#for x in xs}', '${#if x &gt; 1}', 'big ${x}', '${#elseif x == 1}', 'one', '${#else}', 'zero', '${#endif}', '${#endfor}');
        $out = $this->applicator(loops: new LoopSettings())->apply($xml, ExpressionVariables::fromContext(['xs' => [0, 1, 2]]), new ConditionalExpressionReport());

        self::assertSame('zero | one | big 2', $this->text($out));
    }

    public function testLoopsInsideBranchesAreDiscoveredAndLinted(): void
    {
        $applicator = $this->applicator(loops: new LoopSettings());
        $xml        = self::doc('${#if a}', 'A', '${#else}', '${#for row in rows}', '${row}', '${#endfor}', '${#endif}');

        self::assertSame(['row in rows'], $applicator->discoverLoops($xml));
        self::assertSame(['row', 'loop'], $applicator->discoverLoopVariables($xml));
        self::assertSame([], $applicator->lint($xml, ['a', 'rows']));
        self::assertSame('1 | 2', $this->text($applicator->apply($xml, ExpressionVariables::fromContext(['a' => false, 'rows' => [1, 2]]), new ConditionalExpressionReport())));
    }

    public function testDiscoveryAndLintCoverElseifExpressionsAndBranchChildren(): void
    {
        $xml = self::doc('${#if a &gt; 1}', '${#elseif b == 2}', '${#if c}', '${#endif}', '${#else}', '${#if d}', '${#endif}', '${#endif}');

        self::assertSame(['a > 1', 'b == 2', 'c', 'd'], $this->applicator()->discoverExpressions($xml));
        self::assertSame([], $this->applicator()->lint($xml, ['a', 'b', 'c', 'd']));

        $problems = array_map(static fn (ConditionalExpressionException $e): string => $e->getMessage(), $this->applicator()->lint($xml, ['a']));
        self::assertCount(3, $problems);
        self::assertStringContainsString('unknown variable "b"', $problems[0]);
        self::assertStringContainsString('unknown variable "c"', $problems[1]);
        self::assertStringContainsString('unknown variable "d"', $problems[2]);
    }

    public function testTableRowBranches(): void
    {
        $row = static fn (string $text): string => '<w:tr><w:tc>' . self::paragraphs($text) . '</w:tc></w:tr>';
        $xml = '<w:body><w:tbl>' . $row('Header') . $row('${#if paid}') . $row('Paid row') . $row('${#else}') . $row('Unpaid row 1') . $row('Unpaid row 2') . $row('${#endif}') . '</w:tbl></w:body>';

        $paid   = $this->applicator()->apply($xml, ['paid' => true], new ConditionalExpressionReport());
        $unpaid = $this->applicator()->apply($xml, ['paid' => false], new ConditionalExpressionReport());

        self::assertSame('Header | Paid row', $this->text($paid));
        self::assertSame(2, substr_count($paid, '<w:tr>'));
        self::assertSame('Header | Unpaid row 1 | Unpaid row 2', $this->text($unpaid));
        self::assertSame(3, substr_count($unpaid, '<w:tr>'));
    }

    public function testBranchesInsideOneCell(): void
    {
        $xml = '<w:body><w:tbl><w:tr><w:tc>' . self::paragraphs('${#if paid}', 'Paid', '${#else}', 'Pending', '${#endif}', 'Cell end') . '</w:tc></w:tr></w:tbl></w:body>';

        self::assertSame('Pending | Cell end', $this->merge($xml, ['paid' => false]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function structureProblems(): iterable
    {
        yield 'else without an open block' => [self::doc('Text', '${#else}'), 'there is no open block for this branch'];
        yield 'elseif without an open block' => [self::doc('${#elseif a}', 'Text'), 'there is no open block for this branch'];
        yield 'else inside a loop body' => [self::doc('${#for x in xs}', '${x}', '${#else}', 'none', '${#endfor}'), 'cannot belong to the loop "${#for x in xs}"'];
        yield 'two else branches' => [self::doc('${#if not a}', 'A', '${#else}', 'B', '${#else}', 'C', '${#endif}'), 'already has its else branch'];
        yield 'elseif after else' => [self::doc('${#if not a}', 'A', '${#else}', 'B', '${#elseif b}', 'C', '${#endif}'), 'already has its else branch'];
        yield 'else in a named block' => [self::doc('${#if annex}', 'A', '${#else}', 'B', '${#endif annex}'), 'only work in blocks closed by a nameless end marker'];
        yield 'else in an unclosed plain-name block' => [self::doc('${#if annex}', 'A', '${#else}', 'B'), 'never closed'];
        yield 'branch marker shares a paragraph with the closing marker' => [
            '<w:body>' . self::paragraphs('${#if a}', 'A') . '<w:p><w:r><w:t>${#else}${#endif}</w:t></w:r></w:p></w:body>',
            'shares a paragraph with another marker',
        ];
        yield 'branch marker shares the paragraph of a nested block' => [
            '<w:body>' . self::paragraphs('${#if a}', '${#if b}', 'x') . '<w:p><w:r><w:t>${#endif}${#else}</w:t></w:r></w:p>' . self::paragraphs('y', '${#endif}') . '</w:body>',
            'shares a paragraph',
        ];
        yield 'branch marker outside a paragraph' => ['<w:body>' . self::paragraphs('${#if a}', 'A') . '<w:t>${#else}</w:t>' . self::paragraphs('B', '${#endif}') . '</w:body>', 'inside a paragraph'];
        yield 'branch crossing cells' => [
            '<w:body>' . self::paragraphs('${#if a}', 'A') . '<w:tbl><w:tr><w:tc>' . self::paragraphs('${#else}') . '</w:tc></w:tr></w:tbl>' . self::paragraphs('${#endif}') . '</w:body>',
            'crosses table cells',
        ];
        yield 'row block with a branch marker outside the rows' => [
            '<w:body><w:tbl><w:tr><w:tc>' . self::paragraphs('${#if a}') . '</w:tc></w:tr></w:tbl>' . self::paragraphs('${#else}') . '<w:tbl><w:tr><w:tc>' . self::paragraphs('${#endif}') . '</w:tc></w:tr></w:tbl></w:body>',
            'row of its own',
        ];
    }

    #[DataProvider('structureProblems')]
    public function testStructureProblemsThrowInExceptionMode(string $xml, string $reason): void
    {
        self::assertNotSame([], $this->applicator(loops: new LoopSettings())->findProblems($xml));

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage($reason);
        $this->applicator(loops: new LoopSettings())->apply($xml, ExpressionVariables::fromContext(['a' => true, 'b' => true, 'xs' => [1]]), new ConditionalExpressionReport());
    }

    #[DataProvider('structureProblems')]
    public function testStructureProblemsAreReportedAndLeftAsTextInOtherModes(string $xml, string $reason): void
    {
        foreach ([ConditionalExpressionErrorMode::Hide, ConditionalExpressionErrorMode::Keep] as $mode) {
            $report = new ConditionalExpressionReport();
            $out    = $this->applicator($mode, new LoopSettings())->apply($xml, ExpressionVariables::fromContext(['a' => true, 'b' => true, 'xs' => [1]]), $report);

            self::assertNotSame([], $report->errors());
            self::assertStringContainsString($reason, $report->errors()[0]->getMessage());
            self::assertStringContainsString('${#', $out);
        }
    }

    public function testEvaluationErrorInABranchFollowsTheErrorMode(): void
    {
        $xml = self::doc('Before', '${#if a}', 'A', '${#elseif missing &gt; 1}', 'M', '${#if b}', 'Inner', '${#endif}', '${#else}', 'E', '${#endif}', 'After');

        $report = new ConditionalExpressionReport();
        self::assertSame('Before | After', $this->text($this->applicator(ConditionalExpressionErrorMode::Hide)->apply($xml, ['a' => false, 'b' => true], $report)));
        self::assertSame('missing > 1', $report->errors()[0]->expression);

        $report = new ConditionalExpressionReport();
        self::assertSame(
            'Before | ${#if a} | A | ${#elseif missing > 1} | M | Inner | ${#else} | E | ${#endif} | After',
            $this->text($this->applicator(ConditionalExpressionErrorMode::Keep)->apply($xml, ['a' => false, 'b' => true], $report)),
        );

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage('unknown variable "missing"');
        $this->applicator()->apply($xml, ['a' => false, 'b' => true], new ConditionalExpressionReport());
    }

    public function testEveryEvaluatedBranchCountsTowardsTheEvaluationLimit(): void
    {
        $xml        = self::doc('${#if a == 1}', 'One', '${#elseif a == 2}', 'Two', '${#elseif a == 3}', 'Three', '${#endif}');
        $applicator = new ConditionalExpressionApplicator('${#if', '}', '${#endif', '}', new ConditionalExpressionEvaluator(), limits: new ExpressionLimits(maxEvaluations: 2));

        self::assertSame('Two', $this->text($applicator->apply($xml, ['a' => 2], new ConditionalExpressionReport())));

        $this->expectException(TemplateLimitExceededException::class);
        $applicator->apply($xml, ['a' => 3], new ConditionalExpressionReport());
    }

    public function testBranchMarkersAreTextWhenConditionalsAreDisabled(): void
    {
        $xml       = self::doc('${#for x in xs}', '${#if a}', '${#else}', '${#endif}', '${#endfor}');
        $loopsOnly = new ConditionalExpressionApplicator('${#if', '}', '${#endif', '}', new ConditionalExpressionEvaluator(), conditionals: false, loops: new LoopSettings());

        self::assertSame('${#if a} | ${#else} | ${#endif}', $this->text($loopsOnly->apply($xml, ExpressionVariables::fromContext(['xs' => [1]]), new ConditionalExpressionReport())));
    }

    public function testCustomBranchDelimiters(): void
    {
        $applicator = new ConditionalExpressionApplicator(
            '{{#if',
            '}}',
            '{{#endif',
            '}}',
            new ConditionalExpressionEvaluator(),
            elseifOpening: '{{#elif',
            elseifClosing: '}}',
            elseOpening: '{{#otherwise',
            elseClosing: '}}',
        );
        $xml = self::doc('{{#if n &gt; 1}}', 'Many', '{{#elif n == 1}}', 'One', '{{#otherwise}}', 'None', '{{#endif}}');

        self::assertSame('One', $this->text($applicator->apply($xml, ['n' => 1], new ConditionalExpressionReport())));
        self::assertSame('None', $this->text($applicator->apply($xml, ['n' => 0], new ConditionalExpressionReport())));
        self::assertSame(['n > 1', 'n == 1'], $applicator->discoverExpressions($xml));
    }

    public function testTextBeforeABranchMarkerIsRemovedWithItsParagraph(): void
    {
        $xml = '<w:body>' . self::paragraphs('${#if a}', 'A') . '<w:p><w:r><w:t>Note: ${#else}</w:t></w:r></w:p>' . self::paragraphs('B', '${#endif}') . '</w:body>';

        self::assertSame('B', $this->merge($xml, ['a' => false]));
    }

    public function testWordSplitBranchMarkersAreHealed(): void
    {
        $xml = '<w:body>' . self::paragraphs('${#if a}', 'A') . '<w:p><w:r><w:t>${#el</w:t></w:r><w:r><w:t>se}</w:t></w:r></w:p>' . self::paragraphs('B', '${#endif}') . '</w:body>';

        self::assertSame('B', $this->merge($xml, ['a' => false]));
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function merge(string $xml, array $variables): string
    {
        return $this->text($this->applicator()->apply($xml, $variables, new ConditionalExpressionReport()));
    }

    private function applicator(ConditionalExpressionErrorMode $mode = ConditionalExpressionErrorMode::Exception, ?LoopSettings $loops = null): ConditionalExpressionApplicator
    {
        return new ConditionalExpressionApplicator('${#if', '}', '${#endif', '}', new ConditionalExpressionEvaluator(), $mode, true, $loops);
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
