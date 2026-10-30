<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\DependencyInjection;

use Nowo\WordTemplateBundle\Expression\ExpressionFunctionProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Loads the bundle services and exposes the processed {@code nowo_word_template} configuration as container parameters.
 */
final class WordTemplateExtension extends Extension
{
    /**
     * Loads {@code services.yaml} and sets one {@code nowo_word_template.*} parameter per configuration key.
     *
     * @param array<array<string, mixed>> $configs raw configuration arrays from the host application
     * @param ContainerBuilder $container container being compiled
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter(Configuration::ALIAS . '.timeout', $config['timeout']);
        $container->setParameter(Configuration::ALIAS . '.allowed_roots', $config['allowed_roots']);
        $container->setParameter(Configuration::ALIAS . '.html_image_max_bytes', $config['html_image_max_bytes']);
        $container->setParameter(Configuration::ALIAS . '.path_allowlist_required', $config['path_allowlist_required']);
        $container->setParameter(Configuration::ALIAS . '.macro_opening', $config['macro_opening']);
        $container->setParameter(Configuration::ALIAS . '.macro_closing', $config['macro_closing']);
        $container->setParameter(Configuration::ALIAS . '.conditional_if_opening', $config['conditional_if_opening']);
        $container->setParameter(Configuration::ALIAS . '.conditional_if_closing', $config['conditional_if_closing']);
        $container->setParameter(Configuration::ALIAS . '.conditional_endif_opening', $config['conditional_endif_opening']);
        $container->setParameter(Configuration::ALIAS . '.conditional_endif_closing', $config['conditional_endif_closing']);
        $container->setParameter(Configuration::ALIAS . '.loop_for_opening', $config['loop_for_opening']);
        $container->setParameter(Configuration::ALIAS . '.loop_for_closing', $config['loop_for_closing']);
        $container->setParameter(Configuration::ALIAS . '.loop_endfor_opening', $config['loop_endfor_opening']);
        $container->setParameter(Configuration::ALIAS . '.loop_endfor_closing', $config['loop_endfor_closing']);
        $container->setParameter(Configuration::ALIAS . '.loops.enabled', $config['loops']['enabled']);
        $container->setParameter(Configuration::ALIAS . '.loops.max_iterations', $config['loops']['max_iterations']);
        $container->setParameter(Configuration::ALIAS . '.loops.max_total_iterations', $config['loops']['max_total_iterations']);
        $container->setParameter(Configuration::ALIAS . '.loops.max_output_bytes', $config['loops']['max_output_bytes']);
        $container->setParameter(Configuration::ALIAS . '.conditional_expressions.max_markers', $config['conditional_expressions']['max_markers']);
        $container->setParameter(Configuration::ALIAS . '.conditional_expressions.max_depth', $config['conditional_expressions']['max_depth']);
        $container->setParameter(Configuration::ALIAS . '.conditional_expressions.max_evaluations', $config['conditional_expressions']['max_evaluations']);
        $container->setParameter(Configuration::ALIAS . '.conditional_expressions.enabled', $config['conditional_expressions']['enabled']);
        $container->setParameter(Configuration::ALIAS . '.conditional_expressions.on_error', $config['conditional_expressions']['on_error']);
        $container->setParameter(Configuration::ALIAS . '.conditional_expressions.max_length', $config['conditional_expressions']['max_length']);
        $container->setParameter(Configuration::ALIAS . '.conditional_expressions.timezone', $config['conditional_expressions']['timezone']);
        $container->setParameter(Configuration::ALIAS . '.conditional_expressions.functions.allow', $config['conditional_expressions']['functions']['allow']);
        $container->setParameter(Configuration::ALIAS . '.conditional_expressions.functions.deny', $config['conditional_expressions']['functions']['deny']);

        $container->registerForAutoconfiguration(ExpressionFunctionProviderInterface::class)
            ->addTag(ExpressionFunctionProviderInterface::TAG);
    }

    /**
     * Returns the configuration root key.
     *
     * @return string always {@see Configuration::ALIAS} ({@code nowo_word_template})
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
