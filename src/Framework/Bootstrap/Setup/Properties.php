<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Setup;

use Closure;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;

/**
 * Value object that holds all SetupGacela properties.
 */
final class Properties
{
    /** @var callable(AppConfigBuilder):void */
    public $appConfigFn;

    /** @var callable(BindingsBuilder,array<string,mixed>):void */
    public $bindingsFn;

    /** @var callable(SuffixTypesBuilder):void */
    public $suffixTypesFn;

    /** @var ?array<string,class-string|object|callable> */
    public ?array $externalServices = null;

    public ?AppConfigBuilder $appConfigBuilder = null;

    public ?SuffixTypesBuilder $suffixTypesBuilder = null;

    public ?BindingsBuilder $bindingsBuilder = null;

    public ?bool $shouldResetInMemoryCache = null;

    public ?bool $fileCacheEnabled = null;

    public ?string $fileCacheDirectory = null;

    /** @var ?list<string> */
    public ?array $projectNamespaces = null;

    /** @var ?array<string,mixed> */
    public ?array $configKeyValues = null;

    public ?bool $areEventListenersEnabled = null;

    /** @var ?list<callable> */
    public ?array $genericListeners = null;

    /** @var ?array<class-string,list<callable>> */
    public ?array $specificListeners = null;

    public ?EventDispatcherInterface $eventDispatcher = null;

    /** @var ?array<string,list<Closure>> */
    public ?array $servicesToExtend = null;

    /** @var ?list<class-string> */
    public ?array $gacelaConfigsToExtend = null;

    /** @var ?list<class-string|callable> */
    public ?array $plugins = null;

    public function __construct()
    {
        $emptyFn = static function (): void {};

        $this->appConfigFn = $emptyFn;
        $this->bindingsFn = $emptyFn;
        $this->suffixTypesFn = $emptyFn;
    }
}
