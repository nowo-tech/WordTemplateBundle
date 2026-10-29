<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\DependencyInjection;

use Nowo\WordTemplateBundle\DependencyInjection\Configuration;
use Nowo\WordTemplateBundle\DependencyInjection\WordTemplateExtension;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

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
        self::assertSame([], $container->getParameter(Configuration::ALIAS . '.allowed_roots'));
        self::assertFalse($container->getParameter(Configuration::ALIAS . '.path_allowlist_required'));
        self::assertSame(5_242_880, $container->getParameter(Configuration::ALIAS . '.html_image_max_bytes'));
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

    public function testProcessorGetsOptionalLoggerOnWordTemplateChannel(): void
    {
        $container = new ContainerBuilder();
        (new WordTemplateExtension())->load([], $container);

        $definition = $container->getDefinition(WordTemplateProcessor::class);
        $logger     = $definition->getArgument('$logger');

        self::assertInstanceOf(Reference::class, $logger);
        self::assertSame('logger', (string) $logger);
        self::assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $logger->getInvalidBehavior());
        self::assertSame([['channel' => 'word_template']], $definition->getTag('monolog.logger'));
    }

    public function testGetAlias(): void
    {
        self::assertSame(Configuration::ALIAS, (new WordTemplateExtension())->getAlias());
    }
}
