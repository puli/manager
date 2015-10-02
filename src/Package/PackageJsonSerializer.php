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
use Puli\Manager\Api\Config\Config;
use Puli\Manager\Api\Discovery\BindingDescriptor;
use Puli\Manager\Api\Discovery\BindingTypeDescriptor;
use Puli\Manager\Api\Environment;
use Puli\Manager\Api\InvalidConfigException;
use Puli\Manager\Api\Package\InstallInfo;
use Puli\Manager\Api\Package\PackageFile;
use Puli\Manager\Api\Package\PackageFileSerializer;
use Puli\Manager\Api\Package\RootPackageFile;
use Puli\Manager\Api\Package\UnsupportedVersionException;
use Puli\Manager\Api\Repository\PathMapping;
use Puli\Manager\Migration\MigrationManager;
use Rhumsaa\Uuid\Uuid;
use stdClass;
use Webmozart\Json\DecodingFailedException;
use Webmozart\Json\JsonDecoder;
use Webmozart\Json\JsonEncoder;
use Webmozart\Json\JsonValidator;
use Webmozart\PathUtil\Path;

/**
 * Serializes and unserializes package files to/from JSON.
 *
 * The JSON is validated against the schema `res/schema/package-schema.json`.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PackageJsonSerializer implements PackageFileSerializer
{
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
        'override-order',
        'config',
        'plugins',
        'extra',
        'packages',
    );

    /**
     * @var MigrationManager
     */
    private $migrationManager;

    /**
     * @var string
     */
    private $schemaDir;

    /**
     * @var string
     */
    private $targetVersion;

    /**
     * @var string[]
     */
    private $knownVersions;

    public static function compareBindingDescriptors(BindingDescriptor $a, BindingDescriptor $b)
    {
        // Make sure that bindings are always printed in the same order
        return strcmp($a->getUuid()->toString(), $b->getUuid()->toString());
    }

    /**
     * Creates a new serializer.
     *
     * @param MigrationManager $migrationManager The manager for migrating
     *                                           puli.json files between
     *                                           versions.
     * @param string           $schemaDir        The directory that contains the
     *                                           schema files.
     * @param string           $targetVersion    The file version that this
     *                                           serializer reads and produces.
     */
    public function __construct(MigrationManager $migrationManager, $schemaDir, $targetVersion = PackageFile::DEFAULT_VERSION)
    {
        $this->migrationManager = $migrationManager;
        $this->targetVersion = $targetVersion;
        $this->knownVersions = $this->migrationManager->getKnownVersions();

        if (!in_array($targetVersion, $this->knownVersions, true)) {
            $this->knownVersions[] = $targetVersion;
            usort($this->knownVersions, 'version_compare');
        }

        // We can't use realpath(), which doesn't work inside PHARs.
        // However, we want to display nice paths if the file is not found.
        $this->schemaDir = Path::canonicalize($schemaDir);
    }

    /**
     * {@inheritdoc}
     */
    public function serializePackageFile(PackageFile $packageFile)
    {
        $this->assertVersionSupported($packageFile->getVersion());

        $jsonData = (object) array('version' => $this->targetVersion);

        $this->packageFileToJson($packageFile, $jsonData);

        // Sort according to key order
        $jsonArray = (array) $jsonData;
        $orderedKeys = array_intersect_key(array_flip(self::$keyOrder), $jsonArray);
        $jsonData = (object) array_replace($orderedKeys, $jsonArray);

        $this->migrationManager->migrate($jsonData, $packageFile->getVersion());

        return $this->encode($jsonData, $packageFile->getPath());
    }

    /**
     * {@inheritdoc}
     */
    public function serializeRootPackageFile(RootPackageFile $packageFile)
    {
        $this->assertVersionSupported($packageFile->getVersion());

        $jsonData = (object) array('version' => $this->targetVersion);

        $this->packageFileToJson($packageFile, $jsonData);
        $this->rootPackageFileToJson($packageFile, $jsonData);

        // Sort according to key order
        $jsonArray = (array) $jsonData;
        $orderedKeys = array_intersect_key(array_flip(self::$keyOrder), $jsonArray);
        $jsonData = (object) array_replace($orderedKeys, $jsonArray);

        $this->migrationManager->migrate($jsonData, $packageFile->getVersion());

        return $this->encode($jsonData, $packageFile->getPath());
    }

    /**
     * {@inheritdoc}
     */
    public function unserializePackageFile($serialized, $path = null)
    {
        $packageFile = new PackageFile(null, $path);

        $jsonData = $this->decode($serialized, $path);

        // Remember original version of the package file
        $packageFile->setVersion($jsonData->version);

        // Migrate to the expected version
        $this->migrationManager->migrate($jsonData, $this->targetVersion);

        $this->jsonToPackageFile($jsonData, $packageFile);

        return $packageFile;
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeRootPackageFile($serialized, $path = null, Config $baseConfig = null)
    {
        $packageFile = new RootPackageFile(null, $path, $baseConfig);

        $jsonData = $this->decode($serialized, $path);

        // Remember original version of the package file
        $packageFile->setVersion($jsonData->version);

        // Migrate to the expected version
        $this->migrationManager->migrate($jsonData, $this->targetVersion);

        $this->jsonToPackageFile($jsonData, $packageFile);
        $this->jsonToRootPackageFile($jsonData, $packageFile);

        return $packageFile;
    }

    private function packageFileToJson(PackageFile $packageFile, stdClass $jsonData)
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
                    $bindingData->parameters = $binding->getParameterValues(false);
                    ksort($bindingData->parameters);
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

    private function rootPackageFileToJson(RootPackageFile $packageFile, stdClass $jsonData)
    {
        $overrideOrder = $packageFile->getOverrideOrder();
        $installInfos = $packageFile->getInstallInfos();

        // Pass false to exclude base configuration values
        $configValues = $packageFile->getConfig()->toRawArray(false);

        if (count($overrideOrder) > 0) {
            $jsonData->{'override-order'} = $overrideOrder;
        }

        if (count($configValues) > 0) {
            $jsonData->config = (object) $configValues;
        }

        if (array() !== $packageFile->getPluginClasses()) {
            $jsonData->plugins = $packageFile->getPluginClasses();

            sort($jsonData->plugins);
        }

        if (count($installInfos) > 0) {
            $packagesData = array();

            foreach ($installInfos as $installInfo) {
                $installData = new stdClass();
                $installData->{'install-path'} = $installInfo->getInstallPath();

                if (InstallInfo::DEFAULT_INSTALLER_NAME !== $installInfo->getInstallerName()) {
                    $installData->installer = $installInfo->getInstallerName();
                }

                if ($installInfo->hasDisabledBindingUuids()) {
                    $installData->{'disabled-bindings'} = array();

                    foreach ($installInfo->getDisabledBindingUuids() as $uuid) {
                        $installData->{'disabled-bindings'}[] = $uuid->toString();
                    }

                    sort($installData->{'disabled-bindings'});
                }

                if (Environment::PROD !== $installInfo->getEnvironment()) {
                    $installData->env = $installInfo->getEnvironment();
                }

                $packagesData[$installInfo->getPackageName()] = $installData;
            }

            ksort($packagesData);

            $jsonData->packages = (object) $packagesData;
        }
    }

    private function jsonToPackageFile(stdClass $jsonData, PackageFile $packageFile)
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
                        continue;
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

    private function jsonToRootPackageFile(stdClass $jsonData, RootPackageFile $packageFile)
    {
        if (isset($jsonData->{'override-order'})) {
            $packageFile->setOverrideOrder((array) $jsonData->{'override-order'});
        }

        if (isset($jsonData->plugins)) {
            $packageFile->setPluginClasses($jsonData->plugins);
        }

        if (isset($jsonData->config)) {
            $config = $packageFile->getConfig();

            foreach ($this->objectsToArrays($jsonData->config) as $key => $value) {
                $config->set($key, $value);
            }
        }

        if (isset($jsonData->packages)) {
            foreach ($jsonData->packages as $packageName => $packageData) {
                $installInfo = new InstallInfo($packageName, $packageData->{'install-path'});

                if (isset($packageData->env)) {
                    $installInfo->setEnvironment($packageData->env);
                }

                if (isset($packageData->installer)) {
                    $installInfo->setInstallerName($packageData->installer);
                }

                if (isset($packageData->{'disabled-bindings'})) {
                    foreach ($packageData->{'disabled-bindings'} as $uuid) {
                        $installInfo->addDisabledBindingUuid(Uuid::fromString($uuid));
                    }
                }

                $packageFile->addInstallInfo($installInfo);
            }
        }
    }

    private function encode(stdClass $jsonData, $path = null)
    {
        $encoder = new JsonEncoder();
        $encoder->setPrettyPrinting(true);
        $encoder->setEscapeSlash(false);
        $encoder->setTerminateWithLineFeed(true);

        $this->validate($jsonData, $path);

        return $encoder->encode($jsonData);
    }

    private function decode($json, $path = null)
    {
        $decoder = new JsonDecoder();

        try {
            $jsonData = $decoder->decode($json);
        } catch (DecodingFailedException $e) {
            throw new InvalidConfigException(sprintf(
                "The configuration%s could not be decoded:\n%s",
                $path ? ' in '.$path : '',
                $e->getMessage()
            ), $e->getCode(), $e);
        }

        $this->assertVersionSupported($jsonData->version, $path);

        $this->validate($jsonData, $path);

        return $jsonData;
    }

    private function objectsToArrays($data)
    {
        $data = (array) $data;

        foreach ($data as $key => $value) {
            $data[$key] = is_object($value) ? $this->objectsToArrays($value) : $value;
        }

        return $data;
    }

    private function assertVersionSupported($version, $path = null)
    {
        if (!in_array($version, $this->knownVersions, true)) {
            throw UnsupportedVersionException::forVersion(
                $version,
                $this->knownVersions,
                $path
            );
        }
    }

    private function validate($jsonData, $path = null)
    {
        $validator = new JsonValidator();
        $schema = $this->schemaDir.'/package-schema-'.$jsonData->version.'.json';

        if (!file_exists($schema)) {
            throw new InvalidConfigException(sprintf(
                'The JSON schema file for version %s was not found%s.',
                $jsonData->version,
                $path ? ' in '.$path : ''
            ));
        }

        $errors = $validator->validate($jsonData, $schema);

        if (count($errors) > 0) {
            throw new InvalidConfigException(sprintf(
                "The configuration%s is invalid:\n%s",
                $path ? ' in '.$path : '',
                implode("\n", $errors)
            ));
        }
    }
}
