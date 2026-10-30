<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;

/**
 * Collects the expression errors swallowed by the {@code hide} / {@code keep} error modes during one merge, and
 * counts its loop iterations.
 * Created per {@code process()} call (never stored in a service).
 */
final class ConditionalExpressionReport
{
    /** @var list<ConditionalExpressionException> */
    private array $errors = [];

    private int $iterations = 0;

    private int $evaluations = 0;

    private int $outputBytes = 0;

    /**
     * Counts one expression evaluation and returns the total of the merge.
     */
    public function countEvaluation(): int
    {
        // @igor-ignore - Per-call collector created inside process(); never a shared service.
        return ++$this->evaluations;
    }

    /**
     * Adds bytes produced by loops (one budget for all loops and parts of the merge).
     */
    public function addOutputBytes(int $bytes): void
    {
        // @igor-ignore - Per-call collector created inside process(); never a shared service.
        $this->outputBytes += $bytes;
    }

    public function outputBytes(): int
    {
        return $this->outputBytes;
    }

    /**
     * Counts loop iterations of the merge (checked against {@code loops.max_total_iterations}).
     */
    public function addIterations(int $count): void
    {
        // @igor-ignore - Per-call collector created inside process(); never a shared service.
        $this->iterations += $count;
    }

    public function iterations(): int
    {
        return $this->iterations;
    }

    public function add(ConditionalExpressionException $error): void
    {
        // @igor-ignore - Per-call collector created inside process(); never a shared service.
        $this->errors[] = $error;
    }

    /**
     * @return list<ConditionalExpressionException>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
