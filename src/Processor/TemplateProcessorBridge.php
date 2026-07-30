<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Processor;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * @internal exposes document XML parts for bundle-only transforms
 */
final class TemplateProcessorBridge extends TemplateProcessor
{
    /**
     * @param callable(string): string $transform
     */
    public function transformDocumentParts(callable $transform): void
    {
        $this->tempDocumentMainPart = $transform($this->tempDocumentMainPart);

        foreach ($this->tempDocumentHeaders as $index => $header) {
            $this->tempDocumentHeaders[$index] = $transform($header);
        }

        foreach ($this->tempDocumentFooters as $index => $footer) {
            $this->tempDocumentFooters[$index] = $transform($footer);
        }
    }

    /**
     * @return list<string>
     */
    public function documentPartXmls(): array
    {
        $parts = [$this->tempDocumentMainPart];

        foreach ($this->tempDocumentHeaders as $header) {
            $parts[] = $header;
        }

        foreach ($this->tempDocumentFooters as $footer) {
            $parts[] = $footer;
        }

        return $parts;
    }
}
