<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Fixtures\Conditionals;

/**
 * Pure (non-backed) enum: exposed to expressions by case name.
 */
enum Priority
{
    case Low;
    case High;
}
