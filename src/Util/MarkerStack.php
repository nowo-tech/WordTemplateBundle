<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Util;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Expression\ExpressionBlock;

use function array_pop;
use function count;
use function html_entity_decode;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * Bracket-matching state of one scan of {@see ConditionalExpressionApplicator}: open markers, finished root blocks,
 * the names of the named (legacy) blocks and the layout problems found so far. Created per scan.
 *
 * @internal
 *
 * @phpstan-type OpenMarker array{type: 'if'|'for', text: string, raw: string, marker: string, start: int, children: list<ExpressionBlock>}
 */
final class MarkerStack
{
    /** @var list<OpenMarker> */
    public array $stack = [];

    /** @var list<ExpressionBlock> */
    public array $roots = [];

    /** @var array<string, true> */
    public array $openings = [];

    /** @var list<ConditionalExpressionException> */
    public array $problems = [];

    /**
     * @param 'for'|'if' $type
     */
    public function open(string $type, string $text, string $raw, int $start): void
    {
        // @igor-ignore - Per-scan state object, never a shared service.
        $this->stack[] = [
            'type'     => $type,
            'text'     => $text,
            'raw'      => $raw,
            'marker'   => html_entity_decode($raw, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            'start'    => $start,
            'children' => [],
        ];
    }

    /**
     * @return OpenMarker|null
     */
    public function top(): ?array
    {
        return $this->stack === [] ? null : $this->stack[count($this->stack) - 1];
    }

    /**
     * @return OpenMarker
     */
    public function pop(): array
    {
        /** @var OpenMarker $open */
        $open = array_pop($this->stack);

        return $open;
    }

    /**
     * Index of the nearest open marker matching {@code $match}, searching from the top; null when none or when a
     * marker matching {@code $barrier} is met first.
     *
     * @param callable(OpenMarker): bool $match
     * @param callable(OpenMarker): bool $barrier
     */
    public function find(callable $match, callable $barrier): ?int
    {
        for ($i = count($this->stack) - 1; $i >= 0; --$i) {
            if ($match($this->stack[$i])) {
                return $i;
            }
            if ($barrier($this->stack[$i])) {
                return null;
            }
        }

        return null;
    }

    /**
     * Adds finished blocks to the innermost open marker (or to the roots).
     *
     * @param list<ExpressionBlock> $blocks
     */
    public function attach(array $blocks): void
    {
        if ($this->stack === []) {
            // @igor-ignore - Per-scan state object, never a shared service.
            $this->roots = [...$this->roots, ...$blocks];

            return;
        }

        $top = count($this->stack) - 1;
        // @igor-ignore - Per-scan state object, never a shared service.
        $this->stack[$top]['children'] = [...$this->stack[$top]['children'], ...$blocks];
    }
}
