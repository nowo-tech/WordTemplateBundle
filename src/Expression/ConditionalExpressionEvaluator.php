<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use ErrorException;
use InvalidArgumentException;
use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface as SymfonyExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\Parser;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Throwable;

use function abs;
use function array_keys;
use function get_debug_type;
use function implode;
use function in_array;
use function is_bool;
use function is_int;
use function mb_strlen;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

/**
 * Parses and evaluates expression conditionals with a {@see SandboxedExpressionLanguage} whose only callables are
 * the functions of the configured providers ({@see BuiltinExpressionFunctions} by default, plus host providers).
 *
 * - parsed expressions are cached in a bounded PSR-6 pool (default: in-memory {@see ArrayAdapter} capped at
 *   {@see self::DEFAULT_CACHE_ITEMS} entries, so a long-running worker never grows without limit);
 * - method calls ({@code client.getName()}) are rejected; unknown functions are syntax errors;
 * - every variable must be in the context ({@see ExpressionVariables}), otherwise the error names it;
 * - PHP warnings during evaluation (missing list index, invalid regex, …) become errors;
 * - the result must be a bool (a top-level {@code matches} returns 0/1 and is converted).
 */
final readonly class ConditionalExpressionEvaluator
{
    public const DEFAULT_MAX_LENGTH = 500;

    public const DEFAULT_CACHE_ITEMS = 256;

    /** Maximum number of elements of a range literal ({@code 1..100}). */
    public const MAX_RANGE = BuiltinExpressionFunctions::MAX_ITEMS;

    private SandboxedExpressionLanguage $language;

    /**
     * @param int $maxLength maximum expression length in characters
     * @param CacheItemPoolInterface|null $cache pool for parsed expressions; null uses a bounded in-memory pool
     * @param iterable<ExpressionFunctionProviderInterface|SymfonyExpressionFunctionProviderInterface>|null $functionProviders
     *                                                                                                                         function providers in priority order (a later function overrides an earlier one with the same
     *                                                                                                                         name); null = {@see BuiltinExpressionFunctions} only
     * @param list<string>|null $allowedFunctions when not null, only these functions are registered
     * @param list<string> $deniedFunctions functions that are never registered
     */
    public function __construct(
        private int $maxLength = self::DEFAULT_MAX_LENGTH,
        ?CacheItemPoolInterface $cache = null,
        ?iterable $functionProviders = null,
        ?array $allowedFunctions = null,
        array $deniedFunctions = [],
    ) {
        $functions = [];
        foreach ($functionProviders ?? [new BuiltinExpressionFunctions()] as $provider) {
            foreach ($provider->getFunctions() as $function) {
                $functions[$function->getName()] = $function;
            }
        }

        foreach ([...($allowedFunctions ?? []), ...$deniedFunctions] as $name) {
            if (!isset($functions[$name])) {
                throw new InvalidArgumentException(sprintf('Unknown expression function "%s" in nowo_word_template.conditional_expressions.functions (available: %s).', $name, implode(', ', array_keys($functions))));
            }
        }

        $this->language = new SandboxedExpressionLanguage($cache ?? new ArrayAdapter(0, false, 0, self::DEFAULT_CACHE_ITEMS));
        foreach ($functions as $name => $function) {
            if (($allowedFunctions === null || in_array($name, $allowedFunctions, true)) && !in_array($name, $deniedFunctions, true)) {
                // @igor-ignore - Constructor-time setup of the evaluator's own language instance; never changed afterwards.
                $this->language->addFunction($function);
            }
        }
    }

    /**
     * Parses an expression and checks the constructs it uses (not the variables).
     *
     * @throws ConditionalExpressionException when empty, too long, unparsable or using a method call
     */
    public function parse(string $expression): ParsedExpression
    {
        if ($expression === '') {
            throw new ConditionalExpressionException($expression, 'Conditional expression is empty.');
        }

        if (mb_strlen($expression) > $this->maxLength) {
            throw ConditionalExpressionException::tooLong($expression, $this->maxLength);
        }

        try {
            $parsed = $this->language->parse($expression, [], Parser::IGNORE_UNKNOWN_VARIABLES);
        } catch (SyntaxError $e) {
            throw ConditionalExpressionException::invalidSyntax($expression, $e);
        }

        $this->assertAllowed($parsed->getNodes(), $expression);

        return $parsed;
    }

    /**
     * Checks an expression against a list of top-level variable names without evaluating it.
     *
     * @param list<string> $variableNames names available in the context (e.g. {@code client} for {@code client.country})
     *
     * @throws ConditionalExpressionException
     */
    public function lint(string $expression, array $variableNames): void
    {
        $this->assertKnownNames($this->parse($expression), $expression, $variableNames);
    }

    /**
     * Evaluates an expression against exposed variables ({@see ExpressionVariables::fromContext()}).
     *
     * @param array<string, mixed> $variables
     *
     * @throws ConditionalExpressionException
     */
    public function evaluate(string $expression, array $variables): bool
    {
        $parsed = $this->parse($expression);
        $this->assertKnownNames($parsed, $expression, array_keys($variables));

        // Scoped to this call and always restored in finally (same pattern as ExpressionLanguage's own "matches").
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });

        try {
            $result = $this->language->evaluate($parsed, $variables);
        } catch (UnknownVariableError $e) {
            throw ConditionalExpressionException::unknownVariable($expression, $e->safeVariable($expression));
        } catch (Throwable $e) {
            throw ConditionalExpressionException::evaluationFailed($expression, $e);
        } finally {
            restore_error_handler();
        }

        $root = $parsed->getNodes();
        if (is_int($result) && $root instanceof BinaryNode && $root->attributes['operator'] === 'matches') {
            $result = $result === 1;
        }

        if (!is_bool($result)) {
            throw ConditionalExpressionException::notBoolean($expression, get_debug_type($result));
        }

        return $result;
    }

    /**
     * @param list<string> $variableNames
     */
    private function assertKnownNames(ParsedExpression $parsed, string $expression, array $variableNames): void
    {
        foreach (self::collectNames($parsed->getNodes()) as $name) {
            if (!in_array($name, $variableNames, true)) {
                throw ConditionalExpressionException::unknownVariable($expression, $name);
            }
        }
    }

    private function assertAllowed(Node $node, string $expression): void
    {
        if ($node instanceof GetAttrNode && $node->attributes['type'] === GetAttrNode::METHOD_CALL) {
            throw ConditionalExpressionException::forbidden($expression, 'method calls are not available in templates');
        }

        // The range operator allocates the whole list: only between integer literals, with a bounded span.
        if ($node instanceof BinaryNode && $node->attributes['operator'] === '..') {
            $left  = $node->nodes['left'];
            $right = $node->nodes['right'];
            if (!$left instanceof ConstantNode || !$right instanceof ConstantNode || !is_int($left->attributes['value']) || !is_int($right->attributes['value'])) {
                throw ConditionalExpressionException::forbidden($expression, 'a range (a..b) needs two integer literals');
            }

            if (abs($right->attributes['value'] - $left->attributes['value']) >= self::MAX_RANGE) {
                throw ConditionalExpressionException::forbidden($expression, sprintf('a range (a..b) may hold at most %d numbers', self::MAX_RANGE));
            }
        }

        foreach ($node->nodes as $child) {
            $this->assertAllowed($child, $expression);
        }
    }

    /**
     * @return list<string> top-level variable names used by the expression
     */
    private static function collectNames(Node $node): array
    {
        $names = [];
        if ($node instanceof NameNode) {
            $names[] = $node->attributes['name'];
        }

        foreach ($node->nodes as $child) {
            $names = [...$names, ...self::collectNames($child)];
        }

        return $names;
    }
}
