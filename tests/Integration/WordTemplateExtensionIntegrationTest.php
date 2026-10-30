<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Processor\ConditionalExpressionInspectorInterface;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface;
use Nowo\WordTemplateBundle\Tests\Fixtures\AppKernel;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use ZipArchive;

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
        self::assertInstanceOf(
            ConditionalExpressionInspectorInterface::class,
            $container->get(ConditionalExpressionInspectorInterface::class),
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHostFunctionProviderIsAutoconfiguredAndDenyListApplies(): void
    {
        self::bootKernel();

        $processor = self::getContainer()->get(WordTemplateProcessorInterface::class);
        self::assertInstanceOf(WordTemplateProcessorInterface::class, $processor);

        $path    = sys_get_temp_dir() . '/nowo_word_kernel_' . bin2hex(random_bytes(6)) . '.docx';
        $escaped = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);
        $pw      = new PhpWord();
        $section = $pw->addSection();
        $section->addText('${#if vat_rate(country) > 0.2 and upper(country) == "ES"}');
        $section->addText('Spanish VAT');
        $section->addText('${#endif}');
        IOFactory::createWriter($pw, 'Word2007')->save($path);
        Settings::setOutputEscapingEnabled($escaped);

        try {
            $out = $processor->process($path, ['country' => 'ES']);
            self::assertStringContainsString('Spanish VAT', $this->documentXml($out->path()));
            self::assertStringNotContainsString('#if', $this->documentXml($out->path()));
            $out->dispose();

            $inspector = self::getContainer()->get(ConditionalExpressionInspectorInterface::class);
            self::assertInstanceOf(ConditionalExpressionInspectorInterface::class, $inspector);
            self::assertSame([], $inspector->lintConditionalExpressions($path, ['country']));

            $this->expectException(ConditionalExpressionException::class);
            $this->expectExceptionMessage('vat_rate() only knows ES and PT.');
            $processor->process($path, ['country' => 'FR']);
        } finally {
            @unlink($path);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDeniedBuiltinIsNotAvailable(): void
    {
        self::bootKernel();

        $evaluator = self::getContainer()->get(ConditionalExpressionEvaluator::class);
        self::assertInstanceOf(ConditionalExpressionEvaluator::class, $evaluator);

        $this->expectException(ConditionalExpressionException::class);
        $this->expectExceptionMessage('The function "now" does not exist');
        $evaluator->evaluate("now() != ''", []);
    }

    private function documentXml(string $docx): string
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($docx));
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }
}
