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
use Nowo\WordTemplateBundle\Util\ConditionalBlockApplicator;
use Nowo\WordTemplateBundle\Util\ContextFlattener;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\TemplateProcessor;
use Stringable;
use Throwable;

use function count;
use function is_bool;
use function sprintf;

use const DIRECTORY_SEPARATOR;

readonly class WordTemplateProcessor implements WordTemplateProcessorInterface
{
    public function __construct(
        private string $macroOpening = '${',
        private string $macroClosing = '}',
        private string $conditionalIfOpening = '${#if',
        private string $conditionalIfClosing = '}',
        private string $conditionalEndifOpening = '${#endif',
        private string $conditionalEndifClosing = '}',
    ) {
    }

    public function listVariables(string $templatePath): array
    {
        $variables = $this->openTemplate($templatePath)->getVariables();

        return array_values(array_filter(
            $variables,
            static fn (string $name): bool => !str_starts_with($name, '#if ') && !str_starts_with($name, '#endif '),
        ));
    }

    public function process(string $templatePath, array $context, ?string $outputPath = null): ProcessedDocument
    {
        /** @var array<string, ConditionalBlock|HtmlContent|ImageSource|scalar|Stringable|TableRows|null> $flat */
        $flat = ContextFlattener::flatten($context);

        $processor = $this->openTemplate($templatePath);

        $this->applyConditionalBlocks($processor, $flat);

        foreach ($flat as $value) {
            if ($value instanceof TableRows) {
                $this->applyTableRows($processor, $value);

                continue;
            }
        }

        foreach ($flat as $key => $value) {
            if ($value instanceof ConditionalBlock || $value instanceof TableRows) {
                continue;
            }

            if ($value instanceof HtmlContent) {
                $this->applyHtml($processor, $key, $value);

                continue;
            }

            if ($value instanceof ImageSource) {
                $this->applyImage($processor, $key, $value);

                continue;
            }

            $processor->setValue($key, $this->stringify($value));
        }

        $target    = $outputPath ?? $this->makeTempOutputPath();
        $temporary = $outputPath === null;

        try {
            $this->persistTemplate($processor, $target);
        } catch (Throwable $e) {
            if ($temporary && is_file($target)) {
                @unlink($target);
            }

            throw $e;
        }

        return new ProcessedDocument($target, $temporary);
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

    private function applyHtml(TemplateProcessor $processor, string $macroKey, HtmlContent $content): void
    {
        $table = new Table(['borderSize' => 0, 'cellMargin' => 80]);
        $row   = $table->addRow();
        $cell  = $row->addCell(9200);
        Html::addHtml($cell, $content->html, false, false);
        $processor->setComplexBlock($macroKey, $table);
    }

    private function applyImage(TemplateProcessor $processor, string $macroKey, ImageSource $image): void
    {
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

        $processor = new TemplateProcessorBridge($templatePath);
        $processor->setMacroChars($this->macroOpening, $this->macroClosing);

        return $processor;
    }

    private function makeTempOutputPath(): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'nowo_word_tpl_' . bin2hex(random_bytes(8)) . '.docx';
    }
}
