<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Spatie\LaravelData\Data;

class Repository extends Data
{
    public string $title;
    public string $comment;


    /**
     * @var array|Method[] $methods;
     */
    public array $methods;
}
