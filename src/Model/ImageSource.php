<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Model;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Image placeholder replacement — forwarded to {@see TemplateProcessor::setImageValue}.
 */
final readonly class ImageSource
{
    public function __construct(
        public string $path,
        public ?int $width = null,
        public ?int $height = null,
    ) {
    }
}
