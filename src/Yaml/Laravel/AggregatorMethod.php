<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Spatie\LaravelData\Data;

class AggregatorMethod  extends Data
{
    public string $title;
    public string $comment;


    /** @var ControllerMethodFields $controller_fields */
    public ControllerMethodFields $controller_fields;

    /**
     * @var array|InputParam[] $inputParams;
     */
    public ?array $inputParams;

    /**
     * @var array|OutputParam[] $outputParams;
     */
    public ?array $outputParams;
}
