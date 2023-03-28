<?php

namespace Vladitot\Architect\Yaml;

use Spatie\LaravelData\Data;

class Project extends Data
{
    public string $title = 'change-me';
    /**
     * @var array|ModulePath[] $modulePaths;
     */
    public array $modulePaths = [];

    public string $infra_version = 'v0.0.1';

    public ?string $image = 'put-image-here';

}
