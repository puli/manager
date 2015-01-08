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
use Puli\Factory\PuliFactory;
use Puli\RepositoryManager\Config\Config;
use Puli\RepositoryManager\Generator\ProviderFactory;
use Puli\RepositoryManager\Generator\PuliFactoryGenerator;
use Puli\RepositoryManager\Tests\Generator\Fixtures\TestDiscoveryRecipeProvider;
use Puli\RepositoryManager\Tests\Generator\Fixtures\TestRepositoryRecipeProvider;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @runTestsInSeparateProcesses
 */
class PuliFactoryGeneratorTest extends PHPUnit_Framework_TestCase
{
    private $tempDir;

    /**
     * @var PuliFactoryGenerator
     */
    private $factoryGenerator;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|ProviderFactory
     */
    private $providerFactory;

    protected function setUp()
    {
        while (false === @mkdir($this->tempDir = sys_get_temp_dir().'/puli-repo-manager/PuliFactoryGeneratorTest'.rand(10000, 99999), 0777, true)) {}

        $this->providerFactory = $this->getMockBuilder('Puli\RepositoryManager\Generator\ProviderFactory')
            ->disableOriginalConstructor()
            ->getMock();

        $this->providerFactory->expects($this->any())
            ->method('createRepositoryRecipeProvider')
            ->with('my-repo-type')
            ->will($this->returnValue(new TestRepositoryRecipeProvider()));

        $this->providerFactory->expects($this->any())
            ->method('createDiscoveryRecipeProvider')
            ->with('my-discovery-type')
            ->will($this->returnValue(new TestDiscoveryRecipeProvider()));

        $this->factoryGenerator = new PuliFactoryGenerator($this->providerFactory);
    }

    protected function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->tempDir);
    }

    public function testGenerateFactory()
    {
        $config = new Config();
        $config->set(Config::REPOSITORY_TYPE, 'my-repo-type');
        $config->set(Config::REPOSITORY_PATH, 'my-storage-dir');
        $config->set(Config::DISCOVERY_TYPE, 'my-discovery-type');

        $this->factoryGenerator->generateFactory(
            $this->tempDir.'/MyFactory.php',
            'Puli\MyFactory',
            $this->tempDir,
            $config
        );

        require $this->tempDir.'/MyFactory.php';

        /** @var PuliFactory $factory */
        $factory = new \Puli\MyFactory();

        $this->assertInstanceOf('Puli\Factory\PuliFactory', $factory);

        $repo = $factory->createRepository();
        $discovery = $factory->createDiscovery($repo);

        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestRepository', $repo);
        $this->assertSame('my-storage-dir', $repo->getPath());

        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestDiscovery', $discovery);
        $this->assertSame($repo, $discovery->getRepository());
    }

    public function testGenerateFactoryInGlobalNamespace()
    {
        $config = new Config();
        $config->set(Config::REPOSITORY_TYPE, 'my-repo-type');
        $config->set(Config::REPOSITORY_PATH, 'my-storage-dir');
        $config->set(Config::DISCOVERY_TYPE, 'my-discovery-type');

        $this->factoryGenerator->generateFactory(
            $this->tempDir.'/MyFactory.php',
            'MyFactory',
            $this->tempDir,
            $config
        );

        require $this->tempDir.'/MyFactory.php';

        $factory = new \MyFactory();
        $repo = $factory->createRepository();
        $discovery = $factory->createDiscovery($repo);

        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestRepository', $repo);
        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestDiscovery', $discovery);
    }

    public function testGenerateFactoryWithSameShortNameAsInterface()
    {
        $config = new Config();
        $config->set(Config::REPOSITORY_TYPE, 'my-repo-type');
        $config->set(Config::REPOSITORY_PATH, 'my-storage-dir');
        $config->set(Config::DISCOVERY_TYPE, 'my-discovery-type');

        $this->factoryGenerator->generateFactory(
            $this->tempDir.'/PuliFactory.php',
            'Puli\PuliFactory',
            $this->tempDir,
            $config
        );

        require $this->tempDir.'/PuliFactory.php';

        $factory = new \Puli\PuliFactory();
        $repo = $factory->createRepository();
        $discovery = $factory->createDiscovery($repo);

        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestRepository', $repo);
        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestDiscovery', $discovery);
    }

    public function testGenerateFactoryInNonExistingDirectory()
    {
        $config = new Config();
        $config->set(Config::REPOSITORY_TYPE, 'my-repo-type');
        $config->set(Config::REPOSITORY_PATH, 'my-storage-dir');
        $config->set(Config::DISCOVERY_TYPE, 'my-discovery-type');

        $this->factoryGenerator->generateFactory(
            $this->tempDir.'/sub-dir/MyFactory.php',
            'Puli\MyFactory',
            $this->tempDir,
            $config
        );

        require $this->tempDir.'/sub-dir/MyFactory.php';

        $factory = new \Puli\MyFactory();
        $repo = $factory->createRepository();
        $discovery = $factory->createDiscovery($repo);

        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestRepository', $repo);
        $this->assertInstanceOf(__NAMESPACE__.'\Fixtures\TestDiscovery', $discovery);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPathNotString()
    {
        $this->factoryGenerator->generateFactory(
            1234,
            'Puli\MyFactory',
            $this->tempDir,
            new Config()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPathEmpty()
    {
        $this->factoryGenerator->generateFactory(
            '',
            'Puli\MyFactory',
            $this->tempDir,
            new Config()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfPathNotAbsolute()
    {
        $this->factoryGenerator->generateFactory(
            'relative',
            'Puli\MyFactory',
            $this->tempDir,
            new Config()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfClassNotString()
    {
        $this->factoryGenerator->generateFactory(
            $this->tempDir.'/MyFactory.php',
            1234,
            $this->tempDir,
            new Config()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfClassEmpty()
    {
        $this->factoryGenerator->generateFactory(
            $this->tempDir.'/MyFactory.php',
            '',
            $this->tempDir,
            new Config()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfRootDirNotString()
    {
        $this->factoryGenerator->generateFactory(
            $this->tempDir.'/MyFactory.php',
            'Puli\MyFactory',
            1234,
            new Config()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailIfRootDirNoDirectory()
    {
        $this->factoryGenerator->generateFactory(
            $this->tempDir.'/MyFactory.php',
            'Puli\MyFactory',
            $this->tempDir.'/foobar',
            new Config()
        );
    }
}
