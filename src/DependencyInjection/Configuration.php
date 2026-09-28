<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

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
                ->integerNode('timeout')
                    ->defaultValue(180)
                    ->min(1)
                    ->info('Wall-clock timeout in seconds for WordTemplateProcessor::process() (cooperative deadline + set_time_limit). Shared Nowo default: PROCESS_TIMEOUT=180. Keep below PHP max_execution_time / FrankenPHP write timeout.')
                ->end()
                ->arrayNode('allowed_roots')
                    ->info('Absolute directories that templates, images, and output paths must stay under. Empty = unrestricted (BC). Prefer setting this in production.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->booleanNode('path_allowlist_required')
                    ->defaultFalse()
                    ->info('When true, allowed_roots must be non-empty (fail-closed for production).')
                ->end()
                ->scalarNode('macro_opening')
                    ->defaultValue('${')
                    ->info('Opening delimiter for placeholders in the DOCX (PHPWord TemplateProcessor).')
                ->end()
                ->scalarNode('macro_closing')
                    ->defaultValue('}')
                    ->info('Closing delimiter for placeholders in the DOCX.')
                ->end()
                ->scalarNode('conditional_if_opening')
                    ->defaultValue('${#if')
                    ->info('Opening delimiter for conditional blocks (before the block name).')
                ->end()
                ->scalarNode('conditional_if_closing')
                    ->defaultValue('}')
                    ->info('Closing delimiter for conditional opening markers.')
                ->end()
                ->scalarNode('conditional_endif_opening')
                    ->defaultValue('${#endif')
                    ->info('Opening delimiter for conditional end markers (before the block name).')
                ->end()
                ->scalarNode('conditional_endif_closing')
                    ->defaultValue('}')
                    ->info('Closing delimiter for conditional end markers.')
                ->end()
            ->end()
            ->validate()
            ->always(static function (array $v): array {
                if (($v['path_allowlist_required'] ?? false) === true && ($v['allowed_roots'] ?? []) === []) {
                    throw new InvalidConfigurationException(
                        'nowo_word_template.path_allowlist_required is true but allowed_roots is empty.',
                    );
                }

                return $v;
            })
            ->end();

        return $treeBuilder;
    }
}
