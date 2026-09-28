<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Util;

use Nowo\WordTemplateBundle\Exception\PathNotAllowedException;
use Nowo\WordTemplateBundle\Util\PathAllowlist;
use PHPUnit\Framework\TestCase;

final class PathAllowlistTest extends TestCase
{
    public function testEmptyRootsSkipsCheck(): void
    {
        PathAllowlist::assertUnderRoots('/etc/passwd', []);
        $this->addToAssertionCount(1);
    }

    public function testPathUnderRootAccepted(): void
    {
        $root = sys_get_temp_dir();
        $file = tempnam($root, 'wtp_');
        self::assertNotFalse($file);
        try {
            PathAllowlist::assertUnderRoots($file, [$root]);
            $this->addToAssertionCount(1);
        } finally {
            @unlink($file);
        }
    }

    public function testPathOutsideRootRejected(): void
    {
        $root = sys_get_temp_dir() . '/nowo_wtp_root_' . bin2hex(random_bytes(4));
        mkdir($root);
        $outside = tempnam(sys_get_temp_dir(), 'wtp_out_');
        self::assertNotFalse($outside);

        try {
            $this->expectException(PathNotAllowedException::class);
            PathAllowlist::assertUnderRoots($outside, [$root]);
        } finally {
            @unlink($outside);
            @rmdir($root);
        }
    }
}
