<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Tests\Api\Installer\Validation;

use PHPUnit_Framework_TestCase;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Installer\InstallerParameter;
use Puli\Manager\Api\Installer\Validation\ConstraintViolation;
use Puli\Manager\Api\Installer\Validation\InstallerParameterValidator;

/**
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallerParameterValidatorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var InstallerParameterValidator
     */
    private $validator;

    protected function setUp()
    {
        $this->validator = new InstallerParameterValidator();
    }

    public function testSuccess()
    {
        $descriptor = new InstallerDescriptor('installer', 'Class\Name', null, array(
            new InstallerParameter('optional'),
            new InstallerParameter('required', InstallerParameter::REQUIRED),
        ));

        $values = array(
            'optional' => 'value',
            'required' => 'value',
        );

        $this->assertSame(array(), $this->validator->validate($values, $descriptor));
    }

    public function testMissingParameter()
    {
        $descriptor = new InstallerDescriptor('installer', 'Class\Name', null, array(
            new InstallerParameter('required', InstallerParameter::REQUIRED),
        ));

        $this->assertEquals(array(
            new ConstraintViolation(ConstraintViolation::MISSING_PARAMETER, array(), 'installer', 'required'),
        ), $this->validator->validate(array(), $descriptor));
    }

    public function testExtraParameter()
    {
        $descriptor = new InstallerDescriptor('installer', 'Class\Name');

        $this->assertEquals(array(
            new ConstraintViolation(ConstraintViolation::NO_SUCH_PARAMETER, 'foobar', 'installer', 'param'),
        ), $this->validator->validate(array(
            'param' => 'foobar',
        ), $descriptor));
    }
}
