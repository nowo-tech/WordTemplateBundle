<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle;

use Nowo\WordTemplateBundle\DependencyInjection\WordTemplateExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle name {@code WordTemplateBundle} is wired to the extension alias {@code nowo_word_template}.
 */
final class WordTemplateBundle extends Bundle
{
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof WordTemplateExtension) {
            // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
            $this->extension = new WordTemplateExtension();
        }

        return $this->extension;
    }
}
