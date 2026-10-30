<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Util\ConditionalExpressionApplicator;

/**
 * One expression conditional found in a document part: {@code ${#if <expression>}} … {@code ${#endif}}.
 * Offsets are byte offsets in the (healed) WordprocessingML; the region fields are filled by the layout check.
 *
 * @internal built and consumed by {@see ConditionalExpressionApplicator}
 */
final class ExpressionBlock
{
    /** Start of the paragraph holding the opening marker (start of the removable region). */
    public int $regionStart = 0;

    /** End of the paragraph holding the opening marker (start of the content). */
    public int $contentStart = 0;

    /** Start of the paragraph holding the closing marker (end of the content). */
    public int $contentEnd = 0;

    /** End of the paragraph holding the closing marker (end of the removable region). */
    public int $regionEnd = 0;

    /** Layout problem; such a block is never applied. */
    public ?ConditionalExpressionException $problem = null;

    /**
     * @param string $expression normalised expression text
     * @param string $marker opening marker as typed (decoded), for error messages
     * @param int $openStart offset of the opening marker
     * @param int $openEnd offset just after the opening marker
     * @param int $closeStart offset of the closing marker
     * @param int $closeEnd offset just after the closing marker
     * @param list<ExpressionBlock> $children expression blocks nested inside, in document order
     */
    public function __construct(
        public readonly string $expression,
        public readonly string $marker,
        public readonly int $openStart,
        public readonly int $openEnd,
        public readonly int $closeStart,
        public readonly int $closeEnd,
        public readonly array $children,
    ) {
    }
}
