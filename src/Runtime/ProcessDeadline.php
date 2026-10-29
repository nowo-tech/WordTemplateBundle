<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Runtime;

use Nowo\WordTemplateBundle\Exception\ProcessingTimedOutException;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;

use function sprintf;

/**
 * Cooperative wall-clock deadline for {@see WordTemplateProcessor::process()}.
 *
 * @internal
 */
final readonly class ProcessDeadline
{
    private float $deadlineNs;

    /**
     * @param int $timeoutSeconds allowed wall-clock duration in seconds
     * @param float|null $startedAtNs start time from {@see hrtime()} in nanoseconds; null means now
     */
    public function __construct(
        private int $timeoutSeconds,
        ?float $startedAtNs = null,
    ) {
        $startedAtNs ??= hrtime(true);
        $this->deadlineNs = $startedAtNs + ($timeoutSeconds * 1_000_000_000.0);
    }

    /**
     * Checkpoint called between merge steps.
     *
     * @throws ProcessingTimedOutException when the deadline has passed
     */
    public function assertNotTimedOut(): void
    {
        if (hrtime(true) < $this->deadlineNs) {
            return;
        }

        throw new ProcessingTimedOutException(sprintf('Word template processing timed out after %d seconds.', $this->timeoutSeconds));
    }
}
