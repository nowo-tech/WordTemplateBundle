<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Tests\Integration;

use Nowo\WordTemplateBundle\Exception\InvalidContextValueException;
use Nowo\WordTemplateBundle\Exception\PathNotAllowedException;
use Nowo\WordTemplateBundle\Exception\ProcessingTimedOutException;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\ImageSource;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Processor\WordTemplateProcessor;
use Nowo\WordTemplateBundle\Runtime\ProcessDeadline;
use Nowo\WordTemplateBundle\Tests\Fixtures\RecordingLogger;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function basename;
use function bin2hex;
use function dirname;
use function hrtime;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function touch;
use function unlink;

/**
 * REQ-OBS-001: warning/error paths and finished merges are logged through PSR-3, without full paths or context values.
 */
final class WordTemplateProcessorLoggingTest extends TestCase
{
    private RecordingLogger $logger;

    /** @var list<string> */
    private array $files = [];

    /** @var list<string> */
    private array $dirs = [];

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        foreach ($this->dirs as $dir) {
            @rmdir($dir);
        }
    }

    public function testSuccessfulMergeLogsInfoWithTemplateNameOnly(): void
    {
        $tpl = $this->template();

        $out = (new WordTemplateProcessor(logger: $this->logger))->process($tpl, ['v' => 'secret-merge-value']);
        $out->dispose();

        $info = $this->logger->recordsWithLevel('info');
        self::assertCount(1, $info);
        self::assertSame('Word template merged.', $info[0]['message']);
        self::assertSame('word_template', $info[0]['context']['bundle']);
        self::assertSame(basename($tpl), $info[0]['context']['template']);
        self::assertTrue($info[0]['context']['temporary']);
        self::assertIsFloat($info[0]['context']['duration_ms']);
        $this->assertNoLeak($tpl);
    }

    public function testRejectedTemplatePathLogsWarning(): void
    {
        $tpl  = $this->template();
        $root = $this->dir();

        try {
            (new WordTemplateProcessor(allowedRoots: [$root], logger: $this->logger))->listVariables($tpl);
            self::fail('Expected PathNotAllowedException.');
        } catch (PathNotAllowedException) {
        }

        $warnings = $this->logger->recordsWithLevel('warning');
        self::assertCount(1, $warnings);
        self::assertSame('Word template path rejected by nowo_word_template.allowed_roots.', $warnings[0]['message']);
        self::assertSame('template', $warnings[0]['context']['path_kind']);
        self::assertSame(basename($tpl), $warnings[0]['context']['template']);
        $this->assertNoLeak($tpl);
    }

    public function testRejectedOutputPathLogsOneWarning(): void
    {
        $tpl   = $this->template();
        $other = $this->dir();

        try {
            (new WordTemplateProcessor(allowedRoots: [dirname($tpl)], logger: $this->logger))
                ->process($tpl, ['v' => 'x'], $other . '/out.docx');
            self::fail('Expected PathNotAllowedException.');
        } catch (PathNotAllowedException) {
        }

        $warnings = $this->logger->recordsWithLevel('warning');
        self::assertCount(1, $warnings);
        self::assertSame('output', $warnings[0]['context']['path_kind']);
        self::assertSame([], $this->logger->recordsWithLevel('error'));
    }

    public function testRejectedImagePathLogsWarning(): void
    {
        $tpl   = $this->template('${pic}');
        $other = $this->dir();
        $png   = $other . '/pic.png';
        touch($png);
        $this->files[] = $png;

        try {
            (new WordTemplateProcessor(allowedRoots: [dirname($tpl)], logger: $this->logger))
                ->process($tpl, ['pic' => new ImageSource($png)]);
            self::fail('Expected PathNotAllowedException.');
        } catch (PathNotAllowedException) {
        }

        $warnings = $this->logger->recordsWithLevel('warning');
        self::assertCount(1, $warnings);
        self::assertSame('image', $warnings[0]['context']['path_kind']);
    }

    public function testDroppedHtmlImagesLogWarning(): void
    {
        $tpl = $this->template('${rich}');

        $out = (new WordTemplateProcessor(logger: $this->logger))->process($tpl, [
            'rich' => new HtmlContent('<p>Text<img src="https://attacker.example/x.png"/></p>'),
        ]);
        $out->dispose();

        $warnings = $this->logger->recordsWithLevel('warning');
        self::assertCount(1, $warnings);
        self::assertSame('Word template merge dropped HTML images that are not allowed.', $warnings[0]['message']);
        self::assertSame(1, $warnings[0]['context']['dropped_images']);
        self::assertCount(1, $this->logger->recordsWithLevel('info'));
        self::assertStringNotContainsString('attacker.example', (string) json_encode($this->logger->records));
    }

    public function testTimeoutLogsWarning(): void
    {
        $tpl = $this->template();

        try {
            (new LoggingExpiredDeadlineProcessor(timeout: 1, logger: $this->logger))->process($tpl, ['v' => 'x']);
            self::fail('Expected ProcessingTimedOutException.');
        } catch (ProcessingTimedOutException) {
        }

        $warnings = $this->logger->recordsWithLevel('warning');
        self::assertCount(1, $warnings);
        self::assertSame('Word template merge timed out.', $warnings[0]['message']);
        self::assertSame(1, $warnings[0]['context']['timeout_s']);
        self::assertSame(ProcessingTimedOutException::class, $warnings[0]['context']['exception']);
    }

    public function testInvalidContextLogsWarning(): void
    {
        $tpl = $this->template('${row}');

        try {
            (new WordTemplateProcessor(logger: $this->logger))->process($tpl, ['t' => new TableRows('row', [])]);
            self::fail('Expected InvalidContextValueException.');
        } catch (InvalidContextValueException) {
        }

        $warnings = $this->logger->recordsWithLevel('warning');
        self::assertCount(1, $warnings);
        self::assertSame('Word template merge rejected.', $warnings[0]['message']);
        self::assertSame([], $this->logger->recordsWithLevel('error'));
    }

    public function testUnexpectedFailureLogsError(): void
    {
        $tpl = $this->template();

        try {
            (new LoggingThrowingProcessor(logger: $this->logger))->process($tpl, ['v' => 'x']);
            self::fail('Expected RuntimeException.');
        } catch (RuntimeException $e) {
            self::assertSame('Simulated save failure', $e->getMessage());
        }

        $errors = $this->logger->recordsWithLevel('error');
        self::assertCount(1, $errors);
        self::assertSame('Word template merge failed.', $errors[0]['message']);
        self::assertSame(RuntimeException::class, $errors[0]['context']['exception']);
        self::assertSame(basename($tpl), $errors[0]['context']['template']);
    }

    private function assertNoLeak(string $tpl): void
    {
        $dump = (string) json_encode($this->logger->records);
        self::assertStringNotContainsString(json_encode(dirname($tpl)) ?: dirname($tpl), $dump);
        self::assertStringNotContainsString('secret-merge-value', $dump);
    }

    private function template(string $text = 'Value=${v}'): string
    {
        $path = $this->dir() . '/template.docx';
        $pw   = new PhpWord();
        $pw->addSection()->addText($text);
        IOFactory::createWriter($pw, 'Word2007')->save($path);
        $this->files[] = $path;

        return $path;
    }

    private function dir(): string
    {
        $dir = sys_get_temp_dir() . '/nowo_word_log_dir_' . bin2hex(random_bytes(8));
        mkdir($dir);
        $this->dirs[] = $dir;

        return $dir;
    }
}

readonly class LoggingThrowingProcessor extends WordTemplateProcessor
{
    protected function persistTemplate(TemplateProcessor $processor, string $target): void
    {
        throw new RuntimeException('Simulated save failure');
    }
}

readonly class LoggingExpiredDeadlineProcessor extends WordTemplateProcessor
{
    protected function createDeadline(): ProcessDeadline
    {
        return new ProcessDeadline(1, hrtime(true) - 2_000_000_000.0);
    }
}
