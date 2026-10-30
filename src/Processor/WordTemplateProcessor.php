<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Processor;

use Nowo\WordTemplateBundle\Exception\ConditionalExpressionException;
use Nowo\WordTemplateBundle\Exception\InvalidContextValueException;
use Nowo\WordTemplateBundle\Exception\PathNotAllowedException;
use Nowo\WordTemplateBundle\Exception\ProcessingTimedOutException;
use Nowo\WordTemplateBundle\Exception\TemplateNotFoundException;
use Nowo\WordTemplateBundle\Exception\WordTemplateExceptionInterface;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionErrorMode;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionEvaluator;
use Nowo\WordTemplateBundle\Expression\ConditionalExpressionReport;
use Nowo\WordTemplateBundle\Expression\ExpressionVariables;
use Nowo\WordTemplateBundle\Expression\LoopSettings;
use Nowo\WordTemplateBundle\Model\ConditionalBlock;
use Nowo\WordTemplateBundle\Model\HtmlContent;
use Nowo\WordTemplateBundle\Model\ImageSource;
use Nowo\WordTemplateBundle\Model\TableRows;
use Nowo\WordTemplateBundle\Result\ProcessedDocument;
use Nowo\WordTemplateBundle\Runtime\ProcessDeadline;
use Nowo\WordTemplateBundle\Util\ConditionalBlockApplicator;
use Nowo\WordTemplateBundle\Util\ConditionalExpressionApplicator;
use Nowo\WordTemplateBundle\Util\ContextFlattener;
use Nowo\WordTemplateBundle\Util\HtmlImageSanitizer;
use Nowo\WordTemplateBundle\Util\PathAllowlist;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Settings as PhpWordSettings;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\TemplateProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;
use Throwable;

use function basename;
use function chmod;
use function count;
use function hrtime;
use function implode;
use function in_array;
use function ini_get;
use function is_bool;
use function preg_match;
use function preg_quote;
use function preg_split;
use function round;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;

/**
 * Default {@see WordTemplateProcessorInterface} implementation on top of PHPWord {@see TemplateProcessor}.
 *
 * Stateless between calls (safe as a shared service in FrankenPHP worker mode). Operational events are sent
 * to a PSR-3 logger (channel {@code word_template} when MonologBundle is installed): path rejections,
 * dropped HTML images, timeouts and failures as warnings/errors, finished merges as info. Only the template
 * file name and counters are logged — never full paths, context values or document contents.
 */
readonly class WordTemplateProcessor implements WordTemplateProcessorInterface, ConditionalExpressionInspectorInterface
{
    public const DEFAULT_HTML_IMAGE_MAX_BYTES = 5_242_880;

    private LoggerInterface $logger;

    private ConditionalExpressionEvaluator $expressionEvaluator;

    private ConditionalExpressionErrorMode $expressionErrorMode;

    private LoopSettings $loopSettings;

    /**
     * @param string $macroOpening placeholder opening delimiter
     * @param string $macroClosing placeholder closing delimiter
     * @param string $conditionalIfOpening opening delimiter of conditional {@code if} markers
     * @param string $conditionalIfClosing closing delimiter of conditional {@code if} markers
     * @param string $conditionalEndifOpening opening delimiter of conditional {@code endif} markers
     * @param string $conditionalEndifClosing closing delimiter of conditional {@code endif} markers
     * @param int $timeout wall-clock timeout of {@see self::process()} in seconds
     * @param list<string> $allowedRoots absolute directories that template/image/output paths must stay under (empty = unrestricted)
     * @param int $htmlImageMaxBytes maximum decoded size of one {@code data:} image inside {@see HtmlContent}
     * @param LoggerInterface|null $logger PSR-3 logger; null disables logging
     * @param bool $conditionalExpressionsEnabled true applies {@code ${#if <expression>}} … {@code ${#endif}} blocks (opt-in)
     * @param string $conditionalExpressionsOnError {@code exception}, {@code hide} or {@code keep} ({@see ConditionalExpressionErrorMode})
     * @param ConditionalExpressionEvaluator|null $conditionalExpressionEvaluator parser / evaluator (max length, functions); null = defaults
     * @param bool $loopsEnabled true repeats {@code ${#for item in items}} … {@code ${#endfor}} blocks (opt-in)
     * @param int $loopMaxIterations maximum number of iterations of one loop
     * @param int $loopMaxTotalIterations maximum number of iterations of all loops of one merge
     * @param string $loopForOpening opening delimiter of loop markers
     * @param string $loopForClosing closing delimiter of loop markers
     * @param string $loopEndforOpening opening delimiter of loop end markers
     * @param string $loopEndforClosing closing delimiter of loop end markers
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
        ?LoggerInterface $logger = null,
        private bool $conditionalExpressionsEnabled = false,
        string $conditionalExpressionsOnError = 'exception',
        ?ConditionalExpressionEvaluator $conditionalExpressionEvaluator = null,
        private bool $loopsEnabled = false,
        int $loopMaxIterations = LoopSettings::DEFAULT_MAX_ITERATIONS,
        int $loopMaxTotalIterations = LoopSettings::DEFAULT_MAX_TOTAL_ITERATIONS,
        string $loopForOpening = '${#for',
        string $loopForClosing = '}',
        string $loopEndforOpening = '${#endfor',
        string $loopEndforClosing = '}',
    ) {
        $this->loopSettings        = new LoopSettings($loopForOpening, $loopForClosing, $loopEndforOpening, $loopEndforClosing, $macroOpening, $macroClosing, $loopMaxIterations, $loopMaxTotalIterations);
        $this->logger              = $logger ?? new NullLogger();
        $this->expressionErrorMode = ConditionalExpressionErrorMode::from($conditionalExpressionsOnError);
        $this->expressionEvaluator = $conditionalExpressionEvaluator ?? new ConditionalExpressionEvaluator();
    }

    /**
     * {@inheritDoc}
     *
     * @param string $templatePath readable .docx template
     *
     * @return list<string>
     */
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
        if ($markerPattern !== null) {
            $variables = array_filter($variables, static fn (string $name): bool => preg_match($markerPattern, $name) !== 1);
        }

        if ($this->loopsEnabled) {
            $loopVariables = $this->loopVariables($templatePath);
            $variables     = array_filter($variables, static fn (string $name): bool => !in_array(preg_split('/[.\[]/', $name)[0] ?? $name, $loopVariables, true));
        }

        return array_values($variables);
    }

    /**
     * Names introduced by the template's loops ({@code item}, {@code key}, {@code loop}): their placeholders are filled
     * by the loops, not by the context.
     *
     * @return list<string>
     */
    private function loopVariables(string $templatePath): array
    {
        $processor  = $this->openTemplate($templatePath);
        $applicator = $this->createExpressionApplicator(true);
        $names      = [];

        try {
            foreach ($processor->documentPartXmls() as $xml) {
                $names = [...$names, ...$applicator->discoverLoopVariables($xml)];
            }
        } finally {
            // @igor-ignore - Not shared worker service state.
            $processor->removeTemporaryDocument();
        }

        return $names;
    }

    /**
     * Conditional markers are also matched by the placeholder delimiters (e.g. {@code ${#if name}} is seen by
     * PHPWord as variable {@code #if name}). Builds a regex for those names from the configured delimiters;
     * null when no conditional opening delimiter starts with {@code macro_opening}.
     */
    private function conditionalMarkerVariablePattern(): ?string
    {
        $prefixes = [];
        $openings = [$this->conditionalIfOpening, $this->conditionalEndifOpening];
        if ($this->loopsEnabled) {
            $openings = [...$openings, $this->loopSettings->forOpening, $this->loopSettings->endforOpening];
        }
        foreach ($openings as $opening) {
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

        return '/^(?:' . implode('|', $prefixes) . ')(?:\s|$)/';
    }

    /**
     * {@inheritDoc}
     *
     * @param string $templatePath readable .docx template
     *
     * @return list<string>
     */
    public function listConditionalBlocks(string $templatePath): array
    {
        $processor  = $this->openTemplate($templatePath);
        $applicator = $this->conditionalExpressionsEnabled ? $this->createExpressionApplicator(true) : $this->createConditionalApplicator();
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

    /**
     * {@inheritDoc}
     *
     * @return list<string>
     */
    public function listConditionalExpressions(string $templatePath): array
    {
        return $this->inspectParts($templatePath, static fn (ConditionalExpressionApplicator $applicator, string $xml): array => $applicator->discoverExpressions($xml));
    }

    /**
     * {@inheritDoc}
     *
     * @return list<string>
     */
    public function listLoops(string $templatePath): array
    {
        return $this->inspectParts($templatePath, static fn (ConditionalExpressionApplicator $applicator, string $xml): array => $applicator->discoverLoops($xml));
    }

    /**
     * Unique strings returned by {@code $inspect} for every part (main, headers, footers).
     *
     * @param callable(ConditionalExpressionApplicator, string): list<string> $inspect
     *
     * @return list<string>
     */
    private function inspectParts(string $templatePath, callable $inspect): array
    {
        $processor  = $this->openTemplate($templatePath);
        $applicator = $this->createExpressionApplicator(true);
        $found      = [];

        try {
            foreach ($processor->documentPartXmls() as $xml) {
                foreach ($inspect($applicator, $xml) as $item) {
                    $found[$item] = true;
                }
            }
        } finally {
            // @igor-ignore - Not shared worker service state.
            $processor->removeTemporaryDocument();
        }

        return array_keys($found);
    }

    /**
     * {@inheritDoc}
     *
     * @param list<string> $variableNames
     *
     * @return list<ConditionalExpressionException>
     */
    public function lintConditionalExpressions(string $templatePath, array $variableNames): array
    {
        $processor  = $this->openTemplate($templatePath);
        $applicator = $this->createExpressionApplicator(true);
        $problems   = [];

        try {
            foreach ($processor->documentPartXmls() as $xml) {
                $problems = [...$problems, ...$applicator->lint($xml, $variableNames)];
            }
        } finally {
            // @igor-ignore - Not shared worker service state.
            $processor->removeTemporaryDocument();
        }

        return $problems;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $templatePath readable .docx template
     * @param array<string, mixed> $context merge values (nested arrays are flattened to dot keys)
     * @param string|null $outputPath writable path for the result; null creates a temporary file
     *
     * @return ProcessedDocument generated document
     */
    public function process(string $templatePath, array $context, ?string $outputPath = null): ProcessedDocument
    {
        $startedAtNs          = hrtime(true);
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

            $this->applyConditionalExpressions($processor, $context, $templatePath);
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
                    $this->applyImage($processor, $key, $value, $templatePath);
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
                $this->assertPathAllowed($outputPath, false, 'output', $templatePath);
            }

            $deadline->assertNotTimedOut();

            try {
                $this->persistTemplate($processor, $target);
                if ($temporary) {
                    // Merged documents often hold personal data: owner-only in the shared temp dir.
                    chmod($target, 0o600);
                }
            } catch (Throwable $e) {
                if ($temporary && is_file($target)) {
                    @unlink($target);
                }

                throw $e;
            }

            if ($htmlImages->droppedImages() > 0) {
                $this->logger->warning('Word template merge dropped HTML images that are not allowed.', [
                    'bundle'         => 'word_template',
                    'action'         => 'process',
                    'template'       => basename($templatePath),
                    'dropped_images' => $htmlImages->droppedImages(),
                ]);
            }

            $this->logger->info('Word template merged.', [
                'bundle'      => 'word_template',
                'action'      => 'process',
                'template'    => basename($templatePath),
                'temporary'   => $temporary,
                'duration_ms' => round((hrtime(true) - $startedAtNs) / 1_000_000, 1),
            ]);

            return new ProcessedDocument($target, $temporary);
        } catch (Throwable $e) {
            $this->logFailure($e, $templatePath);

            throw $e;
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

    /**
     * Evaluates the expression blocks after the named ones (so expressions inside a removed named block are never
     * evaluated). No-op unless {@code conditional_expressions.enabled}.
     *
     * @param array<string, mixed> $context
     */
    private function applyConditionalExpressions(TemplateProcessorBridge $processor, array $context, string $templatePath): void
    {
        if (!$this->conditionalExpressionsEnabled && !$this->loopsEnabled) {
            return;
        }

        $applicator = $this->createExpressionApplicator(false);
        $variables  = ExpressionVariables::fromContext($context);
        $report     = new ConditionalExpressionReport();
        $processor->transformDocumentParts(static fn (string $xml): string => $applicator->apply($xml, $variables, $report));

        if ($report->errors() !== []) {
            $this->logger->warning('Word template conditional expressions failed and were applied with on_error mode.', [
                'bundle'   => 'word_template',
                'action'   => 'conditional_expressions',
                'template' => basename($templatePath),
                'on_error' => $this->expressionErrorMode->value,
                'errors'   => count($report->errors()),
            ]);
        }
    }

    /**
     * @param bool $inspection true recognises conditionals and loops whatever the configuration (listing / linting)
     */
    private function createExpressionApplicator(bool $inspection): ConditionalExpressionApplicator
    {
        return new ConditionalExpressionApplicator(
            $this->conditionalIfOpening,
            $this->conditionalIfClosing,
            $this->conditionalEndifOpening,
            $this->conditionalEndifClosing,
            $this->expressionEvaluator,
            $this->expressionErrorMode,
            $inspection || $this->conditionalExpressionsEnabled,
            $inspection || $this->loopsEnabled ? $this->loopSettings : null,
        );
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

    private function applyImage(TemplateProcessor $processor, string $macroKey, ImageSource $image, string $templatePath): void
    {
        $this->assertPathAllowed($image->path, true, 'image', $templatePath);

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

        $this->assertPathAllowed($templatePath, true, 'template', $templatePath);

        $processor = new TemplateProcessorBridge($templatePath);
        $processor->setMacroChars($this->macroOpening, $this->macroClosing);

        return $processor;
    }

    /**
     * Runs {@see PathAllowlist::assertUnderRoots()} and logs a warning (path kind + template file name, never the
     * full path) before rethrowing the rejection.
     */
    private function assertPathAllowed(string $path, bool $mustExist, string $kind, string $templatePath): void
    {
        try {
            PathAllowlist::assertUnderRoots($path, $this->allowedRoots, $mustExist);
        } catch (PathNotAllowedException $e) {
            $this->logger->warning('Word template path rejected by nowo_word_template.allowed_roots.', [
                'bundle'    => 'word_template',
                'action'    => 'path_check',
                'path_kind' => $kind,
                'template'  => basename($templatePath),
            ]);

            throw $e;
        }
    }

    /**
     * Logs a failed {@see self::process()} call. Path rejections were already logged by {@see self::assertPathAllowed()}.
     */
    private function logFailure(Throwable $e, string $templatePath): void
    {
        if ($e instanceof PathNotAllowedException) {
            return;
        }

        $context = [
            'bundle'    => 'word_template',
            'action'    => 'process',
            'template'  => basename($templatePath),
            'exception' => $e::class,
        ];

        if ($e instanceof ProcessingTimedOutException) {
            $this->logger->warning('Word template merge timed out.', $context + ['timeout_s' => $this->timeout]);

            return;
        }

        // Caller errors (missing template, invalid context value) are warnings; anything else is an error.
        if ($e instanceof WordTemplateExceptionInterface) {
            $this->logger->warning('Word template merge rejected.', $context);

            return;
        }

        $this->logger->error('Word template merge failed.', $context);
    }

    private function makeTempOutputPath(): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'nowo_word_tpl_' . bin2hex(random_bytes(8)) . '.docx';
    }
}
