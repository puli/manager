<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Installer;

use Exception;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Installer\InstallerParameter;
use Puli\Manager\Api\Package\Package;
use Puli\Manager\Api\Package\PackageCollection;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\RootPackage;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Package\RootPackageFileManager;
use Puli\Manager\Installer\PackageFileInstallerManager;
use Puli\Manager\Tests\TestException;
use Webmozart\Expression\Expr;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileInstallerManagerUnloadedTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var RootPackageFile
     */
    protected $rootPackageFile;

    /**
     * @var PackageFile
     */
    protected $packageFile1;

    /**
     * @var PackageFile
     */
    protected $packageFile2;

    /**
     * @var RootPackage
     */
    protected $rootPackage;

    /**
     * @var Package
     */
    protected $package1;

    /**
     * @var Package
     */
    protected $package2;

    /**
     * @var Package
     */
    protected $package3;

    /**
     * @var PackageCollection
     */
    protected $packages;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|RootPackageFileManager
     */
    protected $packageFileManager;

    /**
     * @var PackageFileInstallerManager
     */
    protected $manager;

    protected function setUp()
    {
        $this->packageFileManager = $this->getMock('Puli\Manager\Api\Package\RootPackageFileManager');
        $this->rootPackageFile = new RootPackageFile('vendor/root');
        $this->packageFile1 = new PackageFile('vendor/package1');
        $this->packageFile2 = new PackageFile('vendor/package2');
        $this->rootPackage = new RootPackage($this->rootPackageFile, '/path');
        $this->package1 = new Package($this->packageFile1, '/path');
        $this->package2 = new Package($this->packageFile2, '/path');
        $this->package3 = new Package(null, '/path', null, array(new Exception()));
        $this->packages = new PackageCollection(array(
            $this->rootPackage,
            $this->package1,
            $this->package2,
            $this->package3,
        ));
        $this->manager = new PackageFileInstallerManager($this->packageFileManager, $this->packages);
    }

    public function testGetInstallerDescriptor()
    {
        $this->populateDefaultManager();

        $descriptor = new InstallerDescriptor('custom-symlink', 'CustomSymlinkInstaller');

        $this->assertEquals($descriptor, $this->manager->getInstallerDescriptor('custom-symlink'));
    }

    public function testGetBuiltinInstallerDescriptor()
    {
        $descriptor = $this->manager->getInstallerDescriptor('symlink');

        $this->assertInstanceOf('Puli\Manager\Api\Installer\InstallerDescriptor', $descriptor);
        $this->assertSame('symlink', $descriptor->getName());
        $this->assertSame('Puli\Manager\Installer\SymlinkInstaller', $descriptor->getClassName());
    }

    /**
     * @expectedException \Puli\Manager\Api\Installer\NoSuchInstallerException
     * @expectedExceptionMessage foobar
     */
    public function testGetInstallerDescriptorFailsIfNotFound()
    {
        $this->manager->getInstallerDescriptor('foobar');
    }

    /**
     * @expectedException \Webmozart\Json\ValidationFailedException
     */
    public function testGetInstallerDescriptorFailsIfJsonIsInvalid()
    {
        $this->packageFile1->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, array(
            (object) array(
                'name' => 'custom-symlink',
                'class' => 'Package1CustomSymlinkInstaller',
            ),
        ));

        $this->manager->getInstallerDescriptor('custom-symlink');
    }

    public function testGetInstallerDescriptorLoadsFullyConfiguredInstaller()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'CustomSymlinkInstaller',
                'description' => 'The description',
                'parameters' => (object) array(
                    'required' => (object) array(
                        'required' => true,
                        'description' => 'The parameter description 1',
                    ),
                    'optional' => (object) array(
                        'description' => 'The parameter description 2',
                    ),
                    'optional-with-default' => (object) array(
                        'default' => 'foobar',
                    ),
                    'optional-empty' => (object) array(),
                ),
            ),
        ));

        $descriptor = new InstallerDescriptor('custom-symlink', 'CustomSymlinkInstaller', 'The description', array(
            new InstallerParameter('required', InstallerParameter::REQUIRED, null, 'The parameter description 1'),
            new InstallerParameter('optional', InstallerParameter::OPTIONAL, null, 'The parameter description 2'),
            new InstallerParameter('optional-with-default', InstallerParameter::OPTIONAL, 'foobar'),
            new InstallerParameter('optional-empty'),
        ));

        $this->assertEquals($descriptor, $this->manager->getInstallerDescriptor('custom-symlink'));
    }

    public function testGetInstallerDescriptors()
    {
        $this->populateDefaultManager();

        $descriptor1 = new InstallerDescriptor('custom-symlink', 'CustomSymlinkInstaller');
        $descriptor2 = new InstallerDescriptor('rsync', 'RsyncInstaller');

        $this->assertEquals(array(
            'copy' => $this->manager->getInstallerDescriptor('copy'),
            'symlink' => $this->manager->getInstallerDescriptor('symlink'),
            'custom-symlink' => $descriptor1,
            'rsync' => $descriptor2,
        ), $this->manager->getInstallerDescriptors());
    }

    public function testFindInstallerDescriptors()
    {
        $this->populateDefaultManager();

        $descriptor1 = new InstallerDescriptor('custom-symlink', 'CustomSymlinkInstaller');
        $descriptor2 = new InstallerDescriptor('rsync', 'RsyncInstaller');
        $descriptor3 = $this->manager->getInstallerDescriptor('copy');
        $descriptor4 = $this->manager->getInstallerDescriptor('symlink');

        $expr1 = Expr::same('custom-symlink', InstallerDescriptor::NAME);

        $expr2 = Expr::endsWith('Installer', InstallerDescriptor::CLASS_NAME);

        $this->assertEquals(array($descriptor1), $this->manager->findInstallerDescriptors($expr1));
        $this->assertEquals(array($descriptor3, $descriptor4, $descriptor2, $descriptor1), $this->manager->findInstallerDescriptors($expr2));
    }

    public function testHasInstallerDescriptor()
    {
        $this->populateDefaultManager();

        $this->assertTrue($this->manager->hasInstallerDescriptor('copy'));
        $this->assertTrue($this->manager->hasInstallerDescriptor('symlink'));
        $this->assertTrue($this->manager->hasInstallerDescriptor('custom-symlink'));
        $this->assertTrue($this->manager->hasInstallerDescriptor('rsync'));
        $this->assertFalse($this->manager->hasInstallerDescriptor('foobar'));
    }

    public function testHasInstallerDescriptors()
    {
        $this->populateDefaultManager();

        $this->assertTrue($this->manager->hasInstallerDescriptors());
        $this->assertTrue($this->manager->hasInstallerDescriptors(Expr::same('copy', InstallerDescriptor::NAME)));
        $this->assertTrue($this->manager->hasInstallerDescriptors(Expr::same('custom-symlink', InstallerDescriptor::NAME)));
        $this->assertFalse($this->manager->hasInstallerDescriptors(Expr::same('foobar', InstallerDescriptor::NAME)));
    }

    public function testGetRootInstallerDescriptor()
    {
        $this->populateRootManager();

        $descriptor = new InstallerDescriptor('custom-symlink', 'CustomSymlinkInstaller');

        $this->assertEquals($descriptor, $this->manager->getRootInstallerDescriptor('custom-symlink'));
    }

    /**
     * @expectedException \Puli\Manager\Api\Installer\NoSuchInstallerException
     * @expectedExceptionMessage foobar
     */
    public function testGetRootInstallerDescriptorFailsIfNotFound()
    {
        $this->manager->getRootInstallerDescriptor('foobar');
    }

    /**
     * @expectedException \Puli\Manager\Api\Installer\NoSuchInstallerException
     * @expectedExceptionMessage The installer "rsync" does not exist in package "vendor/root"
     */
    public function testGetRootInstallerDescriptorFailsIfNotFoundInRootPackage()
    {
        $this->populateRootManager();

        $this->manager->getRootInstallerDescriptor('rsync');
    }

    /**
     * @expectedException \Puli\Manager\Api\Installer\NoSuchInstallerException
     * @expectedExceptionMessage copy
     */
    public function testGetRootInstallerDescriptorFailsIfBuiltin()
    {
        $this->manager->getRootInstallerDescriptor('copy');
    }

    public function testGetRootInstallerDescriptors()
    {
        $this->populateRootManager();

        $descriptor1 = new InstallerDescriptor('custom-symlink', 'CustomSymlinkInstaller');
        $descriptor2 = new InstallerDescriptor('custom-copy', 'CustomCopyInstaller');

        $this->assertEquals(array(
            'custom-symlink' => $descriptor1,
            'custom-copy' => $descriptor2,
        ), $this->manager->getRootInstallerDescriptors());
    }

    public function testFindRootInstallerDescriptors()
    {
        $this->populateRootManager();

        $descriptor1 = new InstallerDescriptor('custom-symlink', 'CustomSymlinkInstaller');
        $descriptor2 = new InstallerDescriptor('custom-copy', 'CustomCopyInstaller');

        $expr1 = Expr::same('custom-symlink', InstallerDescriptor::NAME);

        $expr2 = Expr::endsWith('Installer', InstallerDescriptor::CLASS_NAME);

        $this->assertEquals(array($descriptor1), $this->manager->findRootInstallerDescriptors($expr1));
        $this->assertEquals(array($descriptor1, $descriptor2), $this->manager->findRootInstallerDescriptors($expr2));
    }

    public function testHasRootInstallerDescriptor()
    {
        $this->populateRootManager();

        $this->assertTrue($this->manager->hasRootInstallerDescriptor('custom-symlink'));
        $this->assertTrue($this->manager->hasRootInstallerDescriptor('custom-copy'));
        $this->assertFalse($this->manager->hasRootInstallerDescriptor('copy'));
        $this->assertFalse($this->manager->hasRootInstallerDescriptor('symlink'));
        $this->assertFalse($this->manager->hasRootInstallerDescriptor('rsync'));
        $this->assertFalse($this->manager->hasRootInstallerDescriptor('foobar'));
    }

    public function testHasRootInstallerDescriptors()
    {
        $this->populateRootManager();

        $this->assertTrue($this->manager->hasRootInstallerDescriptors());
        $this->assertTrue($this->manager->hasRootInstallerDescriptors(Expr::same('custom-symlink', InstallerDescriptor::NAME)));
        $this->assertFalse($this->manager->hasRootInstallerDescriptors(Expr::same('rsync', InstallerDescriptor::NAME)));
        $this->assertFalse($this->manager->hasRootInstallerDescriptors(Expr::same('foobar', InstallerDescriptor::NAME)));
    }

    public function testHasNoRootInstallerDescriptors()
    {
        $this->packageFile1->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'rsync' => (object) array(
                'class' => 'RsyncInstaller',
            ),
        ));

        $this->assertFalse($this->manager->hasRootInstallerDescriptors());
    }

    public function testAddRootInstallerDescriptor()
    {
        $this->populateDefaultManager();

        $this->packageFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
                'custom-symlink' => (object) array(
                    'class' => 'CustomSymlinkInstaller',
                ),
                'cdn' => (object) array(
                    'class' => 'CdnInstaller',
                ),
            ));

        $descriptor = new InstallerDescriptor('cdn', 'CdnInstaller');

        $this->manager->addRootInstallerDescriptor($descriptor);

        $this->assertSame($descriptor, $this->manager->getInstallerDescriptor('cdn'));
    }

    public function testAddFullyConfiguredInstallerDescriptor()
    {
        $this->populateDefaultManager();

        $this->packageFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
                'custom-symlink' => (object) array(
                    'class' => 'CustomSymlinkInstaller',
                ),
                'cdn' => (object) array(
                    'class' => 'CdnInstaller',
                    'description' => 'The description',
                    'parameters' => (object) array(
                        'required' => (object) array(
                            'required' => true,
                            'description' => 'The parameter description 1',
                        ),
                        'optional' => (object) array(
                            'description' => 'The parameter description 2',
                        ),
                        'optional-with-default' => (object) array(
                            'default' => 'foobar',
                        ),
                        'optional-empty' => (object) array(),
                    ),
                ),
            ));

        $descriptor = new InstallerDescriptor('cdn', 'CdnInstaller', 'The description', array(
            new InstallerParameter('required', InstallerParameter::REQUIRED, null, 'The parameter description 1'),
            new InstallerParameter('optional', InstallerParameter::OPTIONAL, null, 'The parameter description 2'),
            new InstallerParameter('optional-with-default', InstallerParameter::OPTIONAL, 'foobar'),
            new InstallerParameter('optional-empty'),
        ));

        $this->manager->addRootInstallerDescriptor($descriptor);

        $this->assertSame($descriptor, $this->manager->getInstallerDescriptor('cdn'));
    }

    public function testAddRootInstallerDescriptorOverridesPreviousRootInstaller()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'PreviousInstaller',
            ),
        ));

        $this->packageFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
                'custom-symlink' => (object) array(
                    'class' => 'NewInstaller',
                ),
            ));

        $descriptor = new InstallerDescriptor('custom-symlink', 'NewInstaller');

        $this->manager->addRootInstallerDescriptor($descriptor);

        $this->assertSame($descriptor, $this->manager->getInstallerDescriptor('custom-symlink'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddRootInstallerDescriptorFailsIfInstallerExistsInOtherPackage()
    {
        $this->packageFile1->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'PreviousInstaller',
            ),
        ));

        $this->packageFileManager->expects($this->never())
            ->method('setExtraKey');

        $descriptor = new InstallerDescriptor('custom-symlink', 'NewInstaller');

        $this->manager->addRootInstallerDescriptor($descriptor);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddRootInstallerDescriptorFailsIfInstallerBuiltin()
    {
        $this->packageFileManager->expects($this->never())
            ->method('setExtraKey');

        $descriptor = new InstallerDescriptor('copy', 'NewCopyInstaller');

        $this->manager->addRootInstallerDescriptor($descriptor);
    }

    public function testAddRootInstallerDescriptorRestoresPreviousInstallerIfSavingFails()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'PreviousInstaller',
            ),
        ));

        // The new installer should be saved in the root package
        $this->packageFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY)
            ->willThrowException(new TestException());

        $previousDescriptor = new InstallerDescriptor('custom-symlink', 'PreviousInstaller');
        $newDescriptor = new InstallerDescriptor('custom-symlink', 'NewInstaller');

        try {
            $this->manager->addRootInstallerDescriptor($newDescriptor);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertEquals($previousDescriptor, $this->manager->getInstallerDescriptor('custom-symlink'));
    }

    public function testAddRootInstallerDescriptorRemovesNewInstallerIfSavingFails()
    {
        // The new installer should be saved in the root package
        $this->packageFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY)
            ->willThrowException(new TestException());

        $newDescriptor = new InstallerDescriptor('custom-symlink', 'NewInstaller');

        try {
            $this->manager->addRootInstallerDescriptor($newDescriptor);
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertFalse($this->manager->hasInstallerDescriptor('custom-symlink'));
    }

    public function testRemoveRootInstallerDescriptor()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'CustomSymlinkInstaller',
            ),
            'cdn' => (object) array(
                'class' => 'CdnInstaller',
            ),
        ));

        $this->packageFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
                'cdn' => (object) array(
                    'class' => 'CdnInstaller',
                ),
            ));

        $this->manager->removeRootInstallerDescriptor('custom-symlink');

        $this->assertTrue($this->manager->hasInstallerDescriptor('cdn'));
        $this->assertFalse($this->manager->hasInstallerDescriptor('custom-symlink'));
    }

    public function testRemoveRootInstallerDescriptorRemovesExtraKeyAfterLastInstaller()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'CustomSymlinkInstaller',
            ),
        ));

        $this->packageFileManager->expects($this->once())
            ->method('removeExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY);

        $this->manager->removeRootInstallerDescriptor('custom-symlink');

        $this->assertFalse($this->manager->hasInstallerDescriptor('custom-symlink'));
    }

    public function testRemoveRootInstallerDescriptorRestoresPreviousInstallerIfSavingFails()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'PreviousInstaller',
            ),
        ));

        // The new installer should be saved in the root package
        $this->packageFileManager->expects($this->once())
            ->method('removeExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY)
            ->willThrowException(new TestException());

        try {
            $this->manager->removeRootInstallerDescriptor('custom-symlink');
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertEquals((object) array(
            'custom-symlink' => (object) array(
                'class' => 'PreviousInstaller',
            ),
        ), $this->rootPackageFile->getExtraKey(PackageFileInstallerManager::INSTALLERS_KEY));

        $this->assertEquals(
            new InstallerDescriptor('custom-symlink', 'PreviousInstaller'),
            $this->manager->getInstallerDescriptor('custom-symlink')
        );
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testRemoveRootInstallerDescriptorFailsIfInstallerNotInRoot()
    {
        $this->packageFile1->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'CustomSymlinkInstaller',
            ),
        ));

        $this->packageFileManager->expects($this->never())
            ->method('setExtraKey');

        $this->manager->removeRootInstallerDescriptor('custom-symlink');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testRemoveRootInstallerDescriptorFailsIfInstallerBuiltin()
    {
        $this->packageFileManager->expects($this->never())
            ->method('setExtraKey');

        $this->manager->removeRootInstallerDescriptor('copy');
    }

    public function testRemoveRootInstallerDescriptorDoesNothingIfNotFound()
    {
        $this->populateDefaultManager();

        $this->packageFileManager->expects($this->never())
            ->method('setExtraKey');

        $this->manager->removeRootInstallerDescriptor('foobar');
    }

    public function testRemoveRootInstallerDescriptors()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink1' => (object) array(
                'class' => 'CustomSymlinkInstaller',
            ),
            'cdn' => (object) array(
                'class' => 'CdnInstaller',
            ),
        ));

        $this->packageFile1->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink2' => (object) array(
                'class' => 'CustomSymlinkInstaller',
            ),
        ));

        $this->packageFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
                'cdn' => (object) array(
                    'class' => 'CdnInstaller',
                ),
            ));

        $this->manager->removeRootInstallerDescriptors(Expr::startsWith('custom-symlink', InstallerDescriptor::NAME));

        $this->assertTrue($this->manager->hasInstallerDescriptor('cdn'));
        $this->assertTrue($this->manager->hasInstallerDescriptor('custom-symlink2'));
        $this->assertFalse($this->manager->hasInstallerDescriptor('custom-symlink1'));
    }

    public function testRemoveRootInstallerDescriptorsRestoresPreviousInstallersIfSavingFails()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'CustomSymlinkInstaller',
            ),
            'cdn' => (object) array(
                'class' => 'CdnInstaller',
            ),
        ));

        $this->packageFileManager->expects($this->once())
            ->method('setExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
                'cdn' => (object) array(
                    'class' => 'CdnInstaller',
                ),
            ))
            ->willThrowException(new TestException());

        try {
            $this->manager->removeRootInstallerDescriptors(Expr::startsWith('custom-symlink', InstallerDescriptor::NAME));
            $this->fail('Expected a TestException');
        } catch (TestException $e) {
        }

        $this->assertEquals((object) array(
            'custom-symlink' => (object) array(
                'class' => 'CustomSymlinkInstaller',
            ),
            'cdn' => (object) array(
                'class' => 'CdnInstaller',
            ),
        ), $this->rootPackageFile->getExtraKey(PackageFileInstallerManager::INSTALLERS_KEY));

        $this->assertTrue($this->manager->hasInstallerDescriptor('custom-symlink'));
        $this->assertTrue($this->manager->hasInstallerDescriptor('cdn'));
    }

    public function testClearRootInstallerDescriptors()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'CustomSymlinkInstaller',
            ),
            'cdn' => (object) array(
                'class' => 'CdnInstaller',
            ),
        ));

        $this->packageFileManager->expects($this->once())
            ->method('removeExtraKey')
            ->with(PackageFileInstallerManager::INSTALLERS_KEY);

        $this->manager->clearRootInstallerDescriptors();

        $this->assertFalse($this->manager->hasInstallerDescriptor('custom-symlink'));
        $this->assertFalse($this->manager->hasInstallerDescriptor('cdn'));
    }

    protected function populateDefaultManager()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'CustomSymlinkInstaller',
            ),
        ));
        $this->packageFile1->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'rsync' => (object) array(
                'class' => 'RsyncInstaller',
            ),
        ));
    }

    protected function populateRootManager()
    {
        $this->rootPackageFile->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'custom-symlink' => (object) array(
                'class' => 'CustomSymlinkInstaller',
            ),
            'custom-copy' => (object) array(
                'class' => 'CustomCopyInstaller',
            ),
        ));
        $this->packageFile1->setExtraKey(PackageFileInstallerManager::INSTALLERS_KEY, (object) array(
            'rsync' => (object) array(
                'class' => 'RsyncInstaller',
            ),
        ));
    }
}
