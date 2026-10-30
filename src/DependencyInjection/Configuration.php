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

    /**
     * Builds the {@code nowo_word_template} configuration tree (timeout, path allowlist, macro delimiters).
     *
     * @return TreeBuilder tree validated so that {@code path_allowlist_required: true} needs non-empty {@code allowed_roots}
     */
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
                ->integerNode('html_image_max_bytes')
                    ->defaultValue(5_242_880)
                    ->min(1)
                    ->info('Maximum decoded size (bytes) of one data: image inside HtmlContent. Larger images, remote URLs and local paths outside allowed_roots are dropped from the HTML.')
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
                ->scalarNode('loop_for_opening')
                    ->defaultValue('${#for')
                    ->info('Opening delimiter for loop markers (before "item in items").')
                ->end()
                ->scalarNode('loop_for_closing')
                    ->defaultValue('}')
                    ->info('Closing delimiter for loop markers.')
                ->end()
                ->scalarNode('loop_endfor_opening')
                    ->defaultValue('${#endfor')
                    ->info('Opening delimiter for loop end markers.')
                ->end()
                ->scalarNode('loop_endfor_closing')
                    ->defaultValue('}')
                    ->info('Closing delimiter for loop end markers.')
                ->end()
                ->arrayNode('loops')
                    ->info('Loops: ${#for item in items} … ${#endfor} repeat paragraphs (or table rows) per element; the iterable is an expression (same language, functions and on_error as conditional_expressions). Opt-in.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('When false (default) loop markers are left untouched.')
                        ->end()
                        ->integerNode('max_iterations')
                            ->defaultValue(1000)
                            ->min(1)
                            ->info('Maximum number of elements one loop may iterate over.')
                        ->end()
                        ->integerNode('max_total_iterations')
                            ->defaultValue(10000)
                            ->min(1)
                            ->info('Maximum number of iterations of all loops of one merge together (nested loops multiply).')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('conditional_expressions')
                    ->info('Expression conditionals: ${#if total > 0} … ${#endif} (nameless end marker), evaluated with a function-less Symfony ExpressionLanguage against the context. Opt-in.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('When false (default) expression markers are left untouched, as in 1.7.')
                        ->end()
                        ->enumNode('on_error')
                            ->values(['exception', 'hide', 'keep'])
                            ->defaultValue('exception')
                            ->info('Invalid expression / unknown variable / bad marker layout: exception = throw ConditionalExpressionException; hide = remove the block; keep = leave the markers as text (hide and keep log a warning).')
                        ->end()
                        ->integerNode('max_length')
                            ->defaultValue(500)
                            ->min(1)
                            ->max(10000)
                            ->info('Maximum length of one expression in characters.')
                        ->end()
                        ->scalarNode('timezone')
                            ->defaultNull()
                            ->info('Time zone of now() / date() (e.g. "Europe/Madrid"); null = PHP default time zone. "Now" comes from the Symfony clock service when available.')
                        ->end()
                        ->arrayNode('functions')
                            ->info('Restrict the expression functions (built-ins and host providers tagged nowo_word_template.expression_function_provider).')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('allow')
                                    ->info('Only these functions are available; null (default) = all.')
                                    ->scalarPrototype()->end()
                                    ->defaultNull()
                                ->end()
                                ->arrayNode('deny')
                                    ->info('Functions that are never available (e.g. [now, date]).')
                                    ->scalarPrototype()->end()
                                    ->defaultValue([])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
            ->always(static function (array $v): array {
                if (($v['path_allowlist_required'] ?? false) === true && ($v['allowed_roots'] ?? []) === []) {
                    throw new InvalidConfigurationException('nowo_word_template.path_allowlist_required is true but allowed_roots is empty.');
                }

                return $v;
            })
            ->end();

        return $treeBuilder;
    }
}
