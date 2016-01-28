<?php

/*
 * This file is part of the puli/manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Manager\Package;

use Puli\Discovery\Api\Type\BindingParameter;
use Puli\Discovery\Api\Type\BindingType;
use Puli\Discovery\Binding\ClassBinding;
use Puli\Discovery\Binding\ResourceBinding;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Assert\Assert;
use Rhumsaa\Uuid\Uuid;
use stdClass;
use Webmozart\Json\Conversion\JsonConverter;

/**
 * Converts package files to JSON and back.
 *
 * The JSON is validated against the schema `res/schema/package-schema.json`.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageFileConverter implements JsonConverter
{
    /**
     * The JSON version this converter supports.
     */
    const VERSION = '1.0';

    /**
     * The default order of the keys in the written package file.
     *
     * @var string[]
     */
    private static $keyOrder = array(
        'version',
        'name',
        'path-mappings',
        'bindings',
        'binding-types',
        'override',
        'extra',
    );

    public static function compareBindingDescriptors(BindingDescriptor $a, BindingDescriptor $b)
    {
        // Make sure that bindings are always printed in the same order
        return strcmp($a->getUuid()->toString(), $b->getUuid()->toString());
    }

    /**
     * {@inheritdoc}
     */
    public function toJson($packageFile, array $options = array())
    {
        Assert::isInstanceOf($packageFile, 'Puli\Manager\Api\Package\PackageFile');

        $jsonData = new stdClass();

        $this->addPackageFileToJson($packageFile, $jsonData);

        // Sort according to key order
        $jsonArray = (array) $jsonData;
        $orderedKeys = array_intersect_key(array_flip(self::$keyOrder), $jsonArray);
        $jsonData = (object) array_replace($orderedKeys, $jsonArray);

        return $jsonData;
    }

    /**
     * {@inheritdoc}
     */
    public function fromJson($jsonData, array $options = array())
    {
        Assert::isInstanceOf($jsonData, 'stdClass');

        $packageFile = new PackageFile(null, isset($options['path']) ? $options['path'] : null);

        $this->addJsonToPackageFile($jsonData, $packageFile);

        return $packageFile;
    }

    protected function addPackageFileToJson(PackageFile $packageFile, stdClass $jsonData)
    {
        $mappings = $packageFile->getPathMappings();
        $bindingDescriptors = $packageFile->getBindingDescriptors();
        $typeDescriptors = $packageFile->getTypeDescriptors();
        $overrides = $packageFile->getOverriddenPackages();
        $extra = $packageFile->getExtraKeys();

        if (null !== $packageFile->getPackageName()) {
            $jsonData->name = $packageFile->getPackageName();
        }

        if (count($mappings) > 0) {
            $jsonData->{'path-mappings'} = new stdClass();

            foreach ($mappings as $mapping) {
                $puliPath = $mapping->getRepositoryPath();
                $localPaths = $mapping->getPathReferences();

                $jsonData->{'path-mappings'}->$puliPath = count($localPaths) > 1 ? $localPaths : reset($localPaths);
            }
        }

        if (count($bindingDescriptors) > 0) {
            uasort($bindingDescriptors, array(__CLASS__, 'compareBindingDescriptors'));

            $jsonData->bindings = new stdClass();

            foreach ($bindingDescriptors as $bindingDescriptor) {
                $binding = $bindingDescriptor->getBinding();
                $bindingData = new stdClass();
                $bindingData->_class = get_class($binding);

                // This needs to be moved to external classes to allow adding
                // custom binding classes at some point
                if ($binding instanceof ResourceBinding) {
                    $bindingData->query = $binding->getQuery();

                    if ('glob' !== $binding->getLanguage()) {
                        $bindingData->language = $binding->getLanguage();
                    }
                } elseif ($binding instanceof ClassBinding) {
                    $bindingData->class = $binding->getClassName();
                }

                $bindingData->type = $bindingDescriptor->getTypeName();

                // Don't include the default values of the binding type
                if ($binding->hasParameterValues(false)) {
                    $parameterData = $binding->getParameterValues(false);
                    ksort($parameterData);
                    $bindingData->parameters = (object) $parameterData;
                }

                $jsonData->bindings->{$bindingDescriptor->getUuid()->toString()} = $bindingData;
            }
        }

        if (count($typeDescriptors) > 0) {
            $bindingTypesData = array();

            foreach ($typeDescriptors as $typeDescriptor) {
                $type = $typeDescriptor->getType();
                $typeData = new stdClass();

                if ($typeDescriptor->getDescription()) {
                    $typeData->description = $typeDescriptor->getDescription();
                }

                if ($type->hasParameters()) {
                    $parametersData = array();

                    foreach ($type->getParameters() as $parameter) {
                        $parameterData = new stdClass();

                        if ($typeDescriptor->hasParameterDescription($parameter->getName())) {
                            $parameterData->description = $typeDescriptor->getParameterDescription($parameter->getName());
                        }

                        if ($parameter->isRequired()) {
                            $parameterData->required = true;
                        }

                        if (null !== $parameter->getDefaultValue()) {
                            $parameterData->default = $parameter->getDefaultValue();
                        }

                        $parametersData[$parameter->getName()] = $parameterData;
                    }

                    ksort($parametersData);

                    $typeData->parameters = (object) $parametersData;
                }

                $bindingTypesData[$type->getName()] = $typeData;
            }

            ksort($bindingTypesData);

            $jsonData->{'binding-types'} = (object) $bindingTypesData;
        }

        if (count($overrides) > 0) {
            $jsonData->override = count($overrides) > 1 ? $overrides : reset($overrides);
        }

        if (count($extra) > 0) {
            $jsonData->extra = (object) $extra;
        }
    }

    protected function addJsonToPackageFile(stdClass $jsonData, PackageFile $packageFile)
    {
        if (isset($jsonData->name)) {
            $packageFile->setPackageName($jsonData->name);
        }

        if (isset($jsonData->{'path-mappings'})) {
            foreach ($jsonData->{'path-mappings'} as $path => $relativePaths) {
                $packageFile->addPathMapping(new PathMapping($path, (array) $relativePaths));
            }
        }

        if (isset($jsonData->bindings)) {
            foreach ($jsonData->bindings as $uuid => $bindingData) {
                $binding = null;
                $class = isset($bindingData->_class)
                    ? $bindingData->_class
                    : 'Puli\Discovery\Binding\ResourceBinding';

                // Move this code to external classes to allow use of custom
                // bindings
                switch ($class) {
                    case 'Puli\Discovery\Binding\ClassBinding':
                        $binding = new ClassBinding(
                            $bindingData->class,
                            $bindingData->type,
                            isset($bindingData->parameters) ? (array) $bindingData->parameters : array(),
                            Uuid::fromString($uuid)
                        );
                        break;
                    case 'Puli\Discovery\Binding\ResourceBinding':
                        $binding = new ResourceBinding(
                            $bindingData->query,
                            $bindingData->type,
                            isset($bindingData->parameters) ? (array) $bindingData->parameters : array(),
                            isset($bindingData->language) ? $bindingData->language : 'glob',
                            Uuid::fromString($uuid)
                        );
                        break;
                    default:
                        continue 2;
                }

                $packageFile->addBindingDescriptor(new BindingDescriptor($binding));
            }
        }

        if (isset($jsonData->{'binding-types'})) {
            foreach ((array) $jsonData->{'binding-types'} as $typeName => $data) {
                $parameters = array();
                $parameterDescriptions = array();

                if (isset($data->parameters)) {
                    foreach ((array) $data->parameters as $parameterName => $parameterData) {
                        $required = isset($parameterData->required) ? $parameterData->required : false;

                        $parameters[] = new BindingParameter(
                            $parameterName,
                            $required ? BindingParameter::REQUIRED : BindingParameter::OPTIONAL,
                            isset($parameterData->default) ? $parameterData->default : null
                        );

                        if (isset($parameterData->description)) {
                            $parameterDescriptions[$parameterName] = $parameterData->description;
                        };
                    }
                }

                $packageFile->addTypeDescriptor(new BindingTypeDescriptor(
                    new BindingType($typeName, $parameters),
                    isset($data->description) ? $data->description : null,
                    $parameterDescriptions
                ));
            }
        }

        if (isset($jsonData->override)) {
            $packageFile->setOverriddenPackages((array) $jsonData->override);
        }

        if (isset($jsonData->extra)) {
            $packageFile->setExtraKeys((array) $jsonData->extra);
        }
    }
}
