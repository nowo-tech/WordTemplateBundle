<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Util;

use Nowo\WordTemplateBundle\Exception\InvalidContextValueException;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\ImageSource;
use Nowo\WordTemplateBundle\Model\TableRows;
use Stringable;

use function is_array;
use function is_int;
use function is_object;
use function sprintf;

/**
 * Flattens nested scalar arrays into dot keys; preserves {@see HtmlContent}, {@see TableRows}, {@see ImageSource}.
 *
 * @phpstan-return array<string, scalar|\Stringable|HtmlContent|TableRows|ImageSource|null>
 */
final class ContextFlattener
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, HtmlContent|ImageSource|scalar|Stringable|TableRows|null>
     */
    public static function flatten(array $context, string $prefix = ''): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $segment = self::stringKey($key);
            $fullKey = $prefix === '' ? $segment : $prefix . '.' . $segment;

            if ($value instanceof HtmlContent || $value instanceof TableRows || $value instanceof ImageSource) {
                $out[$fullKey] = $value;

                continue;
            }

            if (is_object($value)) {
                if ($value instanceof Stringable) {
                    $out[$fullKey] = $value;

                    continue;
                }

                throw new InvalidContextValueException(sprintf('Unsupported context value at key "%s": use scalars, Stringable, or HtmlContent / TableRows / ImageSource.', $fullKey));
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

    /**
     * @param array-key $key
     */
    private static function stringKey(string|int $key): string
    {
        return is_int($key) ? (string) $key : $key;
    }
}
