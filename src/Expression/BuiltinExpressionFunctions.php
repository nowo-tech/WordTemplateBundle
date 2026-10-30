<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

use function abs;
use function array_is_list;
use function array_key_exists;
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use function ceil;
use function count;
use function date_default_timezone_get;
use function date_parse;
use function explode;
use function floor;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function max;
use function mb_convert_case;
use function mb_strlen;
use function mb_strtolower;
use function mb_strtoupper;
use function mb_substr;
use function min;
use function number_format;
use function round;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strtr;
use function trim;

use const MB_CASE_TITLE;

/**
 * The functions available in expression conditionals by default — pure, no I/O, bounded input and output.
 * Names follow Twig filters / functions / tests where one exists (a Twig filter {@code x|upper} is written
 * {@code upper(x)}). Every function validates its arguments and throws {@see ExpressionFunctionError}.
 * {@code contains} and {@code in} are operators of the language ({@code name contains 'x'}, {@code 'fire' in covers}),
 * so they are not functions.
 *
 * Dates are {@code 'Y-m-d'} strings (as context dates are exposed); {@code now()} / {@code date()} read the
 * {@see ClockInterface} in the configured time zone, so they are testable and worker-safe.
 */
final readonly class BuiltinExpressionFunctions implements ExpressionFunctionProviderInterface
{
    /** Maximum string length (bytes) a function accepts or returns. */
    public const MAX_STRING_BYTES = 65_536;

    /** Maximum number of elements a function accepts or returns. */
    public const MAX_ITEMS = 10_000;

    /** Names of all built-in functions. */
    public const NAMES = [
        'lower', 'upper', 'capitalize', 'title', 'trim', 'length', 'replace', 'slice', 'starts_with', 'ends_with',
        'abs', 'round', 'floor', 'ceil', 'min', 'max', 'number_format',
        'count', 'keys', 'first', 'last', 'join', 'empty', 'default', 'is_null', 'is_defined',
        'now', 'date', 'date_diff_days',
    ];

    private ClockInterface $clock;

    /**
     * @param ClockInterface|null $clock source of "now" (Symfony {@code clock} service); null uses the system clock
     * @param string|null $timezone time zone of {@code now()} / {@code date()}; null uses PHP's default time zone
     */
    public function __construct(
        ?ClockInterface $clock = null,
        private ?string $timezone = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /**
     * @return list<ExpressionFunction>
     */
    public function getFunctions(): array
    {
        $functions = [
            // strings
            'lower'      => static fn (mixed $s): string => mb_strtolower(self::string($s, 'lower')),
            'upper'      => static fn (mixed $s): string => mb_strtoupper(self::string($s, 'upper')),
            'capitalize' => static function (mixed $s): string {
                $s = self::string($s, 'capitalize');

                return mb_strtoupper(mb_substr($s, 0, 1)) . mb_strtolower(mb_substr($s, 1));
            },
            'title'       => static fn (mixed $s): string => mb_convert_case(self::string($s, 'title'), MB_CASE_TITLE),
            'trim'        => static fn (mixed $s, mixed $characters = null): string => $characters === null ? trim(self::string($s, 'trim')) : trim(self::string($s, 'trim'), self::string($characters, 'trim')),
            'length'      => static fn (mixed $value): int => self::length($value, 'length'),
            'replace'     => $this->replace(...),
            'slice'       => $this->slice(...),
            'starts_with' => static fn (mixed $s, mixed $prefix): bool => str_starts_with(self::string($s, 'starts_with'), self::string($prefix, 'starts_with')),
            'ends_with'   => static fn (mixed $s, mixed $suffix): bool => str_ends_with(self::string($s, 'ends_with'), self::string($suffix, 'ends_with')),
            // numbers
            'abs'           => static fn (mixed $n): int|float => abs(self::number($n, 'abs')),
            'round'         => static fn (mixed $n, mixed $precision = 0): float => round(self::number($n, 'round'), self::int($precision, 'round')),
            'floor'         => static fn (mixed $n): float => floor(self::number($n, 'floor')),
            'ceil'          => static fn (mixed $n): float => ceil(self::number($n, 'ceil')),
            'min'           => static fn (mixed ...$values): int|float => min(self::numbers($values, 'min')),
            'max'           => static fn (mixed ...$values): int|float => max(self::numbers($values, 'max')),
            'number_format' => static fn (mixed $n, mixed $decimals = 0, mixed $decimalPoint = '.', mixed $thousands = ','): string => self::output(number_format(
                self::number($n, 'number_format'),
                self::int($decimals, 'number_format'),
                self::string($decimalPoint, 'number_format'),
                self::string($thousands, 'number_format'),
            ), 'number_format'),
            // arrays
            'count' => static fn (mixed $value): int => count(self::items($value, 'count')),
            'keys'  => static fn (mixed $value): array => array_keys(self::items($value, 'keys')),
            'first' => static fn (mixed $value): mixed => self::edge($value, 'first', true),
            'last'  => static fn (mixed $value): mixed => self::edge($value, 'last', false),
            'join'  => static fn (mixed $value, mixed $glue = ''): string => self::output(implode(self::string($glue, 'join'), array_map(
                static fn (mixed $item): string => self::string($item, 'join'),
                self::items($value, 'join'),
            )), 'join'),
            'empty'   => self::isEmpty(...),
            'default' => static fn (mixed $value, mixed $fallback = ''): mixed => self::isEmpty($value) ? $fallback : $value,
            'is_null' => static fn (mixed $value): bool => $value === null,
            // dates
            'now'            => fn (mixed $format = 'Y-m-d'): string => $this->now()->format(self::string($format, 'now')),
            'date'           => fn (mixed $value, mixed $format = 'Y-m-d'): string => $this->date($value, 'date')->format(self::string($format, 'date')),
            'date_diff_days' => fn (mixed $from, mixed $to): int => (int) $this->date($from, 'date_diff_days')->setTime(0, 0)->diff($this->date($to, 'date_diff_days')->setTime(0, 0))->format('%r%a'),
        ];

        $out = [];
        foreach ($functions as $name => $implementation) {
            $out[] = new ExpressionFunction($name, self::notCompilable(...), static fn (array $variables, mixed ...$arguments): mixed => $implementation(...$arguments));
        }

        $out[] = new ExpressionFunction('is_defined', self::notCompilable(...), static fn (array $variables, mixed $path): bool => self::isDefined($variables, self::string($path, 'is_defined')));

        return $out;
    }

    /**
     * Compiler of every built-in: template expressions are evaluated, never compiled to PHP.
     */
    public static function notCompilable(): never
    {
        throw new LogicException('Template expression functions cannot be compiled.');
    }

    private function replace(mixed $subject, mixed $search, mixed $replacement = null): string
    {
        $subject = self::string($subject, 'replace');
        if ($search instanceof ExpressionMap || is_array($search)) {
            $pairs = [];
            foreach (self::items($search, 'replace') as $from => $to) {
                $pairs[(string) $from] = self::string($to, 'replace');
            }

            return self::output(strtr($subject, $pairs), 'replace');
        }

        return self::output(strtr($subject, [self::string($search, 'replace') => self::string($replacement, 'replace')]), 'replace');
    }

    /**
     * @return array<array-key, mixed>|list<mixed>|string
     */
    private function slice(mixed $value, mixed $start, mixed $length = null): string|array
    {
        $start  = self::int($start, 'slice');
        $length = $length === null ? null : self::int($length, 'slice');

        if (is_array($value) || $value instanceof ExpressionMap) {
            $items = self::items($value, 'slice');

            return array_slice($items, $start, $length, !array_is_list($items));
        }

        return mb_substr(self::string($value, 'slice'), $start, $length);
    }

    private static function length(mixed $value, string $function): int
    {
        if (is_array($value) || $value instanceof ExpressionMap) {
            return count(self::items($value, $function));
        }

        return mb_strlen(self::string($value, $function));
    }

    private static function edge(mixed $value, string $function, bool $first): mixed
    {
        if (is_array($value) || $value instanceof ExpressionMap) {
            $items = self::items($value, $function);
            $key   = $first ? array_key_first($items) : array_key_last($items);

            return $key === null ? null : $items[$key];
        }

        $string = self::string($value, $function);

        return $string === '' ? '' : mb_substr($string, $first ? 0 : -1, 1);
    }

    /**
     * Twig's "empty" test: null, '', false, an empty list or an empty map ({@code 0} is not empty).
     */
    private static function isEmpty(mixed $value): bool
    {
        if ($value instanceof ExpressionMap) {
            return $value->all() === [];
        }

        return in_array($value, [null, '', false, []], true);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private static function isDefined(array $variables, string $path): bool
    {
        $current = $variables;
        foreach (explode('.', $path) as $segment) {
            if ($current instanceof ExpressionMap) {
                $current = $current->all();
            }

            if (!is_array($current) || !isset($current[$segment]) && !array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }

    private function now(): DateTimeImmutable
    {
        // @igor-ignore - Reads the clock; setTimezone() acts on the returned immutable value, not on the clock.
        return $this->clock->now()->setTimezone(new DateTimeZone($this->timezone ?? date_default_timezone_get()));
    }

    /**
     * Accepts a timestamp, a date string ({@code '2026-10-30'}) or a relative format ({@code '+30 days'}, relative to now).
     */
    private function date(mixed $value, string $function): DateTimeImmutable
    {
        if (is_int($value)) {
            return $this->now()->setTimestamp($value);
        }

        $string = self::string($value, $function);
        $parsed = date_parse($string);
        if ($string === '' || $parsed['error_count'] > 0) {
            throw new ExpressionFunctionError(sprintf('%s() cannot read "%s" as a date.', $function, $string));
        }

        return $this->now()->modify($string);
    }

    private static function string(mixed $value, string $function): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (!is_string($value)) {
            throw new ExpressionFunctionError(sprintf('%s() expects a string, got %s.', $function, get_debug_type($value)));
        }

        if (strlen($value) > self::MAX_STRING_BYTES) {
            throw new ExpressionFunctionError(sprintf('%s() accepts strings up to %d bytes.', $function, self::MAX_STRING_BYTES));
        }

        return $value;
    }

    private static function output(string $value, string $function): string
    {
        if (strlen($value) > self::MAX_STRING_BYTES) {
            throw new ExpressionFunctionError(sprintf('%s() would return more than %d bytes.', $function, self::MAX_STRING_BYTES));
        }

        return $value;
    }

    private static function number(mixed $value, string $function): int|float
    {
        if (!is_int($value) && !is_float($value)) {
            throw new ExpressionFunctionError(sprintf('%s() expects a number, got %s.', $function, get_debug_type($value)));
        }

        return $value;
    }

    private static function int(mixed $value, string $function): int
    {
        if (!is_int($value)) {
            throw new ExpressionFunctionError(sprintf('%s() expects an integer, got %s.', $function, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values one list argument or several numbers
     *
     * @return non-empty-list<float|int>
     */
    private static function numbers(array $values, string $function): array
    {
        $values = array_values($values);
        if (count($values) === 1 && (is_array($values[0]) || $values[0] instanceof ExpressionMap)) {
            $values = array_values(self::items($values[0], $function));
        }

        if ($values === []) {
            throw new ExpressionFunctionError(sprintf('%s() expects at least one number.', $function));
        }

        return array_map(static fn (mixed $value): int|float => self::number($value, $function), $values);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function items(mixed $value, string $function): array
    {
        if ($value instanceof ExpressionMap) {
            $value = $value->all();
        }

        if (!is_array($value)) {
            throw new ExpressionFunctionError(sprintf('%s() expects a list or a map, got %s.', $function, get_debug_type($value)));
        }

        if (count($value) > self::MAX_ITEMS) {
            throw new ExpressionFunctionError(sprintf('%s() accepts up to %d elements.', $function, self::MAX_ITEMS));
        }

        return $value;
    }
}
