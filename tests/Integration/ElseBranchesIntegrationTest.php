<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PHPUnit\Framework\TestCase;
use ZipArchive;

use function array_map;
use function bin2hex;
use function html_entity_decode;
use function implode;
use function is_string;
use function preg_replace;
use function random_bytes;
use function str_replace;
use function strip_tags;
use function sys_get_temp_dir;
use function trim;
use function unlink;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * {@code ${#elseif}} / {@code ${#else}} branches on real .docx files (spec 004-else-branches).
 */
final class ElseBranchesIntegrationTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testBranchesInBodyTableAndHeader(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $header  = $section->addHeader();
            $header->addText('${#if draft}');
            $header->addText('DRAFT');
            $header->addText('${#else}');
            $header->addText('FINAL');
            $header->addText('${#endif}');

            $section->addText('Premium: ${premium}');
            $section->addText('${#if premium > 5000}');
            $section->addText('Signed by the general manager.');
            $section->addText('${#elseif premium > 1000}');
            $section->addText('Signed by the underwriting manager.');
            $section->addText('${#else}');
            $section->addText('Signed by the underwriter.');
            $section->addText('${#endif}');

            $table = $section->addTable();
            $table->addRow()->addCell(3000)->addText('Status');
            $table->addRow()->addCell(3000)->addText('${#if paid}');
            $table->addRow()->addCell(3000)->addText('Paid');
            $table->addRow()->addCell(3000)->addText('${#else}');
            $table->addRow()->addCell(3000)->addText('Pending');
            $table->addRow()->addCell(3000)->addText('${#endif}');
        });

        $processor = new WordTemplateProcessor(conditionalExpressionsEnabled: true);

        $out = $processor->process($template, ['draft' => true, 'premium' => 2500, 'paid' => false]);
        try {
            self::assertSame('Premium: 2500 Signed by the underwriting manager. Status Pending', $this->text($out->path(), 'word/document.xml'));
            self::assertSame('DRAFT', $this->text($out->path(), 'word/header1.xml'));
        } finally {
            $out->dispose();
        }

        $out = $processor->process($template, ['draft' => false, 'premium' => 10, 'paid' => true]);
        try {
            self::assertSame('Premium: 10 Signed by the underwriter. Status Paid', $this->text($out->path(), 'word/document.xml'));
            self::assertSame('FINAL', $this->text($out->path(), 'word/header1.xml'));
        } finally {
            $out->dispose();
        }
    }

    public function testListingLintingAndDisabledFlag(): void
    {
        $template = $this->template(static function (PhpWord $pw): void {
            $section = $pw->addSection();
            $section->addText('${#if total > 0}');
            $section->addText('${name}');
            $section->addText('${#elseif refund}');
            $section->addText('Refund');
            $section->addText('${#else}');
            $section->addText('Nothing');
            $section->addText('${#endif}');
            $section->addText('${#if annex}');
            $section->addText('${#else}');
            $section->addText('${#endif annex}');
        });

        $enabled = new WordTemplateProcessor(conditionalExpressionsEnabled: true);

        self::assertSame(['name'], $enabled->listVariables($template));
        self::assertSame(['total > 0', 'refund'], $enabled->listConditionalExpressions($template));
        self::assertSame(['annex'], $enabled->listConditionalBlocks($template));

        $problems = array_map(static fn (ConditionalExpressionException $e): string => $e->getMessage(), $enabled->lintConditionalExpressions($template, ['total']));
        $all      = implode("\n", $problems);
        self::assertCount(2, $problems);
        self::assertStringContainsString('"${#else}": else / elseif branches only work in blocks closed by a nameless end marker', $all);
        self::assertStringContainsString('unknown variable "refund"', $all);

        // Disabled: branch markers are ordinary placeholders and stay as text.
        $disabled = new WordTemplateProcessor();
        self::assertContains('#else', $disabled->listVariables($template));

        $out = $disabled->process($template, ['total' => 1, 'name' => 'N', 'annex' => new ConditionalBlock('annex', true)]);
        try {
            self::assertSame('${#if total > 0} N ${#elseif refund} Refund ${#else} Nothing ${#endif} ${#else}', $this->text($out->path(), 'word/document.xml'));
        } finally {
            $out->dispose();
        }
    }

    private function template(callable $build): string
    {
        $path    = sys_get_temp_dir() . '/nowo_word_else_' . bin2hex(random_bytes(8)) . '.docx';
        $escaped = Settings::isOutputEscapingEnabled();
        Settings::setOutputEscapingEnabled(true);

        try {
            $pw = new PhpWord();
            $build($pw);
            IOFactory::createWriter($pw, 'Word2007')->save($path);
        } finally {
            Settings::setOutputEscapingEnabled($escaped);
        }

        $this->files[] = $path;

        return $path;
    }

    private function text(string $docxPath, string $part): string
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($docxPath));
        $xml = $zip->getFromName($part);
        $zip->close();
        self::assertTrue(is_string($xml), $part . ' missing');

        $text = strip_tags(str_replace('</w:p>', ' </w:p>', $xml));

        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($text, ENT_QUOTES | ENT_XML1)));
    }
}
