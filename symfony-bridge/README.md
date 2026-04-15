# gacela/symfony-bridge

A thin compiler pass that teaches Symfony's DependencyInjection container to
honor Gacela's `#[Inject]` attribute on Symfony-managed services (most often
`Command` classes).

## Problem

Symfony autowires constructor parameters through its own container. Gacela's
`#[Inject]` attribute is recognized by Gacela's container only. On a class
managed by Symfony (e.g. a `Command`), writing `#[Inject]` has no effect —
Symfony's autowire claims the parameter first, and Gacela never gets a chance
to resolve it.

## Solution

Register `GacelaInjectCompilerPass` in your Symfony kernel. At compile time
the pass walks every service definition, looks at each constructor parameter
for `#[Inject]`, and rewrites the argument so Symfony resolves that slot via
Gacela's container instead of its own autowire.

If both containers claim the same parameter the pass fails the build with a
clear message identifying the service and parameter.

## Install

```
composer require gacela-project/symfony-bridge
```

## Use

```php
use Gacela\SymfonyBridge\GacelaInjectCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$container = new ContainerBuilder();
$container->addCompilerPass(new GacelaInjectCompilerPass());
```

The Gacela container must be registered in Symfony as a service named
`gacela.container` so the rewritten arguments can resolve through it at
runtime. Typically done in a bootstrap or a bundle's extension:

```php
$container->set('gacela.container', Gacela::container());
```

## Status

Experimental. API may change until it graduates out of `gacela/gacela`'s
`symfony-bridge/` subfolder.
