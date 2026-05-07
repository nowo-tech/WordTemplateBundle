<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\DependencyInjection;

use Nowo\WordTemplateBundle\DependencyInjection\Configuration;
use Nowo\WordTemplateBundle\DependencyInjection\WordTemplateExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class WordTemplateExtensionTest extends TestCase
{
    public function testLoadSetsMacroParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new WordTemplateExtension();
        $extension->load([
            [
                'macro_opening' => '[[',
                'macro_closing' => ']]',
            ],
        ], $container);

        self::assertSame('[[', $container->getParameter(Configuration::ALIAS . '.macro_opening'));
        self::assertSame(']]', $container->getParameter(Configuration::ALIAS . '.macro_closing'));
    }
}
