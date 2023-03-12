<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Spatie\LaravelData\Data;

class ControllerMethodFields extends Data
{
    public string $route;
    public string $http_method;
}
