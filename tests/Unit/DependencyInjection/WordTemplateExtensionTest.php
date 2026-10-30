<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\DependencyInjection;

use Nowo\WordTemplateBundle\DependencyInjection\Configuration;
use Nowo\WordTemplateBundle\DependencyInjection\WordTemplateExtension;
use Nowo\WordTemplateBundle\Processor\ConditionalExpressionInspectorInterface;
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

    public function testLoadSetsConditionalExpressionParametersAndInspectorAlias(): void
    {
        $container = new ContainerBuilder();
        (new WordTemplateExtension())->load([
            ['conditional_expressions' => ['enabled' => true, 'on_error' => 'keep', 'max_length' => 80]],
        ], $container);

        self::assertTrue($container->getParameter(Configuration::ALIAS . '.conditional_expressions.enabled'));
        self::assertSame('keep', $container->getParameter(Configuration::ALIAS . '.conditional_expressions.on_error'));
        self::assertSame(80, $container->getParameter(Configuration::ALIAS . '.conditional_expressions.max_length'));
        self::assertSame(WordTemplateProcessor::class, (string) $container->getAlias(ConditionalExpressionInspectorInterface::class));
        self::assertSame('%nowo_word_template.conditional_expressions.enabled%', $container->getDefinition(WordTemplateProcessor::class)->getArgument('$conditionalExpressionsEnabled'));
    }

    public function testLoadSetsLoopParameters(): void
    {
        $container = new ContainerBuilder();
        (new WordTemplateExtension())->load([
            ['loops' => ['enabled' => true, 'max_iterations' => 7, 'max_total_iterations' => 70], 'loop_endfor_opening' => '${#done'],
        ], $container);

        self::assertTrue($container->getParameter(Configuration::ALIAS . '.loops.enabled'));
        self::assertSame(7, $container->getParameter(Configuration::ALIAS . '.loops.max_iterations'));
        self::assertSame(70, $container->getParameter(Configuration::ALIAS . '.loops.max_total_iterations'));
        self::assertSame('${#for', $container->getParameter(Configuration::ALIAS . '.loop_for_opening'));
        self::assertSame('}', $container->getParameter(Configuration::ALIAS . '.loop_for_closing'));
        self::assertSame('${#done', $container->getParameter(Configuration::ALIAS . '.loop_endfor_opening'));
        self::assertSame('}', $container->getParameter(Configuration::ALIAS . '.loop_endfor_closing'));
        self::assertSame('%nowo_word_template.loops.enabled%', $container->getDefinition(WordTemplateProcessor::class)->getArgument('$loopsEnabled'));
    }

    public function testGetAlias(): void
    {
        self::assertSame(Configuration::ALIAS, (new WordTemplateExtension())->getAlias());
    }
}
