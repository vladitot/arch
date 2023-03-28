<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Vladitot\Architect\Yaml\Memory;
use Spatie\LaravelData\Data;

class Service extends Data
{
    public string $title;
    public string $comment;

    /**
     * @var array|Method[] $methods;
     */
    public array $methods = [];

    /**
     * @var array|string[] $repositories;
     * @autocompleteFunction getRepositoriesFromModule
     */
    public ?array $repositories = [];

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
