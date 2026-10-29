<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Model;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Image placeholder replacement — forwarded to {@see TemplateProcessor::setImageValue}.
 */
final readonly class ImageSource
{
    /**
     * @param string $path local image file (must stay under {@code allowed_roots} when configured)
     * @param int|null $width optional width forwarded to PHPWord
     * @param int|null $height optional height forwarded to PHPWord
     */
    public function __construct(
        public string $path,
        public ?int $width = null,
        public ?int $height = null,
    ) {
    }
}
