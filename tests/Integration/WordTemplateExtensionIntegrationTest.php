<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface;
use Nowo\WordTemplateBundle\Tests\Fixtures\AppKernel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WordTemplateExtensionIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return AppKernel::class;
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProcessorIsPublicAndConfigured(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        self::assertInstanceOf(
            WordTemplateProcessorInterface::class,
            $container->get(WordTemplateProcessorInterface::class),
        );
    }
}
