<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration\Worker;

use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessorInterface;
use Nowo\WordTemplateBundle\Tests\Fixtures\AppKernel;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Html;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use ZipArchive;

use function bin2hex;
use function gc_collect_cycles;
use function glob;
use function is_string;
use function memory_get_usage;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function str_contains;
use function sys_get_temp_dir;
use function ucfirst;
use function unlink;

/**
 * REQ-RUNTIME-002: one kept-alive kernel, the services resetter never runs (FrankenPHP worker mode with kernel reset
 * false), N merges as two users in alternation through the public processor service.
 *
 * Each result must hold only its own user's data (text, HTML style sheet), leave no PHPWord working copy behind and
 * leave PHPWord's static HTML state untouched, and memory must stay flat.
 */
final class WorkerKeptAliveKernelTest extends TestCase
{
    private const ITERATIONS = 1000;

    private const WARM_UP = 50;

    private const MEMORY_MARGIN_BYTES = 4 * 1024 * 1024;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/nowo_word_tpl_kept_alive_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir);
        Settings::setTempDir($this->tempDir);
    }

    protected function tearDown(): void
    {
        Settings::setTempDir('');
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tempDir);
    }

    public function testMergesInOneKernelWithoutResetterKeepDataSeparatedAndMemoryFlat(): void
    {
        $template = $this->createTemplate();

        $kernel = new AppKernel();
        $kernel->boot();
        // Fetched once and kept, as a kept-alive worker does; `services_resetter` is never called.
        $processor = $kernel->getContainer()->get(WordTemplateProcessorInterface::class);
        self::assertInstanceOf(WordTemplateProcessorInterface::class, $processor);

        $users = [
            'alpha' => ['name' => 'Alice Alpha', 'html' => '<style>.hl{color:#FF0000}</style><p class="hl">Alpha note</p>'],
            'beta'  => ['name' => 'Bob Beta', 'html' => '<p class="hl">Beta note</p>'],
        ];

        $baseline = 0;

        try {
            for ($i = 0; $i < self::ITERATIONS; ++$i) {
                if ($i === self::WARM_UP) {
                    gc_collect_cycles();
                    $baseline = memory_get_usage();
                }

                $user  = $i % 2 === 0 ? 'alpha' : 'beta';
                $other = $user === 'alpha' ? 'beta' : 'alpha';
                $out   = $processor->process($template, [
                    'name' => $users[$user]['name'],
                    'rich' => new HtmlContent($users[$user]['html']),
                ]);

                try {
                    $xml = $this->readMainDocumentXml($out->path());
                } finally {
                    $out->dispose();
                }

                $at = sprintf('iteration %d (%s)', $i, $user);
                self::assertStringContainsString($users[$user]['name'], $xml, $at);
                self::assertStringNotContainsString($users[$other]['name'], $xml, $at);
                self::assertStringContainsString(ucfirst($user) . ' note', $xml, $at);
                self::assertStringNotContainsString(ucfirst($other) . ' note', $xml, $at);
                // Only the alpha request ships the style sheet; beta must never inherit it.
                self::assertSame($user === 'alpha', str_contains($xml, 'FF0000'), $at);
                self::assertNull((new ReflectionProperty(Html::class, 'css'))->getValue(), $at);
                self::assertNull((new ReflectionProperty(Html::class, 'xpath'))->getValue(), $at);
                self::assertSame([], glob($this->tempDir . '/*') ?: [], $at);
            }
        } finally {
            @unlink($template);
        }

        gc_collect_cycles();
        $growth = memory_get_usage() - $baseline;
        self::assertLessThan(self::MEMORY_MARGIN_BYTES, $growth, sprintf('memory grew by %d bytes over %d merges', $growth, self::ITERATIONS - self::WARM_UP));

        $kernel->shutdown();
    }

    private function createTemplate(): string
    {
        $path    = sys_get_temp_dir() . '/nowo_word_tpl_ka_' . bin2hex(random_bytes(8)) . '.docx';
        $pw      = new PhpWord();
        $section = $pw->addSection();
        $section->addText('Name=${name}');
        $section->addText('${rich}');
        IOFactory::createWriter($pw, 'Word2007')->save($path);

        return $path;
    }

    private function readMainDocumentXml(string $docxPath): string
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($docxPath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        self::assertTrue(is_string($xml));

        return $xml;
    }
}
