<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Result;

use RuntimeException;

use function sprintf;

/**
 * Resulting .docx path: use {@see self::readContents()} for bytes or copy the file to a stable location.
 */
final readonly class ProcessedDocument
{
    public function __construct(
        private string $path,
        private bool $isTemporary,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isTemporary(): bool
    {
        return $this->isTemporary;
    }

    public function readContents(): string
    {
        $c = @file_get_contents($this->path);
        if ($c === false) {
            throw new RuntimeException(sprintf('Could not read processed document at "%s".', $this->path));
        }

        return $c;
    }

    /**
     * Removes the temporary output file when {@see self::isTemporary()} is true.
     */
    public function dispose(): void
    {
        if (!$this->isTemporary) {
            return;
        }

        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
