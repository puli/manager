<?php

/*
 * This file is part of the puli/repository-manager package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\RepositoryManager\Generator;

use Puli\RepositoryManager\Config\Config;
use Webmozart\PathUtil\Path;

/**
 * Generates the code of the registry class.
 *
 * The registry class is later used to retrieve repository and discovery
 * instances. This is needed to use the same repository/discovery factory code
 * in the CLI application and the users's web application.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RegistryGenerator
{
    /**
     * The name of the resource repository variable.
     */
    const REPO_VAR_NAME = '$repo';

    /**
     * The name of the resource discovery variable.
     */
    const DISCOVERY_VAR_NAME = '$discovery';

    /**
     * @var GeneratorFactory
     */
    private $factory;

    /**
     * Creates a new registry generator.
     *
     * @param GeneratorFactory $factory The factory used to create the
     *                                  generators for the individual repository
     *                                  and discovery types.
     */
    public function __construct(GeneratorFactory $factory = null)
    {
        $this->factory = $factory ?: new GeneratorFactory();
    }

    /**
     * Generates the registry class at the given path.
     *
     * @param string $rootDir The root directory of the project.
     * @param Config $config  The configuration storing the generator settings.
     */
    public function generateRegistry($rootDir, Config $config)
    {
        $path = Path::makeAbsolute($config->get(Config::REGISTRY_FILE), $rootDir);
        $outputDir = Path::getDirectory($path);

        $variables = $this->generateVariables(
            $config,
            $this->generateRepositoryCode($outputDir, $rootDir, $config),
            $this->generateDiscoveryCode($outputDir, $rootDir, $config)
        );

        $source = $this->generateSource($variables);

        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        file_put_contents($path, $source);
    }

    /**
     * Indents a block of source code for the given number of spaces.
     *
     * @param string $source     Some source code.
     * @param int    $nbOfSpaces The number of spaces to indent.
     *
     * @return string The indented source code.
     */
    protected function indent($source, $nbOfSpaces)
    {
        $prefix = str_repeat(' ', $nbOfSpaces);

        return $prefix.implode("\n".$prefix, explode("\n", $source));
    }

    /**
     * Generates the PHP source code using the given variables.
     *
     * @param array $variables The variables used in the generator template.
     *
     * @return string The PHP source code of the registry class.
     */
    private function generateSource(array $variables)
    {
        extract($variables);

        ob_start();

        require __DIR__.'/../../res/php/registry.tpl.php';

        return "<?php\n".ob_get_clean();
    }

    /**
     * Generates the variables needed by the generator template.
     *
     * @param Config      $config        The configuration.
     * @param FactoryCode $repoCode      The factory code of the repository.
     * @param FactoryCode $discoveryCode The factory code of the discovery.
     *
     * @return array A mapping of variable names to values.
     */
    private function generateVariables(Config $config, FactoryCode $repoCode, FactoryCode $discoveryCode)
    {
        $fqcn = trim($config->get(Config::REGISTRY_CLASS, 'Puli\PuliRegistry'), '\\');
        $pos = strrpos($fqcn, '\\');

        $variables = array();
        $variables['namespace'] = false !== $pos ? substr($fqcn, 0, $pos) : '';
        $variables['shortClassName'] = false !== $pos ? substr($fqcn, $pos + 1) : $fqcn;

        $variables['imports'] = array_unique(array_merge(
            $repoCode->getImports(),
            $discoveryCode->getImports(),
            array(
                'Puli\Repository\Api\ResourceRepository',
                'Puli\Discovery\Api\ResourceDiscovery',
            )
        ));

        sort($variables['imports']);

        $variables['repoDeclarations'] = $repoCode->getVarDeclarations();
        $variables['repoVarName'] = self::REPO_VAR_NAME;

        $variables['discoveryDeclarations'] = $discoveryCode->getVarDeclarations();
        $variables['discoveryVarName'] = self::DISCOVERY_VAR_NAME;

        return $variables;
    }

    /**
     * Generates the factory code for the resource repository.
     *
     * @param string $outputDir The directory where the generated file is placed.
     * @param string $rootDir   The root directory of the project.
     * @param Config $config    The configuration.
     *
     * @return FactoryCode The generated code.
     */
    private function generateRepositoryCode($outputDir, $rootDir, Config $config)
    {
        $generator = $this->factory->createRepositoryGenerator($config->get(Config::REPOSITORY_TYPE));

        return $generator->generateFactoryCode(
            self::REPO_VAR_NAME,
            $outputDir,
            $rootDir,
            $this->camelizeKeys($config->get(Config::REPOSITORY)),
            $this->factory
        );
    }

    /**
     * Generates the factory code for the resource discovery.
     *
     * @param string $outputDir The directory where the generated file is placed.
     * @param string $rootDir   The root directory of the project.
     * @param Config $config    The configuration.
     *
     * @return FactoryCode The generated code.
     */
    private function generateDiscoveryCode($outputDir, $rootDir, Config $config)
    {
        $generator = $this->factory->createDiscoveryGenerator($config->get(Config::DISCOVERY_TYPE));

        $code = $generator->generateFactoryCode(
            self::DISCOVERY_VAR_NAME,
            $outputDir,
            $rootDir,
            $this->camelizeKeys($config->get(Config::DISCOVERY)),
            $this->factory
        );

        $result = new FactoryCode();
        $result->addImports($code->getImports());
        $result->addVarDeclaration(self::REPO_VAR_NAME, self::REPO_VAR_NAME.' = self::getRepository();');
        $result->addVarDeclarations($code->getVarDeclarations());

        return $result;
    }

    /**
     * Recursively camelizes the keys of an array.
     *
     * @param array $array The array to process.
     *
     * @return array The input array with camelized keys.
     */
    private function camelizeKeys(array $array)
    {
        $camelized = array();

        foreach ($array as $key => $value) {
            $camelized[$this->camelize($key)] = is_array($value)
                ? $this->camelizeKeys($value)
                : $value;
        }

        return $camelized;
    }

    /**
     * Camelizes a string.
     *
     * @param string $string A string.
     *
     * @return string The camelized string.
     */
    private function camelize($string)
    {
        return preg_replace_callback('/\W+([a-z])/', function ($matches) {
            return strtoupper($matches[1]);
        }, $string);
    }
}
