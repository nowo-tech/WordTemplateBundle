<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Processor;

use Nowo\WordTemplateBundle\Model;
use Nowo\WordTemplateBundle\Result\ProcessedDocument;
use PhpOffice\PhpWord\TemplateProcessor;

interface WordTemplateProcessorInterface
{
    /**
     * Applies context to a .docx template (PHPWord {@see TemplateProcessor}).
     *
     * @param array<string, mixed> $context nested arrays are flattened to dot keys (e.g. {@code client.name});
     *                                      use {@see Model} value objects for conditionals / rows / HTML / images
     * @param string|null $outputPath writable path for the result; if null, a temporary file is created
     */
    public function process(string $templatePath, array $context, ?string $outputPath = null): ProcessedDocument;

    /**
     * Returns unique placeholder names in the template (main part, headers, footers).
     * Omits conditional markers ({@code #if …} / {@code #endif …}); paint fields inside
     * conditional regions are included.
     *
     * @return list<string>
     */
    public function listVariables(string $templatePath): array;

    /**
     * Returns unique conditional block names found via opening markers
     * (e.g. {@code annex} from {@code ${#if annex}}), using configured {@code conditional_*} delimiters.
     * Scans the main document part plus headers and footers.
     *
     * @return list<string>
     */
    public function listConditionalBlocks(string $templatePath): array;
}
