<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Util;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionErrorMode;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionReport;
use Nowo\WordTemplateBundle\Expression\ExpressionVariables;
use Nowo\WordTemplateBundle\Expression\LoopSettings;
use Nowo\WordTemplateBundle\Util\ConditionalExpressionApplicator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function html_entity_decode;
use function implode;
use function preg_replace;
use function range;
use function str_replace;
use function strip_tags;
use function trim;

use const ENT_QUOTES;
use const ENT_XML1;

final class LoopApplicatorTest extends TestCase
{
    public function testParagraphLoopWithItemAndLoopVariables(): void
    {
        $xml = self::doc('Lines:', '${#for line in lines}', '${loop.index}/${loop.length} ${line.code}: ${line.desc} first=${loop.first} last=${loop.last} rev=${loop.revindex}/${loop.revindex0} i0=${loop.index0}', '${#endfor}', 'End');

        self::assertSame(
            'Lines: | 1/2 A-1: Bolts &amp; nuts first=1 last=0 rev=2/1 i0=0 | 2/2 B-2: Brackets first=0 last=1 rev=1/0 i0=1 | End',
            $this->merge($xml, ['lines' => [['code' => 'A-1', 'desc' => 'Bolts & nuts'], ['code' => 'B-2', 'desc' => 'Brackets']]], raw: true),
        );
    }

    public function testScalarItemsKeysMapsAndIndexes(): void
    {
        $xml = self::doc('${#for tag in tags}', '- ${tag}', '${#endfor}', '${#for code, label in labels}', '${code}=${label}', '${#endfor}', '${#for row in matrix}', '${row[1]}', '${#endfor}');

        self::assertSame('- fire | - theft | ES=Spain | PT=Portugal | b | d', $this->merge($xml, [
            'tags'   => ['fire', 'theft'],
            'labels' => ['ES' => 'Spain', 'PT' => 'Portugal'],
            'matrix' => [['a', 'b'], ['c', 'd']],
        ]));
    }

    public function testEmptyListRemovesTheBlock(): void
    {
        $xml = self::doc('Before', '${#for line in lines}', '${line}', '${#endfor}', '${#if length(lines) == 0}', 'No lines.', '${#endif}', 'After');

        self::assertSame('Before | No lines. | After', $this->merge($xml, ['lines' => []]));
    }

    public function testNestedLoopsIfInsideForAndParent(): void
    {
        $xml = self::doc(
            '${#for order in orders}',
            'Order ${order.id}',
            '${#if order.urgent}',
            'URGENT',
            '${#endif}',
            '${#for line in order.lines}',
            '${loop.parent.index}.${loop.index} ${line} of ${order.id}',
            '${#endfor}',
            '${#endfor}',
        );

        self::assertSame('Order 7 | URGENT | 1.1 a of 7 | 1.2 b of 7 | Order 8 | 2.1 c of 8', $this->merge($xml, ['orders' => [
            ['id' => 7, 'urgent' => true, 'lines' => ['a', 'b']],
            ['id' => 8, 'urgent' => false, 'lines' => ['c']],
        ]]));
    }

    public function testForInsideIfAndShadowing(): void
    {
        $xml = self::doc('${#if show}', '${#for item in items}', '${#if item > limit}', 'big ${item}', '${#endif}', '${#endfor}', '${#endif}', 'item=${item}');

        self::assertSame('big 5 | item=${item}', $this->merge($xml, ['show' => true, 'items' => [1, 5], 'limit' => 2, 'item' => 'outer']));
        self::assertSame('item=${item}', $this->merge($xml, ['show' => false, 'items' => 'not evaluated', 'limit' => 2]));
    }

    public function testIterableExpressionWithFunctions(): void
    {
        $xml = self::doc('${#for n in slice(numbers, 0, 2)}', '${n}', '${#endfor}', '${#for k in keys(client)}', '${k}', '${#endfor}');

        self::assertSame('3 | 1 | name | city', $this->merge($xml, ['numbers' => [3, 1, 2], 'client' => ['name' => 'A', 'city' => 'B']]));
    }

    public function testTableRowLoop(): void
    {
        $row = static fn (string ...$cells): string => '<w:tr>' . implode('', array_map(static fn (string $c): string => '<w:tc>' . self::paragraphs($c) . '</w:tc>', $cells)) . '</w:tr>';
        $xml = '<w:body><w:tbl>' . $row('Code', 'Qty') . $row('${#for line in lines}') . $row('${line.code}', '${line.qty}') . $row('${#endfor}') . $row('Total', '${total}') . '</w:tbl></w:body>';

        $out = $this->applicator()->apply($xml, ExpressionVariables::fromContext(['lines' => [['code' => 'A', 'qty' => 1], ['code' => 'B', 'qty' => 2]]]), new ConditionalExpressionReport());

        self::assertSame(4, substr_count($out, '<w:tr>'));
        self::assertSame('Code | Qty | A | 1 | B | 2 | Total | ${total}', $this->text($out));
    }

    public function testNullPrintsEmptyAndNothingEnabledIsANoOp(): void
    {
        $xml = self::doc('${#for x in xs}', '[${x}]', '${#endfor}');

        self::assertSame('[]', $this->merge($xml, ['xs' => [null]]));

        $nothing = new ConditionalExpressionApplicator('${#if', '}', '${#endif', '}', new ConditionalExpressionEvaluator(), conditionals: false);
        self::assertSame($xml, $nothing->apply($xml, ['xs' => [1]], new ConditionalExpressionReport()));
    }

    public function testDisabledFeaturesLeaveMarkersAsText(): void
    {
        $xml = self::doc('${#for x in xs}', '${x}', '${#endfor}', '${#if a}', 'A', '${#endif}');

        $conditionsOnly = new ConditionalExpressionApplicator('${#if', '}', '${#endif', '}', new ConditionalExpressionEvaluator());
        self::assertSame('${#for x in xs} | ${x} | ${#endfor}', $this->text($conditionsOnly->apply($xml, ['a' => false, 'xs' => [1]], new ConditionalExpressionReport())));

        $loopsOnly = new ConditionalExpressionApplicator('${#if', '}', '${#endif', '}', new ConditionalExpressionEvaluator(), conditionals: false, loops: new LoopSettings());
        self::assertSame('1 | ${#if a} | A | ${#endif}', $this->text($loopsOnly->apply($xml, ['a' => false, 'xs' => [1]], new ConditionalExpressionReport())));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function failures(): iterable
    {
        yield 'not iterable' => [self::doc('${#for x in total}', '${x}', '${#endfor}'), ['total' => 5], 'must iterate over a list or a map, got int'];
        yield 'unknown iterable' => [self::doc('${#for x in itms}', '${x}', '${#endfor}'), ['items' => []], 'unknown variable "itms"'];
        yield 'too many items' => [self::doc('${#for x in xs}', '${x}', '${#endfor}'), ['xs' => range(1, 4)], 'has 4 items; the limit is 3'];
        yield 'nested product too large' => [self::doc('${#for a in xs}', '${#for b in xs}', '${b}', '${#endfor}', '${#endfor}'), ['xs' => range(1, 3)], 'exceed the allowed 5 iterations'];
        yield 'unknown placeholder key' => [self::doc('${#for x in xs}', '${x.nope}', '${#endfor}'), ['xs' => [['a' => 1]]], 'unknown variable "x.nope"'];
        yield 'placeholder of a list' => [self::doc('${#for x in xs}', '${x}', '${#endfor}'), ['xs' => [[1, 2]]], 'holds a array, not a printable value'];
        yield 'placeholder of a map' => [self::doc('${#for x in xs}', '${x}', '${#endfor}'), ['xs' => [['a' => 1]]], 'holds a Nowo\WordTemplateBundle\Expression\ExpressionMap'];
        yield 'missing list index' => [self::doc('${#for x in xs}', '${x[3]}', '${#endfor}'), ['xs' => [[1]]], 'unknown variable "x[3]"'];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('failures')]
    public function testFailuresThrowInExceptionMode(string $xml, array $context, string $message): void
    {
        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage($message);
        $this->applicator(new LoopSettings(maxIterations: 3, maxTotalIterations: 5))->apply($xml, ExpressionVariables::fromContext($context), new ConditionalExpressionReport());
    }

    public function testOutputSizeIsBounded(): void
    {
        $big = str_repeat('x', 1_000_000);
        $xml = self::doc('${#for x in xs}', '${x}', '${#endfor}');

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage('would produce more than 33554432 bytes');
        $this->applicator()->apply($xml, ['xs' => array_fill(0, 40, $big)], new ConditionalExpressionReport());
    }

    public function testHideAndKeepModes(): void
    {
        $xml = self::doc('Before', '${#for x in xs}', '${x.a}', '${#endfor}', 'After');
        $ctx = ExpressionVariables::fromContext(['xs' => [['a' => 1], ['b' => 2]]]);

        $report = new ConditionalExpressionReport();
        self::assertSame('Before | 1 |  | After', $this->text($this->applicator(null, ConditionalExpressionErrorMode::Hide)->apply($xml, $ctx, $report)), 'the failing placeholder is emptied');
        self::assertCount(1, $report->errors());

        $report = new ConditionalExpressionReport();
        self::assertSame('Before | 1 | ${x.a} | After', $this->text($this->applicator(null, ConditionalExpressionErrorMode::Keep)->apply($xml, $ctx, $report)));
        self::assertSame(2, $report->iterations());

        $notIterable = self::doc('Before', '${#for x in xs}', '${x}', '${#endfor}', 'After');
        self::assertSame('Before | After', $this->text($this->applicator(null, ConditionalExpressionErrorMode::Hide)->apply($notIterable, ['xs' => 1], new ConditionalExpressionReport())));
        self::assertSame('Before | ${#for x in xs} | ${x} | ${#endfor} | After', $this->text($this->applicator(null, ConditionalExpressionErrorMode::Keep)->apply($notIterable, ['xs' => 1], new ConditionalExpressionReport())));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function layoutProblems(): iterable
    {
        yield 'stray endfor' => [self::doc('${#endfor}'), 'there is no open loop to close'];
        yield 'unclosed loop' => [self::doc('${#for x in xs}', 'x'), 'the loop is never closed'];
        yield 'invalid head' => [self::doc('${#for xs}', 'x', '${#endfor}'), 'a loop needs "item in <expression>"'];
        yield 'endif closing a loop' => [self::doc('${#for x in xs}', '${#endif}', '${#endfor}'), 'it cannot close the loop "${#for x in xs}"'];
        yield 'unclosed if inside a loop' => [self::doc('${#for x in xs}', '${#if x > 1}', '${#endfor}'), 'the block is never closed'];
        yield 'named endif across a loop is stray' => [self::doc('${#if a > 1}', '${#for x in xs}', '${#endif a > 1}', '${#endfor}', '${#endif}'), 'TEXT:${#endif a > 1}'];
    }

    #[DataProvider('layoutProblems')]
    public function testLayoutProblems(string $xml, string $reason): void
    {
        if (str_starts_with($reason, 'TEXT:')) {
            self::assertStringContainsString(substr($reason, 5), $this->text($this->applicator()->apply($xml, ['a' => 2, 'xs' => [1]], new ConditionalExpressionReport())));

            return;
        }

        $messages = implode("\n", array_map(static fn (ConditionalExpressionException $e): string => $e->getMessage(), $this->applicator()->findProblems($xml)));
        self::assertStringContainsString($reason, $messages);
    }

    public function testDiscoveryAndScopedLint(): void
    {
        $xml        = self::doc('${#for key, order in orders}', '${#if order.total > limit}', '${#for line in order.lines}', '${#if loop.first and line != key}', '${#endif}', '${#endfor}', '${#endif}', '${#endfor}', '${#for x in xs}', '${#endfor}');
        $applicator = $this->applicator();

        self::assertSame(['key, order in orders', 'line in order.lines', 'x in xs'], $applicator->discoverLoops($xml));
        self::assertSame(['order.total > limit', 'loop.first and line != key'], $applicator->discoverExpressions($xml));
        self::assertSame(['order', 'loop', 'key', 'line', 'x'], $applicator->discoverLoopVariables($xml));
        self::assertSame([], $applicator->lint($xml, ['orders', 'limit', 'xs']));

        $messages = array_map(static fn (ConditionalExpressionException $e): string => $e->getMessage(), $applicator->lint($xml, ['orders']));
        self::assertCount(2, $messages);
        self::assertStringContainsString('unknown variable "limit"', $messages[0]);
        self::assertStringContainsString('unknown variable "xs"', $messages[1]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function merge(string $xml, array $context, bool $raw = false): string
    {
        $out = $this->applicator()->apply($xml, ExpressionVariables::fromContext($context), new ConditionalExpressionReport());

        return $this->text($out, !$raw);
    }

    private function applicator(?LoopSettings $loops = null, ConditionalExpressionErrorMode $mode = ConditionalExpressionErrorMode::Exception): ConditionalExpressionApplicator
    {
        return new ConditionalExpressionApplicator('${#if', '}', '${#endif', '}', new ConditionalExpressionEvaluator(), $mode, true, $loops ?? new LoopSettings());
    }

    private static function doc(string ...$paragraphs): string
    {
        return '<w:body>' . self::paragraphs(...$paragraphs) . '</w:body>';
    }

    private static function paragraphs(string ...$paragraphs): string
    {
        return implode('', array_map(static fn (string $text): string => '<w:p><w:r><w:t>' . $text . '</w:t></w:r></w:p>', $paragraphs));
    }

    private function text(string $xml, bool $decode = true): string
    {
        $text = strip_tags(str_replace(['</w:p>', '</w:tc>'], ['|</w:p>', '</w:tc>'], $xml));
        if ($decode) {
            $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1);
        }

        return trim((string) preg_replace('/\s*\|\s*/', ' | ', trim($text, ' |')), ' |');
    }
}
