<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Symfony ExpressionLanguage without its default functions: {@code constant()}, {@code enum()}, {@code min()} and
 * {@code max()} are not registered, so a template can never read PHP constants or enum cases. The only callables
 * are the functions added by {@see ConditionalExpressionEvaluator} (built-ins and host providers).
 *
 * @internal used by {@see ConditionalExpressionEvaluator}
 */
final class SandboxedExpressionLanguage extends ExpressionLanguage
{
    public function __construct(?CacheItemPoolInterface $cache = null)
    {
        parent::__construct($cache);
    }

    /**
     * Registers nothing (the parent registers constant(), enum(), min() and max()).
     */
    protected function registerFunctions(): void
    {
    }
}
