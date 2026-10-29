<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Model;

use PhpOffice\PhpWord\Shared\Html;

/**
 * Rich fragment (lists, tables, bold, etc.) inserted via PHPWord {@see Html::addHtml}
 * into the DOCX block identified by the context key (placeholder {@code ${key}} without delimiters in PHP).
 */
final readonly class HtmlContent
{
    /**
     * @param string $html HTML fragment passed to PHPWord after image sanitizing
     */
    public function __construct(
        public string $html,
    ) {
    }
}
