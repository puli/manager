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
use Puli\Manager\Api\Asset\NoSuchAssetMappingException;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Event\AddAssetMappingEvent;
use Puli\Manager\Api\Event\PuliEvents;
use Puli\Manager\Api\Event\RemoveAssetMappingEvent;
use Puli\Manager\Api\Server\NoSuchServerException;
use Puli\Manager\Api\Server\ServerCollection;
use Puli\UrlGenerator\DiscoveryUrlGenerator;
use Rhumsaa\Uuid\Uuid;
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
        if (!($flags & self::IGNORE_SERVER_NOT_FOUND) && !$this->servers->contains($mapping->getServerName())) {
            throw NoSuchServerException::forServerName($mapping->getServerName());
        }

        if (!($flags & self::OVERRIDE) && $this->hasAssetMapping($mapping->getUuid())) {
            throw DuplicateAssetMappingException::forUuid($mapping->getUuid());
        }

        $this->discoveryManager->addRootBinding(new BindingDescriptor(
            // Match directories as well as all of their contents
            $mapping->getGlob().'{,/**/*}',
            DiscoveryUrlGenerator::BINDING_TYPE,
            array(
                DiscoveryUrlGenerator::SERVER_PARAMETER => $mapping->getServerName(),
                DiscoveryUrlGenerator::PATH_PARAMETER => $mapping->getServerPath(),
            ),
            'glob',
            $mapping->getUuid()
        ), ($flags & self::OVERRIDE) ? DiscoveryManager::OVERRIDE : 0);

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
    public function removeRootAssetMapping(Uuid $uuid)
    {
        $mapping = null;
        $hasListener = $this->dispatcher && $this->dispatcher->hasListeners(PuliEvents::POST_REMOVE_ASSET_MAPPING);
        $expr = Expr::same($uuid->toString(), BindingDescriptor::UUID)
            ->andX($this->exprBuilder->buildExpression());

        if ($hasListener) {
            // Query the mapping for the event
            try {
                $mapping = $this->getRootAssetMapping($uuid);
            } catch (NoSuchAssetMappingException $e) {
                return;
            }
        }

        $this->discoveryManager->removeRootBindings($expr);

        if ($hasListener) {
            $this->dispatcher->dispatch(
                PuliEvents::POST_REMOVE_ASSET_MAPPING,
                new RemoveAssetMappingEvent($mapping)
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

        $this->discoveryManager->removeRootBindings($this->exprBuilder->buildExpression($expr));

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

        $this->discoveryManager->removeRootBindings($this->exprBuilder->buildExpression());

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
    public function getRootAssetMapping(Uuid $uuid)
    {
        $mappings = $this->findRootAssetMappings(Expr::same($uuid->toString(), AssetMapping::UUID));

        if (!$mappings) {
            throw NoSuchAssetMappingException::forUuid($uuid);
        }

        return reset($mappings);
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
    public function hasRootAssetMapping(Uuid $uuid)
    {
        $expr = Expr::same($uuid->toString(), BindingDescriptor::UUID)
            ->andX($this->exprBuilder->buildExpression());

        return $this->discoveryManager->hasRootBindings($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootAssetMappings(Expression $expr = null)
    {
        return $this->discoveryManager->hasRootBindings($this->exprBuilder->buildExpression($expr));
    }

    /**
     * {@inheritdoc}
     */
    public function findRootAssetMappings(Expression $expr)
    {
        $bindings = $this->discoveryManager->findRootBindings($this->exprBuilder->buildExpression($expr));
        $mappings = array();

        foreach ($bindings as $binding) {
            $mappings[] = $this->bindingToMapping($binding);
        }

        return $mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function getAssetMapping(Uuid $uuid)
    {
        $mappings = $this->findAssetMappings(Expr::same($uuid->toString(), AssetMapping::UUID));

        if (!$mappings) {
            throw NoSuchAssetMappingException::forUuid($uuid);
        }

        return reset($mappings);
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
        $bindings = $this->discoveryManager->findBindings($this->exprBuilder->buildExpression($expr));
        $mappings = array();

        foreach ($bindings as $binding) {
            $mappings[] = $this->bindingToMapping($binding);
        }

        return $mappings;
    }

    /**
     * {@inheritdoc}
     */
    public function hasAssetMapping(Uuid $uuid)
    {
        $expr = Expr::same($uuid->toString(), BindingDescriptor::UUID)
            ->andX($this->exprBuilder->buildExpression());

        return $this->discoveryManager->hasBindings($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasAssetMappings(Expression $expr = null)
    {
        return $this->discoveryManager->hasBindings($this->exprBuilder->buildExpression($expr));
    }

    private function bindingToMapping(BindingDescriptor $binding)
    {
        return new AssetMapping(
            // Remove "{,/**/*}" suffix
            substr($binding->getQuery(), 0, -8),
            $binding->getParameterValue(DiscoveryUrlGenerator::SERVER_PARAMETER),
            $binding->getParameterValue(DiscoveryUrlGenerator::PATH_PARAMETER),
            $binding->getUuid()
        );
    }
}
