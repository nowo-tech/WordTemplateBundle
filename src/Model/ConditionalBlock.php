<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\Model;

/**
 * Twig-style conditional region in a DOCX template.
 *
 * Authors wrap content between opening {@code ${#if blockName}} and closing {@code ${#endif blockName}}
 * markers (delimiters are configured under {@code nowo_word_template.conditional_*}; defaults shown).
 * When {@see $visible} is true the inner content is kept and the markers are removed; otherwise the
 * whole region (markers + content) is removed from the document. Nested blocks are resolved inside-out.
 */
final readonly class ConditionalBlock
{
    /**
     * @param string $blockName block name used in the opening / closing markers
     * @param bool $visible true keeps the inner content, false removes the whole region
     */
    public function __construct(
        public string $blockName,
        public bool $visible,
    ) {
    }
}
