<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\DependencyInjection;

use Nowo\WordTemplateBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultMacroDelimiters(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), []);

        self::assertSame(180, $config['timeout']);
        self::assertSame('${', $config['macro_opening']);
        self::assertSame('}', $config['macro_closing']);
        self::assertSame('${#if', $config['conditional_if_opening']);
        self::assertSame('}', $config['conditional_if_closing']);
        self::assertSame('${#endif', $config['conditional_endif_opening']);
        self::assertSame('}', $config['conditional_endif_closing']);
    }

    public function testCustomTimeout(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [
            ['timeout' => 60],
        ]);

        self::assertSame(60, $config['timeout']);
    }

    public function testCustomMacroDelimiters(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [
            ['macro_opening' => '[[', 'macro_closing' => ']]'],
        ]);

        self::assertSame('[[', $config['macro_opening']);
        self::assertSame(']]', $config['macro_closing']);
    }

    public function testCustomConditionalDelimiters(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [
            [
                'conditional_if_opening'    => '[[#if',
                'conditional_if_closing'    => ']]',
                'conditional_endif_opening' => '[[#endif',
                'conditional_endif_closing' => ']]',
            ],
        ]);

        self::assertSame('[[#if', $config['conditional_if_opening']);
        self::assertSame(']]', $config['conditional_if_closing']);
        self::assertSame('[[#endif', $config['conditional_endif_opening']);
        self::assertSame(']]', $config['conditional_endif_closing']);
    }

    public function testDefaultAllowedRootsEmpty(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertSame([], $config['allowed_roots']);
        self::assertFalse($config['path_allowlist_required']);
    }

    public function testPathAllowlistRequiredWithoutRootsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'path_allowlist_required' => true,
            'allowed_roots'           => [],
        ]]);
    }

    public function testPathAllowlistRequiredWithRootsAccepted(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'path_allowlist_required' => true,
            'allowed_roots'           => ['/tmp/templates'],
        ]]);

        self::assertTrue($config['path_allowlist_required']);
        self::assertSame(['/tmp/templates'], $config['allowed_roots']);
    }
}
