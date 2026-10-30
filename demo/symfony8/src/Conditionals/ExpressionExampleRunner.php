<?php

declare(strict_types=1);

namespace App\Conditionals;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use RuntimeException;
use ZipArchive;

use function array_map;
use function bin2hex;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function html_entity_decode;
use function implode;
use function is_string;
use function preg_match_all;
use function random_bytes;
use function str_contains;
use function str_starts_with;
use function strip_tags;
use function sys_get_temp_dir;
use function trim;
use function unlink;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * Builds the .docx template of an {@see ExpressionExamples} entry and merges it with the bundle processor.
 *
 * @phpstan-import-type Example from ExpressionExamples
 */
final readonly class ExpressionExampleRunner
{
    public function __construct(private WordTemplateProcessorInterface $processor)
    {
    }

    /**
     * Writes the template of an example and returns its bytes.
     *
     * @param Example $example
     */
    public function templateBytes(array $example): string
    {
        $path = $this->buildTemplate($example);

        try {
            return $this->read($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Merges the example and returns the generated .docx bytes.
     *
     * @param Example $example
     *
     * @throws ConditionalExpressionException when the example is meant to fail
     */
    public function resultBytes(array $example): string
    {
        $path = $this->buildTemplate($example);

        try {
            $document = $this->processor->process($path, $example['context']);
        } finally {
            @unlink($path);
        }

        try {
            return $document->readContents();
        } finally {
            $document->dispose();
        }
    }

    /**
     * Merges the example and returns the generated document as lines (paragraphs; table rows as "a | b"),
     * or the error message.
     *
     * @param Example $example
     *
     * @return array{lines: list<string>, error: string|null}
     */
    public function preview(array $example): array
    {
        try {
            $bytes = $this->resultBytes($example);
        } catch (ConditionalExpressionException $e) {
            return ['lines' => [], 'error' => $e->getMessage()];
        }

        $tmp = sys_get_temp_dir() . '/wt_demo_result_' . bin2hex(random_bytes(6)) . '.docx';
        file_put_contents($tmp, $bytes);

        try {
            return ['lines' => $this->lines($tmp), 'error' => null];
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @param Example $example
     */
    private function buildTemplate(array $example): string
    {
        $path    = sys_get_temp_dir() . '/wt_demo_tpl_' . bin2hex(random_bytes(6)) . '.docx';
        $escaped = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);

        try {
            $phpWord = new PhpWord();
            $phpWord->setDefaultFontName('Calibri');
            $phpWord->setDefaultFontSize(14);
            $section = $phpWord->addSection(['marginTop' => 700, 'marginBottom' => 700, 'marginLeft' => 900, 'marginRight' => 900]);
            $section->addText($example['title'], ['bold' => true, 'size' => 16, 'color' => '1F3864']);

            foreach ($example['word'] as $line) {
                if (str_starts_with($line, '|')) {
                    $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'A0AEC0', 'cellMargin' => 80]);
                    $table->addRow();
                    foreach (array_map(trim(...), explode('|', trim($line, '|'))) as $cell) {
                        $table->addCell(3000)->addText($cell);
                    }

                    continue;
                }

                $isMarker = str_starts_with($line, '${#');
                $section->addText($line, $isMarker ? ['color' => '2B6CB0', 'name' => 'Consolas', 'size' => 13] : []);
            }

            IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        } finally {
            Settings::setOutputEscapingEnabled($escaped);
        }

        return $path;
    }

    /**
     * @return list<string>
     */
    private function lines(string $docx): array
    {
        $zip = new ZipArchive();
        if ($zip->open($docx) !== true) {
            throw new RuntimeException('Cannot open the generated document.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!is_string($xml)) {
            throw new RuntimeException('The generated document has no body.');
        }

        preg_match_all('#<w:tr\b.*?</w:tr>|<w:p\b.*?</w:p>#s', $xml, $matches);
        $lines = [];
        foreach ($matches[0] as $fragment) {
            if (str_starts_with($fragment, '<w:tr')) {
                preg_match_all('#<w:tc\b.*?</w:tc>#s', $fragment, $cells);
                $lines[] = implode(' | ', array_map(static fn (string $cell): string => self::text($cell), $cells[0]));

                continue;
            }
            if (str_contains($fragment, '<w:tbl')) {
                continue;
            }
            $text = self::text($fragment);
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return $lines;
    }

    private static function text(string $xml): string
    {
        return trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));
    }

    private function read(string $path): string
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException('Cannot read the generated template.');
        }

        return $bytes;
    }
}
