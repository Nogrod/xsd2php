<?php
namespace GoetasWebservices\Xsd\XsdToPhp\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('xsd2php');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('naming_strategy')
                    ->defaultValue('short')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('path_generator')
                    ->defaultValue('psr4')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('namespaces')
                    ->cannotBeEmpty()->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->arrayNode('known_locations')
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->arrayNode('known_namespace_locations')
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->arrayNode('configs_jms')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('xml_cdata')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('destinations_php')
                    ->cannotBeEmpty()->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->arrayNode('destinations_jms')
                    ->cannotBeEmpty()->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->arrayNode('destinations_validation')
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->arrayNode('aliases')
                    ->prototype('array')
                        ->prototype('scalar')
                        ->end()
                    ->end()
                ->end()
            ->end();
        return $treeBuilder;
    }
}
