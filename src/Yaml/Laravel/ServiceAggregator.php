<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Vladitot\Architect\Yaml\Memory;
use Spatie\LaravelData\Data;

class ServiceAggregator extends Data
{
    public string $title;
    public ?string $comment = '';

    /**
     * @var array|AggregatorMethod[] $methods;
     */
    public array $methods = [];

    /**
     * @var array|string[] $services;
     * @autocompleteFunction getServicesFromModule
     */
    public ?array $services = [];

    /**
     * @var array|string[] $repositories;
     * @autocompleteFunction getRepositoriesFromModule
     */
    public ?array $repositories = [];

    public static function getServicesFromModule() {
        $currentModulePath = Memory::$currentModuleName;
        $currentModule = Memory::$modules[$currentModulePath];
        $result = [];
        if (!isset($currentModule->services)) return [];
        foreach ($currentModule->services as $service) {
            $result[] = $service->title;
        }
        return $result;
    }

    public static function getRepositoriesFromModule() {
        $currentModulePath = Memory::$currentModuleName;
        $currentModule = Memory::$modules[$currentModulePath];
        $result = [];
        if (!isset($currentModule->repositories)) return [];
        foreach ($currentModule->repositories as $repository) {
            $result[] = $repository->title;
        }
        return $result;
    }
}
