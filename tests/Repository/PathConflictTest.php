<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Repository;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Module\Module;
use Puli\Manager\Api\Module\ModuleList;
use Puli\Manager\Api\Module\ModuleFile;
use Puli\Manager\Api\Repository\PathConflict;
use Puli\Manager\Api\Repository\PathMapping;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PathConflictTest extends PHPUnit_Framework_TestCase
{
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
     * @var ModuleList
     */
    private $modules;

    protected function setUp()
    {
        $this->module1 = new Module(new ModuleFile('vendor/module1'), __DIR__.'/Fixtures/module1');
        $this->module2 = new Module(new ModuleFile('vendor/module2'), __DIR__.'/Fixtures/module2');
        $this->module3 = new Module(new ModuleFile('vendor/module3'), __DIR__.'/Fixtures/module3');
        $this->modules = new ModuleList(array($this->module1, $this->module2, $this->module3));
    }

    public function testAddMapping()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());

        $conflict->addMapping($mapping);

        $this->assertCount(1, $conflict->getMappings());
        $this->assertContains($mapping, $conflict->getMappings());
        $this->assertFalse($conflict->isResolved());
        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($conflict, $mapping->getConflicts());
    }

    public function testAddMultipleMappings()
    {
        $mapping1 = new PathMapping('/path', 'resources');
        $mapping1->load($this->module1, $this->modules);
        $mapping2 = new PathMapping('/path', 'resources');
        $mapping2->load($this->module2, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $conflict->addMapping($mapping1);
        $conflict->addMapping($mapping2);

        $this->assertCount(2, $conflict->getMappings());
        $this->assertContains($mapping1, $conflict->getMappings());
        $this->assertContains($mapping2, $conflict->getMappings());
        $this->assertFalse($conflict->isResolved());
        $this->assertCount(1, $mapping1->getConflicts());
        $this->assertContains($conflict, $mapping1->getConflicts());
        $this->assertCount(1, $mapping2->getConflicts());
        $this->assertContains($conflict, $mapping2->getConflicts());
    }

    public function testAddMappingIgnoresDuplicates()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());

        $conflict->addMapping($mapping);
        $conflict->addMapping($mapping);

        $this->assertCount(1, $conflict->getMappings());
        $this->assertContains($mapping, $conflict->getMappings());
        $this->assertFalse($conflict->isResolved());
        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($conflict, $mapping->getConflicts());
    }

    public function testAddMappingRemovesPreviousMappingFromSameModule()
    {
        $previousMapping = new PathMapping('/path', 'resources');
        $previousMapping->load($this->module1, $this->modules);
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $conflict->addMapping($previousMapping);
        $conflict->addMapping($mapping);

        $this->assertCount(1, $conflict->getMappings());
        $this->assertContains($mapping, $conflict->getMappings());
        $this->assertFalse($conflict->isResolved());
        $this->assertCount(1, $mapping->getConflicts());
        $this->assertContains($conflict, $mapping->getConflicts());
        $this->assertCount(0, $previousMapping->getConflicts());
    }

    /**
     * @expectedException \Puli\Manager\Api\NotLoadedException
     */
    public function testAddMappingFailsIfModuleNotLoaded()
    {
        $mapping = new PathMapping('/path', 'resources');
        $conflict = new PathConflict('/path/conflict');

        $conflict->addMapping($mapping);
    }

    public function testRemoveMapping()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $conflict->addMapping($mapping);
        $conflict->removeMapping($mapping);

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());
        $this->assertTrue($conflict->isResolved());
    }

    public function testRemoveMappingIgnoresUnknownMappings()
    {
        $mapping = new PathMapping('/path', 'resources');
        $mapping->load($this->module1, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $conflict->removeMapping($mapping);

        $this->assertCount(0, $mapping->getConflicts());
        $this->assertCount(0, $conflict->getMappings());
        $this->assertTrue($conflict->isResolved());
    }

    public function testRemoveMappingResolvesConflictIfOnlyOneMappingLeft()
    {
        $mapping1 = new PathMapping('/path', 'resources');
        $mapping1->load($this->module1, $this->modules);
        $mapping2 = new PathMapping('/path', 'resources');
        $mapping2->load($this->module2, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $conflict->addMapping($mapping1);
        $conflict->addMapping($mapping2);
        $conflict->removeMapping($mapping1);

        $this->assertCount(0, $conflict->getMappings());
        $this->assertCount(0, $mapping1->getConflicts());
        $this->assertCount(0, $mapping2->getConflicts());
        $this->assertTrue($conflict->isResolved());
    }

    public function testRemoveMappingDoesNotResolveConflictIfMoreThanOneMappingLeft()
    {
        $mapping1 = new PathMapping('/path', 'resources');
        $mapping1->load($this->module1, $this->modules);
        $mapping2 = new PathMapping('/path', 'resources');
        $mapping2->load($this->module2, $this->modules);
        $mapping3 = new PathMapping('/path', 'resources');
        $mapping3->load($this->module3, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $conflict->addMapping($mapping1);
        $conflict->addMapping($mapping2);
        $conflict->addMapping($mapping3);
        $conflict->removeMapping($mapping1);

        $this->assertCount(2, $conflict->getMappings());
        $this->assertCount(0, $mapping1->getConflicts());
        $this->assertCount(1, $mapping2->getConflicts());
        $this->assertCount(1, $mapping3->getConflicts());
        $this->assertFalse($conflict->isResolved());
    }

    /**
     * @expectedException \Puli\Manager\Api\NotLoadedException
     */
    public function testRemoveMappingFailsIfModuleNotLoaded()
    {
        $mapping = new PathMapping('/path', 'resources');
        $conflict = new PathConflict('/path/conflict');

        $conflict->removeMapping($mapping);
    }

    public function testResolveRemovesAllMappings()
    {
        $mapping1 = new PathMapping('/path', 'resources');
        $mapping1->load($this->module1, $this->modules);
        $mapping2 = new PathMapping('/path', 'resources');
        $mapping2->load($this->module2, $this->modules);
        $mapping3 = new PathMapping('/path', 'resources');
        $mapping3->load($this->module3, $this->modules);
        $conflict = new PathConflict('/path/conflict');

        $conflict->addMapping($mapping1);
        $conflict->addMapping($mapping2);
        $conflict->addMapping($mapping3);
        $conflict->resolve();

        $this->assertCount(0, $conflict->getMappings());
        $this->assertCount(0, $mapping1->getConflicts());
        $this->assertCount(0, $mapping2->getConflicts());
        $this->assertCount(0, $mapping3->getConflicts());
        $this->assertTrue($conflict->isResolved());
    }
}
