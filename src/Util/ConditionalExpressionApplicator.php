<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Util;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionErrorMode;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionReport;
use Nowo\WordTemplateBundle\Expression\ExpressionBlock;
use Nowo\WordTemplateBundle\Expression\ExpressionTextNormalizer;
use Nowo\WordTemplateBundle\Expression\ExpressionVariables;

use function array_keys;
use function array_pop;
use function count;
use function html_entity_decode;
use function max;
use function preg_last_error_msg;
use function preg_match;
use function preg_match_all;
use function preg_quote;
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
 * Applies expression conditionals: {@code ${#if <expression>}} … {@code ${#endif}} (nameless end marker).
 *
 * Classification (purely syntactic, independent of the context): markers are paired like brackets in document
 * order. A nameless {@code ${#endif}} closes the nearest open {@code ${#if …}}, which makes that block an
 * expression block. A named {@code ${#endif name}} closes the nearest open {@code ${#if name}} (case-insensitive,
 * as {@see ConditionalBlockApplicator}) and leaves it to {@see ConditionalBlockApplicator} — named blocks keep
 * their legacy behaviour exactly. Blocks are evaluated outside-in: the inner expressions of a removed block are
 * never evaluated. Like named blocks, each marker must sit in its own paragraph; the marker paragraphs are removed.
 */
final readonly class ConditionalExpressionApplicator
{
    /** Opening text that is a plain legacy block name: an unclosed one is ignored silently (legacy behaviour). */
    private const PLAIN_NAME = '/^[A-Za-z0-9_.\-]+$/';

    /** Containers that a block must not cut in half. */
    private const CONTAINER_PATTERN = '#<(/?)w:(tbl|tr|tc|sdt|sdtContent|txbxContent)(?=[\s>/])[^>]*?(/?)>#';

    /**
     * @param string $ifOpening opening delimiter of {@code if} markers (e.g. {@code ${#if})
     * @param string $ifClosing closing delimiter of {@code if} markers
     * @param string $endifOpening opening delimiter of {@code endif} markers (e.g. {@code ${#endif})
     * @param string $endifClosing closing delimiter of {@code endif} markers
     * @param ConditionalExpressionEvaluator $evaluator parses / evaluates the expressions
     * @param ConditionalExpressionErrorMode $onError what to do when an expression or its layout is invalid
     */
    public function __construct(
        private string $ifOpening,
        private string $ifClosing,
        private string $endifOpening,
        private string $endifClosing,
        private ConditionalExpressionEvaluator $evaluator,
        private ConditionalExpressionErrorMode $onError = ConditionalExpressionErrorMode::Exception,
    ) {
    }

    /**
     * Keeps or removes every expression block of one WordprocessingML part.
     *
     * @param array<string, mixed> $variables exposed context ({@see ExpressionVariables})
     * @param ConditionalExpressionReport $report receives the errors swallowed by the {@code hide} / {@code keep} modes
     *
     * @throws ConditionalExpressionException in {@code exception} mode
     */
    public function apply(string $xml, array $variables, ConditionalExpressionReport $report): string
    {
        $normalized = $this->normalize($xml);
        $scan       = $this->scan($normalized);

        if ($scan['blocks'] === [] && $scan['problems'] === []) {
            return $xml;
        }

        $problems = [...$scan['problems'], ...$this->checkLayout($normalized, $scan['blocks'], 0, strlen($normalized))];
        foreach ($problems as $problem) {
            if ($this->onError === ConditionalExpressionErrorMode::Exception) {
                throw $problem;
            }
            $report->add($problem);
        }

        return $this->render($normalized, $scan['blocks'], 0, strlen($normalized), $variables, $report);
    }

    /**
     * Returns the unique expressions of the expression blocks (normalised text), in document order.
     *
     * @return list<string>
     */
    public function discoverExpressions(string $xml): array
    {
        $expressions = [];
        $this->collectExpressions($this->scan($this->normalize($xml))['blocks'], $expressions);

        return array_keys($expressions);
    }

    /**
     * Returns the unique names of the named (legacy) blocks — every {@code ${#if …}} opening marker that is not
     * closed by a nameless {@code ${#endif}} — as {@see ConditionalBlockApplicator::discoverBlockNames()} does.
     *
     * @return list<string>
     */
    public function discoverBlockNames(string $xml): array
    {
        return array_keys($this->scan($this->normalize($xml))['names']);
    }

    /**
     * Returns the layout problems of a part (stray end markers, unclosed expressions, markers sharing a paragraph,
     * blocks crossing table cells) without evaluating anything.
     *
     * @return list<ConditionalExpressionException>
     */
    public function findProblems(string $xml): array
    {
        $normalized = $this->normalize($xml);
        $scan       = $this->scan($normalized);

        return [...$scan['problems'], ...$this->checkLayout($normalized, $scan['blocks'], 0, strlen($normalized))];
    }

    private function normalize(string $xml): string
    {
        return BrokenMacroNormalizer::forConditionalDelimiters($this->ifOpening, $this->ifClosing)->normalize($xml);
    }

    /**
     * Pairs the markers like brackets.
     *
     * @return array{blocks: list<ExpressionBlock>, names: array<string, true>, problems: list<ConditionalExpressionException>}
     */
    private function scan(string $xml): array
    {
        $pattern = '/(?<endif>' . preg_quote($this->endifOpening, '/') . '(?:\s+(?<endname>[^<]*?))?\s*' . preg_quote($this->endifClosing, '/') . ')'
            . '|(?<if>' . preg_quote($this->ifOpening, '/') . '\s+(?<text>[^<]+?)' . preg_quote($this->ifClosing, '/') . ')/';

        if (preg_match_all($pattern, $xml, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL) === false) {
            // Fail closed whatever on_error says: unscanned markers would leave conditional content visible.
            throw new ConditionalExpressionException('', sprintf('Conditional markers could not be scanned (%s).', preg_last_error_msg()));
        }

        /** @var list<array{text: string, marker: string, start: int, end: int, children: list<ExpressionBlock>}> $stack */
        $stack    = [];
        $roots    = [];
        $openings = [];
        $problems = [];

        foreach ($matches as $match) {
            if ($match['if'][0] !== null) {
                $stack[] = [
                    'text'     => trim($match['text'][0] ?? ''),
                    'marker'   => $this->decode($match['if'][0]),
                    'start'    => $match['if'][1],
                    'end'      => $match['if'][1] + strlen($match['if'][0]),
                    'children' => [],
                ];

                continue;
            }

            $endMarker = (string) $match['endif'][0];
            $endStart  = $match['endif'][1];
            $endName   = trim($match['endname'][0] ?? '');

            if ($endName !== '') {
                $this->closeNamed($stack, $roots, $openings, $problems, $endName);

                continue;
            }

            $open = array_pop($stack);
            if ($open === null) {
                $problems[] = ConditionalExpressionException::invalidStructure($this->decode($endMarker), 'there is no open block to close.');

                continue;
            }

            $block = new ExpressionBlock(
                ExpressionTextNormalizer::normalize($open['text']),
                $open['marker'],
                $open['start'],
                $open['end'],
                $endStart,
                $endStart + strlen($endMarker),
                $open['children'],
            );
            $this->attach($stack, $roots, [$block]);
        }

        while ($stack !== []) {
            $this->discardUnclosed($stack, $roots, $openings, $problems);
        }

        return ['blocks' => $roots, 'names' => $openings, 'problems' => $problems];
    }

    /**
     * Closes the nearest open block with the same name; blocks opened after it are unclosed.
     *
     * @param list<array{text: string, marker: string, start: int, end: int, children: list<ExpressionBlock>}> $stack
     * @param list<ExpressionBlock> $roots
     * @param array<string, true> $openings
     * @param list<ConditionalExpressionException> $problems
     */
    private function closeNamed(array &$stack, array &$roots, array &$openings, array &$problems, string $name): void
    {
        $index = null;
        for ($i = count($stack) - 1; $i >= 0; --$i) {
            if (strcasecmp($stack[$i]['text'], $name) === 0) {
                $index = $i;

                break;
            }
        }

        if ($index === null) {
            return; // stray named end marker: left as literal text, as before
        }

        while ($stack !== [] && count($stack) - 1 > $index) {
            $this->discardUnclosed($stack, $roots, $openings, $problems);
        }

        /** @var array{text: string, marker: string, start: int, end: int, children: list<ExpressionBlock>} $open */
        $open                    = array_pop($stack);
        $openings[$open['text']] = true;
        $this->attach($stack, $roots, $open['children']);
    }

    /**
     * Drops the innermost open block (never closed); its expression children move up one level.
     *
     * @param non-empty-list<array{text: string, marker: string, start: int, end: int, children: list<ExpressionBlock>}> $stack
     *
     * @param-out list<array{text: string, marker: string, start: int, end: int, children: list<ExpressionBlock>}> $stack
     *
     * @param list<ExpressionBlock> $roots
     * @param array<string, true> $openings
     * @param list<ConditionalExpressionException> $problems
     */
    private function discardUnclosed(array &$stack, array &$roots, array &$openings, array &$problems): void
    {
        /** @var array{text: string, marker: string, start: int, end: int, children: list<ExpressionBlock>} $open */
        $open                    = array_pop($stack);
        $openings[$open['text']] = true;

        if (preg_match(self::PLAIN_NAME, $open['text']) !== 1) {
            $problems[] = ConditionalExpressionException::invalidStructure($open['marker'], 'the block is never closed (add a nameless end marker).');
        }

        $this->attach($stack, $roots, $open['children']);
    }

    /**
     * @param list<array{text: string, marker: string, start: int, end: int, children: list<ExpressionBlock>}> $stack
     * @param list<ExpressionBlock> $roots
     * @param list<ExpressionBlock> $blocks
     */
    private function attach(array &$stack, array &$roots, array $blocks): void
    {
        if ($stack === []) {
            $roots = [...$roots, ...$blocks];

            return;
        }

        $top                     = count($stack) - 1;
        $stack[$top]['children'] = [...$stack[$top]['children'], ...$blocks];
    }

    /**
     * Computes the paragraph regions of the blocks and records layout problems on them.
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
        $open  = $this->paragraph($xml, $block->openStart, $block->openEnd);
        $close = $this->paragraph($xml, $block->closeStart, $block->closeEnd);

        if ($open === null || $close === null) {
            return ConditionalExpressionException::invalidStructure($block->marker, 'each marker must be inside a paragraph.');
        }

        if ($open[1] > $close[0]) {
            return ConditionalExpressionException::invalidStructure($block->marker, 'the opening and the closing marker must be in different paragraphs (inline blocks are not supported).');
        }

        if ($open[0] < $from || $close[1] > $to || $open[0] < $previousEnd) {
            return ConditionalExpressionException::invalidStructure($block->marker, 'the marker shares a paragraph with the marker of another block; put each marker in its own paragraph.');
        }

        if (!$this->balanced(substr($xml, $open[1], $close[0] - $open[1]))) {
            return ConditionalExpressionException::invalidStructure($block->marker, 'the block crosses table cells, rows or content controls; wrap whole paragraphs or a whole table.');
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
    private function render(string $xml, array $blocks, int $from, int $to, array $variables, ConditionalExpressionReport $report): string
    {
        $out = '';
        $pos = $from;

        foreach ($blocks as $block) {
            if ($block->problem !== null) {
                continue; // left as literal text (only reachable in hide / keep modes)
            }

            $out .= substr($xml, $pos, $block->regionStart - $pos);
            $pos = $block->regionEnd;

            try {
                $visible = $this->evaluator->evaluate($block->expression, $variables);
            } catch (ConditionalExpressionException $e) {
                if ($this->onError === ConditionalExpressionErrorMode::Exception) {
                    throw $e;
                }
                $report->add($e);

                if ($this->onError === ConditionalExpressionErrorMode::Keep) {
                    $out .= substr($xml, $block->regionStart, $block->contentStart - $block->regionStart)
                        . $this->render($xml, $block->children, $block->contentStart, $block->contentEnd, $variables, $report)
                        . substr($xml, $block->contentEnd, $block->regionEnd - $block->contentEnd);
                }

                continue;
            }

            if ($visible) {
                $out .= $this->render($xml, $block->children, $block->contentStart, $block->contentEnd, $variables, $report);
            }
        }

        return $out . substr($xml, $pos, $to - $pos);
    }

    /**
     * @param list<ExpressionBlock> $blocks
     * @param array<string, true> $expressions
     */
    private function collectExpressions(array $blocks, array &$expressions): void
    {
        foreach ($blocks as $block) {
            $expressions[$block->expression] = true;
            $this->collectExpressions($block->children, $expressions);
        }
    }

    /**
     * Bounds of the paragraph ({@code <w:p>} … {@code </w:p>}) holding the marker at [start, end), or null.
     *
     * @return array{int, int}|null
     */
    private function paragraph(string $xml, int $start, int $end): ?array
    {
        $before = substr($xml, 0, $start);
        $open   = max(strrpos($before, '<w:p>'), strrpos($before, '<w:p '));
        $close  = strpos($xml, '</w:p>', $end);

        if ($open === false || $close === false || strpos($before, '</w:p>', $open) !== false) {
            return null;
        }

        return [$open, $close + 6];
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
