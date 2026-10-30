<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Backed enum driving the "one of N" conditional blocks of the conditionals demo.
 */
enum InvoiceStatus: string
{
    case Draft   = 'draft';
    case Paid    = 'paid';
    case Overdue = 'overdue';
}
