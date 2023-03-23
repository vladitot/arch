<?php

namespace Vladitot\Architect\Yaml;

use Spatie\LaravelData\Data;
use Vladitot\Architect\Yaml\Laravel\InfraAnchor;

class Project extends Data
{
    public string $title;
    /**
     * @var array|ModulePath[] $modulePaths;
     */
    public array $modulePaths;

    public string $infra_version;

    public ?string $image = 'put-image-here';

    public string $base_domain;


}
