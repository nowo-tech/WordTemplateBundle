<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Util;

use BackedEnum;
use DateTimeInterface;
use Nowo\WordTemplateBundle\Exception\InvalidContextValueException;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\ImageSource;
use Nowo\WordTemplateBundle\Model\TableRows;
use Stringable;
use UnitEnum;

use function is_array;
use function is_int;
use function is_object;
use function sprintf;

/**
 * Flattens nested scalar arrays into dot keys; preserves {@see ConditionalBlock}, {@see HtmlContent},
 * {@see TableRows}, {@see ImageSource}. Backed enums become their value, pure enums their name and
 * {@see DateTimeInterface} a {@code 'Y-m-d'} string (the same values expression conditionals compare).
 *
 * @phpstan-return array<string, scalar|Stringable|ConditionalBlock|HtmlContent|TableRows|ImageSource|null>
 */
final class ContextFlattener
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, ConditionalBlock|HtmlContent|ImageSource|scalar|Stringable|TableRows|null>
     */
    public static function flatten(array $context, string $prefix = ''): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $segment = self::stringKey($key);
            $fullKey = $prefix === '' ? $segment : $prefix . '.' . $segment;

            if ($value instanceof ConditionalBlock || $value instanceof HtmlContent || $value instanceof TableRows || $value instanceof ImageSource) {
                $out[$fullKey] = $value;

                continue;
            }

            if ($value instanceof BackedEnum) {
                $out[$fullKey] = $value->value;

                continue;
            }

            if ($value instanceof UnitEnum) {
                $out[$fullKey] = $value->name;

                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $out[$fullKey] = $value->format('Y-m-d');

                continue;
            }

            if (is_object($value)) {
                if ($value instanceof Stringable) {
                    $out[$fullKey] = $value;

                    continue;
                }

                throw new InvalidContextValueException(sprintf('Unsupported context value at key "%s": use scalars, Stringable, enums, DateTimeInterface, or ConditionalBlock / HtmlContent / TableRows / ImageSource.', $fullKey));
            }

            if (is_array($value)) {
                foreach (self::flatten($value, $fullKey) as $nestedKey => $nestedValue) {
                    $out[$nestedKey] = $nestedValue;
                }

                continue;
            }

            $out[$fullKey] = $value;
        }

        return $out;
    }

    private static function stringKey(string|int $key): string
    {
        return is_int($key) ? (string) $key : $key;
    }
}
