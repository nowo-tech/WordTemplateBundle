<?php

declare(strict_types=1);

namespace Nowo\WordTemplateBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class WordTemplateExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter(Configuration::ALIAS . '.timeout', $config['timeout']);
        $container->setParameter(Configuration::ALIAS . '.macro_opening', $config['macro_opening']);
        $container->setParameter(Configuration::ALIAS . '.macro_closing', $config['macro_closing']);
        $container->setParameter(Configuration::ALIAS . '.conditional_if_opening', $config['conditional_if_opening']);
        $container->setParameter(Configuration::ALIAS . '.conditional_if_closing', $config['conditional_if_closing']);
        $container->setParameter(Configuration::ALIAS . '.conditional_endif_opening', $config['conditional_endif_opening']);
        $container->setParameter(Configuration::ALIAS . '.conditional_endif_closing', $config['conditional_endif_closing']);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
