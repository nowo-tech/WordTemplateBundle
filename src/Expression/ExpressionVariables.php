<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use BackedEnum;
use DateTimeInterface;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Stringable;
use UnitEnum;

use function array_is_list;
use function is_array;
use function is_int;
use function is_scalar;
use function preg_match;

/**
 * Builds the variables an expression conditional can read from a {@code process()} context.
 *
 * Exposure rules (documented in docs/USAGE.md "Expression conditionals"):
 * - top-level keys that are identifiers ({@code [A-Za-z_][A-Za-z0-9_]*}) become variables;
 * - scalars and null as is; {@see Stringable} as its string; backed enums as their value, pure enums as their name;
 *   {@see DateTimeInterface} as a {@code 'Y-m-d'} string (calendar day in the object's own time zone);
 * - lists stay PHP arrays ({@code in}, {@code lines[0]}); associative arrays become {@see ExpressionMap}
 *   ({@code client.country} or {@code client['country']});
 * - {@see ConditionalBlock}, HtmlContent, ImageSource, TableRows and any other object are not exposed;
 * - each {@see ConditionalBlock} whose name is an identifier is also exposed as a bool under its block name,
 *   unless a context key already uses that name.
 */
final class ExpressionVariables
{
    private const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * @param array<array-key, mixed> $context the context passed to {@code process()}
     *
     * @return array<string, mixed>
     */
    public static function fromContext(array $context): array
    {
        $variables = [];
        $blocks    = [];

        foreach ($context as $key => $value) {
            self::collectBlocks($value, $blocks);

            if (is_int($key) || preg_match(self::IDENTIFIER, $key) !== 1) {
                continue;
            }

            [$exposable, $exposed] = self::expose($value, $key);
            if ($exposable) {
                $variables[$key] = $exposed;
            }
        }

        foreach ($blocks as $name => $visible) {
            if (!isset($variables[$name]) && preg_match(self::IDENTIFIER, $name) === 1) {
                $variables[$name] = $visible;
            }
        }

        return $variables;
    }

    /**
     * @return array{bool, mixed} [exposable, exposed value]
     */
    private static function expose(mixed $value, string $path): array
    {
        if ($value === null || is_scalar($value)) {
            return [true, $value];
        }

        if (is_array($value)) {
            return [true, self::exposeArray($value, $path)];
        }

        return self::exposeObject($value);
    }

    /**
     * @return array{bool, mixed}
     */
    private static function exposeObject(mixed $value): array
    {
        if ($value instanceof BackedEnum) {
            return [true, $value->value];
        }

        if ($value instanceof UnitEnum) {
            return [true, $value->name];
        }

        if ($value instanceof DateTimeInterface) {
            return [true, $value->format('Y-m-d')];
        }

        if ($value instanceof Stringable) {
            return [true, (string) $value];
        }

        return [false, null];
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return ExpressionMap|list<mixed>
     */
    private static function exposeArray(array $value, string $path): array|ExpressionMap
    {
        $isList = array_is_list($value);
        $list   = [];
        $map    = [];

        foreach ($value as $key => $item) {
            [$exposable, $exposed] = self::expose($item, $path . (is_int($key) ? '[' . $key . ']' : '.' . $key));
            if (!$exposable) {
                continue;
            }

            $list[]    = $exposed;
            $map[$key] = $exposed;
        }

        return $isList ? $list : new ExpressionMap($map, $path);
    }

    /**
     * @param array<string, bool> $blocks
     */
    private static function collectBlocks(mixed $value, array &$blocks): void
    {
        if ($value instanceof ConditionalBlock) {
            $blocks[$value->blockName] = $value->visible;

            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                self::collectBlocks($item, $blocks);
            }
        }
    }
}
