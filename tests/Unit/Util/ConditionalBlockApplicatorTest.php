<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Util;

use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Util\ConditionalBlockApplicator;
use PHPUnit\Framework\TestCase;

final class ConditionalBlockApplicatorTest extends TestCase
{
    public function testBuildsMarkersFromConfiguredDelimiters(): void
    {
        $applicator = new ConditionalBlockApplicator('{{#if', '}}', '{{#endif', '}}');

        self::assertSame('{{#if vip_section}}', $applicator->openingMarker('vip_section'));
        self::assertSame('{{#endif vip_section}}', $applicator->closingMarker('vip_section'));
    }

    public function testKeepsInnerContentWhenVisible(): void
    {
        $xml = $this->sampleXml('${#if vip_section}', 'VIP content', '${#endif vip_section}');

        $result = $this->applicator()->apply(
            $xml,
            new ConditionalBlock('vip_section', true),
        );

        self::assertStringContainsString('VIP content', $result);
        self::assertStringNotContainsString('${#if vip_section}', $result);
        self::assertStringNotContainsString('${#endif vip_section}', $result);
    }

    public function testRemovesRegionWhenHidden(): void
    {
        $xml = $this->sampleXml('${#if vip_section}', 'VIP content', '${#endif vip_section}') . $this->paragraph('Footer');

        $result = $this->applicator()->apply(
            $xml,
            new ConditionalBlock('vip_section', false),
        );

        self::assertStringNotContainsString('VIP content', $result);
        self::assertStringContainsString('Footer', $result);
    }

    public function testResolvesNestedBlocksInsideOut(): void
    {
        $xml = $this->paragraph('${#if outer}')
            . $this->paragraph('before')
            . $this->paragraph('${#if inner}')
            . $this->paragraph('secret')
            . $this->paragraph('${#endif inner}')
            . $this->paragraph('after')
            . $this->paragraph('${#endif outer}');

        $result = $this->applicator()->applyAll($xml, [
            new ConditionalBlock('outer', true),
            new ConditionalBlock('inner', false),
        ]);

        self::assertStringContainsString('before', $result);
        self::assertStringContainsString('after', $result);
        self::assertStringNotContainsString('secret', $result);
        self::assertStringNotContainsString('#if inner', $result);
        self::assertStringNotContainsString('#endif inner', $result);
        self::assertStringNotContainsString('#if outer', $result);
        self::assertStringNotContainsString('#endif outer', $result);
    }

    public function testApplyAllReturnsXmlUnchangedWhenBlocksEmpty(): void
    {
        $xml = $this->paragraph('unchanged');

        $result = $this->applicator()->applyAll($xml, []);

        self::assertSame($xml, $result);
    }

    public function testHidesOuterBlockIncludingNestedContent(): void
    {
        $xml = $this->paragraph('${#if outer}')
            . $this->paragraph('${#if inner}')
            . $this->paragraph('secret')
            . $this->paragraph('${#endif inner}')
            . $this->paragraph('${#endif outer}')
            . $this->paragraph('tail');

        $result = $this->applicator()->applyAll($xml, [
            new ConditionalBlock('outer', false),
            new ConditionalBlock('inner', true),
        ]);

        self::assertStringNotContainsString('secret', $result);
        self::assertStringContainsString('tail', $result);
    }

    private function applicator(): ConditionalBlockApplicator
    {
        return new ConditionalBlockApplicator('${#if', '}', '${#endif', '}');
    }

    private function sampleXml(string $open, string $body, string $close): string
    {
        return $this->paragraph($open) . $this->paragraph($body) . $this->paragraph($close);
    }

    private function paragraph(string $text): string
    {
        return '<w:p><w:r><w:t xml:space="preserve">' . $text . '</w:t></w:r></w:p>';
    }
}
