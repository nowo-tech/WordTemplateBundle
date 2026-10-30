<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Stand-in for a Doctrine entity with a OneToMany collection.
 */
final readonly class Order
{
    /** @var Collection<int, OrderLine> */
    private Collection $lines;

    /**
     * @param list<OrderLine> $lines
     */
    public function __construct(array $lines = [])
    {
        $this->lines = new ArrayCollection($lines);
    }

    /**
     * @return Collection<int, OrderLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }
}
