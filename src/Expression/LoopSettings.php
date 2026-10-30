<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

/**
 * Configuration of template loops ({@code ${#for item in items}} … {@code ${#endfor}}): marker and placeholder
 * delimiters and the iteration limits ({@code nowo_word_template.loops.*}).
 */
final readonly class LoopSettings
{
    public const DEFAULT_MAX_ITERATIONS = 1000;

    public const DEFAULT_MAX_TOTAL_ITERATIONS = 10_000;

    /**
     * @param string $forOpening opening delimiter of loop markers (e.g. {@code ${#for})
     * @param string $forClosing closing delimiter of loop markers
     * @param string $endforOpening opening delimiter of loop end markers (e.g. {@code ${#endfor})
     * @param string $endforClosing closing delimiter of loop end markers
     * @param string $macroOpening placeholder opening delimiter (for {@code ${item.name}} in loop bodies)
     * @param string $macroClosing placeholder closing delimiter
     * @param int $maxIterations maximum number of iterations of one loop
     * @param int $maxTotalIterations maximum number of iterations of all loops of one merge (nested loops multiply)
     */
    public function __construct(
        public string $forOpening = '${#for',
        public string $forClosing = '}',
        public string $endforOpening = '${#endfor',
        public string $endforClosing = '}',
        public string $macroOpening = '${',
        public string $macroClosing = '}',
        public int $maxIterations = self::DEFAULT_MAX_ITERATIONS,
        public int $maxTotalIterations = self::DEFAULT_MAX_TOTAL_ITERATIONS,
    ) {
    }
}
