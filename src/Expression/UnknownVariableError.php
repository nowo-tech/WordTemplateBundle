<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use OutOfBoundsException;

use function is_int;
use function sprintf;
use function str_contains;

/**
 * Raised by {@see ExpressionMap} when an expression reads a key the context does not have.
 *
 * @internal converted to {@see ConditionalExpressionException} by {@see ConditionalExpressionEvaluator}
 */
final class UnknownVariableError extends OutOfBoundsException
{
    /** Full path of the missing value (e.g. {@code client.zip}, {@code lines[3]}). */
    public readonly string $variable;

    /**
     * @param string $parent path of the map holding the key (built from context key names, never from values)
     * @param int|string $key missing key — may come from a value ({@code client[client.secret]})
     */
    public function __construct(
        public readonly string $parent,
        public readonly int|string $key,
    ) {
        $this->variable = $this->join($parent, (string) $key, is_int($key));
        parent::__construct(sprintf('Unknown variable "%s".', $this->variable));
    }

    /**
     * The path with the key replaced by "…" when the key is not written in the expression (it then comes from a value).
     */
    public function safeVariable(string $expression): string
    {
        return str_contains($expression, (string) $this->key) ? $this->variable : $this->join($this->parent, '…', is_int($this->key));
    }

    private function join(string $parent, string $key, bool $index): string
    {
        return $parent . ($index ? '[' . $key . ']' : '.' . $key);
    }
}
