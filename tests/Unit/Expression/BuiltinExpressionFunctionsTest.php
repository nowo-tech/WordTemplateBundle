<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Expression;

use InvalidArgumentException;
use LogicException;
use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Expression\BuiltinExpressionFunctions;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Expression\ExpressionFunctionError;
use Nowo\WordTemplateBundle\Expression\ExpressionFunctionProviderInterface;
use Nowo\WordTemplateBundle\Expression\ExpressionVariables;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

use function array_map;
use function sort;
use function str_repeat;

final class BuiltinExpressionFunctionsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'name'   => 'acme industries',
            'city'   => 'MÁLAGA',
            'code'   => '  ES-01  ',
            'total'  => -149.956,
            'qty'    => 3,
            'nil'    => null,
            'empty'  => '',
            'tags'   => ['fire', 'theft', 'flood'],
            'none'   => [],
            'prices' => [10, 2.5, 7],
            'client' => ['name' => 'ACME', 'country' => 'ES', 'zip' => null],
            'due'    => '2026-11-09',
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function trueExpressions(): iterable
    {
        // strings
        yield 'lower' => ["lower(city) == 'málaga'"];
        yield 'upper' => ["upper(name) == 'ACME INDUSTRIES'"];
        yield 'upper of null' => ["upper(nil) == ''"];
        yield 'capitalize' => ["capitalize(city) == 'Málaga'"];
        yield 'title' => ["title(name) == 'Acme Industries'"];
        yield 'trim' => ["trim(code) == 'ES-01'"];
        yield 'trim characters' => ["trim('--x--', '-') == 'x'"];
        yield 'length of string (multibyte)' => ['length(city) == 6'];
        yield 'length of number' => ['length(qty) == 1'];
        yield 'length of list' => ['length(tags) == 3'];
        yield 'length of map' => ['length(client) == 3'];
        yield 'replace' => ["replace(name, 'acme', 'ACME') == 'ACME industries'"];
        yield 'replace with numeric map keys' => ["replace('a1b2', {'1': 'one', '2': 'two'}) == 'aonebtwo'"];
        yield 'replace with a list (index keys)' => ["replace('0-1', ['zero', 'one']) == 'zero-one'"];
        yield 'replace with map' => ["replace(name, {'acme': 'Acme', 'industries': 'Inc.'}) == 'Acme Inc.'"];
        yield 'slice string' => ["slice(city, 0, 3) == 'MÁL'"];
        yield 'slice string to end' => ["slice(city, -2) == 'GA'"];
        yield 'slice list' => ["slice(tags, 1, 1) == ['theft']"];
        yield 'slice map' => ["slice(client, 1, 1) == {'country': 'ES'}"];
        yield 'starts_with' => ["starts_with(name, 'acme')"];
        yield 'ends_with' => ["ends_with(name, 'tries')"];
        // numbers
        yield 'abs' => ['abs(total) > 149'];
        yield 'round' => ['round(total, 2) == -149.96'];
        yield 'round default' => ['round(2.5) == 3'];
        yield 'floor' => ['floor(total) == -150'];
        yield 'ceil' => ['ceil(total) == -149'];
        yield 'min of arguments' => ['min(qty, 2, 5) == 2'];
        yield 'max of list' => ['max(prices) == 10'];
        yield 'number_format' => ["number_format(1234.5, 2, ',', '.') == '1.234,50'"];
        yield 'number_format default' => ["number_format(1234.5) == '1,235'"];
        // arrays
        yield 'count' => ['count(tags) == 3'];
        yield 'keys' => ["keys(client) == ['name', 'country', 'zip']"];
        yield 'first' => ["first(tags) == 'fire'"];
        yield 'last' => ["last(tags) == 'flood'"];
        yield 'first of map' => ["first(client) == 'ACME'"];
        yield 'first of empty list' => ['first(none) == null'];
        yield 'first of string' => ["first(city) == 'M'"];
        yield 'last of string' => ["last(city) == 'A'"];
        yield 'last of empty string' => ["last(empty) == ''"];
        yield 'join' => ["join(tags, ', ') == 'fire, theft, flood'"];
        yield 'join numbers default glue' => ["join(prices) == '102.57'"];
        yield 'empty null' => ['empty(nil)'];
        yield 'empty string' => ['empty(empty)'];
        yield 'empty list' => ['empty(none)'];
        yield 'empty false' => ['empty(false)'];
        yield 'zero is not empty' => ['not empty(0)'];
        yield 'map is not empty' => ['not empty(client)'];
        yield 'default on null' => ["default(client.zip, 'n/a') == 'n/a'"];
        yield 'default keeps value' => ["default(client.country, 'n/a') == 'ES'"];
        yield 'default fallback empty string' => ["default(nil) == ''"];
        yield 'is_null' => ['is_null(client.zip)'];
        yield 'is_defined' => ["is_defined('client.zip')"];
        yield 'is_defined top level' => ["is_defined('qty')"];
        yield 'is_defined missing' => ["not is_defined('client.vat')"];
        yield 'is_defined through scalar' => ["not is_defined('qty.x')"];
        yield 'is_defined list index' => ["is_defined('tags.1')"];
        // dates (now = 2026-10-30 12:00 Europe/Madrid)
        yield 'now' => ["now() == '2026-10-30'"];
        yield 'now with format' => ["now('H:i') == '12:00'"];
        yield 'date relative' => ["date('+30 days') == '2026-11-29'"];
        yield 'date absolute with format' => ["date(due, 'd/m/Y') == '09/11/2026'"];
        yield 'date timestamp' => ["date(0, 'Y') == '1970'"];
        yield 'date comparison' => ["due <= date('+10 days')"];
        yield 'date_diff_days' => ['date_diff_days(now(), due) == 10'];
        yield 'date_diff_days negative' => ["date_diff_days(due, '2026-11-01') == -8"];
    }

    #[DataProvider('trueExpressions')]
    public function testFunctions(string $expression): void
    {
        self::assertTrue($this->evaluator()->evaluate($expression, ExpressionVariables::fromContext($this->context())), $expression);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badInput(): iterable
    {
        yield 'string of list' => ["upper(tags) == ''", 'upper() expects a string, got array'];
        yield 'string of bool' => ['lower(true) == 1', 'lower() expects a string, got bool'];
        yield 'number of string' => ["abs('1') == 1", 'abs() expects a number, got string'];
        yield 'integer precision' => ['round(1.5, 1.5) == 1', 'round() expects an integer, got float'];
        yield 'items of string' => ["count('abc') == 3", 'count() expects a list or a map, got string'];
        yield 'min of nothing' => ['min(none) == 1', 'min() expects at least one number'];
        yield 'max of strings' => ["max('a', 'b') == 1", 'max() expects a number, got string'];
        yield 'join of nested list' => ["join([[1]]) == ''", 'join() expects a string, got array'];
        yield 'date unreadable' => ["date('not a date') == ''", 'date() cannot read its argument as a date'];
        yield 'date empty' => ["date('') == ''", 'date() cannot read its argument as a date'];
        yield 'number_format huge decimals' => ["number_format(1.5, 1000000000) == ''", 'number_format() accepts 0 to 20 decimals'];
        yield 'number_format negative decimals' => ["number_format(1.5, -1) == ''", 'number_format() accepts 0 to 20 decimals'];
        yield 'date format too long' => ["now(join(['Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y'], 'YY')) == ''", 'now() accepts formats up to 100 bytes'];
        yield 'date of list' => ['date(tags) == 1', 'date() expects a string, got array'];
        yield 'missing argument' => ['upper() == 1', 'could not be evaluated'];
        yield 'is_defined of list' => ['is_defined(tags)', 'is_defined() expects a string, got array'];
    }

    #[DataProvider('badInput')]
    public function testBadInputBecomesExpressionError(string $expression, string $message): void
    {
        try {
            $this->evaluator()->evaluate($expression, ExpressionVariables::fromContext($this->context()));
            self::fail('ConditionalExpressionException expected');
        } catch (ConditionalExpressionException $e) {
            self::assertStringContainsString('could not be evaluated', $e->getMessage());
            self::assertStringContainsString($message, $e->getMessage());
        }
    }

    public function testSizeCaps(): void
    {
        $big   = str_repeat('a', BuiltinExpressionFunctions::MAX_STRING_BYTES + 1);
        $half  = str_repeat('a', BuiltinExpressionFunctions::MAX_STRING_BYTES / 2 + 1);
        $many  = array_map(static fn (int $i): int => $i, range(0, BuiltinExpressionFunctions::MAX_ITEMS));
        $cases = [
            ['upper(big) == 1', ['big' => $big], 'accepts strings up to 65536 bytes'],
            ["replace(half, 'a', 'aa') == 1", ['half' => $half], 'would return more than 65536 bytes'],
            ['count(many) == 1', ['many' => $many], 'accepts up to 10000 elements'],
            ['number_format(1.0e300, 0, \'.\', sep) == 1', ['sep' => str_repeat('-', 1000)], 'number_format() would return more than 65536 bytes'],
            ['join(items, \'\') == 1', ['items' => array_fill(0, 10, str_repeat('x', 7000))], 'join() would return more than 65536 bytes'],
            ["replace(s, {'a': big}) == 1", ['s' => str_repeat('a', 1000), 'big' => str_repeat('b', 100)], 'replace() would return more than 65536 bytes'],
            // REQ-SEC-004 BQ replacecpu: 9,999 search strings over a 49 KB subject.
            ["replace(join(1..9999, 'x'), keys(1..9999)) == ''", [], 'replace() accepts at most 1000000 search strings × subject bytes'],
        ];

        foreach ($cases as [$expression, $context, $message]) {
            try {
                $this->evaluator()->evaluate($expression, $context);
                self::fail('ConditionalExpressionException expected for ' . $expression);
            } catch (ConditionalExpressionException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testEveryBuiltinIsListedAndNotCompilable(): void
    {
        $functions = (new BuiltinExpressionFunctions())->getFunctions();
        $names     = array_map(static fn (ExpressionFunction $function): string => $function->getName(), $functions);
        $expected  = BuiltinExpressionFunctions::NAMES;
        sort($names);
        sort($expected);
        self::assertSame($expected, $names);

        $this->expectException(LogicException::class);
        ($functions[0]->getCompiler())('x');
    }

    public function testDefaultClockAndTimezone(): void
    {
        $evaluator = new ConditionalExpressionEvaluator(functionProviders: [new BuiltinExpressionFunctions()]);

        self::assertTrue($evaluator->evaluate("now() == date('today')", []));
    }

    public function testAllowAndDenyLists(): void
    {
        $providers = [new BuiltinExpressionFunctions(new MockClock())];

        $onlyUpper = new ConditionalExpressionEvaluator(functionProviders: $providers, allowedFunctions: ['upper']);
        self::assertTrue($onlyUpper->evaluate("upper('a') == 'A'", []));

        $noDates = new ConditionalExpressionEvaluator(functionProviders: $providers, deniedFunctions: ['now', 'date']);
        self::assertTrue($noDates->evaluate("lower('A') == 'a'", []));

        foreach ([[$onlyUpper, "lower('A') == 'a'"], [$noDates, "now() != ''"]] as [$evaluator, $expression]) {
            try {
                $evaluator->evaluate($expression, []);
                self::fail('disabled function must not parse');
            } catch (ConditionalExpressionException $e) {
                self::assertStringContainsString('does not exist', $e->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown expression function "uper"');
        new ConditionalExpressionEvaluator(functionProviders: $providers, allowedFunctions: ['uper']);
    }

    public function testHostProviderAddsAndOverridesFunctions(): void
    {
        $host = new class implements ExpressionFunctionProviderInterface {
            public function getFunctions(): array
            {
                return [
                    new ExpressionFunction('vat_rate', BuiltinExpressionFunctions::notCompilable(...), static function (array $variables, mixed $country): float {
                        if ($country !== 'ES' && $country !== 'PT') {
                            throw new ExpressionFunctionError('vat_rate() only knows ES and PT.');
                        }

                        return $country === 'ES' ? 0.21 : 0.23;
                    }),
                    new ExpressionFunction('upper', BuiltinExpressionFunctions::notCompilable(...), static fn (array $variables, mixed $s): string => 'overridden'),
                ];
            }
        };

        $evaluator = new ConditionalExpressionEvaluator(functionProviders: [new BuiltinExpressionFunctions(), $host]);

        self::assertTrue($evaluator->evaluate("vat_rate(country) > 0.2 and upper('x') == 'overridden'", ['country' => 'ES']));

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage('vat_rate() only knows ES and PT.');
        $evaluator->evaluate('vat_rate(country) > 0', ['country' => 'FR']);
    }

    private function evaluator(): ConditionalExpressionEvaluator
    {
        $clock = new MockClock('2026-10-30 12:00:00', 'Europe/Madrid');

        return new ConditionalExpressionEvaluator(functionProviders: [new BuiltinExpressionFunctions($clock, 'Europe/Madrid')]);
    }
}
