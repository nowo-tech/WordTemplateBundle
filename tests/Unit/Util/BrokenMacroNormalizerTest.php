<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Util;

use Nowo\WordTemplateBundle\Util\BrokenMacroNormalizer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class BrokenMacroNormalizerTest extends TestCase
{
    public function testForConditionalDelimitersUsesBracePrefixBeforeHash(): void
    {
        $normalizer = BrokenMacroNormalizer::forConditionalDelimiters('${#if', '}');

        $split = '<w:t>${</w:t></w:r><w:r><w:t>#</w:t></w:r><w:r><w:t>if</w:t></w:r>'
            . '<w:r><w:t xml:space="preserve"> annex}</w:t>';

        self::assertSame('<w:t>${#if annex}</w:t>', $normalizer->normalize($split));
        self::assertStringContainsString('${#if annex}', $normalizer->normalize($split));
        self::assertStringNotContainsString('${</w:t>', $normalizer->normalize($split));
    }

    public function testHealsWordSplitIfAndEndifMarkers(): void
    {
        $normalizer = new BrokenMacroNormalizer('${', '}');

        $xml = '<w:p><w:r><w:t>${</w:t></w:r><w:r><w:t>#</w:t></w:r><w:r><w:t>if</w:t></w:r>'
            . '<w:r><w:t xml:space="preserve"> CONDICION_MOSTRAR}</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>body</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>${#</w:t></w:r><w:r><w:t>endif</w:t></w:r>'
            . '<w:r><w:t xml:space="preserve"> CONDICION_MOSTRAR}</w:t></w:r></w:p>';

        $result = $normalizer->normalize($xml);

        self::assertStringContainsString('${#if CONDICION_MOSTRAR}', $result);
        self::assertStringContainsString('${#endif CONDICION_MOSTRAR}', $result);
        self::assertStringNotContainsString('${</w:t>', $result);
    }

    public function testLeavesContiguousMarkersUnchanged(): void
    {
        $normalizer = new BrokenMacroNormalizer('${', '}');
        $xml        = '<w:p><w:r><w:t>${#if annex}</w:t></w:r></w:p>';

        self::assertSame($xml, $normalizer->normalize($xml));
    }

    public function testReturnsInputWhenDelimitersEmpty(): void
    {
        $xml = '<w:t>${x}</w:t>';

        self::assertSame($xml, (new BrokenMacroNormalizer('', '}'))->normalize($xml));
        self::assertSame($xml, (new BrokenMacroNormalizer('${', ''))->normalize($xml));
    }

    public function testForConditionalDelimitersWithoutHashUsesFullOpening(): void
    {
        $normalizer = BrokenMacroNormalizer::forConditionalDelimiters('OPEN', '}');
        $property   = new ReflectionProperty(BrokenMacroNormalizer::class, 'openingChars');

        self::assertSame('OPEN', $property->getValue($normalizer));
    }

    public function testNormalizeLooseWithSingleCharOpening(): void
    {
        $normalizer = new BrokenMacroNormalizer('$', '}');
        $split      = '<w:t>$</w:t></w:r><w:r><w:t>name}</w:t>';

        self::assertSame('<w:t>$name}</w:t>', $normalizer->normalize($split));
    }

    public function testNormalizeLooseWithMultiCharClosing(): void
    {
        $normalizer = new BrokenMacroNormalizer('{{', '}}');
        $split      = '<w:t>{{</w:t></w:r><w:r><w:t>name}}</w:t>';

        self::assertSame('<w:t>{{name}}</w:t>', $normalizer->normalize($split));
    }
}
