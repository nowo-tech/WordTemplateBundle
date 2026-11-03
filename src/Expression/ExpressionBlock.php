<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Util\ConditionalExpressionApplicator;

/**
 * One expression block found in a document part: a conditional ({@code ${#if <expression>}} … {@code ${#endif}})
 * or a loop ({@code ${#for item in <expression>}} … {@code ${#endfor}}, {@see self::$loopItem} set).
 *
 * A conditional may have {@see self::$branches}: its {@code ${#elseif <expression>}} and {@code ${#else}} parts, each
 * one an {@see ExpressionBlock} whose opening marker is the branch marker and whose closing marker is the next branch
 * marker (or the end marker). The content of the conditional itself then ends at its first branch.
 * Offsets are byte offsets in the (healed) WordprocessingML; the region fields are filled by the layout check.
 *
 * @internal built and consumed by {@see ConditionalExpressionApplicator}
 */
final class ExpressionBlock
{
    /** Start of the paragraph (or table row, for blocks whose markers sit in different rows) holding the opening marker (a branch: its marker). */
    public int $regionStart = 0;

    /** End of the paragraph holding the opening marker (start of the content). */
    public int $contentStart = 0;

    /** Start of the paragraph holding the closing marker (end of the content); with branches, start of the first branch marker. */
    public int $contentEnd = 0;

    /** End of the paragraph holding the closing marker (end of the removable region). */
    public int $regionEnd = 0;

    /** Layout problem; such a block is never applied. */
    public ?ConditionalExpressionException $problem = null;

    /**
     * @param string $expression normalised expression text (for a loop: the iterable expression after {@code in})
     * @param string $marker opening marker as typed (decoded), for error messages
     * @param int $openStart offset of the opening marker
     * @param int $openEnd offset just after the opening marker
     * @param int $closeStart offset of the closing marker
     * @param int $closeEnd offset just after the closing marker
     * @param list<ExpressionBlock> $children expression blocks nested inside, in document order
     * @param string|null $loopItem loop value variable ({@code item} in {@code item in items}); null for a conditional
     * @param string|null $loopKey loop key variable ({@code key} in {@code key, item in items})
     * @param list<ExpressionBlock> $branches {@code ${#elseif}} / {@code ${#else}} parts of a conditional, in document order
     * @param bool $else true for an {@code ${#else}} branch (its expression is empty and never evaluated)
     */
    public function __construct(
        public readonly string $expression,
        public readonly string $marker,
        public readonly int $openStart,
        public readonly int $openEnd,
        public readonly int $closeStart,
        public readonly int $closeEnd,
        public readonly array $children,
        public readonly ?string $loopItem = null,
        public readonly ?string $loopKey = null,
        public readonly array $branches = [],
        public readonly bool $else = false,
    ) {
    }

    public function isLoop(): bool
    {
        return $this->loopItem !== null;
    }

    /**
     * Loop head as written ({@code item in items} / {@code key, item in items}); the expression for a conditional.
     */
    public function loopHead(): string
    {
        if ($this->loopItem === null) {
            return $this->expression;
        }

        return ($this->loopKey !== null ? $this->loopKey . ', ' : '') . $this->loopItem . ' in ' . $this->expression;
    }
}
