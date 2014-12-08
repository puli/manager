<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tag;

use Puli\Repository\Util\Selector;
use Puli\RepositoryManager\Environment\ProjectEnvironment;
use Puli\RepositoryManager\Package\Collection\PackageCollection;
use Puli\RepositoryManager\Package\NoSuchPackageException;
use Puli\RepositoryManager\Package\PackageFile\PackageFileStorage;

/**
 * Manages the resource tags in the root package file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TagManager
{
    /**
     * @var ProjectEnvironment
     */
    private $environment;

    /**
     * @var PackageCollection
     */
    private $packages;

    /**
     * @var PackageFileStorage
     */
    private $packageFileStorage;

    /**
     * @var TagDefinition[]
     */
    private $tagDefinitions = array();

    /**
     * Creates a tag manager.
     *
     * @param ProjectEnvironment $environment
     * @param PackageCollection  $packages
     * @param PackageFileStorage $packageFileStorage
     */
    public function __construct(
        ProjectEnvironment $environment,
        PackageCollection $packages,
        PackageFileStorage $packageFileStorage
    )
    {
        $this->environment = $environment;
        $this->packages = $packages;
        $this->packageFileStorage = $packageFileStorage;

        $this->loadTagDefinitions();
    }

    /**
     * Returns whether a tag is defined.
     *
     * @param string $tag The tag name.
     *
     * @return bool Whether the tag is defined.
     */
    public function isTagDefined($tag)
    {
        return isset($this->tagDefinitions[$tag]);
    }

    /**
     * Returns a tag definition.
     *
     * @param string $tag The tag name.
     *
     * @return TagDefinition The tag definition.
     *
     * @throws UndefinedTagException If the tag has not been defined.
     */
    public function getTagDefinition($tag)
    {
        if (!isset($this->tagDefinitions[$tag])) {
            throw new UndefinedTagException(sprintf(
                'Could not get tag definition: The tag "%s" does not exist.',
                $tag
            ));
        }

        return $this->tagDefinitions[$tag];
    }

    /**
     * Finds tag definitions matching a tag selector.
     *
     * @param string               $tagSelector The tag selector. May contain
     *                                          the wildcard "*".
     * @param null|string|string[] $packageName One or more package names to
     *                                          filter for.
     *
     * @return TagDefinition[] The matching tag definitions.
     *
     * @throws NoSuchPackageException If a package name is invalid.
     */
    public function findTagDefinitions($tagSelector, $packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $tagRegex = Selector::toRegEx($tagSelector);
        $tagDefinitions = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getTagDefinitions() as $tagDefinition) {
                if (preg_match($tagRegex, $tagDefinition->getTag())) {
                    $tagDefinitions[] = $tagDefinition;
                }
            }
        }

        return $tagDefinitions;
    }

    /**
     * Returns all tag definitions.
     *
     * @param null|string|string[] $packageName One or more package names to
     *                                          filter for.
     *
     * @return TagDefinition[] The tag definitions.
     *
     * @throws NoSuchPackageException If a package name is invalid.
     */
    public function getTagDefinitions($packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $tagDefinitions = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();

            foreach ($packageFile->getTagDefinitions() as $tagDefinition) {
                $tagDefinitions[] = $tagDefinition;
            }
        }

        return $tagDefinitions;
    }

    /**
     * Adds a tag definition to the root package.
     *
     * The root package file is saved immediately after adding the definition.
     *
     * @param TagDefinition $tagDefinition The tag definition to add.
     *
     * @throws DuplicateTagException If the tag is already defined.
     */
    public function addRootTagDefinition(TagDefinition $tagDefinition)
    {
        if (isset($this->tagDefinitions[$tagDefinition->getTag()])) {
            throw new DuplicateTagException(sprintf(
                'Could not add tag definition: The tag "%s" is already defined.',
                $tagDefinition->getTag()
            ));
        }

        $rootPackageFile = $this->environment->getRootPackageFile();

        $rootPackageFile->addTagDefinition($tagDefinition);
        $this->tagDefinitions[$tagDefinition->getTag()] = $tagDefinition;

        $this->packageFileStorage->saveRootPackageFile($rootPackageFile);
    }

    /**
     * Removes a tag definition from the root package.
     *
     * The root package file is saved immediately after removing the definition.
     *
     * If the root package does not contain a definition for the tag, this
     * method does nothing.
     *
     * @param string $tag The tag name.
     */
    public function removeRootTagDefinition($tag)
    {
        if (!isset($this->tagDefinitions[$tag])) {
            return;
        }

        $rootPackageFile = $this->environment->getRootPackageFile();

        $rootPackageFile->removeTagDefinition($tag);
        unset($this->tagDefinitions[$tag]);

        $this->packageFileStorage->saveRootPackageFile($rootPackageFile);
    }

    /**
     * Returns whether the root package contains a definition for a tag.
     *
     * @param string $tag The tag name.
     *
     * @return bool Whether the tag is defined in the root package.
     */
    public function hasRootTagDefinition($tag)
    {
        $rootPackageFile = $this->environment->getRootPackageFile();

        return $rootPackageFile->hasTagDefinition($tag);
    }

    /**
     * Removes all tag definitions from the root package.
     *
     * The root package file is saved immediately after removing the definitions.
     *
     * If the root package contains no tag definitions, this method does nothing.
     */
    public function clearRootTagDefinitions()
    {
        $rootPackageFile = $this->environment->getRootPackageFile();
        $tagDefinitions = $rootPackageFile->getTagDefinitions();

        if (0 === count($tagDefinitions)) {
            return;
        }

        foreach ($tagDefinitions as $tagDefinition) {
            unset($this->tagDefinitions[$tagDefinition->getTag()]);
        }

        $rootPackageFile->clearTagDefinitions();

        $this->packageFileStorage->saveRootPackageFile($rootPackageFile);
    }

    /**
     * Returns all tag definitions in the root package that match a selector.
     *
     * @param string $tagSelector The tag selector. May contain the wildcard "*".
     *
     * @return TagDefinition[] The matching tag definitions.
     */
    public function findRootTagDefinitions($tagSelector)
    {
        return $this->findTagDefinitions($tagSelector, $this->packages->getRootPackage()->getName());
    }

    /**
     * Returns a tag definition from the root package.
     *
     * @param string $tag The tag name.
     *
     * @return TagDefinition The tag definition.
     *
     * @throws UndefinedTagException If the tag has not been defined.
     */
    public function getRootTagDefinition($tag)
    {
        return $this->getTagDefinition($tag, $this->packages->getRootPackage()->getName());
    }

    /**
     * Returns all tag definitions from the root package.
     *
     * @return TagDefinition[] The tag definitions.
     */
    public function getRootTagDefinitions()
    {
        return $this->getTagDefinitions($this->packages->getRootPackage()->getName());
    }

    /**
     * Adds a tag mapping to the root package.
     *
     * The root package file is saved immediately after adding the mapping.
     *
     * @param TagMapping $tagMapping The tag mapping to add.
     *
     * @throws UndefinedTagException If the tag has not been defined.
     */
    public function addRootTagMapping(TagMapping $tagMapping)
    {
        if (!isset($this->tagDefinitions[$tagMapping->getTag()])) {
            throw new UndefinedTagException(sprintf(
                'Could not add root tag mapping: The tag "%s" has not been defined.',
                $tagMapping->getTag()
            ));
        }

        $rootPackageFile = $this->environment->getRootPackageFile();

        if ($rootPackageFile->hasTagMapping($tagMapping)) {
            return;
        }

        $rootPackageFile->addTagMapping($tagMapping);

        $this->packageFileStorage->saveRootPackageFile($rootPackageFile);
    }

    /**
     * Removes a tag mapping from the root package.
     *
     * The root package file is saved immediately after removing the mapping.
     *
     * If the mapping does not exist, this method does nothing.
     *
     * @param TagMapping $tagMapping The tag mapping to remove.
     */
    public function removeRootTagMapping(TagMapping $tagMapping)
    {
        $rootPackageFile = $this->environment->getRootPackageFile();

        if (!$rootPackageFile->hasTagMapping($tagMapping)) {
            return;
        }

        $rootPackageFile->removeTagMapping($tagMapping);

        $this->packageFileStorage->saveRootPackageFile($rootPackageFile);
    }

    /**
     * Removes all tag mappings from the root package whose tags have not been
     * defined.
     *
     * The root package file is saved immediately after removing the mappings.
     *
     * If no undefined tags are found, this method does nothing.
     */
    public function removeUndefinedRootTagMappings()
    {
        $rootPackageFile = $this->environment->getRootPackageFile();
        $save = false;

        foreach ($rootPackageFile->getTagMappings() as $tagMapping) {
            if (!isset($this->tagDefinitions[$tagMapping->getTag()])) {
                $rootPackageFile->removeTagMapping($tagMapping);
                $save = true;
            }
        }

        if ($save) {
            $this->packageFileStorage->saveRootPackageFile($rootPackageFile);
        }
    }

    /**
     * Removes all tag mappings from the root package.
     *
     * The root package file is saved immediately after removing the mappings.
     *
     * If the root package contains no tag mappings, this method does nothing.
     */
    public function clearRootTagMappings()
    {
        $rootPackageFile = $this->environment->getRootPackageFile();

        if (0 === count($rootPackageFile->getTagMappings())) {
            return;
        }

        $rootPackageFile->clearTagMappings();

        $this->packageFileStorage->saveRootPackageFile($rootPackageFile);
    }

    /**
     * Returns all tag mapping from the root package for a given tag.
     *
     * @param string $tag The tag name.
     *
     * @return TagMapping[] The tag mappings.
     *
     * @throws UndefinedTagException If the tag has not been defined.
     */
    public function getRootTagMappings($tag = null)
    {
        if (null !== $tag && !isset($this->tagDefinitions[$tag])) {
            throw new UndefinedTagException(sprintf(
                'Could not get root tag mappings: The tag "%s" does not exist.',
                $tag
            ));
        }

        $tagMappings = array();
        $rootPackageFile = $this->environment->getRootPackageFile();

        $this->extractDefinedTagMappings($rootPackageFile->getTagMappings(), $tagMappings, $tag);

        return $tagMappings;
    }

    /**
     * Returns all tag mappings from the root package matching a path selector
     * and/or a tag selector.
     *
     * @param null|string $pathSelector The selector for the path of the mapping.
     *                                  May contain the wildcard "*".
     * @param null|string $tagSelector  The selector for the tag of the mapping.
     *                                  May contain the wildcard "*".
     *
     * @return TagMapping[] The matching tag mappings.
     */
    public function findRootTagMappings($pathSelector = null, $tagSelector = null)
    {
        $selectorRegex = $pathSelector ? Selector::toRegEx($pathSelector) : null;
        $tagRegex = $tagSelector ? Selector::toRegEx($tagSelector) : null;
        $tagMappings = array();
        $rootPackageFile = $this->environment->getRootPackageFile();

        foreach ($rootPackageFile->getTagMappings() as $tagMapping) {
            $isDefined = isset($this->tagDefinitions[$tagMapping->getTag()]);
            $selectorIsMatch = null === $selectorRegex || preg_match($selectorRegex, $tagMapping->getPuliSelector());
            $tagIsMatch = null === $tagRegex || preg_match($tagRegex, $tagMapping->getTag());

            // Undefined tags are ignored
            if ($isDefined && $selectorIsMatch && $tagIsMatch) {
                $tagMappings[] = $tagMapping;
            }
        }

        return $tagMappings;
    }

    /**
     * Enables a tag mapping from an installed package.
     *
     * The root package file is saved immediately after enabling the mapping.
     *
     * If the tag mapping is already enabled, this method does nothing.
     *
     * @param string     $packageName The name of the package.
     * @param TagMapping $tagMapping  The tag mapping to enable.
     *
     * @throws NoSuchPackageException If the package does not exist.
     * @throws NoSuchTagMappingException If the tag mapping was not found in
     *                                   the package.
     */
    public function enablePackageTagMapping($packageName, TagMapping $tagMapping)
    {
        $rootPackageFile = $this->environment->getRootPackageFile();
        $packageFile = $this->packages->get($packageName)->getPackageFile();
        $installInfo = $rootPackageFile->getInstallInfo($packageName);

        if (!$packageFile->hasTagMapping($tagMapping)) {
            throw new NoSuchTagMappingException(sprintf(
                'Cannot enable tag mapping: The mapping from "%s" to "%s" '.
                'could not be found in %s.',
                $tagMapping->getPuliSelector(),
                $tagMapping->getTag(),
                $packageFile->getPath()
            ));
        }

        if ($installInfo->hasEnabledTagMapping($tagMapping)) {
            return;
        }

        $installInfo->addEnabledTagMapping($tagMapping);

        $this->packageFileStorage->saveRootPackageFile($rootPackageFile);
    }

    /**
     * Disables a tag mapping from an installed package.
     *
     * The root package file is saved immediately after disabling the mapping.
     *
     * If the tag mapping is already disabled, this method does nothing.
     *
     * @param string     $packageName The name of the package.
     * @param TagMapping $tagMapping  The tag mapping to disable.
     *
     * @throws NoSuchPackageException If the package does not exist.
     * @throws NoSuchTagMappingException If the tag mapping was not found in
     *                                   the package.
     */
    public function disablePackageTagMapping($packageName, TagMapping $tagMapping)
    {
        $rootPackageFile = $this->environment->getRootPackageFile();
        $packageFile = $this->packages->get($packageName)->getPackageFile();
        $installInfo = $rootPackageFile->getInstallInfo($packageName);

        if (!$packageFile->hasTagMapping($tagMapping)) {
            throw new NoSuchTagMappingException(sprintf(
                'Cannot disable tag mapping: The mapping from "%s" to "%s" '.
                'could not be found in %s.',
                $tagMapping->getPuliSelector(),
                $tagMapping->getTag(),
                $packageFile->getPath()
            ));
        }

        if ($installInfo->hasDisabledTagMapping($tagMapping)) {
            return;
        }

        $installInfo->addDisabledTagMapping($tagMapping);

        $this->packageFileStorage->saveRootPackageFile($rootPackageFile);
    }

    /**
     * Removes all enabled or disabled tag mappings whose tags have not been
     * defined.
     *
     * The root package file is saved immediately after removing the mappings.
     *
     * If no such mappings are found, this method does nothing.
     *
     * @param null|string|string[] $packageName One or more package names to
     *                                          filter for.
     */
    public function removeUndefinedPackageTagMappings($packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $rootPackageName = $this->packages->getRootPackage()->getName();
        $rootPackageFile = $this->environment->getRootPackageFile();
        $save = false;

        foreach ($packageNames as $packageName) {
            if ($packageName === $rootPackageName) {
                continue;
            }

            $installInfo = $rootPackageFile->getInstallInfo($packageName);

            foreach ($installInfo->getEnabledTagMappings() as $tagMapping) {
                if (!isset($this->tagDefinitions[$tagMapping->getTag()])) {
                    $installInfo->removeEnabledTagMapping($tagMapping);
                    $save = true;
                }
            }

            foreach ($installInfo->getDisabledTagMappings() as $tagMapping) {
                if (!isset($this->tagDefinitions[$tagMapping->getTag()])) {
                    $installInfo->removeDisabledTagMapping($tagMapping);
                    $save = true;
                }
            }
        }

        if ($save) {
            $this->packageFileStorage->saveRootPackageFile($rootPackageFile);
        }
    }

    /**
     * Returns all enabled tag mappings from the installed packages.
     *
     * @param null|string          $tag         The tag to filter for.
     * @param null|string|string[] $packageName One or more package names to
     *                                          filter for.
     *
     * @return TagMapping[] The enabled tag mappings.
     */
    public function getEnabledPackageTagMappings($tag = null, $packageName = null)
    {
        if (null !== $tag && !isset($this->tagDefinitions[$tag])) {
            throw new UndefinedTagException(sprintf(
                'Could not get enabled tag mappings: The tag "%s" does not exist.',
                $tag
            ));
        }

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $tagMappings = array();

        foreach ($packageNames as $packageName) {
            $installInfo = $this->packages[$packageName]->getInstallInfo();

            // Package without install info? Ignore.
            if (!$installInfo) {
                continue;
            }

            $this->extractDefinedTagMappings($installInfo->getEnabledTagMappings(), $tagMappings, $tag);
        }

        return $tagMappings;
    }

    /**
     * Returns all disabled tag mappings from the installed packages.
     *
     * @param null|string          $tag         The tag to filter for.
     * @param null|string|string[] $packageName One or more package names to
     *                                          filter for.
     *
     * @return TagMapping[] The disabled tag mappings.
     */
    public function getDisabledPackageTagMappings($tag = null, $packageName = null)
    {
        if (null !== $tag && !isset($this->tagDefinitions[$tag])) {
            throw new UndefinedTagException(sprintf(
                'Could not get disabled tag mappings: The tag "%s" does not exist.',
                $tag
            ));
        }

        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $tagMappings = array();

        foreach ($packageNames as $packageName) {
            $installInfo = $this->packages[$packageName]->getInstallInfo();

            // Package without install info? Ignore.
            if (!$installInfo) {
                continue;
            }

            $this->extractDefinedTagMappings($installInfo->getDisabledTagMappings(), $tagMappings, $tag);
        }

        return $tagMappings;
    }

    /**
     * Returns all tag mappings from the installed packages that are neither
     * enabled nor disabled.
     *
     * @param null|string          $tag         The tag to filter for.
     * @param null|string|string[] $packageName One or more package names to
     *                                          filter for.
     *
     * @return TagMapping[] The new tag mappings.
     */
    public function getNewPackageTagMappings($tag = null, $packageName = null)
    {
        $packageNames = $packageName ? (array) $packageName : $this->packages->getPackageNames();
        $tagMappings = array();

        foreach ($packageNames as $packageName) {
            $packageFile = $this->packages[$packageName]->getPackageFile();
            $installInfo = $this->packages[$packageName]->getInstallInfo();

            // Package without install info? Ignore.
            if (!$installInfo) {
                continue;
            }

            $enabledMappings = $installInfo->getEnabledTagMappings();
            $disabledMappings = $installInfo->getDisabledTagMappings();

            foreach ($packageFile->getTagMappings() as $tagMapping) {
                $isEnabled = in_array($tagMapping, $enabledMappings);
                $isDisabled = in_array($tagMapping, $disabledMappings);
                $isDefined = isset($this->tagDefinitions[$tagMapping->getTag()]);
                $tagMatches = null === $tag || $tag === $tagMapping->getTag();

                if ($isDefined && !$isEnabled && !$isDisabled && $tagMatches) {
                    $tagMappings[] = $tagMapping;
                }
            }
        }

        return $tagMappings;
    }

    private function loadTagDefinitions()
    {
        foreach ($this->packages as $package) {
            foreach ($package->getPackageFile()->getTagDefinitions() as $tagDefinition) {
                $this->tagDefinitions[$tagDefinition->getTag()] = $tagDefinition;
            }
        }
    }

    /**
     * @param TagMapping[] $tagMappings
     * @param TagMapping[] $output
     * @param string|null  $tag
     */
    private function extractDefinedTagMappings(array $tagMappings, &$output, $tag = null)
    {
        foreach ($tagMappings as $tagMapping) {
            $isDefined = isset($this->tagDefinitions[$tagMapping->getTag()]);

            // Undefined tags are ignored
            if ($isDefined && (null === $tag || $tag === $tagMapping->getTag())) {
                $output[] = $tagMapping;
            }
        }
    }
}
