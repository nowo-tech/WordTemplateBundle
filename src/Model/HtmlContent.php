<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Model;

/**
 * Rich fragment (lists, tables, bold, etc.) inserted via PHPWord {@see \PhpOffice\PhpWord\Shared\Html::addHtml}
 * into the DOCX block identified by the context key (placeholder {@code ${key}} without delimiters in PHP).
 */
final readonly class HtmlContent
{
    public function __construct(
        public string $html,
    ) {
    }
}
