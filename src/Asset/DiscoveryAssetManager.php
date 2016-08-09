<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Asset;

use Puli\Manager\Api\Asset\AssetManager;
use Puli\Manager\Api\Asset\AssetMapping;
use Puli\Manager\Api\Asset\DuplicateAssetMappingException;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Event\AddAssetMappingEvent;
use Puli\Manager\Api\Event\PuliEvents;
use Puli\Manager\Api\Event\RemoveAssetMappingEvent;
use Puli\Manager\Api\Server\NoSuchServerException;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\Repository\Discovery\ResourceBinding;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * An asset manager that uses a {@link DiscoveryManager} as storage backend.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryAssetManager implements AssetManager
{
    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var ServerCollection
     */
    private $servers;

    /**
     * @var BindingExpressionBuilder
     */
    private $exprBuilder;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    public function __construct(DiscoveryManager $discoveryManager, ServerCollection $servers, EventDispatcherInterface $dispatcher = null)
    {
        $this->discoveryManager = $discoveryManager;
        $this->servers = $servers;
        $this->exprBuilder = new BindingExpressionBuilder();
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function addRootAssetMapping(AssetMapping $mapping, $flags = 0)
    {
        if (!$this->discoveryManager->hasTypeDescriptor(DiscoveryUrlGenerator::BINDING_TYPE)) {
            throw new RuntimeException(sprintf(
                'The binding type "%s" was not found. Please install the '.
                '"puli/url-generator" module with Composer:'."\n\n".
                '    $ composer require puli/url-generator',
                DiscoveryUrlGenerator::BINDING_TYPE
            ));
        }

        if (!($flags & self::IGNORE_SERVER_NOT_FOUND) && !$this->servers->contains($mapping->getServerName())) {
            throw NoSuchServerException::forServerName($mapping->getServerName());
        }

        $expr = Expr::method('getGlob', Expr::same($mapping->getGlob()))
            ->andMethod('getServerName', Expr::same($mapping->getServerName()))
            ->andMethod('getServerPath', Expr::same($mapping->getServerPath()));

        if (!($flags & self::OVERRIDE) && $this->hasAssetMappings($expr)) {
            throw DuplicateAssetMappingException::forAssetMapping($mapping);
        }

        $binding = new ResourceBinding(
            // Match directories as well as all of their contents
            $mapping->getGlob().'{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => $mapping->getServerName(),
                DiscoveryUrlGenerator::PATH_PARAMETER => $mapping->getServerPath(),
            ),
            'glob'
        );

        $this->discoveryManager->addRootBindingDescriptor(
            new BindingDescriptor($binding),
            ($flags & self::OVERRIDE) ? DiscoveryManager::OVERRIDE : 0
        );

        if ($this->dispatcher && $this->dispatcher->hasListeners(PuliEvents::POST_ADD_ASSET_MAPPING)) {
            $this->dispatcher->dispatch(
                PuliEvents::POST_ADD_ASSET_MAPPING,
                new AddAssetMappingEvent($mapping)
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootAssetMappings(Expression $expr)
    {
        $mappings = array();
        $hasListener = $this->dispatcher && $this->dispatcher->hasListeners(PuliEvents::POST_REMOVE_ASSET_MAPPING);

        if ($hasListener) {
            // Query the mappings for the event
            $mappings = $this->findRootAssetMappings($expr);
        }

        $this->discoveryManager->removeRootBindingDescriptors($this->exprBuilder->buildExpression($expr));

        if ($hasListener) {
            foreach ($mappings as $mapping) {
                $this->dispatcher->dispatch(
                    PuliEvents::POST_REMOVE_ASSET_MAPPING,
                    new RemoveAssetMappingEvent($mapping)
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearRootAssetMappings()
    {
        $mappings = array();
        $hasListener = $this->dispatcher && $this->dispatcher->hasListeners(PuliEvents::POST_REMOVE_ASSET_MAPPING);

        if ($hasListener) {
            // Query the mappings for the event
            $mappings = $this->getRootAssetMappings();
        }

        $this->discoveryManager->removeRootBindingDescriptors($this->exprBuilder->buildExpression());

        if ($hasListener) {
            foreach ($mappings as $mapping) {
                $this->dispatcher->dispatch(
                    PuliEvents::POST_REMOVE_ASSET_MAPPING,
                    new RemoveAssetMappingEvent($mapping)
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRootAssetMappings()
    {
        return $this->findRootAssetMappings(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootAssetMappings(Expression $expr = null)
    {
        return $this->discoveryManager->hasRootBindingDescriptors($this->exprBuilder->buildExpression($expr));
    }

    /**
     * {@inheritdoc}
     */
    public function findRootAssetMappings(Expression $expr)
    {
        $descriptors = $this->discoveryManager->findRootBindingDescriptors($this->exprBuilder->buildExpression($expr));
        $mappings = array();

        foreach ($descriptors as $descriptor) {
            $mappings[] = $this->bindingToMapping($descriptor->getBinding());
        }

        return $mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function getAssetMappings()
    {
        return $this->findAssetMappings(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function findAssetMappings(Expression $expr)
    {
        $descriptors = $this->discoveryManager->findBindingDescriptors($this->exprBuilder->buildExpression($expr));
        $mappings = array();

        foreach ($descriptors as $descriptor) {
            $mappings[] = $this->bindingToMapping($descriptor->getBinding());
        }

        return $mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAssetMappings(Expression $expr = null)
    {
        return $this->discoveryManager->hasBindingDescriptors($this->exprBuilder->buildExpression($expr));
    }

    private function bindingToMapping(ResourceBinding $binding)
    {
        return new AssetMapping(
            // Remove "{,/**/*}" suffix
            substr($binding->getQuery(), 0, -8),
            $binding->getParameterValue(DiscoveryUrlGenerator::SERVER_PARAMETER),
            $binding->getParameterValue(DiscoveryUrlGenerator::PATH_PARAMETER)
        );
    }
}
