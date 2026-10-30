<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Thrown when an expression conditional ({@code ${#if total > 0}} … {@code ${#endif}}) cannot be used:
 * invalid syntax, forbidden construct, unknown variable, failed evaluation, non-boolean result or a marker
 * layout the bundle cannot apply safely. {@see self::$expression} holds the expression (or marker) text.
 */
final class ConditionalExpressionException extends RuntimeException implements WordTemplateExceptionInterface
{
    /**
     * @param string $expression normalised expression (or marker) text the error refers to
     * @param string $message human-readable reason, always naming the expression
     * @param Throwable|null $previous underlying parser / evaluation error
     */
    public function __construct(
        public readonly string $expression,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The expression does not parse (or uses a function, which is never available).
     */
    public static function invalidSyntax(string $expression, Throwable $previous): self
    {
        return new self($expression, sprintf('Conditional expression "%s" is invalid: %s', $expression, $previous->getMessage()), $previous);
    }

    /**
     * The expression uses a construct that is not allowed in templates (method calls).
     */
    public static function forbidden(string $expression, string $what): self
    {
        return new self($expression, sprintf('Conditional expression "%s" is not allowed: %s.', $expression, $what));
    }

    /**
     * The expression is longer than {@code conditional_expressions.max_length}.
     */
    public static function tooLong(string $expression, int $maxLength): self
    {
        return new self($expression, sprintf('Conditional expression "%s" is longer than the allowed %d characters.', $expression, $maxLength));
    }

    /**
     * The expression reads a variable (or map key) that the context does not provide.
     */
    public static function unknownVariable(string $expression, string $variable): self
    {
        return new self($expression, sprintf('Conditional expression "%s" uses the unknown variable "%s" (not in the template context).', $expression, $variable));
    }

    /**
     * Evaluating the expression failed (type error, missing list index, invalid regex, …).
     */
    public static function evaluationFailed(string $expression, Throwable $previous): self
    {
        return new self($expression, sprintf('Conditional expression "%s" could not be evaluated: %s', $expression, $previous->getMessage()), $previous);
    }

    /**
     * The expression evaluated to something other than true / false.
     */
    public static function notBoolean(string $expression, string $type): self
    {
        return new self($expression, sprintf('Conditional expression "%s" must evaluate to true or false, got %s. Compare explicitly (e.g. "!= \'\'", "> 0").', $expression, $type));
    }

    /**
     * The markers are not laid out in a way the bundle can apply (stray end marker, same paragraph, crossing table cells, …).
     */
    public static function invalidStructure(string $marker, string $reason): self
    {
        return new self($marker, sprintf('Conditional marker "%s": %s', $marker, $reason));
    }
}
