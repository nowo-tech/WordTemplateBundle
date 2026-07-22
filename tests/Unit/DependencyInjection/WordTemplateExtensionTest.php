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

        self::assertSame(180, $container->getParameter(Configuration::ALIAS . '.timeout'));
        self::assertSame('[[', $container->getParameter(Configuration::ALIAS . '.macro_opening'));
        self::assertSame(']]', $container->getParameter(Configuration::ALIAS . '.macro_closing'));
        self::assertSame('${#if', $container->getParameter(Configuration::ALIAS . '.conditional_if_opening'));
        self::assertSame('}', $container->getParameter(Configuration::ALIAS . '.conditional_if_closing'));
        self::assertSame('${#endif', $container->getParameter(Configuration::ALIAS . '.conditional_endif_opening'));
        self::assertSame('}', $container->getParameter(Configuration::ALIAS . '.conditional_endif_closing'));
    }

    public function testLoadSetsTimeoutParameter(): void
    {
        $container = new ContainerBuilder();
        $extension = new WordTemplateExtension();
        $extension->load([
            ['timeout' => 90],
        ], $container);

        self::assertSame(90, $container->getParameter(Configuration::ALIAS . '.timeout'));
    }

    public function testLoadSetsConditionalParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new WordTemplateExtension();
        $extension->load([
            [
                'conditional_if_opening'    => '[[#if',
                'conditional_if_closing'    => ']]',
                'conditional_endif_opening' => '[[#endif',
                'conditional_endif_closing' => ']]',
            ],
        ], $container);

        self::assertSame('[[#if', $container->getParameter(Configuration::ALIAS . '.conditional_if_opening'));
        self::assertSame(']]', $container->getParameter(Configuration::ALIAS . '.conditional_if_closing'));
        self::assertSame('[[#endif', $container->getParameter(Configuration::ALIAS . '.conditional_endif_opening'));
        self::assertSame(']]', $container->getParameter(Configuration::ALIAS . '.conditional_endif_closing'));
    }

    public function testGetAlias(): void
    {
        self::assertSame(Configuration::ALIAS, (new WordTemplateExtension())->getAlias());
    }
}
