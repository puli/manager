<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Tests\Generator;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Generator\GeneratorFactory;
use Puli\RepositoryManager\Generator\RegistryGenerator;
use Puli\RepositoryManager\Tests\Generator\Fixtures\TestDiscoveryGenerator;
use Puli\RepositoryManager\Tests\Generator\Fixtures\TestRepositoryGenerator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @runTestsInSeparateProcesses
 */
class RegistryGeneratorTest extends PHPUnit_Framework_TestCase
{
    private $tempDir;

    /**
     * @var RegistryGenerator
     */
    private $registryGenerator;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|GeneratorFactory
     */
    private $generatorFactory;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/RegistryGeneratorTest'.rand(10000, 99999), 0777, true)) {}

        $this->generatorFactory = $this->getMockBuilder('Puli\RepositoryManager\Generator\GeneratorFactory')
            ->disableOriginalConstructor()
            ->getMock();

        $this->generatorFactory->expects($this->any())
            ->method('createRepositoryGenerator')
            ->with('my-repo-type')
            ->will($this->returnValue(new TestRepositoryGenerator()));

        $this->generatorFactory->expects($this->any())
            ->method('createDiscoveryGenerator')
            ->with('my-discovery-type')
            ->will($this->returnValue(new TestDiscoveryGenerator()));

        $this->registryGenerator = new RegistryGenerator($this->generatorFactory);
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testGenerateRegistry()
    {
        $config = new Config();
        $config->set(Config::REGISTRY_CLASS, 'Puli\\MyRegistry');
        $config->set(Config::REGISTRY_FILE, 'MyRegistry.php');
        $config->set(Config::REPO_TYPE, 'my-repo-type');
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');
        $config->set(Config::DISCOVERY_TYPE, 'my-discovery-type');

        $this->registryGenerator->generateRegistry($this->tempDir, $config);

        require $this->tempDir.'/MyRegistry.php';

        $repo = \Puli\MyRegistry::getRepository();
        $discovery = \Puli\MyRegistry::getDiscovery();

        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestRepository', $repo);
        $this->assertSame('my-storage-dir', $repo->getStorageDir());

        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestDiscovery', $discovery);
        $this->assertSame($repo, $discovery->getRepository());
    }

    public function testGeneratedRegistryCachesInstances()
    {
        $config = new Config();
        $config->set(Config::REGISTRY_CLASS, 'Puli\\MyRegistry');
        $config->set(Config::REGISTRY_FILE, 'MyRegistry.php');
        $config->set(Config::REPO_TYPE, 'my-repo-type');
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');
        $config->set(Config::DISCOVERY_TYPE, 'my-discovery-type');

        $this->registryGenerator->generateRegistry($this->tempDir, $config);

        require $this->tempDir.'/MyRegistry.php';

        $repo = \Puli\MyRegistry::getRepository();
        $discovery = \Puli\MyRegistry::getDiscovery();

        $this->assertSame($repo, \Puli\MyRegistry::getRepository());
        $this->assertSame($discovery, \Puli\MyRegistry::getDiscovery());
    }

    public function testGenerateRegistryInGlobalNamespace()
    {
        $config = new Config();
        $config->set(Config::REGISTRY_CLASS, 'MyRegistry');
        $config->set(Config::REGISTRY_FILE, 'MyRegistry.php');
        $config->set(Config::REPO_TYPE, 'my-repo-type');
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');
        $config->set(Config::DISCOVERY_TYPE, 'my-discovery-type');

        $this->registryGenerator->generateRegistry($this->tempDir, $config);

        require $this->tempDir.'/MyRegistry.php';

        $repo = \MyRegistry::getRepository();
        $discovery = \MyRegistry::getDiscovery();

        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestRepository', $repo);
        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestDiscovery', $discovery);
    }

    public function testGenerateRegistryInNonExistingDirectory()
    {
        $config = new Config();
        $config->set(Config::REGISTRY_CLASS, 'Puli\\MyRegistry');
        $config->set(Config::REGISTRY_FILE, 'sub-dir/MyRegistry.php');
        $config->set(Config::REPO_TYPE, 'my-repo-type');
        $config->set(Config::REPO_STORAGE_DIR, 'my-storage-dir');
        $config->set(Config::DISCOVERY_TYPE, 'my-discovery-type');

        $this->registryGenerator->generateRegistry($this->tempDir, $config);

        require $this->tempDir.'/sub-dir/MyRegistry.php';

        $repo = \Puli\MyRegistry::getRepository();
        $discovery = \Puli\MyRegistry::getDiscovery();

        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestRepository', $repo);
        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestDiscovery', $discovery);
    }
}
