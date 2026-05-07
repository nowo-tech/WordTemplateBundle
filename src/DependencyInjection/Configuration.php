<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Root key: {@see self::ALIAS} — PHPWord template macro delimiters.
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_word_template';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $root        = $treeBuilder->getRootNode();

        $root
            ->children()
                ->scalarNode('macro_opening')
                    ->defaultValue('${')
                    ->info('Opening delimiter for placeholders in the DOCX (PHPWord TemplateProcessor).')
                ->end()
                ->scalarNode('macro_closing')
                    ->defaultValue('}')
                    ->info('Closing delimiter for placeholders in the DOCX.')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
