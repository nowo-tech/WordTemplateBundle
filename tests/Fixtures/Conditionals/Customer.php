<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals;

/**
 * Customer with a free-text plan, used by the "one of N" string example of docs/USAGE.md.
 */
final readonly class Customer
{
    public function __construct(
        public string $plan,
    ) {
    }
}
