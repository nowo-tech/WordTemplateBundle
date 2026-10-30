<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals;

/**
 * Stand-in for a Doctrine entity with an optional ManyToOne relation.
 */
final readonly class Contract
{
    public function __construct(
        private ?Guarantor $guarantor,
    ) {
    }

    public function getGuarantor(): ?Guarantor
    {
        return $this->guarantor;
    }
}
