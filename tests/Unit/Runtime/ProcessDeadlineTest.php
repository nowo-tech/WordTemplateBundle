<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Runtime;

use Nowo\WordTemplateBundle\Exception\ProcessingTimedOutException;
use Nowo\WordTemplateBundle\Runtime\ProcessDeadline;
use PHPUnit\Framework\TestCase;

final class ProcessDeadlineTest extends TestCase
{
    public function testAssertNotTimedOutPassesWhileWithinBudget(): void
    {
        $deadline = new ProcessDeadline(5);

        $deadline->assertNotTimedOut();

        $this->addToAssertionCount(1);
    }

    public function testAssertNotTimedOutThrowsWhenExpired(): void
    {
        $deadline = new ProcessDeadline(1, hrtime(true) - 2_000_000_000.0);

        $this->expectException(ProcessingTimedOutException::class);
        $this->expectExceptionMessage('Word template processing timed out after 1 seconds.');

        $deadline->assertNotTimedOut();
    }
}
