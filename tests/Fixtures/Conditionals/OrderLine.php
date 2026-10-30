<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals;

/**
 * Stand-in for a Doctrine entity on the many side of a OneToMany relation.
 */
final readonly class OrderLine
{
    public function __construct(
        private bool $hazardous = false,
    ) {
    }

    public function isHazardous(): bool
    {
        return $this->hazardous;
    }
}
