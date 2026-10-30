<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals;

use DateTimeImmutable;

/**
 * Plain invoice model used by the docs/USAGE.md conditional examples.
 */
final readonly class Invoice
{
    public function __construct(
        public string $number,
        public InvoiceStatus $status,
        public DateTimeImmutable $dueDate,
    ) {
    }
}
