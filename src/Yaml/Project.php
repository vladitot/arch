<?php

namespace Vladitot\Architect\Yaml;

use Spatie\LaravelData\Data;

class Project extends Data
{
    public string $title;
    /**
     * @var array|ModulePath[] $modulePaths;
     */
    public array $modulePaths;
}
