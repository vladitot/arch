<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Spatie\LaravelData\Data;

class AggregatorMethod  extends Data
{
    public string $title;
    public string $comment;


    public ?ControllerMethodFields $controller_fields = null;

    /**
     * @var array|InputParam[] $inputParams;
     */
    public ?array $inputParams;

    /**
     * @var array|OutputParam[] $outputParams;
     */
    public ?array $outputParams;
}
