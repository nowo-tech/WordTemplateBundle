<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;

/**
 * Collects the expression errors swallowed by the {@code hide} / {@code keep} error modes during one merge.
 * Created per {@code process()} call (never stored in a service).
 */
final class ConditionalExpressionReport
{
    /** @var list<ConditionalExpressionException> */
    private array $errors = [];

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
