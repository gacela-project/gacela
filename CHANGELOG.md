# Changelog

### Unreleased

- Added command `gacela list:modules`

### 1.4.0
### 2023-05-20

- Added `Gacela::rootDir()`
- Added `GacelaConfig::enableFileCache()`
- Added plugins as callable
    - `GacelaConfig::addPlugin(string|callable)`
- Rename `addExtendConfig()` to `extendGacelaConfig()` in `GacelaConfig`
- Removed deprecated `withPhpConfigDefault()`

### 1.3.0
### 2023-05-08

- Deleted `PluginInterface`
  - A plugin can be any class that implements `__invoke()`
- Added `GacelaConfig::addExtendConfig()`
- Remove the deprecated methods `setFileCacheEnabled()` & `setFileCacheDirectory()`

### 1.2.0
### 2023-04-29

- Unify `setFileCacheEnabled` and `setFileCacheDirectory` into one single method: `setFileCache(bool $enabled, string $dir)`. Deprecated the former methods
- Rename dependency; from `resolver` to `container`.
- Moved the current `Container` logic to the decoupled `container` dependency
- Add "plugins" to run right after the `Gacela::bootstrap()`
- Deprecated `addMappingInterface()` in favor of `addBinding()`

### 1.1.1
### 2023-04-19

- Deprecate `withPhpConfigDefault()` in favor of `defaultPhpConfig()`
- Extract the dependency resolver logic into a different repo `gacela-project/resolver`

### 1.1.0
### 2023-03-21

- Allow using static facade methods
  - You can use `::getFactory()` from a static or non-static context 
- ResetInMemoryCache also from anonymous globals and factory containers

### 1.0.1
### 2023-03-12

- Normalise internal events' `toString()`
- Bugfix Register only once specific events on bootstrap

### 1.0.0
### 2023-01-01

- Allow extending raw arrays as services
- The Locator cannot resolve any more interface classes only because of the `Interface` suffix in their name
- Drop support for PHP 7.4

### 0.32.0
#### 2022-11-24

- Froze a "Container service" after its first usage with `get()`
- Added `Container::protect(service)`

### 0.31.0
#### 2022-11-15

- Added `Container::factory(service)`
- Added `Container::extend(id, service)`
- Added `GacelaConfig::extendService(id, service)`

### 0.30.1
#### 2022-11-09

- Fixed `DocBlockResolver` resolvableType
- Fixed `DocBlockResolverAwareTrait` cache

### 0.30.0
#### 2022-11-07

- Allow combine and override different `GacelaConfig` from project level
- Added internal events for the `ClassResolver\Cache` scope
- Fixed `PhpFileCache` bug

### 0.29.0
#### 2022-11-02

- Added `GacelaConfig::registerSpecificListener(event, listener)`
- Added `GacelaConfig::registerGenericListener(listener)`

### 0.28.0
#### 2022-10-27

- Add file cache for resolved classes
- Remove profiler, because it does the same as the file cache system under the hood

### 0.27.0
#### 2022-10-12

- Read autoload-dev psr-4 namespaces for gacela make commands
- Cache default resolved gacela class
- Allow optional project namespace on class name finder rules

### 0.26.0
#### 2022-10-01

- Added new feature: gacela file profiler (disabled by default)
- Removed gacela file cache. Instead, use InMemoryCache always
- Removed `gacela cache:clear` command

### 0.25.0
#### 2022-09-18

- Removed deprecated `SetupGacelaInterface` from `gacela.php`
- Allow using abstracts Factory and Config by default
- Create `gacela cache:clear` command
- Process configFn from appRootDir if exists, and it wasn't defined on bootstrap

### 0.24.0
#### 2022-07-23

- Change cache default directory to `.gacela/cache`
- Added project namespaces
  - `GacelaConfig::setProjectNamespaces(array)` to be able to resolve gacela classes with priorities
- Added gacela configuration for different environments
- Allow adding config key-values from GacelaConfig
  - `GacelaConfig::addAppConfigKeyValue(string, mixed)`
  - `GacelaConfig::addAppConfigKeyValues( array<string, mixed> )`
- When cache is disabled on bootstrap, Gacela won't generate `*.cache` files

### 0.23.1
#### 2022-06-25

- Fix `setCacheDirectory()` with nested dir levels

### 0.23.0
#### 2022-06-24

- Group gacela cache files inside a `cache/` directory
- Allow enabling/disabling cache files from the project config files
- Added `setCacheDirectory()` to `GacelaConfig`
- Added `vendor/bin/gacela` script
- Add `.editorconfig` & `.gitattributes` files
- Ignore `composer.lock`

### 0.22.0
#### 2022-06-10

- Added a (file) cache layer 
  - for class-names to their resolvable-type (in a file: `.gacela-class-names.cache`)
  - for custom-services to their resolvable-class (in a file: `.custom-services.cache`)
- Delete unnecessary Backtrace for exceptions
- Rename resetCache() to setCacheEnabled() from `GacelaConfig`

### 0.21.0
#### 2022-05-29

- Allow only a `Closure(GacelaConfig):void` object to 2nd parameter type of `Gacela::bootstrap()`
- Add new key Gacela configuration key: `GacelaConfig::setResetCache(bool)`

### 0.20.0
#### 2022-05-27

- Add `GacelaConfig::withPhpConfigDefault()`
- Allow gacela anon-classes using parent methods
- Define local pattern php config default
- Add `AbstractClassResolver::resetCache()`

### 0.19.0
#### 2022-05-19

- Removed bin/gacela util from this repo
  - CodeGenerator moved to its own repo: `gacela-project/gacela-cli`

### 0.18.1
#### 2022-05-15

- Bugfix SetupGacela using proper method from parent class

### 0.18.0
#### 2022-05-14

- Removed default config path from config/*.php to empty
- Added allow gacela.php using a callable with GacelaConfig arg
- Moved namespace from Setup to Bootstrap (affecting SetupGacela)
  - Deprecated Setup namespace in favor of Bootstrap
- Remove deprecated `globalServices()` method
- Deprecate SetupGacelaInterface from gacela.php and `Gacela::bootstrap()`. Use callable(GacelaConfig) instead

### 0.17.2
#### 2022-05-02

- Ensure GLOB_BRACE constant is defined for Alpine and Solaris OS

### 0.17.1
#### 2022-05-02

- Removing illegal c-char from filename

### 0.17.0
#### 2022-04-29

- Added DocBlockResolverAwareTrait
- Deprecated FacadeResolverAwareTrait in favor of DocBlockResolverAwareTrait
- Removed deprecated setup as array in `Gacela::bootstrap()`
- Allow overriding Gacela resolvable Facade type

### 0.16.0
#### 2022-04-14

- Combine gacela file and bootstrap setup
- Rename the concept of GlobalServices to ExternalServices
- Make the Facade accessible from module-internal sub-folders
- Allow to return an instance of SetupGacela on gacela.php

### 0.15.0
#### 2022-03-26

- Updated ClassInfo improve performance adding cache
- Renamed GlobalServices to Setup
- Added SetupGacela to replace AbstractConfigGacela
- Added support for dark mode logo

### 0.14.0
#### 2022-03-14

- Updated from protected to public the `getAppRootDir()` from `AbstractConfig`
- Updated `AbstractConfigGacela` to use builders instead of returning arrays

### 0.13.0
#### 2022-03-01

- Added allow defining a config reader as class-string too
- Moved the "config readers" next to their config item itself
  - Performance improvement specially when using different config readers in the same project
- Added OverrideResolvableTypes feature
  - Allow overriding Gacela resolvable types (Factory, Config, DependencyProvider)
- Removed deprecated methods `getApplicationRootDir()` & `setApplicationRootDir()` from Config
  - Use `getAppRootDir()` & `setAppRootDir()` instead
- Deprecated and removed `CustomService` feature. Use `MappingInterfaces` feature instead
  - Why? Too much magic

### 0.12.0
#### 2022-02-13

- Added `getAppRootDir()` to AbstractConfig
- Added `APP_ENV` environment key, to define different config files on different environments
- Added `'config-readers'` key in the globalServices and `gacela.php`
- Added `'custom-services-location'` key in the globalServices and `gacela.php`
  - Define namespaces (relative to a module) where Gacela should check for custom services that will be auto-resolved
- Deprecated `getApplicationRootDir()` from Config. Use `getAppRootDir()` instead
- Removed `EnvConfigReader` from `gacela-project/gacela`
  - If you want to read `.env` values, you should require `gacela-project/gacela-env-config-reader`

### 0.11.0
#### 2022-01-18

- Deleted deprecated array config in `gacela.php`
- Allow `null` as default config value
- The globalServices are passed into `mappingInterfaces()` and not as constructor argument

### 0.10.0
#### 2021-10-04

- Allow setup custom config from `Gacela::bootstrap()` directly

### 0.9.0
##### 2021-08-27

- Allow return JsonSerializable objects in PHP config files

### 0.8.0
##### 2021-08-16

- Updated `gacela.php` config file:
  - returning a simple array has been deprecated
  - an anonymous function that creates an anonymous class that extends from AbstractConfigGacela should be used
- Remove deprecated `gacela.json` config file

### 0.7.0
##### 2021-08-07

- Improve the flexibility from the ConfigReaders
- Deprecated `gacela.json` config file, in favor of `gacela.php`
- Added 'mapping-interfaces' key to `gacela.php`
- Added autowiring for Factory dependencies

### 0.6.0
##### 2021-07-27

- Added `AbstractClassResolver::overrideExistingResolvedClass()`
- Locator uses `AbstractClassResolver::getGlobalInstance()` before creating a new instance
- Unify the cacheKey using `GlobalKey`

### 0.5.0
##### 2021-07-19

- `Config::setConfigReaders()` create a new config instance singleton
- Added `AbstractClassResolver::addAnonymousGlobal()` you can now use anonymous classes
- Added matrix for the GitHub CI for diff PHP versions (7.4,8.0), and diff OS (mac,linux,windows)

### 0.4.0
##### 2021-07-10

- Allow multiple (and different) config files defined in `gacela.json`
- Make extensible the Config Readers

### 0.3.0
##### 2021-07-04

- Allow using config php and env files defined in `gacela.json`
- Use long name by default in the generator code commands. Optional short names

### 0.2.0
##### 2021-04-27

- Added CodeGenerator
- Refactoring Config reading all php files from config directory

### 0.1.0
##### 2021-04-02

- Added Facade, Factory, Config and DependencyProvider basic functionality
- Provide documentation for each of these concepts with examples
