<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Repository;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleCollection;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Repository\PathConflict;
use Puli\Manager\Api\Repository\PathMapping;
use Webmozart\PathUtil\Path;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PathMappingTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $moduleDir1;

    /**
     * @var string
     */
    private $moduleDir2;

    /**
     * @var string
     */
    private $moduleDir3;

    /**
     * @var Module
     */
    private $module1;

    /**
     * @var Module
     */
    private $module2;

    /**
     * @var Module
     */
    private $module3;

    /**
     * @var ModuleCollection
     */
    private $modules;

    protected function setUp()
    {
        $this->moduleDir1 = Path::normalize(__DIR__.'/Fixtures/module1');
        $this->moduleDir2 = Path::normalize(__DIR__.'/Fixtures/module2');
        $this->moduleDir3 = Path::normalize(__DIR__.'/Fixtures/module3');
        $this->module1 = new Module(new ModuleFile('vendor/module1'), $this->moduleDir1);
        $this->module2 = new Module(new ModuleFile('vendor/module2'), $this->moduleDir2);
        $this->module3 = new Module(new ModuleFile('vendor/module3'), $this->moduleDir3);
        $this->modules = new ModuleCollection(array(
            $this->module1,
            $this->module2,
            $this->module3,
        ));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfRepositoryPathNotString()
    {
        new PathMapping(12345, 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfRepositoryPathEmpty()
    {
        new PathMapping('', 'resources');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsNotStringOrArray()
    {
        new PathMapping('/path', 12345);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsEmptyString()
    {
        new PathMapping('/path', '');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsNotStringArray()
    {
        new PathMapping('/path', array(12345));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfFilesystemPathsContainEmptyString()
    {
        new PathMapping('/path', array(''));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfNoFilesystemPaths()
    {
        new PathMapping('/path', array());
    }

    public function testLoad()
    {
        $mapping = new PathMapping('/path', 'resources');

        $this->assertSame(array('resources'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());

        $mapping->load($this->module1, $this->modules);

        $this->assertSame(array('resources'), $mapping->getPathReferences());
        $this->assertSame(array($this->moduleDir1.'/resources'), $mapping->getFilesystemPaths());
        $this->assertSame(array(
            $this->moduleDir1.'/resources' => '/path',
            $this->moduleDir1.'/resources/config' => '/path/config',
            $this->moduleDir1.'/resources/config/config.yml' => '/path/config/config.yml',
            $this->moduleDir1.'/resources/css' => '/path/css',
            $this->moduleDir1.'/resources/css/style.css' => '/path/css/style.css',
        ), $mapping->listPathMappings());
        $this->assertSame(array(
            '/path',
            '/path/config',
            '/path/config/config.yml',
            '/path/css',
            '/path/css/style.css',
        ), $mapping->listRepositoryPaths());
        $this->assertSame(array(), $mapping->getLoadErrors());
        $this->assertSame($this->module1, $mapping->getContainingModule());
        $this->assertTrue($mapping->isLoaded());
    }

    public function testLoadMultiplePathReferences()
    {
        $mapping = new PathMapping('/path', array('resources', 'assets'));

        $this->assertSame(array('resources', 'assets'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());

        $mapping->load($this->module1, $this->modules);

        $this->assertSame(array('resources', 'assets'), $mapping->getPathReferences());
        $this->assertSame(array(
            $this->moduleDir1.'/resources',
            $this->moduleDir1.'/assets',
        ), $mapping->getFilesystemPaths());
        $this->assertSame(array(
            $this->moduleDir1.'/resources' => '/path',
            $this->moduleDir1.'/resources/config' => '/path/config',
            $this->moduleDir1.'/resources/config/config.yml' => '/path/config/config.yml',
            $this->moduleDir1.'/resources/css' => '/path/css',
            $this->moduleDir1.'/resources/css/style.css' => '/path/css/style.css',
            $this->moduleDir1.'/assets' => '/path',
            $this->moduleDir1.'/assets/css' => '/path/css',
            $this->moduleDir1.'/assets/css/style.css' => '/path/css/style.css',
        ), $mapping->listPathMappings());
        $this->assertSame(array(
            '/path',
            '/path/config',
            '/path/config/config.yml',
            '/path/css',
            '/path/css/style.css',
        ), $mapping->listRepositoryPaths());
        $this->assertSame(array(), $mapping->getLoadErrors());
        $this->assertSame($this->module1, $mapping->getContainingModule());
        $this->assertTrue($mapping->isLoaded());
    }

    public function testLoadMultiplePathReferences2()
    {
        $mapping = new PathMapping('/path', array('assets', 'resources'));

        $this->assertSame(array('assets', 'resources'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());

        $mapping->load($this->module1, $this->modules);

        $this->assertSame(array('assets', 'resources'), $mapping->getPathReferences());
        $this->assertSame(array(
            $this->moduleDir1.'/assets',
            $this->moduleDir1.'/resources',
        ), $mapping->getFilesystemPaths());
        $this->assertSame(array(
            $this->moduleDir1.'/assets' => '/path',
            $this->moduleDir1.'/assets/css' => '/path/css',
            $this->moduleDir1.'/assets/css/style.css' => '/path/css/style.css',
            $this->moduleDir1.'/resources' => '/path',
            $this->moduleDir1.'/resources/config' => '/path/config',
            $this->moduleDir1.'/resources/config/config.yml' => '/path/config/config.yml',
            $this->moduleDir1.'/resources/css' => '/path/css',
            $this->moduleDir1.'/resources/css/style.css' => '/path/css/style.css',
        ), $mapping->listPathMappings());
        $this->assertSame(array(
            '/path',
            '/path/config',
            '/path/config/config.yml',
            '/path/css',
            '/path/css/style.css',
        ), $mapping->listRepositoryPaths());
        $this->assertSame(array(), $mapping->getLoadErrors());
        $this->assertSame($this->module1, $mapping->getContainingModule());
        $this->assertTrue($mapping->isLoaded());
    }

    public function testLoadReferencesToOtherModule()
    {
        $mapping = new PathMapping('/path', '@vendor/module2:resources');

        $this->assertSame(array('@vendor/module2:resources'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());

        $mapping->load($this->module1, $this->modules);

        $this->assertSame(array('@vendor/module2:resources'), $mapping->getPathReferences());
        $this->assertSame(array($this->moduleDir2.'/resources'), $mapping->getFilesystemPaths());
        $this->assertSame(array(
            $this->moduleDir2.'/resources' => '/path',
            $this->moduleDir2.'/resources/config' => '/path/config',
            $this->moduleDir2.'/resources/config/config.yml' => '/path/config/config.yml',
            $this->moduleDir2.'/resources/css' => '/path/css',
            $this->moduleDir2.'/resources/css/style.css' => '/path/css/style.css',
        ), $mapping->listPathMappings());
        $this->assertSame(array(
            '/path',
            '/path/config',
            '/path/config/config.yml',
            '/path/css',
            '/path/css/style.css',
        ), $mapping->listRepositoryPaths());
        $this->assertSame(array(), $mapping->getLoadErrors());
        $this->assertSame($this->module1, $mapping->getContainingModule());
        $this->assertTrue($mapping->isLoaded());
    }

    /**
     * @expectedException \Puli\Manager\Api\AlreadyLoadedException
     */
    public function testLoadFailsIfCalledTwice()
    {
        $mapping = new PathMapping('/path', 'resources');

        $mapping->load($this->module1, $this->modules);
        $mapping->load($this->module1, $this->modules);
    }

    public function testLoadStoresErrorIfPathNotFound()
    {
        $mapping = new PathMapping('/path', array('foo', 'assets'));

        $mapping->load($this->module1, $this->modules);

        $this->assertSame(array('foo', 'assets'), $mapping->getPathReferences());
        $this->assertSame(array($this->moduleDir1.'/assets'), $mapping->getFilesystemPaths());

        // there's at least one found path, so the mapping is still enabled
        $this->assertTrue($mapping->isEnabled());

        $loadErrors = $mapping->getLoadErrors();
        $this->assertCount(1, $loadErrors);
        $this->assertInstanceOf('Puli\Manager\Api\FileNotFoundException', $loadErrors[0]);
    }

    public function testLoadStoresErrorsIfNoPathFound()
    {
        $mapping = new PathMapping('/path', array('foo', 'bar'));

        $mapping->load($this->module1, $this->modules);

        $this->assertSame(array('foo', 'bar'), $mapping->getPathReferences());
        $this->assertSame(array(), $mapping->getFilesystemPaths());

        // no found path, not enabled
        $this->assertFalse($mapping->isEnabled());
        $this->assertTrue($mapping->isNotFound());

        $loadErrors = $mapping->getLoadErrors();
        $this->assertCount(2, $loadErrors);
        $this->assertInstanceOf('Puli\Manager\Api\FileNotFoundException', $loadErrors[0]);
        $this->assertInstanceOf('Puli\Manager\Api\FileNotFoundException', $loadErrors[1]);
    }

    public function testLoadStoresErrorIfModuleNotFound()
    {
        $mapping = new PathMapping('/path', array('@foo:resources', 'assets'));

        $mapping->load($this->module1, $this->modules);

        $this->assertSame(array('@foo:resources', 'assets'), $mapping->getPathReferences());
        $this->assertSame(array($this->moduleDir1.'/assets'), $mapping->getFilesystemPaths());

        // there's at least one found path, so the mapping is still enabled
        $this->assertTrue($mapping->isEnabled());

        $loadErrors = $mapping->getLoadErrors();
        $this->assertCount(1, $loadErrors);
        $this->assertInstanceOf('Puli\Manager\Api\Module\NoSuchModuleException', $loadErrors[0]);
    }

    public function testUnload()
    {
        $mapping = new PathMapping('/path', 'resources');

        $mapping->load($this->module1, $this->modules);
        $mapping->unload();

        $this->assertSame(array('resources'), $mapping->getPathReferences());
        $this->assertFalse($mapping->isLoaded());
    }

    public function testUnloadReleasesConflict()
    {
        $mapping = new PathMapping('/path', 'resources');

        $mapping->load($this->module1, $this->modules);
        $mapping->addConflict($conflict = new PathConflict('/path/conflict'));

        $this->assertCount(1, $mapping->getConflicts());
        $this->assertCount(1, $conflict->getMappings());

        $mapping->unload();

        $this->assertCount(0, $conflict->getMappings());
    }

    /**
     * @expectedException \Puli\Manager\Api\NotLoadedException
     */
    public function testUnloadFailsIfNotLoaded()
    {
        $mapping = new PathMapping('/path', 'resources');

        $mapping->unload();
    }

    public function testAddConflictWithAmePath()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/path');

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());

        $mapping->addConflict($conflict);

        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($conflict, $mapping->getConflicts());
        $this->assertCount(1, $conflict->getMappings());
        $this->assertContains($mapping, $conflict->getMappings());
        $this->assertTrue($mapping->isConflicting());
    }

    public function testAddConflictWithNestedPath()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $mapping->addConflict($conflict);

        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($conflict, $mapping->getConflicts());
        $this->assertCount(1, $conflict->getMappings());
        $this->assertContains($mapping, $conflict->getMappings());
        $this->assertTrue($mapping->isConflicting());
    }

    public function testAddMultipleConflicts()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict1 = new PathConflict('/path/conflict1');
        $conflict2 = new PathConflict('/path/conflict2');

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict1->getMappings());

        $mapping->addConflict($conflict1);
        $mapping->addConflict($conflict2);

        $this->assertCount(2, $mapping->getConflicts());
        $this->assertContains($conflict1, $mapping->getConflicts());
        $this->assertContains($conflict2, $mapping->getConflicts());
        $this->assertCount(1, $conflict1->getMappings());
        $this->assertContains($mapping, $conflict1->getMappings());
        $this->assertCount(1, $conflict2->getMappings());
        $this->assertContains($mapping, $conflict2->getMappings());
        $this->assertTrue($mapping->isConflicting());
    }

    public function testAddConflictIgnoresDuplicates()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());

        $mapping->addConflict($conflict);
        $mapping->addConflict($conflict);

        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($conflict, $mapping->getConflicts());
        $this->assertCount(1, $conflict->getMappings());
        $this->assertContains($mapping, $conflict->getMappings());
        $this->assertTrue($mapping->isConflicting());
    }

    public function testAddConflictRemovesPreviousConflictWithSameRepositoryPath()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $previousConflict = new PathConflict('/path/conflict');
        $newConflict = new PathConflict('/path/conflict');

        $mapping->addConflict($previousConflict);
        $mapping->addConflict($newConflict);

        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($newConflict, $mapping->getConflicts());
        $this->assertCount(0, $previousConflict->getMappings());
        $this->assertCount(1, $newConflict->getMappings());
        $this->assertContains($mapping, $newConflict->getMappings());
        $this->assertTrue($mapping->isConflicting());
    }

    /**
     * @expectedException \Puli\Manager\Api\NotLoadedException
     */
    public function testAddConflictFailsIfNotLoaded()
    {
        $mapping = new PathMapping('/path', 'resources');
        $conflict = new PathConflict('/path/conflict');

        $mapping->addConflict($conflict);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAddConflictFailsIfConflictWithDifferentRepositoryBasePath()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/other/path/conflict');

        $mapping->addConflict($conflict);
    }

    public function testRemoveConflict()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $mapping->addConflict($conflict);
        $mapping->removeConflict($conflict);

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());
        $this->assertFalse($mapping->isConflicting());
    }

    public function testRemoveConflictIgnoresUnknownConflicts()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $mapping->removeConflict($conflict);

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());
        $this->assertFalse($mapping->isConflicting());
    }

    /**
     * @expectedException \Puli\Manager\Api\NotLoadedException
     */
    public function testRemoveConflictFailsIfNotLoaded()
    {
        $mapping = new PathMapping('/path', 'resources');
        $conflict = new PathConflict('/path/conflict');

        $mapping->removeConflict($conflict);
    }

    public function testGetConflictingModules()
    {
        $mapping1 = new PathMapping('/path', 'resources');
        $mapping1->load($this->module1, $this->modules);

        $this->assertInstanceOf('Puli\Manager\Api\Module\ModuleCollection', $mapping1->getConflictingModules());
        $this->assertCount(0, $mapping1->getConflictingModules());

        $mapping2 = new PathMapping('/path', 'resources');
        $mapping2->load($this->module2, $this->modules);

        $conflict = new PathConflict('/path/conflict');

        $mapping1->addConflict($conflict);
        $mapping2->addConflict($conflict);

        $this->assertCount(1, $mapping1->getConflictingModules());
        $this->assertTrue($mapping1->getConflictingModules()->contains('vendor/module2'));
        $this->assertCount(1, $mapping2->getConflictingModules());
        $this->assertTrue($mapping2->getConflictingModules()->contains('vendor/module1'));

        $mapping3 = new PathMapping('/path', 'resources');
        $mapping3->load($this->module3, $this->modules);
        $mapping3->addConflict($conflict);

        $this->assertCount(2, $mapping1->getConflictingModules());
        $this->assertTrue($mapping1->getConflictingModules()->contains('vendor/module2'));
        $this->assertTrue($mapping1->getConflictingModules()->contains('vendor/module3'));
        $this->assertCount(2, $mapping2->getConflictingModules());
        $this->assertTrue($mapping2->getConflictingModules()->contains('vendor/module1'));
        $this->assertTrue($mapping2->getConflictingModules()->contains('vendor/module3'));
        $this->assertCount(2, $mapping3->getConflictingModules());
        $this->assertTrue($mapping3->getConflictingModules()->contains('vendor/module1'));
        $this->assertTrue($mapping3->getConflictingModules()->contains('vendor/module2'));
    }

    public function testGetConflictingMappings()
    {
        $mapping1 = new PathMapping('/path', 'resources');
        $mapping1->load($this->module1, $this->modules);

        $this->assertCount(0, $mapping1->getConflictingMappings());

        $mapping2 = new PathMapping('/path', 'resources');
        $mapping2->load($this->module2, $this->modules);

        $conflict = new PathConflict('/path/conflict');

        $mapping1->addConflict($conflict);
        $mapping2->addConflict($conflict);

        $this->assertCount(1, $mapping1->getConflictingMappings());
        $this->assertContains($mapping2, $mapping1->getConflictingMappings());
        $this->assertCount(1, $mapping2->getConflictingMappings());
        $this->assertContains($mapping1, $mapping2->getConflictingMappings());

        $mapping3 = new PathMapping('/path', 'resources');
        $mapping3->load($this->module3, $this->modules);
        $mapping3->addConflict($conflict);

        $this->assertCount(2, $mapping1->getConflictingMappings());
        $this->assertContains($mapping2, $mapping1->getConflictingMappings());
        $this->assertContains($mapping3, $mapping1->getConflictingMappings());
        $this->assertCount(2, $mapping2->getConflictingMappings());
        $this->assertContains($mapping1, $mapping2->getConflictingMappings());
        $this->assertContains($mapping3, $mapping2->getConflictingMappings());
        $this->assertCount(2, $mapping3->getConflictingMappings());
        $this->assertContains($mapping1, $mapping3->getConflictingMappings());
        $this->assertContains($mapping2, $mapping3->getConflictingMappings());
    }
}
