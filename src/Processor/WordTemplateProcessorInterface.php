<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Processor;

use Nowo\WordTemplateBundle\Result\ProcessedDocument;

interface WordTemplateProcessorInterface
{
    /**
     * Applies context to a .docx template (PHPWord {@see \PhpOffice\PhpWord\TemplateProcessor}).
     *
     * @param array<string, mixed> $context nested arrays are flattened to dot keys (e.g. {@code client.name});
     *                                      use {@see \Nowo\WordTemplateBundle\Model} value objects for conditionals / rows / HTML / images
     * @param string|null $outputPath writable path for the result; if null, a temporary file is created
     */
    public function process(string $templatePath, array $context, ?string $outputPath = null): ProcessedDocument;

    /**
     * Returns unique placeholder names in the template (main part, headers, footers).
     *
     * @return list<string>
     */
    public function listVariables(string $templatePath): array;
}
