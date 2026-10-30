<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Expression;

use function html_entity_decode;
use function strip_tags;
use function strtr;
use function trim;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * Turns the raw text of an {@code ${#if …}} marker (as stored in WordprocessingML) into an expression.
 *
 * - removes any leftover XML tags and decodes XML entities ({@code &gt;} → {@code >}, {@code &amp;} → {@code &});
 * - replaces the typographic characters Word's AutoCorrect inserts while typing: curly quotes ‘ ’ ‚ ‛ ′ → {@code '},
 *   “ ” „ ‟ ″ « » → {@code "}, en/em dash and minus sign → {@code -};
 * - turns non-breaking / narrow / figure spaces, tabs and line breaks into plain spaces and drops zero-width characters;
 * - trims (spaces inside string literals are kept).
 */
final class ExpressionTextNormalizer
{
    private const REPLACEMENTS = [
        "\u{2018}" => "'",
        "\u{2019}" => "'",
        "\u{201A}" => "'",
        "\u{201B}" => "'",
        "\u{2032}" => "'",
        "\u{201C}" => '"',
        "\u{201D}" => '"',
        "\u{201E}" => '"',
        "\u{201F}" => '"',
        "\u{2033}" => '"',
        "\u{00AB}" => '"',
        "\u{00BB}" => '"',
        "\u{2013}" => '-',
        "\u{2014}" => '-',
        "\u{2212}" => '-',
        "\u{00A0}" => ' ',
        "\u{202F}" => ' ',
        "\u{2007}" => ' ',
        "\u{2009}" => ' ',
        "\t"       => ' ',
        "\r"       => ' ',
        "\n"       => ' ',
        "\u{200B}" => '',
        "\u{200C}" => '',
        "\u{200D}" => '',
        "\u{2060}" => '',
        "\u{FEFF}" => '',
    ];

    /**
     * @param string $markerText text between the opening delimiter and the closing delimiter, as found in the XML
     */
    public static function normalize(string $markerText): string
    {
        $text = html_entity_decode(strip_tags($markerText), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim(strtr($text, self::REPLACEMENTS));
    }
}
