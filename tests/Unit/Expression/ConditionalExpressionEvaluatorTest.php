<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Expression;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Exception\WordTemplateExceptionInterface;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Expression\ExpressionVariables;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

use function count;
use function str_repeat;

final class ConditionalExpressionEvaluatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string, mixed>, bool}>
     */
    public static function operators(): iterable
    {
        $ctx = [
            'qty'     => 3,
            'total'   => 149.9,
            'country' => 'ES',
            'email'   => 'jane@example.com',
            'is_vip'  => true,
            'notes'   => '',
            'signed'  => null,
            'covers'  => ['fire', 'theft'],
            'client'  => ['country' => 'ES', 'address' => ['city' => 'Madrid']],
            'lines'   => [['qty' => 2], ['qty' => 12]],
        ];

        yield '==' => ['qty == 3', $ctx, true];
        yield '== loose' => ["qty == '3'", $ctx, true];
        yield '===' => ["qty === '3'", $ctx, false];
        yield '!=' => ["country != 'PT'", $ctx, true];
        yield '!==' => ['qty !== 3', $ctx, false];
        yield '<' => ['qty < 3', $ctx, false];
        yield '>' => ['total > 100', $ctx, true];
        yield '<=' => ['qty <= 3', $ctx, true];
        yield '>=' => ['total >= 150', $ctx, false];
        yield 'and' => ['is_vip and qty > 1', $ctx, true];
        yield '&&' => ['is_vip && qty > 5', $ctx, false];
        yield 'or' => ['qty > 5 or is_vip', $ctx, true];
        yield '||' => ['qty > 5 || not is_vip', $ctx, false];
        yield 'not' => ['not is_vip', $ctx, false];
        yield '!' => ["!(notes == '')", $ctx, false];
        yield 'in' => ["'fire' in covers", $ctx, true];
        yield 'in literal list' => ["country in ['ES', 'PT']", $ctx, true];
        yield 'not in' => ["'flood' not in covers", $ctx, true];
        yield 'in is strict' => ["'3' in [3]", $ctx, false];
        yield 'matches' => ["email matches '/@example\\\\.com$/'", $ctx, true];
        yield 'matches no' => ["country matches '/^[a-z]+$/'", $ctx, false];
        yield 'not matches' => ["not (country matches '/^[a-z]+$/')", $ctx, true];
        yield 'starts with' => ["email starts with 'jane'", $ctx, true];
        yield 'ends with' => ["email ends with '@example.com'", $ctx, true];
        yield 'contains' => ["email contains '@'", $ctx, true];
        yield 'arithmetic' => ['qty * 50 + 1 > total', $ctx, true];
        yield 'modulo' => ['qty % 2 == 1', $ctx, true];
        yield 'string concat' => ["country ~ '-' ~ qty == 'ES-3'", $ctx, true];
        yield 'double quotes' => ['country == "ES"', $ctx, true];
        yield 'null' => ['signed == null', $ctx, true];
        yield 'null coalesce' => ["(signed ?? 'none') == 'none'", $ctx, true];
        yield 'dot access' => ["client.country == 'ES'", $ctx, true];
        yield 'nested dot access' => ["client.address.city == 'Madrid'", $ctx, true];
        yield 'bracket access' => ["client['country'] == 'ES'", $ctx, true];
        yield 'list index' => ['lines[1].qty > 10', $ctx, true];
        yield 'missing key with ??' => ["(client.zip ?? '') == ''", $ctx, true];
        yield 'null-safe on null' => ['signed?.code == null', $ctx, true];
        yield 'empty list' => ['covers != []', $ctx, true];
        yield 'ternary' => ['(qty > 1 ? total : 0) > 100', $ctx, true];
        yield 'range' => ['qty in 1..5', $ctx, true];
        yield 'largest allowed range' => ['qty in 1..10000', $ctx, true];
        yield 'literal true' => ['true', $ctx, true];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('operators')]
    public function testOperators(string $expression, array $context, bool $expected): void
    {
        self::assertSame($expected, (new ConditionalExpressionEvaluator())->evaluate($expression, ExpressionVariables::fromContext($context)));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function failures(): iterable
    {
        yield 'syntax' => ['qty >', ['qty' => 1], 'is invalid'];
        yield 'function constant()' => ["constant('PHP_VERSION') != ''", [], 'is invalid'];
        yield 'unknown function' => ['system(1) == 1', [], 'The function "system" does not exist'];
        yield 'function enum()' => ["enum('X::Y') == null", [], 'is invalid'];
        yield 'method call' => ['client.offsetGet(\'country\') == 1', ['client' => ['country' => 'ES']], 'method calls are not available'];
        yield 'unknown variable' => ['totl > 0', ['total' => 1], 'uses the unknown variable "totl"'];
        yield 'unknown map key' => ['client.zip == 1', ['client' => ['country' => 'ES']], 'uses the unknown variable "client.zip"'];
        yield 'unknown nested list key' => ['client[0] == 1', ['client' => ['country' => 'ES']], 'uses the unknown variable "client[0]"'];
        yield 'missing list index' => ['covers[5] == 1', ['covers' => ['a']], 'could not be evaluated'];
        yield 'property of scalar' => ['qty.foo == 1', ['qty' => 1], 'could not be evaluated'];
        yield 'in on a map' => ["'ES' in client", ['client' => ['country' => 'ES']], 'could not be evaluated'];
        yield 'invalid regex variable' => ['qty matches pattern', ['qty' => '1', 'pattern' => '/[/'], 'could not be evaluated'];
        yield 'not boolean string' => ['notes', ['notes' => 'text'], 'must evaluate to true or false, got string'];
        yield 'not boolean null' => ['signed', ['signed' => null], 'got null'];
        yield 'not boolean int' => ['qty', ['qty' => 1], 'got int'];
        yield 'empty' => ['', [], 'is empty'];
        yield 'too long' => [str_repeat('a', 501), [], 'longer than the allowed 500 characters'];
        // SEC-004 BQ M1: the range operator would allocate the whole list.
        yield 'huge range in count()' => ['count(1..100000000) > 0', [], 'a range (a..b) may hold at most 10000 numbers'];
        yield 'huge range with in' => ['5 in 1..100000000', [], 'a range (a..b) may hold at most 10000 numbers'];
        yield 'range span just over' => ['5 in 0..10000', [], 'at most 10000 numbers'];
        yield 'range from a variable' => ['5 in 1..n', ['n' => 3], 'a range (a..b) needs two integer literals'];
        yield 'range of floats' => ['5 in 1.5..9', [], 'needs two integer literals'];
        yield 'write attempt' => ['client.country = 1', ['client' => ['country' => 'ES']], 'is invalid'];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('failures')]
    public function testFailures(string $expression, array $context, string $message): void
    {
        try {
            (new ConditionalExpressionEvaluator())->evaluate($expression, ExpressionVariables::fromContext($context));
            self::fail('ConditionalExpressionException expected');
        } catch (WordTemplateExceptionInterface $e) {
            self::assertInstanceOf(ConditionalExpressionException::class, $e);
            self::assertStringContainsString($message, $e->getMessage());
            self::assertSame($expression, $e->expression);
        }
    }

    /**
     * REQ-SEC-004 BQ N4: runtime errors never quote values taken from the context.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function secretLeaks(): iterable
    {
        yield 'dynamic map key' => ['client[client.secret] == 1', 'unknown variable "client.…"'];
        yield 'dynamic list index' => ['lines[client.secret] == 1', 'a list index or map key does not exist'];
        yield 'regex from a value' => ["'x' matches client.secret", 'a regular expression is invalid'];
        yield 'division by zero' => ['1 / zero > 0', 'division by zero'];
        yield 'wrong type' => ['client.secret contains lines', 'wrong type'];
        yield 'other runtime error' => ['zero.foo == 1', 'RuntimeException (details withheld'];
    }

    #[DataProvider('secretLeaks')]
    public function testErrorsNeverEchoContextValues(string $expression, string $message): void
    {
        try {
            (new ConditionalExpressionEvaluator())->evaluate($expression, ExpressionVariables::fromContext(['client' => ['secret' => 's3cr3t'], 'lines' => ['a'], 'zero' => 0]));
            self::fail('ConditionalExpressionException expected');
        } catch (ConditionalExpressionException $e) {
            self::assertStringContainsString($message, $e->getMessage());
            self::assertStringNotContainsString('s3cr3t', $e->getMessage());
        }
    }

    public function testLiteralKeysAreStillNamedInErrors(): void
    {
        $this->expectExceptionMessage('unknown variable "client.zip"');
        (new ConditionalExpressionEvaluator())->evaluate("client['zip'] == 1", ExpressionVariables::fromContext(['client' => ['a' => 1]]));
    }

    public function testLintChecksNamesWithoutValues(): void
    {
        $evaluator = new ConditionalExpressionEvaluator();
        $evaluator->lint('client.country == "ES" and total > 0', ['client', 'total']);

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage('unknown variable "total"');
        $evaluator->lint('client.country == "ES" and total > 0', ['client']);
    }

    public function testCustomMaxLengthAndCachePool(): void
    {
        $pool      = new ArrayAdapter();
        $evaluator = new ConditionalExpressionEvaluator(5, $pool);

        self::assertTrue($evaluator->evaluate('a', ['a' => true]));
        self::assertTrue($evaluator->evaluate('a', ['a' => true]));
        self::assertCount(1, $pool->getValues());

        $this->expectException(ConditionalExpressionException::class);
        $evaluator->evaluate('a == true', ['a' => true]);
    }

    public function testDefaultCacheIsBounded(): void
    {
        $evaluator = new ConditionalExpressionEvaluator();
        for ($i = 0; $i < ConditionalExpressionEvaluator::DEFAULT_CACHE_ITEMS + 50; ++$i) {
            self::assertTrue($evaluator->evaluate('a == ' . $i, ['a' => $i]));
        }

        $language = (new ReflectionProperty($evaluator, 'language'))->getValue($evaluator);
        $cache    = (new ReflectionProperty(ExpressionLanguage::class, 'cache'))->getValue($language);
        self::assertInstanceOf(ArrayAdapter::class, $cache);
        self::assertLessThanOrEqual(ConditionalExpressionEvaluator::DEFAULT_CACHE_ITEMS, count($cache->getValues()));
    }

    public function testEvaluationRestoresTheErrorHandler(): void
    {
        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler
        $previous = set_error_handler(static fn (): bool => true);
        restore_error_handler();

        try {
            (new ConditionalExpressionEvaluator())->evaluate('covers[3] == 1', ['covers' => []]);
        } catch (ConditionalExpressionException) {
        }

        // @phpstan-ignore frankenphp.worker.noSetErrorExceptionHandler
        $current = set_error_handler(static fn (): bool => true);
        restore_error_handler();
        self::assertSame($previous, $current);
    }
}
