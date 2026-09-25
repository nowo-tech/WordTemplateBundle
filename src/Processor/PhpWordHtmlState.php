<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Processor;

use PhpOffice\PhpWord\Shared\Html;
use ReflectionProperty;

use function property_exists;

/**
 * Scopes PHPWord's process-global {@see Html} parser state to one merge.
 *
 * {@see Html::addHtml()} keeps the last parsed {@code <style>} sheet ({@code $css}), the last DOM
 * ({@code $xpath}) and options in static properties; in a long-lived worker they would style (and
 * stay in memory for) every later document.
 *
 * @internal
 */
final class PhpWordHtmlState
{
    private const PROPERTIES = ['css', 'xpath', 'options'];

    /**
     * Clears the state and returns the previous values for {@see restore()}.
     *
     * Properties missing in the installed PHPWord version are skipped.
     *
     * @param list<string> $names
     *
     * @return array<string, mixed>
     */
    public static function isolate(array $names = self::PROPERTIES): array
    {
        $previous = [];
        foreach ($names as $name) {
            $property = self::property($name);
            if (!$property instanceof ReflectionProperty) {
                continue;
            }
            $previous[$name] = $property->getValue();
            $property->setValue(null, null);
        }

        return $previous;
    }

    /**
     * @param array<string, mixed> $previous
     */
    public static function restore(array $previous): void
    {
        foreach ($previous as $name => $value) {
            self::property($name)?->setValue(null, $value);
        }
    }

    private static function property(string $name): ?ReflectionProperty
    {
        if (!property_exists(Html::class, $name)) {
            return null;
        }

        return new ReflectionProperty(Html::class, $name);
    }
}
