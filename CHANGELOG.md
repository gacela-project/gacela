# Changelog

### 0.11.0
#### 2022-01-18

- Deleted deprecated array config in gacela.php
- Allow null as default config value
- The globalServices are passed into mappingInterfaces() and not as constructor argument.

### 0.10.0
#### 2021-10-04

- Allow setup custom config from Gacela::bootstrap directly.

### 0.9.0
##### 2021-08-27

- Allow return JsonSerializable objects in PHP config files.

### 0.8.0
##### 2021-08-16

- Updated `gacela.php` config file:
  - returning a simple array has been deprecated,
  - an anonymous function that creates an anonymous class that extends from `AbstractConfigGacela` should be used.
- Remove deprecated `gacela.json` config file.

### 0.7.0
##### 2021-08-07

- Improve the flexibility from the ConfigReaders.
- Deprecated `gacela.json` config file, in favor of `gacela.php`.
- Added 'mapping-interfaces' key to `gacela.php`.
- Added autowiring for Factory dependencies.

### 0.6.0
##### 2021-07-27

- Added AbstractClassResolver::overrideExistingResolvedClass().
- Locator uses AbstractClassResolver::getGlobalInstance() before creating a new instance.
- Unify the cacheKey using GlobalKey.

### 0.5.0
##### 2021-07-19

- Config::setConfigReaders() create a new config instance singleton.
- Added AbstractClassResolver::addAnonymousGlobal(); you can now use anonymous classes.
- Added matrix for the GitHub CI for diff PHP versions (7.4,8.0), and diff OS (mac,linux,windows).

### 0.4.0
##### 2021-07-10

- Allow multiple (and different) config files defined in `gacela.json`.
- Make extensible the Config Readers.

### 0.3.0
##### 2021-07-04

- Allow using config php and env files defined in `gacela.json`.
- Use long name by default in the generator code commands. Optional short names.

### 0.2.0
##### 2021-04-27

- Added CodeGenerator.
- Refactoring Config reading all php files from config directory.

### 0.1.0
##### 2021-04-02

- Added Facade, Factory, Config and DependencyProvider basic functionality.
- Provide documentation for each of these concepts with examples.
