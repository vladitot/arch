<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Spatie\LaravelData\Data;

class Method extends Data
{
    public string $title;
    public string $comment;

    /** @var array|InputParam[] $inputParams */
    public ?array $inputParams;

    /**
     * @var array|OutputParam[] $outputParams
     */
    public ?array $outputParams;
}
