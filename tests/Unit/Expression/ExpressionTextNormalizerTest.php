<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Unit\Expression;

use Nowo\WordTemplateBundle\Expression\ExpressionTextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExpressionTextNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function texts(): iterable
    {
        yield 'xml entities' => ['total &gt;= 10 &amp;&amp; a &lt; 2', 'total >= 10 && a < 2'];
        yield 'entity quotes' => ['x == &quot;a&quot; or y == &apos;b&apos;', 'x == "a" or y == \'b\''];
        yield 'curly single quotes' => ["country == \u{2018}ES\u{2019}", "country == 'ES'"];
        yield 'curly double quotes' => ["plan == \u{201C}pro\u{201D}", 'plan == "pro"'];
        yield 'low quotes and guillemets' => ["a == \u{201A}x\u{201B} or b == \u{201E}y\u{201F} or c == \u{00AB}z\u{00BB}", "a == 'x' or b == \"y\" or c == \"z\""];
        yield 'primes' => ["a == \u{2032}x\u{2032} or b == \u{2033}y\u{2033}", "a == 'x' or b == \"y\""];
        yield 'dashes' => ["total \u{2013} paid \u{2014} fee \u{2212} 1 > 0", 'total - paid - fee - 1 > 0'];
        yield 'spaces' => ["\u{00A0}a\u{202F}==\u{2007}1\u{2009}and\tb\r\n", 'a == 1 and b'];
        yield 'zero width' => ["a\u{200B}\u{200C}\u{200D}\u{2060}\u{FEFF} == 1", 'a == 1'];
        yield 'leftover tags' => ['a <w:proofErr/>== 1', 'a == 1'];
        yield 'spaces inside literals are kept' => ["name == 'a  b'", "name == 'a  b'"];
    }

    #[DataProvider('texts')]
    public function testNormalize(string $raw, string $expected): void
    {
        self::assertSame($expected, ExpressionTextNormalizer::normalize($raw));
    }
}
