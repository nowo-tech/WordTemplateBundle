<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals;

/**
 * Backed enum used by the "Conditionals by data type" examples of docs/USAGE.md.
 */
enum InvoiceStatus: string
{
    case Draft   = 'draft';
    case Paid    = 'paid';
    case Overdue = 'overdue';
}
