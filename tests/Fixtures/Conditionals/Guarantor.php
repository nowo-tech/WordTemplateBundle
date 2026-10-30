<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals;

/**
 * Stand-in for an optional Doctrine relation target.
 */
final readonly class Guarantor
{
    public function __construct(
        private bool $company,
    ) {
    }

    public function isCompany(): bool
    {
        return $this->company;
    }
}
