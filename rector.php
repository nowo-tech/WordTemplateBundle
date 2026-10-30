<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\If_\ExplicitNullableObjectInstanceRector;
use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    // The closures of this test are verbatim copies of the expressions documented in docs/USAGE.md
    // ("Conditionals by data type"): rewriting them would make the docs and the tests drift apart.
    ->withSkip([
        ExplicitNullableObjectInstanceRector::class                  => [__DIR__ . '/tests/Integration/ConditionalsUsageDocTest.php'],
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class => [__DIR__ . '/tests/Integration/ConditionalsUsageDocTest.php'],
    ]);
