<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Processor;

use Nowo\WordTemplateBundle\Exception\InvalidContextValueException;
use Nowo\WordTemplateBundle\Exception\TemplateNotFoundException;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\ImageSource;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Result\ProcessedDocument;
use Nowo\WordTemplateBundle\Runtime\ProcessDeadline;
use Nowo\WordTemplateBundle\Util\ConditionalBlockApplicator;
use Nowo\WordTemplateBundle\Util\ContextFlattener;
use Nowo\WordTemplateBundle\Util\HtmlImageSanitizer;
use Nowo\WordTemplateBundle\Util\PathAllowlist;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Settings as PhpWordSettings;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\TemplateProcessor;
use Stringable;
use Throwable;

use function count;
use function implode;
use function ini_get;
use function is_bool;
use function preg_match;
use function preg_quote;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;

readonly class WordTemplateProcessor implements WordTemplateProcessorInterface
{
    public const DEFAULT_HTML_IMAGE_MAX_BYTES = 5_242_880;

    /**
     * @param list<string> $allowedRoots absolute directories that template/image/output paths must stay under (empty = unrestricted)
     * @param int $htmlImageMaxBytes maximum decoded size of one {@code data:} image inside {@see HtmlContent}
     */
    public function __construct(
        private string $macroOpening = '${',
        private string $macroClosing = '}',
        private string $conditionalIfOpening = '${#if',
        private string $conditionalIfClosing = '}',
        private string $conditionalEndifOpening = '${#endif',
        private string $conditionalEndifClosing = '}',
        private int $timeout = 180,
        private array $allowedRoots = [],
        private int $htmlImageMaxBytes = self::DEFAULT_HTML_IMAGE_MAX_BYTES,
    ) {
    }

    public function listVariables(string $templatePath): array
    {
        $processor = $this->openTemplate($templatePath);

        try {
            $variables = $processor->getVariables();
        } finally {
            // @igor-ignore - Not shared worker service state.
            $processor->removeTemporaryDocument();
        }

        $markerPattern = $this->conditionalMarkerVariablePattern();
        if ($markerPattern === null) {
            return array_values($variables);
        }

        return array_values(array_filter(
            $variables,
            static fn (string $name): bool => preg_match($markerPattern, $name) !== 1,
        ));
    }

    /**
     * Conditional markers are also matched by the placeholder delimiters (e.g. {@code ${#if name}} is seen by
     * PHPWord as variable {@code #if name}). Builds a regex for those names from the configured delimiters;
     * null when no conditional opening delimiter starts with {@code macro_opening}.
     */
    private function conditionalMarkerVariablePattern(): ?string
    {
        $prefixes = [];
        foreach ([$this->conditionalIfOpening, $this->conditionalEndifOpening] as $opening) {
            if ($this->macroOpening === '' || !str_starts_with($opening, $this->macroOpening)) {
                continue;
            }
            $prefix = substr($opening, strlen($this->macroOpening));
            if ($prefix !== '') {
                $prefixes[] = preg_quote($prefix, '/');
            }
        }

        if ($prefixes === []) {
            return null;
        }

        return '/^(?:' . implode('|', $prefixes) . ')\s/';
    }

    public function listConditionalBlocks(string $templatePath): array
    {
        $processor  = $this->openTemplate($templatePath);
        $applicator = $this->createConditionalApplicator();
        $names      = [];

        try {
            foreach ($processor->documentPartXmls() as $xml) {
                foreach ($applicator->discoverBlockNames($xml) as $blockName) {
                    if (isset($names[$blockName])) {
                        continue;
                    }
                    $names[$blockName] = true;
                }
            }
        } finally {
            // @igor-ignore - Not shared worker service state.
            $processor->removeTemporaryDocument();
        }

        return array_keys($names);
    }

    public function process(string $templatePath, array $context, ?string $outputPath = null): ProcessedDocument
    {
        $previousMaxExecution = (int) ini_get('max_execution_time');
        $previousXmlEscaping  = PhpWordSettings::isOutputEscapingEnabled();
        set_time_limit($this->timeout);
        // PHPWord TemplateProcessor writes merge values into OOXML. Without escaping,
        // characters such as "&" / "<" (e.g. partner names "Ores & Bryan…") corrupt
        // document.xml and downstream LibreOffice Word→PDF conversion fails.
        PhpWordSettings::setOutputEscapingEnabled(true);
        $previousHtmlState = PhpWordHtmlState::isolate();
        $processor         = null;
        $htmlImages        = new HtmlImageSanitizer($this->allowedRoots, $this->htmlImageMaxBytes);

        try {
            $deadline = $this->createDeadline();

            /** @var array<string, ConditionalBlock|HtmlContent|ImageSource|scalar|Stringable|TableRows|null> $flat */
            $flat = ContextFlattener::flatten($context);

            $processor = $this->openTemplate($templatePath);
            $deadline->assertNotTimedOut();

            $this->applyConditionalBlocks($processor, $flat);
            $deadline->assertNotTimedOut();

            foreach ($flat as $value) {
                if ($value instanceof TableRows) {
                    $this->applyTableRows($processor, $value);
                    $deadline->assertNotTimedOut();
                }
            }

            foreach ($flat as $key => $value) {
                if ($value instanceof ConditionalBlock || $value instanceof TableRows) {
                    continue;
                }

                if ($value instanceof HtmlContent) {
                    $this->applyHtml($processor, $key, $value, $htmlImages);
                    $deadline->assertNotTimedOut();

                    continue;
                }

                if ($value instanceof ImageSource) {
                    $this->applyImage($processor, $key, $value);
                    $deadline->assertNotTimedOut();

                    continue;
                }

                // @igor-ignore - Not shared worker service state.
                $processor->setValue($key, $this->stringify($value));
                $deadline->assertNotTimedOut();
            }

            $target    = $outputPath ?? $this->makeTempOutputPath();
            $temporary = $outputPath === null;

            if ($outputPath !== null) {
                PathAllowlist::assertUnderRoots($outputPath, $this->allowedRoots, false);
            }

            $deadline->assertNotTimedOut();

            try {
                $this->persistTemplate($processor, $target);
            } catch (Throwable $e) {
                if ($temporary && is_file($target)) {
                    @unlink($target);
                }

                throw $e;
            }

            return new ProcessedDocument($target, $temporary);
        } finally {
            $processor?->removeTemporaryDocument();
            $htmlImages->cleanup();
            PhpWordHtmlState::restore($previousHtmlState);
            PhpWordSettings::setOutputEscapingEnabled($previousXmlEscaping);
            set_time_limit($previousMaxExecution);
        }
    }

    /**
     * @internal hook for unit tests that force an already-expired deadline
     */
    protected function createDeadline(): ProcessDeadline
    {
        return new ProcessDeadline($this->timeout);
    }

    /**
     * @param array<string, ConditionalBlock|HtmlContent|ImageSource|scalar|Stringable|TableRows|null> $flat
     */
    private function applyConditionalBlocks(TemplateProcessorBridge $processor, array $flat): void
    {
        $blocks = [];
        foreach ($flat as $value) {
            if ($value instanceof ConditionalBlock) {
                $blocks[] = $value;
            }
        }

        if ($blocks === []) {
            return;
        }

        $applicator = $this->createConditionalApplicator();
        $processor->transformDocumentParts(static fn (string $xml): string => $applicator->applyAll($xml, $blocks));
    }

    private function createConditionalApplicator(): ConditionalBlockApplicator
    {
        return new ConditionalBlockApplicator(
            $this->conditionalIfOpening,
            $this->conditionalIfClosing,
            $this->conditionalEndifOpening,
            $this->conditionalEndifClosing,
        );
    }

    private function applyTableRows(TemplateProcessor $processor, TableRows $rows): void
    {
        if ($rows->rows === []) {
            throw new InvalidContextValueException(sprintf('TableRows for variable "%s" must contain at least one data row.', $rows->rowVariable));
        }

        $processor->cloneRow($rows->rowVariable, count($rows->rows));

        foreach ($rows->rows as $i => $row) {
            $n = $i + 1;
            foreach ($row as $var => $cellValue) {
                $processor->setValue($var . '#' . $n, $this->stringify($cellValue));
            }
        }
    }

    private function applyHtml(TemplateProcessor $processor, string $macroKey, HtmlContent $content, HtmlImageSanitizer $images): void
    {
        $table = new Table(['borderSize' => 0, 'cellMargin' => 80]);
        $row   = $table->addRow();
        $cell  = $row->addCell(9200);
        // PHPWord would fetch any <img src> (remote URL, stream wrapper, file outside allowed_roots) and leak
        // data: temp files; only data: images and allowlisted local files reach it.
        Html::addHtml($cell, $images->sanitize($content->html), false, false);
        $processor->setComplexBlock($macroKey, $table);
    }

    private function applyImage(TemplateProcessor $processor, string $macroKey, ImageSource $image): void
    {
        PathAllowlist::assertUnderRoots($image->path, $this->allowedRoots, true);

        $replace = $image->path;
        if ($image->width !== null || $image->height !== null) {
            $replace = [
                'path'   => $image->path,
                'width'  => $image->width,
                'height' => $image->height,
            ];
        }

        $processor->setImageValue($macroKey, $replace);
    }

    private function stringify(string|Stringable|int|float|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    protected function persistTemplate(TemplateProcessor $processor, string $target): void
    {
        $processor->saveAs($target);
    }

    private function openTemplate(string $templatePath): TemplateProcessorBridge
    {
        if (!is_file($templatePath) || !is_readable($templatePath)) {
            throw new TemplateNotFoundException(sprintf('DOCX template not found or not readable: "%s".', $templatePath));
        }

        PathAllowlist::assertUnderRoots($templatePath, $this->allowedRoots, true);

        $processor = new TemplateProcessorBridge($templatePath);
        $processor->setMacroChars($this->macroOpening, $this->macroClosing);

        return $processor;
    }

    private function makeTempOutputPath(): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'nowo_word_tpl_' . bin2hex(random_bytes(8)) . '.docx';
    }
}
