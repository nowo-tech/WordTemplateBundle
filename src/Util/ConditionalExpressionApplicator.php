<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Util;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionErrorMode;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionReport;
use Nowo\WordTemplateBundle\Expression\ExpressionBlock;
use Nowo\WordTemplateBundle\Expression\ExpressionMap;
use Nowo\WordTemplateBundle\Expression\ExpressionTextNormalizer;
use Nowo\WordTemplateBundle\Expression\ExpressionVariables;
use Nowo\WordTemplateBundle\Expression\LoopSettings;

use function array_key_exists;
use function array_keys;
use function count;
use function get_debug_type;
use function html_entity_decode;
use function htmlspecialchars;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function preg_last_error_msg;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace_callback;
use function sprintf;
use function strcasecmp;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;

use const ENT_QUOTES;
use const ENT_XML1;
use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;
use const PREG_UNMATCHED_AS_NULL;

/**
 * Applies expression blocks: conditionals ({@code ${#if <expression>}} … {@code ${#endif}}, nameless end marker) and,
 * when {@see LoopSettings} are given, loops ({@code ${#for item in <expression>}} … {@code ${#endfor}}).
 *
 * Classification (purely syntactic, independent of the context): markers are paired like brackets in document
 * order. A nameless {@code ${#endif}} closes the nearest open {@code ${#if …}}, which makes that block an
 * expression block. A named {@code ${#endif name}} closes the nearest open {@code ${#if name}} (case-insensitive,
 * as {@see ConditionalBlockApplicator}) and leaves it to {@see ConditionalBlockApplicator} — named blocks keep
 * their legacy behaviour exactly. {@code ${#endfor}} closes the nearest open loop.
 *
 * Blocks are rendered outside-in with a scope: a removed block is never evaluated, and each loop iteration renders
 * its body with the loop variables ({@code item}, {@code key}, {@code loop}) added to the scope — so conditions and
 * nested loops inside a loop body see them, and {@code ${item.name}} placeholders are replaced per iteration.
 * Each marker must sit in its own paragraph; the marker paragraphs are removed. When the opening and the closing
 * marker sit in different rows of the same table, the block works on whole rows (the marker rows are removed).
 */
final readonly class ConditionalExpressionApplicator
{
    /** Opening text that is a plain legacy block name: an unclosed one is ignored silently (legacy behaviour). */
    private const PLAIN_NAME = '/^[A-Za-z0-9_.\-]+$/';

    /** Containers that a block must not cut in half. */
    private const CONTAINER_PATTERN = '#<(/?)w:(tbl|tr|tc|sdt|sdtContent|txbxContent)(?=[\s>/])[^>]*?(/?)>#';

    /** {@code item in items} / {@code key, item in items}. */
    private const LOOP_HEAD = '/^([A-Za-z_][A-Za-z0-9_]*)(?:\s*,\s*([A-Za-z_][A-Za-z0-9_]*))?\s+in\s+(.+)$/s';

    /**
     * @param string $ifOpening opening delimiter of {@code if} markers (e.g. {@code ${#if})
     * @param string $ifClosing closing delimiter of {@code if} markers
     * @param string $endifOpening opening delimiter of {@code endif} markers (e.g. {@code ${#endif})
     * @param string $endifClosing closing delimiter of {@code endif} markers
     * @param ConditionalExpressionEvaluator $evaluator parses / evaluates the expressions
     * @param ConditionalExpressionErrorMode $onError what to do when an expression or its layout is invalid
     * @param bool $conditionals false ignores {@code if} markers (left as text) — loops only
     * @param LoopSettings|null $loops null ignores {@code for} markers (left as text) — conditionals only
     */
    public function __construct(
        private string $ifOpening,
        private string $ifClosing,
        private string $endifOpening,
        private string $endifClosing,
        private ConditionalExpressionEvaluator $evaluator,
        private ConditionalExpressionErrorMode $onError = ConditionalExpressionErrorMode::Exception,
        private bool $conditionals = true,
        private ?LoopSettings $loops = null,
    ) {
    }

    /**
     * Keeps, removes or repeats every expression block of one WordprocessingML part.
     *
     * @param array<string, mixed> $variables exposed context ({@see ExpressionVariables})
     * @param ConditionalExpressionReport $report receives the errors swallowed by the {@code hide} / {@code keep} modes
     *                                            and counts loop iterations
     *
     * @throws ConditionalExpressionException in {@code exception} mode
     */
    public function apply(string $xml, array $variables, ConditionalExpressionReport $report): string
    {
        $normalized = $this->normalize($xml);
        $scan       = $this->scan($normalized);

        if ($scan->roots === [] && $scan->problems === []) {
            return $xml;
        }

        foreach ([...$scan->problems, ...$this->checkLayout($normalized, $scan->roots, 0, strlen($normalized))] as $problem) {
            $this->fail($problem, $report);
        }

        return $this->render($normalized, $scan->roots, 0, strlen($normalized), $variables, null, $report);
    }

    /**
     * Returns the unique expressions of the conditional expression blocks (normalised text), in document order.
     *
     * @return list<string>
     */
    public function discoverExpressions(string $xml): array
    {
        $expressions = [];
        $this->collect($this->scan($this->normalize($xml))->roots, $expressions, false);

        return array_keys($expressions);
    }

    /**
     * Returns the unique loop heads ({@code item in items}, normalised) in document order.
     *
     * @return list<string>
     */
    public function discoverLoops(string $xml): array
    {
        $loops = [];
        $this->collect($this->scan($this->normalize($xml))->roots, $loops, true);

        return array_keys($loops);
    }

    /**
     * Returns the variable names introduced by the loops of a part ({@code item}, {@code key} and {@code loop}).
     *
     * @return list<string>
     */
    public function discoverLoopVariables(string $xml): array
    {
        $names = [];
        $this->collectLoopVariables($this->scan($this->normalize($xml))->roots, $names);

        return array_keys($names);
    }

    /**
     * Returns the unique names of the named (legacy) blocks — every {@code ${#if …}} opening marker that is not
     * closed by a nameless {@code ${#endif}} — as {@see ConditionalBlockApplicator::discoverBlockNames()} does.
     *
     * @return list<string>
     */
    public function discoverBlockNames(string $xml): array
    {
        return array_keys($this->scan($this->normalize($xml))->openings);
    }

    /**
     * Returns the layout problems of a part (stray end markers, unclosed blocks, markers sharing a paragraph,
     * blocks crossing table cells, invalid loop heads) without evaluating anything.
     *
     * @return list<ConditionalExpressionException>
     */
    public function findProblems(string $xml): array
    {
        $normalized = $this->normalize($xml);
        $scan       = $this->scan($normalized);

        return [...$scan->problems, ...$this->checkLayout($normalized, $scan->roots, 0, strlen($normalized))];
    }

    /**
     * Layout problems plus, for every expression, syntax / function / length / unknown-variable checks — scope-aware:
     * the loop variables are known inside the loop body.
     *
     * @param list<string> $variableNames top-level names the context will provide
     *
     * @return list<ConditionalExpressionException>
     */
    public function lint(string $xml, array $variableNames): array
    {
        $normalized = $this->normalize($xml);
        $scan       = $this->scan($normalized);

        return [
            ...$scan->problems,
            ...$this->checkLayout($normalized, $scan->roots, 0, strlen($normalized)),
            ...$this->lintBlocks($scan->roots, $variableNames),
        ];
    }

    /**
     * @param list<ExpressionBlock> $blocks
     * @param list<string> $names
     *
     * @return list<ConditionalExpressionException>
     */
    private function lintBlocks(array $blocks, array $names): array
    {
        $problems = [];
        foreach ($blocks as $block) {
            try {
                $this->evaluator->lint($block->expression, $names);
            } catch (ConditionalExpressionException $e) {
                $problems[] = $e;
            }

            $inner = $names;
            if ($block->loopItem !== null) {
                $inner = [...$names, $block->loopItem, 'loop'];
                if ($block->loopKey !== null) {
                    $inner[] = $block->loopKey;
                }
            }

            $problems = [...$problems, ...$this->lintBlocks($block->children, $inner)];
        }

        return $problems;
    }

    private function normalize(string $xml): string
    {
        return BrokenMacroNormalizer::forConditionalDelimiters($this->ifOpening, $this->ifClosing)->normalize($xml);
    }

    /**
     * Pairs the markers like brackets.
     */
    private function scan(string $xml): MarkerStack
    {
        $alternatives = [];
        if ($this->loops instanceof LoopSettings) {
            $alternatives[] = '(?<endfor>' . preg_quote($this->loops->endforOpening, '/') . '\s*' . preg_quote($this->loops->endforClosing, '/') . ')';
            $alternatives[] = '(?<for>' . preg_quote($this->loops->forOpening, '/') . '\s+(?<head>[^<]+?)' . preg_quote($this->loops->forClosing, '/') . ')';
        }
        if ($this->conditionals) {
            $alternatives[] = '(?<endif>' . preg_quote($this->endifOpening, '/') . '(?:\s+(?<endname>[^<]*?))?\s*' . preg_quote($this->endifClosing, '/') . ')';
            $alternatives[] = '(?<if>' . preg_quote($this->ifOpening, '/') . '\s+(?<text>[^<]+?)' . preg_quote($this->ifClosing, '/') . ')';
        }

        $state = new MarkerStack();
        if ($alternatives === []) {
            return $state;
        }

        if (preg_match_all('/' . implode('|', $alternatives) . '/', $xml, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === false) {
            // Fail closed whatever on_error says: unscanned markers would leave conditional content visible.
            throw new ConditionalExpressionException('', sprintf('Conditional markers could not be scanned (%s).', preg_last_error_msg()));
        }

        foreach ($matches as $match) {
            if (($match['if'][0] ?? null) !== null) {
                $state->open('if', trim((string) $match['text'][0]), $match['if'][0], $match['if'][1]);
            } elseif (($match['for'][0] ?? null) !== null) {
                $state->open('for', trim((string) $match['head'][0]), $match['for'][0], $match['for'][1]);
            } elseif (($match['endfor'][0] ?? null) !== null) {
                $this->closeLoop($state, $match['endfor'][0], $match['endfor'][1]);
            } elseif (trim($match['endname'][0] ?? '') !== '') {
                $this->closeNamed($state, trim((string) $match['endname'][0]));
            } else {
                $this->closeExpression($state, (string) $match['endif'][0], $match['endif'][1]);
            }
        }

        while ($state->stack !== []) {
            $this->discardUnclosed($state);
        }

        return $state;
    }

    private function closeExpression(MarkerStack $state, string $raw, int $start): void
    {
        $open = $state->top();
        if ($open === null || $open['type'] !== 'if') {
            $state->problems[] = ConditionalExpressionException::invalidStructure($this->decode($raw), $open === null
                ? 'there is no open block to close.'
                : sprintf('it cannot close the loop "%s"; close the loop first.', $open['marker']));

            return;
        }

        $state->pop();
        $state->attach([new ExpressionBlock(
            ExpressionTextNormalizer::normalize($open['text']),
            $open['marker'],
            $open['start'],
            $open['start'] + strlen($open['raw']),
            $start,
            $start + strlen($raw),
            $open['children'],
        )]);
    }

    private function closeLoop(MarkerStack $state, string $raw, int $start): void
    {
        $index = $state->find(static fn (array $entry): bool => $entry['type'] === 'for', static fn (array $entry): bool => false);
        if ($index === null) {
            $state->problems[] = ConditionalExpressionException::invalidStructure($this->decode($raw), 'there is no open loop to close.');

            return;
        }

        while (count($state->stack) - 1 > $index) {
            $this->discardUnclosed($state);
        }

        $open = $state->pop();
        if (preg_match(self::LOOP_HEAD, ExpressionTextNormalizer::normalize($open['text']), $parts) !== 1) {
            $state->problems[] = ConditionalExpressionException::invalidStructure($open['marker'], 'a loop needs "item in <expression>" or "key, item in <expression>".');
            $state->attach($open['children']);

            return;
        }

        $state->attach([new ExpressionBlock(
            trim($parts[3]),
            $open['marker'],
            $open['start'],
            $open['start'] + strlen($open['raw']),
            $start,
            $start + strlen($raw),
            $open['children'],
            $parts[2] !== '' ? $parts[2] : $parts[1],
            $parts[2] !== '' ? $parts[1] : null,
        )]);
    }

    /**
     * Closes the nearest open block with the same name; blocks opened after it are unclosed. An open loop in
     * between makes the end marker a stray one (left as text).
     */
    private function closeNamed(MarkerStack $state, string $name): void
    {
        $index = $state->find(
            static fn (array $entry): bool => $entry['type'] === 'if' && strcasecmp($entry['text'], $name) === 0,
            static fn (array $entry): bool => $entry['type'] === 'for',
        );

        if ($index === null) {
            return; // stray named end marker: left as literal text, as before
        }

        while (count($state->stack) - 1 > $index) {
            $this->discardUnclosed($state);
        }

        $open                           = $state->pop();
        $state->openings[$open['text']] = true;
        $state->attach($open['children']);
    }

    /**
     * Drops the innermost open block (never closed); its children move up one level.
     */
    private function discardUnclosed(MarkerStack $state): void
    {
        $open = $state->pop();

        if ($open['type'] === 'for') {
            $state->problems[] = ConditionalExpressionException::invalidStructure($open['marker'], 'the loop is never closed (add an end marker).');
        } else {
            $state->openings[$open['text']] = true;
            if (preg_match(self::PLAIN_NAME, $open['text']) !== 1) {
                $state->problems[] = ConditionalExpressionException::invalidStructure($open['marker'], 'the block is never closed (add a nameless end marker).');
            }
        }

        $state->attach($open['children']);
    }

    /**
     * Computes the regions of the blocks and records layout problems on them.
     *
     * @param list<ExpressionBlock> $blocks
     *
     * @return list<ConditionalExpressionException>
     */
    private function checkLayout(string $xml, array $blocks, int $from, int $to): array
    {
        $problems = [];
        $previous = $from;

        foreach ($blocks as $block) {
            $block->problem = $this->layoutProblem($xml, $block, $from, $to, $previous);
            if ($block->problem instanceof ConditionalExpressionException) {
                $problems[] = $block->problem;

                continue;
            }

            $previous = $block->regionEnd;
            $problems = [...$problems, ...$this->checkLayout($xml, $block->children, $block->contentStart, $block->contentEnd)];
        }

        return $problems;
    }

    private function layoutProblem(string $xml, ExpressionBlock $block, int $from, int $to, int $previousEnd): ?ConditionalExpressionException
    {
        $open  = $this->enclosing($xml, $block->openStart, $block->openEnd, 'p');
        $close = $this->enclosing($xml, $block->closeStart, $block->closeEnd, 'p');

        if ($open === null || $close === null) {
            return ConditionalExpressionException::invalidStructure($block->marker, 'each marker must be inside a paragraph.');
        }

        if ($open[1] > $close[0]) {
            return ConditionalExpressionException::invalidStructure($block->marker, 'the opening and the closing marker must be in different paragraphs (inline blocks are not supported).');
        }

        // Markers in different rows of a table: the block works on whole rows.
        $openRow  = $this->enclosing($xml, $open[0], $open[1], 'tr');
        $closeRow = $this->enclosing($xml, $close[0], $close[1], 'tr');
        if ($openRow !== null && $closeRow !== null && $openRow[1] <= $closeRow[0]) {
            $open  = $openRow;
            $close = $closeRow;
        }

        if ($open[0] < $from || $close[1] > $to || $open[0] < $previousEnd) {
            return ConditionalExpressionException::invalidStructure($block->marker, 'the marker shares a paragraph with the marker of another block; put each marker in its own paragraph.');
        }

        if (!$this->balanced(substr($xml, $open[1], $close[0] - $open[1]))) {
            return ConditionalExpressionException::invalidStructure($block->marker, 'the block crosses table cells, rows or content controls; wrap whole paragraphs, whole rows (markers in rows of their own) or a whole table.');
        }

        $block->regionStart  = $open[0];
        $block->contentStart = $open[1];
        $block->contentEnd   = $close[0];
        $block->regionEnd    = $close[1];

        return null;
    }

    /**
     * @param list<ExpressionBlock> $blocks
     * @param array<string, mixed> $variables
     */
    private function render(string $xml, array $blocks, int $from, int $to, array $variables, ?ExpressionMap $loop, ConditionalExpressionReport $report): string
    {
        $out = '';
        $pos = $from;

        foreach ($blocks as $block) {
            if ($block->problem instanceof ConditionalExpressionException) {
                continue; // left as literal text (only reachable in hide / keep modes)
            }

            $out .= substr($xml, $pos, $block->regionStart - $pos);
            $pos = $block->regionEnd;
            $out .= $block->isLoop()
                ? $this->renderLoop($xml, $block, $variables, $loop, $report)
                : $this->renderCondition($xml, $block, $variables, $loop, $report);
        }

        return $out . substr($xml, $pos, $to - $pos);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderCondition(string $xml, ExpressionBlock $block, array $variables, ?ExpressionMap $loop, ConditionalExpressionReport $report): string
    {
        try {
            $visible = $this->evaluator->evaluate($block->expression, $variables);
        } catch (ConditionalExpressionException $e) {
            $this->fail($e, $report);

            if ($this->onError !== ConditionalExpressionErrorMode::Keep) {
                return '';
            }

            return substr($xml, $block->regionStart, $block->contentStart - $block->regionStart)
                . $this->render($xml, $block->children, $block->contentStart, $block->contentEnd, $variables, $loop, $report)
                . substr($xml, $block->contentEnd, $block->regionEnd - $block->contentEnd);
        }

        return $visible ? $this->render($xml, $block->children, $block->contentStart, $block->contentEnd, $variables, $loop, $report) : '';
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderLoop(string $xml, ExpressionBlock $block, array $variables, ?ExpressionMap $parent, ConditionalExpressionReport $report): string
    {
        /** @var LoopSettings $loops loops are only scanned with LoopSettings */
        $loops = $this->loops;
        $item  = (string) $block->loopItem;

        try {
            $entries = $this->entries($block, $this->evaluator->evaluateValue($block->expression, $variables), $loops);
            $report->addIterations(count($entries));
            if ($report->iterations() > $loops->maxTotalIterations) {
                throw new ConditionalExpressionException($block->expression, sprintf('Loop "%s": all loops of the document together exceed the allowed %d iterations (nested loops multiply).', $block->marker, $loops->maxTotalIterations));
            }

            $out    = '';
            $length = count($entries);
            foreach ($entries as $index => [$key, $value]) {
                $loopMap = new ExpressionMap([
                    'index'     => $index + 1,
                    'index0'    => $index,
                    'revindex'  => $length - $index,
                    'revindex0' => $length - $index - 1,
                    'first'     => $index === 0,
                    'last'      => $index === $length - 1,
                    'length'    => $length,
                    'parent'    => $parent,
                ], 'loop');

                $scope         = $variables;
                $scope[$item]  = $value;
                $scope['loop'] = $loopMap;
                $names         = [$item, 'loop'];
                if ($block->loopKey !== null) {
                    $scope[$block->loopKey] = $key;
                    $names[]                = $block->loopKey;
                }

                $body = $this->render($xml, $block->children, $block->contentStart, $block->contentEnd, $scope, $loopMap, $report);
                $out .= $this->substitute($body, $scope, $names, $loops, $report);

                if (strlen($out) > LoopSettings::MAX_OUTPUT_BYTES) {
                    throw new ConditionalExpressionException($block->expression, sprintf('Loop "%s" produces more than %d bytes of document XML.', $block->marker, LoopSettings::MAX_OUTPUT_BYTES));
                }
            }

            return $out;
        } catch (ConditionalExpressionException $e) {
            $this->fail($e, $report);

            return $this->onError === ConditionalExpressionErrorMode::Keep ? substr($xml, $block->regionStart, $block->regionEnd - $block->regionStart) : '';
        }
    }

    /**
     * @return list<array{int|string, mixed}> [key, value] pairs
     */
    private function entries(ExpressionBlock $block, mixed $iterable, LoopSettings $loops): array
    {
        if ($iterable instanceof ExpressionMap) {
            $iterable = $iterable->all();
        }

        if (!is_array($iterable)) {
            throw new ConditionalExpressionException($block->expression, sprintf('Loop "%s" must iterate over a list or a map, got %s.', $block->marker, get_debug_type($iterable)));
        }

        if (count($iterable) > $loops->maxIterations) {
            throw new ConditionalExpressionException($block->expression, sprintf('Loop "%s" has %d items; the limit is %d.', $block->marker, count($iterable), $loops->maxIterations));
        }

        $entries = [];
        foreach ($iterable as $key => $value) {
            $entries[] = [$key, $value];
        }

        return $entries;
    }

    /**
     * Replaces the placeholders of the loop variables ({@code ${item}}, {@code ${item.name}}, {@code ${loop.index}}).
     *
     * @param array<string, mixed> $scope
     * @param list<string> $names variables introduced by the current loop
     */
    private function substitute(string $body, array $scope, array $names, LoopSettings $loops, ConditionalExpressionReport $report): string
    {
        $pattern = '/' . preg_quote($loops->macroOpening, '/') . '\s*(' . implode('|', $names) . ')((?:\.[A-Za-z0-9_]+|\[[0-9]+\])*)\s*' . preg_quote($loops->macroClosing, '/') . '/';

        return (string) preg_replace_callback($pattern, function (array $match) use ($scope, $report): string {
            try {
                return htmlspecialchars($this->printable($match[1] . $match[2], $this->resolve($scope[$match[1]], $match[1], $match[2])), ENT_QUOTES | ENT_XML1, 'UTF-8');
            } catch (ConditionalExpressionException $e) {
                $this->fail($e, $report);

                return $this->onError === ConditionalExpressionErrorMode::Keep ? $match[0] : '';
            }
        }, $body);
    }

    private function resolve(mixed $value, string $path, string $segments): mixed
    {
        preg_match_all('/\.([A-Za-z0-9_]+)|\[([0-9]+)\]/', $segments, $parts, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        foreach ($parts as $part) {
            $key = $part[1] ?? (int) $part[2];
            $path .= is_int($key) ? '[' . $key . ']' : '.' . $key;
            if ($value instanceof ExpressionMap) {
                $value = $value->all();
            }

            if (!is_array($value) || !array_key_exists($key, $value)) {
                throw ConditionalExpressionException::unknownVariable($path, $path);
            }

            $value = $value[$key];
        }

        return $value;
    }

    private function printable(string $path, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new ConditionalExpressionException($path, sprintf('Placeholder "%s" holds a %s, not a printable value; print one of its values or loop over it.', $path, get_debug_type($value)));
    }

    private function fail(ConditionalExpressionException $error, ConditionalExpressionReport $report): void
    {
        if ($this->onError === ConditionalExpressionErrorMode::Exception) {
            throw $error;
        }

        $report->add($error);
    }

    /**
     * @param list<ExpressionBlock> $blocks
     * @param array<string, true> $found
     */
    private function collect(array $blocks, array &$found, bool $loops): void
    {
        foreach ($blocks as $block) {
            if ($block->isLoop() === $loops) {
                $found[$block->loopHead()] = true;
            }
            $this->collect($block->children, $found, $loops);
        }
    }

    /**
     * @param list<ExpressionBlock> $blocks
     * @param array<string, true> $names
     */
    private function collectLoopVariables(array $blocks, array &$names): void
    {
        foreach ($blocks as $block) {
            foreach ([$block->loopItem, $block->loopKey] as $name) {
                if ($name !== null) {
                    $names[$name]  = true;
                    $names['loop'] = true;
                }
            }
            $this->collectLoopVariables($block->children, $names);
        }
    }

    /**
     * Bounds of the element ({@code <w:p>} or {@code <w:tr>}) holding [start, end), or null.
     *
     * @return array{int, int}|null
     */
    private function enclosing(string $xml, int $start, int $end, string $tag): ?array
    {
        $before = substr($xml, 0, $start);
        $open   = max(strrpos($before, '<w:' . $tag . '>'), strrpos($before, '<w:' . $tag . ' '));
        $close  = strpos($xml, '</w:' . $tag . '>', $end);

        if ($open === false || $close === false || strpos($before, '</w:' . $tag . '>', $open) !== false) {
            return null;
        }

        return [$open, $close + strlen('</w:' . $tag . '>')];
    }

    /**
     * True when every container opened in the fragment is closed in it and nothing closes an outer container.
     */
    private function balanced(string $fragment): bool
    {
        preg_match_all(self::CONTAINER_PATTERN, $fragment, $tags, PREG_SET_ORDER);

        $depth = [];
        foreach ($tags as $tag) {
            if ($tag[3] === '/') {
                continue;
            }

            $name         = $tag[2];
            $depth[$name] = ($depth[$name] ?? 0) + ($tag[1] === '/' ? -1 : 1);
            if ($depth[$name] < 0) {
                return false;
            }
        }

        foreach ($depth as $level) {
            if ($level !== 0) {
                return false;
            }
        }

        return true;
    }

    private function decode(string $marker): string
    {
        return html_entity_decode($marker, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
