<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit;

use Nowo\WordTemplateBundle\DependencyInjection\WordTemplateExtension;
use Nowo\WordTemplateBundle\WordTemplateBundle;
use PHPUnit\Framework\TestCase;

final class WordTemplateBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsStableInstance(): void
    {
        $bundle = new WordTemplateBundle();
        $a      = $bundle->getContainerExtension();
        $b      = $bundle->getContainerExtension();

        self::assertSame($a, $b);
        self::assertInstanceOf(WordTemplateExtension::class, $a);
    }
}
