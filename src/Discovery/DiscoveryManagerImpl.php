<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Discovery;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Puli\Discovery\Api\Binding\Binding;
use Puli\Discovery\Api\EditableDiscovery;
use Puli\Manager\Api\Context\ProjectContext;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Discovery\DiscoveryManager;
use Puli\Manager\Api\Discovery\DiscoveryNotEmptyException;
use Puli\Manager\Api\Discovery\DuplicateBindingException;
use Puli\Manager\Api\Discovery\DuplicateTypeException;
use Puli\Manager\Api\Discovery\NoSuchBindingException;
use Puli\Manager\Api\Discovery\NoSuchTypeException;
use Puli\Manager\Api\Discovery\TypeNotEnabledException;
use Puli\Manager\Api\Module\InstallInfo;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\RootModule;
use Puli\Manager\Api\Module\RootModuleFile;
use Puli\Manager\Api\NonRootModuleExpectedException;
use Puli\Manager\Assert\Assert;
use Puli\Manager\Discovery\Binding\AddBinding;
use Puli\Manager\Discovery\Binding\AddBindingDescriptorToModuleFile;
use Puli\Manager\Discovery\Binding\BindingDescriptorCollection;
use Puli\Manager\Discovery\Binding\DisableBindingUuid;
use Puli\Manager\Discovery\Binding\EnableBindingUuid;
use Puli\Manager\Discovery\Binding\LoadBindingDescriptor;
use Puli\Manager\Discovery\Binding\ReloadBindingDescriptorsByTypeName;
use Puli\Manager\Discovery\Binding\RemoveBindingDescriptorFromModuleFile;
use Puli\Manager\Discovery\Binding\SyncBinding;
use Puli\Manager\Discovery\Binding\UnloadBindingDescriptor;
use Puli\Manager\Discovery\Type\AddBindingType;
use Puli\Manager\Discovery\Type\AddTypeDescriptorToModuleFile;
use Puli\Manager\Discovery\Type\BindingTypeDescriptorCollection;
use Puli\Manager\Discovery\Type\LoadTypeDescriptor;
use Puli\Manager\Discovery\Type\RemoveTypeDescriptorFromModuleFile;
use Puli\Manager\Discovery\Type\SyncTypeName;
use Puli\Manager\Discovery\Type\UnloadTypeDescriptor;
use Puli\Manager\Discovery\Type\UpdateDuplicateMarksForTypeName;
use Puli\Manager\Json\JsonStorage;
use Puli\Manager\Transaction\InterceptedOperation;
use Puli\Manager\Transaction\Transaction;
use Rhumsaa\Uuid\Uuid;
use Webmozart\Expression\Expr;
use Webmozart\Expression\Expression;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DiscoveryManagerImpl implements DiscoveryManager
{
    /**
     * @var ProjectContext
     */
    private $context;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EditableDiscovery
     */
    private $discovery;

    /**
     * @var ModuleList|Module[]
     */
    private $modules;

    /**
     * @var JsonStorage
     */
    private $jsonStorage;

    /**
     * @var RootModule
     */
    private $rootModule;

    /**
     * @var RootModuleFile
     */
    private $rootModuleFile;

    /**
     * @var BindingTypeDescriptorCollection
     */
    private $typeDescriptors;

    /**
     * @var BindingDescriptorCollection
     */
    private $bindingDescriptors;

    /**
     * Creates a tag manager.
     *
     * @param ProjectContext       $context
     * @param EditableDiscovery    $discovery
     * @param ModuleList           $modules
     * @param JsonStorage          $jsonStorage
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        ProjectContext $context,
        EditableDiscovery $discovery,
        ModuleList $modules,
        JsonStorage $jsonStorage,
        LoggerInterface $logger = null
    ) {
        $this->context = $context;
        $this->discovery = $discovery;
        $this->modules = $modules;
        $this->jsonStorage = $jsonStorage;
        $this->rootModule = $modules->getRootModule();
        $this->rootModuleFile = $context->getRootModuleFile();
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function addRootTypeDescriptor(BindingTypeDescriptor $typeDescriptor, $flags = 0)
    {
        Assert::integer($flags, 'The argument $flags must be a boolean.');

        $this->assertModulesLoaded();
        $this->emitWarningForDuplicateTypes();

        $typeName = $typeDescriptor->getTypeName();

        if (!($flags & self::OVERRIDE) && $this->typeDescriptors->contains($typeName)) {
            throw DuplicateTypeException::forTypeName($typeName);
        }

        $tx = new Transaction();

        try {
            $syncBindingOps = array();

            foreach ($this->bindingDescriptors->listByTypeName($typeName) as $bindingDescriptor) {
                $syncBindingOp = $this->syncBinding($bindingDescriptor->getBinding());
                $syncBindingOp->takeSnapshot();
                $syncBindingOps[] = $syncBindingOp;
            }

            $syncOp = $this->syncTypeName($typeName);
            $syncOp->takeSnapshot();

            $tx->execute($this->loadTypeDescriptor($typeDescriptor, $this->rootModule));
            $tx->execute($this->addTypeDescriptorToModuleFile($typeDescriptor));
            $tx->execute($syncOp);

            foreach ($syncBindingOps as $syncBindingOp) {
                $tx->execute($syncBindingOp);
            }

            $this->saveRootModuleFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootTypeDescriptor($typeName)
    {
        // Only check that this is a string. The error message "not found" is
        // more helpful than e.g. "type name must contain /".
        Assert::string($typeName, 'The type name must be a string');

        $this->assertModulesLoaded();

        if (!$this->typeDescriptors->contains($typeName, $this->rootModule->getName())) {
            return;
        }

        $typeDescriptor = $this->typeDescriptors->get($typeName, $this->rootModule->getName());
        $tx = new Transaction();

        try {
            $tx->execute($this->removeTypeDescriptorFromModuleFile($typeName));

            $syncBindingOps = array();

            foreach ($this->bindingDescriptors->listByTypeName($typeName) as $bindingDescriptor) {
                $syncBindingOp = $this->syncBinding($bindingDescriptor->getBinding());
                $syncBindingOp->takeSnapshot();
                $syncBindingOps[] = $syncBindingOp;
            }

            $syncOp = $this->syncTypeName($typeName);
            $syncOp->takeSnapshot();

            $tx->execute($this->unloadTypeDescriptor($typeDescriptor));
            $tx->execute($syncOp);

            foreach ($syncBindingOps as $syncBindingOp) {
                $tx->execute($syncBindingOp);
            }

            $this->saveRootModuleFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }

        $this->emitWarningForDuplicateTypes();
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootTypeDescriptors(Expression $expr)
    {
        $this->assertModulesLoaded();

        $tx = new Transaction();
        $syncBindingOps = array();

        try {
            foreach ($this->getRootTypeDescriptors() as $typeDescriptor) {
                if ($expr->evaluate($typeDescriptor)) {
                    $typeName = $typeDescriptor->getTypeName();

                    $tx->execute($this->removeTypeDescriptorFromModuleFile($typeName));

                    foreach ($this->bindingDescriptors->listByTypeName($typeName) as $bindingDescriptor) {
                        $syncBindingOp = $this->syncBinding($bindingDescriptor->getBinding());
                        $syncBindingOp->takeSnapshot();
                        $syncBindingOps[] = $syncBindingOp;
                    }

                    $syncOp = $this->syncTypeName($typeName);
                    $syncOp->takeSnapshot();

                    $tx->execute($this->unloadTypeDescriptor($typeDescriptor));
                    $tx->execute($syncOp);
                }
            }

            foreach ($syncBindingOps as $syncBindingOp) {
                $tx->execute($syncBindingOp);
            }

            $this->saveRootModuleFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }

        $this->emitWarningForDuplicateTypes();
    }

    /**
     * {@inheritdoc}
     */
    public function clearRootTypeDescriptors()
    {
        $this->removeRootTypeDescriptors(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function getRootTypeDescriptor($typeName)
    {
        return $this->getTypeDescriptor($typeName, $this->rootModule->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function getRootTypeDescriptors()
    {
        $this->assertModulesLoaded();

        $types = array();
        $rootModuleName = $this->rootModule->getName();

        foreach ($this->typeDescriptors->toArray() as $typeName => $typesByModule) {
            if (isset($typesByModule[$rootModuleName])) {
                $types[] = $typesByModule[$rootModuleName];
            }
        }

        return $types;
    }

    /**
     * {@inheritdoc}
     */
    public function findRootTypeDescriptors(Expression $expr)
    {
        $expr = Expr::method('getContainingModule', Expr::same($this->rootModule))
            ->andX($expr);

        return $this->findTypeDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootTypeDescriptor($typeName)
    {
        return $this->hasTypeDescriptor($typeName, $this->rootModule->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootTypeDescriptors(Expression $expr = null)
    {
        $expr2 = Expr::method('getContainingModule', Expr::same($this->rootModule));

        if ($expr) {
            $expr2 = $expr2->andX($expr);
        }

        return $this->hasTypeDescriptors($expr2);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeDescriptor($typeName, $moduleName)
    {
        Assert::string($typeName, 'The type name must be a string. Got: %s');
        Assert::string($moduleName, 'The module name must be a string. Got: %s');

        $this->assertModulesLoaded();

        if (!$this->typeDescriptors->contains($typeName, $moduleName)) {
            throw NoSuchTypeException::forTypeName($typeName);
        }

        return $this->typeDescriptors->get($typeName, $moduleName);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeDescriptors()
    {
        $this->assertModulesLoaded();

        $types = array();

        foreach ($this->typeDescriptors->toArray() as $typeName => $typesByModule) {
            foreach ($typesByModule as $type) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * {@inheritdoc}
     */
    public function findTypeDescriptors(Expression $expr)
    {
        $this->assertModulesLoaded();

        $typeDescriptors = array();

        foreach ($this->typeDescriptors->toArray() as $typeName => $descriptorsByModule) {
            foreach ($descriptorsByModule as $typeDescriptor) {
                if ($expr->evaluate($typeDescriptor)) {
                    $typeDescriptors[] = $typeDescriptor;
                }
            }
        }

        return $typeDescriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeDescriptor($typeName, $moduleName = null)
    {
        Assert::nullOrString($moduleName, 'The module name must be a string or null. Got: %s');

        $this->assertModulesLoaded();

        return $this->typeDescriptors->contains($typeName, $moduleName);
    }

    /**
     * {@inheritdoc}
     */
    public function hasTypeDescriptors(Expression $expr = null)
    {
        $this->assertModulesLoaded();

        if (!$expr) {
            return !$this->typeDescriptors->isEmpty();
        }

        foreach ($this->typeDescriptors->toArray() as $typeName => $descriptorsByModule) {
            foreach ($descriptorsByModule as $typeDescriptor) {
                if ($expr->evaluate($typeDescriptor)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function addRootBindingDescriptor(BindingDescriptor $bindingDescriptor, $flags = 0)
    {
        $this->assertModulesLoaded();

        $typeName = $bindingDescriptor->getTypeName();
        $typeExists = $this->typeDescriptors->contains($typeName);

        if (!($flags & self::IGNORE_TYPE_NOT_FOUND) && !$typeExists) {
            throw NoSuchTypeException::forTypeName($typeName);
        }

        if (!($flags & self::IGNORE_TYPE_NOT_ENABLED) && $typeExists && !$this->typeDescriptors->getFirst($typeName)->isEnabled()) {
            throw TypeNotEnabledException::forTypeName($typeName);
        }

        $binding = $bindingDescriptor->getBinding();
        $exists = $this->bindingDescriptors->contains($binding);
        $existsInNonRoot = $exists
            ? !$this->bindingDescriptors->contains($binding, $this->rootModule->getName())
            : false;

        // We can only override bindings in the root module
        if ($existsInNonRoot || ($exists && !($flags & self::OVERRIDE))) {
            throw DuplicateBindingException::forBinding($binding);
        }

        $tx = new Transaction();

        try {
            $syncOp = $this->syncBinding($binding);
            $syncOp->takeSnapshot();

            $tx->execute($this->loadBindingDescriptor($bindingDescriptor, $this->rootModule));

            $this->assertBindingValid($bindingDescriptor);

            $tx->execute($this->addBindingDescriptorToModuleFile($bindingDescriptor));
            $tx->execute($syncOp);

            $this->saveRootModuleFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeRootBindingDescriptors(Expression $expr)
    {
        $this->assertModulesLoaded();

        $tx = new Transaction();

        try {
            foreach ($this->getRootBindingDescriptors() as $bindingDescriptor) {
                if ($expr->evaluate($bindingDescriptor)) {
                    $syncOp = $this->syncBinding($bindingDescriptor->getBinding());
                    $syncOp->takeSnapshot();

                    $tx->execute($this->unloadBindingDescriptor($bindingDescriptor));
                    $tx->execute($syncOp);
                    $tx->execute($this->removeBindingDescriptorFromModuleFile($bindingDescriptor->getUuid()));
                }
            }

            $this->saveRootModuleFile();

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearRootBindingDescriptors()
    {
        $this->removeRootBindingDescriptors(Expr::true());
    }

    /**
     * {@inheritdoc}
     */
    public function getRootBindingDescriptors()
    {
        $this->assertModulesLoaded();

        return $this->bindingDescriptors->listByModuleName($this->rootModule->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function findRootBindingDescriptors(Expression $expr)
    {
        $expr = Expr::method('getContainingModule', Expr::same($this->rootModule))
            ->andX($expr);

        return $this->findBindingDescriptors($expr);
    }

    /**
     * {@inheritdoc}
     */
    public function hasRootBindingDescriptors(Expression $expr = null)
    {
        $expr2 = Expr::method('getContainingModule', Expr::same($this->rootModule));

        if ($expr) {
            $expr2 = $expr2->andX($expr);
        }

        return $this->hasBindingDescriptors($expr2);
    }

    /**
     * {@inheritdoc}
     */
    public function getBindingDescriptors()
    {
        $this->assertModulesLoaded();

        return array_values($this->bindingDescriptors->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function findBindingDescriptors(Expression $expr)
    {
        $this->assertModulesLoaded();

        $descriptors = array();

        foreach ($this->bindingDescriptors->toArray() as $descriptor) {
            if ($expr->evaluate($descriptor)) {
                $descriptors[] = $descriptor;
            }
        }

        return $descriptors;
    }

    /**
     * {@inheritdoc}
     */
    public function hasBindingDescriptors(Expression $expr = null)
    {
        $this->assertModulesLoaded();

        if (!$expr) {
            return !$this->bindingDescriptors->isEmpty();
        }

        foreach ($this->bindingDescriptors->toArray() as $bindingDescriptor) {
            if ($expr->evaluate($bindingDescriptor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function buildDiscovery()
    {
        $this->assertModulesLoaded();
        $this->emitWarningForDuplicateTypes();
        $this->emitWarningForInvalidBindings();

        if ($this->discovery->hasBindings() || $this->discovery->hasBindingTypes()) {
            throw new DiscoveryNotEmptyException('The discovery is not empty.');
        }

        $tx = new Transaction();

        try {
            foreach ($this->typeDescriptors->toArray() as $typeName => $descriptorsByModule) {
                foreach ($descriptorsByModule as $typeDescriptor) {
                    if ($typeDescriptor->isEnabled()) {
                        $tx->execute($this->addBindingType($typeDescriptor));
                    }
                }
            }

            foreach ($this->bindingDescriptors->toArray() as $bindingDescriptors) {
                foreach ($bindingDescriptors as $bindingDescriptor) {
                    if ($bindingDescriptor->isEnabled()) {
                        $tx->execute($this->addBinding($bindingDescriptor));
                    }
                }
            }

            $tx->commit();
        } catch (Exception $e) {
            $tx->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clearDiscovery()
    {
        $this->discovery->removeBindingTypes();
    }

    private function assertModulesLoaded()
    {
        if (!$this->typeDescriptors) {
            $this->loadModules();
        }
    }

    private function assertBindingValid(BindingDescriptor $bindingDescriptor)
    {
        if ($bindingDescriptor->isTypeNotFound() || $bindingDescriptor->isTypeNotEnabled()) {
            return;
        }

        foreach ($bindingDescriptor->getLoadErrors() as $exception) {
            throw $exception;
        }
    }

    private function loadModules()
    {
        $this->typeDescriptors = new BindingTypeDescriptorCollection();
        $this->bindingDescriptors = new BindingDescriptorCollection();

        // First load all the types
        foreach ($this->modules as $module) {
            if (null === $module->getModuleFile()) {
                continue;
            }

            foreach ($module->getModuleFile()->getTypeDescriptors() as $typeDescriptor) {
                $this->loadTypeDescriptor($typeDescriptor, $module)->execute();
            }
        }

        // Then the bindings for the loaded types
        foreach ($this->modules as $module) {
            if (null === $module->getModuleFile()) {
                continue;
            }

            foreach ($module->getModuleFile()->getBindingDescriptors() as $bindingDescriptor) {
                $this->loadBindingDescriptor($bindingDescriptor, $module)->execute();
            }
        }
    }

    private function emitWarningForDuplicateTypes()
    {
        foreach ($this->typeDescriptors->getTypeNames() as $typeName) {
            $moduleNames = $this->typeDescriptors->getModuleNames($typeName);

            if (count($moduleNames) > 1) {
                $lastModuleName = array_pop($moduleNames);

                $this->logger->warning(sprintf(
                    'The modules "%s" and "%s" contain type definitions for '.
                    'the same type "%s". The type has been disabled.',
                    implode('", "', $moduleNames),
                    $lastModuleName,
                    $typeName
                ));
            }
        }
    }

    private function emitWarningForInvalidBindings()
    {
        foreach ($this->bindingDescriptors->toArray() as $bindingDescriptors) {
            foreach ($bindingDescriptors as $bindingDescriptor) {
                foreach ($bindingDescriptor->getLoadErrors() as $exception) {
                    $this->logger->warning(sprintf(
                        'The binding "%s" in module "%s" is invalid: %s',
                        $bindingDescriptor->getUuid()->toString(),
                        $bindingDescriptor->getContainingModule()->getName(),
                        $exception->getMessage()
                    ));
                }
            }
        }
    }

    private function saveRootModuleFile()
    {
        $this->jsonStorage->saveRootModuleFile($this->rootModuleFile);
    }

    private function addTypeDescriptorToModuleFile(BindingTypeDescriptor $typeDescriptor)
    {
        return new AddTypeDescriptorToModuleFile($typeDescriptor, $this->rootModuleFile);
    }

    private function removeTypeDescriptorFromModuleFile($typeName)
    {
        return new RemoveTypeDescriptorFromModuleFile($typeName, $this->rootModuleFile);
    }

    private function loadTypeDescriptor(BindingTypeDescriptor $typeDescriptor, Module $module)
    {
        $typeName = $typeDescriptor->getTypeName();

        return new InterceptedOperation(
            new LoadTypeDescriptor($typeDescriptor, $module, $this->typeDescriptors),
            array(
                new UpdateDuplicateMarksForTypeName($typeName, $this->typeDescriptors),
                new ReloadBindingDescriptorsByTypeName($typeName, $this->bindingDescriptors, $this->typeDescriptors),
            )
        );
    }

    private function unloadTypeDescriptor(BindingTypeDescriptor $typeDescriptor)
    {
        $typeName = $typeDescriptor->getTypeName();

        return new InterceptedOperation(
            new UnloadTypeDescriptor($typeDescriptor, $this->typeDescriptors),
            array(
                new UpdateDuplicateMarksForTypeName($typeName, $this->typeDescriptors),
                new ReloadBindingDescriptorsByTypeName($typeName, $this->bindingDescriptors, $this->typeDescriptors),
            )
        );
    }

    private function addBindingType(BindingTypeDescriptor $typeDescriptor)
    {
        return new AddBindingType($typeDescriptor, $this->discovery);
    }

    private function syncTypeName($typeName)
    {
        return new SyncTypeName($typeName, $this->discovery, $this->typeDescriptors);
    }

    private function addBindingDescriptorToModuleFile(BindingDescriptor $bindingDescriptor)
    {
        return new AddBindingDescriptorToModuleFile($bindingDescriptor, $this->rootModuleFile);
    }

    private function removeBindingDescriptorFromModuleFile(Uuid $uuid)
    {
        return new RemoveBindingDescriptorFromModuleFile($uuid, $this->rootModuleFile);
    }

    private function loadBindingDescriptor(BindingDescriptor $bindingDescriptor, Module $module)
    {
        return new LoadBindingDescriptor($bindingDescriptor, $module, $this->bindingDescriptors, $this->typeDescriptors);
    }

    private function unloadBindingDescriptor(BindingDescriptor $bindingDescriptor)
    {
        return new UnloadBindingDescriptor($bindingDescriptor, $this->bindingDescriptors);
    }

    private function addBinding(BindingDescriptor $bindingDescriptor)
    {
        return new AddBinding($bindingDescriptor, $this->discovery);
    }

    private function syncBinding(Binding $binding)
    {
        return new SyncBinding($binding, $this->discovery, $this->bindingDescriptors);
    }
}
