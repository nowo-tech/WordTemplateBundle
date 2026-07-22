<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Model;

use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Repeating table row: {@see TemplateProcessor::cloneRow}.
 *
 * {@see $rowVariable} must match the first placeholder name in the row to duplicate (e.g. {@code ${lineId}} → {@code lineId}).
 * Each row is a map of placeholder base names (without {@code #n}) to cell values.
 *
 * @phpstan-type Row array<string, scalar|\Stringable|null>
 *
 * @phpstan-param non-empty-list<Row> $rows
 */
final readonly class TableRows
{
    /**
     * @param list<Row> $rows
     */
    public function __construct(
        public string $rowVariable,
        public array $rows,
    ) {
    }
}
