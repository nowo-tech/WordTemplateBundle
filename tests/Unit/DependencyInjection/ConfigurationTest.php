<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\DependencyInjection;

use Nowo\WordTemplateBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaultMacroDelimiters(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), []);

        self::assertSame('${', $config['macro_opening']);
        self::assertSame('}', $config['macro_closing']);
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
}
