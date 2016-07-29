The Puli Manager Component
==========================

[![Build Status](https://travis-ci.org/puli/manager.svg?branch=master)](https://travis-ci.org/puli/manager)
[![Build status](https://ci.appveyor.com/api/projects/status/eb5apotdnp0h021b/branch/master?svg=true)](https://ci.appveyor.com/project/webmozart/manager/branch/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/puli/manager/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/puli/manager/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/puli/manager/v/stable.svg)](https://packagist.org/packages/puli/manager)
[![Total Downloads](https://poser.pugx.org/puli/manager/downloads.svg)](https://packagist.org/packages/puli/manager)
[![Dependency Status](https://www.versioneye.com/php/puli:manager/1.0.0/badge.svg)](https://www.versioneye.com/php/puli:manager/1.0.0)

Latest release: [1.0.0-beta10](https://packagist.org/packages/puli/manager#1.0.0-beta10)

PHP >= 5.3.9

The [Puli] Manager Component builds a [resource repository] and [discovery] from 
a puli.json configuration in the root of your project:

```json
{
    "path-mappings": {
        "/app": "res"
    }
}
```

This mapping can be loaded with the [`RepositoryManager`]:

```php
use Puli\Manager\Api\Container;

$puli = new Container(getcwd());
$puli->start();

$repoManager = $puli->getRepositoryManager();
$repoManager->buildRepository();
```

The [`RepositoryManager`] also supports methods to manipulate the puli.json.

Modules
-------

A puli.json configuration can also be placed in any module installed in your
project. This module needs to be registered with Puli with the 
[`ModuleManager`]:

```php
$moduleManager = $puli->getModuleManager();

$moduleManager->installModule('path/to/module', 'vendor/module-name');
```

Usually, modules are installed automatically by Puli's [Composer Plugin].

Managers
--------

The following is a table of all managers supported by this package:

Class                      | Description
-------------------------- | -------------
[`RepositoryManager`]      | Manages resource mappings and builds [`ResourceRepository`] instances
[`DiscoveryManager`]       | Manages bindings and binding types and builds [`Discovery`] instances
[`AssetManager`]           | Manages asset mappings used by the [`UrlGenerator`]
[`ServerManager`]          | Manages servers used by the [`UrlGenerator`]
[`FactoryManager`]         | Manages the generation of the `GeneratedPuliFactory` class
[`ModuleManager`]          | Manages the installed modules
[`ConfigFileManager`]      | Manages changes to a global `config.json` file
[`RootModuleFileManager`]  | Manages changes to the `puli.json` file of the project

Authors
-------

* [Bernhard Schussek] a.k.a. [@webmozart]
* [The Community Contributors]

Installation
------------

Follow the [Installation guide] guide to install Puli in your project.

Documentation
-------------

Read the [Puli Documentation] if you want to learn more about Puli.

Contribute
----------

Contributions to are very welcome!

* Report any bugs or issues you find on the [issue tracker].
* You can grab the source code at Puli’s [Git repository].

Support
-------

If you are having problems, send a mail to bschussek@gmail.com or shout out to
[@webmozart] on Twitter.

License
-------

All contents of this package are licensed under the [MIT license].

[Puli]: http://puli.io
[resource repository]: https://github.com/puli/repository
[discovery]: https://github.com/puli/discovery
[Composer Plugin]: https://github.com/puli/composer-plugin
[Bernhard Schussek]: http://webmozarts.com
[The Community Contributors]: https://github.com/puli/manager/graphs/contributors
[Installation guide]: http://docs.puli.io/en/latest/installation.html
[Puli Documentation]: http://docs.puli.io/en/latest/index.html
[Puli at a Glance]: http://docs.puli.io/en/latest/at-a-glance.html
[issue tracker]: https://github.com/puli/issues/issues
[Git repository]: https://github.com/puli/manager
[@webmozart]: https://twitter.com/webmozart
[MIT license]: LICENSE
[`RepositoryManager`]: http://api.puli.io/latest/class-Puli.Manager.Api.Repository.RepositoryManager.html
[`ModuleManager`]: http://api.puli.io/latest/class-Puli.Manager.Api.Module.ModuleManager.html
[`DiscoveryManager`]: http://api.puli.io/latest/class-Puli.Manager.Api.Discovery.DiscoveryManager.html
[`AssetManager`]: http://api.puli.io/latest/class-Puli.Manager.Api.Asset.AssetManager.html
[`ServerManager`]: http://api.puli.io/latest/class-Puli.Manager.Api.Server.ServerManager.html
[`FactoryManager`]: http://api.puli.io/latest/class-Puli.Manager.Api.Factory.FactoryManager.html
[`ConfigFileManager`]: http://api.puli.io/latest/class-Puli.Manager.Api.Config.ConfigFileManager.html
[`RootModuleFileManager`]: http://api.puli.io/latest/class-Puli.Manager.Api.Module.RootModuleFileManager.html
[`ResourceRepository`]: http://api.puli.io/latest/class-Puli.Repository.Api.ResourceRepository.html
[`Discovery`]: http://api.puli.io/latest/class-Puli.Discovery.Api.Discovery.html
[`UrlGenerator`]: http://api.puli.io/latest/class-Puli.UrlGenerator.Api.UrlGenerator.html
