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
        self::assertSame(5_242_880, $config['html_image_max_bytes']);
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

    public function testConditionalExpressionsDefaultToDisabled(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertSame(['enabled' => false, 'on_error' => 'exception', 'max_length' => 500, 'timezone' => null, 'functions' => ['allow' => null, 'deny' => []]], $config['conditional_expressions']);
    }

    public function testConditionalExpressionsCustom(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'conditional_expressions' => ['enabled' => true, 'on_error' => 'hide', 'max_length' => 200, 'timezone' => 'Europe/Madrid', 'functions' => ['allow' => ['upper'], 'deny' => ['now']]],
        ]]);

        self::assertSame(['enabled' => true, 'on_error' => 'hide', 'max_length' => 200, 'timezone' => 'Europe/Madrid', 'functions' => ['allow' => ['upper'], 'deny' => ['now']]], $config['conditional_expressions']);
    }

    public function testConditionalExpressionsRejectInvalidTimezone(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must be a valid PHP time zone identifier');

        (new Processor())->processConfiguration(new Configuration(), [[
            'conditional_expressions' => ['timezone' => 'Europe/Madird'],
        ]]);
    }

    public function testConditionalExpressionsAcceptUtc(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['conditional_expressions' => ['timezone' => 'UTC']]]);

        self::assertSame('UTC', $config['conditional_expressions']['timezone']);
    }

    public function testConditionalExpressionsRejectUnknownErrorMode(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'conditional_expressions' => ['on_error' => 'ignore'],
        ]]);
    }
}
