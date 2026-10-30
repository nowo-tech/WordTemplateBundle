<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

/**
 * Resource limits of expression blocks (conditionals and loops) for one merge or one inspection
 * ({@code nowo_word_template.conditional_expressions.max_markers / max_depth / max_evaluations},
 * {@code nowo_word_template.loops.max_output_bytes}).
 */
final readonly class ExpressionLimits
{
    public const DEFAULT_MAX_MARKERS = 2000;

    public const DEFAULT_MAX_DEPTH = 32;

    public const DEFAULT_MAX_EVALUATIONS = 100_000;

    public const DEFAULT_MAX_OUTPUT_BYTES = 33_554_432;

    /**
     * @param int $maxMarkers maximum number of expression / loop markers in one document part
     * @param int $maxDepth maximum nesting depth of expression blocks and loops
     * @param int $maxEvaluations maximum number of expression evaluations of one merge (loops re-evaluate their bodies)
     * @param int $maxOutputBytes maximum bytes produced by all loops of one merge, all parts together
     */
    public function __construct(
        public int $maxMarkers = self::DEFAULT_MAX_MARKERS,
        public int $maxDepth = self::DEFAULT_MAX_DEPTH,
        public int $maxEvaluations = self::DEFAULT_MAX_EVALUATIONS,
        public int $maxOutputBytes = self::DEFAULT_MAX_OUTPUT_BYTES,
    ) {
    }
}
