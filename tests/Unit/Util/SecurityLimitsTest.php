<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Util;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Exception\ProcessingTimedOutException;
use Nowo\WordTemplateBundle\Exception\TemplateLimitExceededException;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionErrorMode;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionReport;
use Nowo\WordTemplateBundle\Expression\ExpressionLimits;
use Nowo\WordTemplateBundle\Expression\ExpressionVariables;
use Nowo\WordTemplateBundle\Expression\LoopSettings;
use Nowo\WordTemplateBundle\Runtime\ProcessDeadline;
use Nowo\WordTemplateBundle\Util\BoundaryIndex;
use Nowo\WordTemplateBundle\Util\ConditionalExpressionApplicator;
use PHPUnit\Framework\TestCase;

use function hrtime;
use function html_entity_decode;
use function str_repeat;
use function strip_tags;
use function strlen;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * Regression tests for the REQ-SEC-004 Appendix BQ proofs of concept (each runs in milliseconds).
 */
final class SecurityLimitsTest extends TestCase
{
    /**
     * M2: nine sibling loops of 1,000 iterations — the merge-wide output budget is checked before rendering.
     */
    public function testSiblingLoopsShareOneOutputBudget(): void
    {
        $body = str_repeat('${#for i in 1..1000}' . "\n" . str_repeat('A', 1000) . "\n" . '${#endfor}' . "\n", 9);
        $xml  = $this->doc(...explode("\n", trim($body)));

        $started = hrtime(true);

        try {
            $this->applicator(new ExpressionLimits(maxOutputBytes: 3_000_000))->apply($xml, [], new ConditionalExpressionReport());
            self::fail('ConditionalExpressionException expected');
        } catch (ConditionalExpressionException $e) {
            self::assertStringContainsString('would produce more than 3000000 bytes', $e->getMessage());
        }

        self::assertLessThan(2_000_000_000, hrtime(true) - $started);
    }

    public function testOutputBudgetIsSharedAcrossDocumentParts(): void
    {
        $part   = $this->doc('${#for i in 1..10}', str_repeat('B', 100), '${#endfor}');
        $report = new ConditionalExpressionReport();
        $apply  = $this->applicator(new ExpressionLimits(maxOutputBytes: 3000));

        $apply->apply($part, [], $report);
        self::assertGreaterThan(1000, $report->outputBytes());

        $this->expectException(ConditionalExpressionException::class);
        $apply->apply($part, [], $report);
        $apply->apply($part, [], $report);
    }

    public function testNestedLoopsAreCheckedWhileRendering(): void
    {
        $xml = $this->doc('${#for a in 1..20}', '${#for b in 1..20}', str_repeat('C', 50), '${#endfor}', '${#endfor}');

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage('would produce more than 5000 bytes');
        $this->applicator(new ExpressionLimits(maxOutputBytes: 5000))->apply($xml, [], new ConditionalExpressionReport());
    }

    /**
     * M3: 10,000 flat blocks are refused before any layout work.
     */
    public function testMarkerCapRejectsHugeTemplatesQuickly(): void
    {
        $xml = $this->doc(...explode("\n", trim(str_repeat("\${#if true}\nx\n\${#endif}\n", 10_000))));

        $started = hrtime(true);

        try {
            $this->applicator()->lint($xml, []);
            self::fail('TemplateLimitExceededException expected');
        } catch (TemplateLimitExceededException $e) {
            self::assertStringContainsString('has 20000 expression / loop markers; the limit is 2000', $e->getMessage());
        }

        self::assertLessThan(1_000_000_000, hrtime(true) - $started);
    }

    public function testLayoutCheckIsLinearAtTheMarkerCap(): void
    {
        $xml = $this->doc(...explode("\n", trim(str_repeat("\${#if true}\nx\n\${#endif}\n", 1000))));

        $started = hrtime(true);
        self::assertSame([], $this->applicator()->findProblems($xml));
        self::assertLessThan(1_000_000_000, hrtime(true) - $started);
    }

    public function testNestingDepthIsCapped(): void
    {
        $xml = $this->doc(...[...array_fill(0, 33, '${#if true}'), 'x', ...array_fill(0, 33, '${#endif}')]);

        $this->expectException(TemplateLimitExceededException::class);
        $this->expectExceptionMessage('nested more than 32 levels deep');
        $this->applicator()->apply($xml, [], new ConditionalExpressionReport());
    }

    /**
     * L2: the evaluation budget and the cooperative deadline fail closed, whatever on_error says.
     */
    public function testEvaluationBudgetFailsClosed(): void
    {
        $xml = $this->doc('${#for i in 1..10}', '${#if i > 0}', 'x', '${#endif}', '${#endfor}');

        $this->expectException(TemplateLimitExceededException::class);
        $this->expectExceptionMessage('evaluates more than 5 expressions');
        $this->applicator(new ExpressionLimits(maxEvaluations: 5), ConditionalExpressionErrorMode::Hide)->apply($xml, [], new ConditionalExpressionReport());
    }

    public function testExpiredDeadlineStopsRendering(): void
    {
        $applicator = new ConditionalExpressionApplicator('${#if', '}', '${#endif', '}', new ConditionalExpressionEvaluator(), ConditionalExpressionErrorMode::Hide, true, new LoopSettings(), new ExpressionLimits(), new ProcessDeadline(1, hrtime(true) - 2_000_000_000.0));

        $this->expectException(ProcessingTimedOutException::class);
        $applicator->apply($this->doc('${#if true}', 'x', '${#endif}'), [], new ConditionalExpressionReport());
    }

    /**
     * L1: values printed by loops can never be read as placeholders again (PoC: data pulls other context keys).
     */
    public function testLoopValuesAreInert(): void
    {
        $xml = $this->doc('${#for c in clients}', '${#for n in c.notes}', '${n} ${c.name}', '${#endfor}', '${#endfor}', '${#for l in lines}', '${l.desc}', '${#endfor}');
        $out = $this->applicator()->apply($xml, ExpressionVariables::fromContext([
            'clients'         => [['name' => 'ACME', 'iban' => 'ES91-SECRET', 'notes' => ['hello ${c.iban}']]],
            'lines'           => [['desc' => "Widget \${internal_margin}\x01"]],
            'internal_margin' => 'MARGIN-42%',
        ]), new ConditionalExpressionReport());

        self::assertStringContainsString('hello &#36;{c.iban} ACME', $out);
        self::assertStringContainsString('Widget &#36;{internal_margin}<', $out);
        self::assertSame('hello ${c.iban} ACMEWidget ${internal_margin}', html_entity_decode(strip_tags($out), ENT_QUOTES | ENT_XML1));
    }

    public function testProtectKeepsPlainValuesAndHandlesEmptyDelimiters(): void
    {
        self::assertSame('a&amp;b', ConditionalExpressionApplicator::protect("a&amp;\x1Fb", '${'));
        self::assertSame('${x}', ConditionalExpressionApplicator::protect('${x}', ''));
        self::assertSame('&#91;[x]]', ConditionalExpressionApplicator::protect('[[x]]', '[['));
    }

    public function testBoundaryIndex(): void
    {
        $xml   = '<w:body><w:p><w:r><w:t>a</w:t></w:r></w:p><w:t>loose</w:t><w:p w:x="1"><w:t>b</w:t></w:p></w:body>';
        $index = new BoundaryIndex($xml);
        $a     = (int) strpos($xml, 'a');
        $loose = (int) strpos($xml, 'loose');

        self::assertNull($index->enclosing(0, 1, 'p'), 'before any paragraph');
        self::assertSame([8, 42], $index->enclosing($a, $a + 1, 'p'));
        self::assertNull($index->enclosing($loose, $loose + 5, 'p'), 'between paragraphs');
        self::assertNull($index->enclosing($a, strlen($xml), 'p'), 'no closing tag after the end');
        self::assertNull($index->enclosing($a, $a + 1, 'tr'), 'no row');
    }

    private function applicator(?ExpressionLimits $limits = null, ConditionalExpressionErrorMode $mode = ConditionalExpressionErrorMode::Exception): ConditionalExpressionApplicator
    {
        return new ConditionalExpressionApplicator('${#if', '}', '${#endif', '}', new ConditionalExpressionEvaluator(), $mode, true, new LoopSettings(maxTotalIterations: 100_000), $limits ?? new ExpressionLimits());
    }

    private function doc(string ...$paragraphs): string
    {
        $xml = '';
        foreach ($paragraphs as $paragraph) {
            $xml .= '<w:p><w:r><w:t>' . $paragraph . '</w:t></w:r></w:p>';
        }

        return '<w:body>' . $xml . '</w:body>';
    }
}
