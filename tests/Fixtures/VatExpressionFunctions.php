<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Fixtures;

use Nowo\WordTemplateBundle\Expression\BuiltinExpressionFunctions;
use Nowo\WordTemplateBundle\Expression\ExpressionFunctionError;
use Nowo\WordTemplateBundle\Expression\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

/**
 * Host function provider used by the kernel tests (autoconfigured through {@see ExpressionFunctionProviderInterface}).
 */
final class VatExpressionFunctions implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('vat_rate', BuiltinExpressionFunctions::notCompilable(...), static fn (array $variables, mixed $country): float => match ($country) {
                'ES'    => 0.21,
                'PT'    => 0.23,
                default => throw new ExpressionFunctionError('vat_rate() only knows ES and PT.'),
            }),
        ];
    }
}
