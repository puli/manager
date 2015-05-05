<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Api\Installer\Validation;

use Puli\Manager\Api\Installer\InstallerDescriptor;

/**
 * Validates parameter values against the constraints defined by an installer
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallerParameterValidator
{
    /**
     * Returns whether the given parameter values are valid.
     *
     * @param array $parameterValues          The parameter values to
     *                                        validate.
     * @param InstallerDescriptor $descriptor The installer descriptor to
     *                                        validate the values for.
     *
     * @return ConstraintViolation[] The found violations. If no violations were
     *                               found, an empty array is returned.
     */
    public function validate(array $parameterValues, InstallerDescriptor $descriptor)
    {
        $violations = array();

        foreach ($parameterValues as $name => $value) {
            if (!$descriptor->hasParameter($name)) {
                $violations[] = new ConstraintViolation(
                    ConstraintViolation::NO_SUCH_PARAMETER,
                    $value,
                    $descriptor->getName(),
                    $name
                );
            }
        }

        foreach ($descriptor->getParameters() as $parameter) {
            if (!isset($parameterValues[$parameter->getName()])) {
                if ($parameter->isRequired()) {
                    $violations[] = new ConstraintViolation(
                        ConstraintViolation::MISSING_PARAMETER,
                        $parameterValues,
                        $descriptor->getName(),
                        $parameter->getName()
                    );
                }
            }
        }

        return $violations;
    }
}
