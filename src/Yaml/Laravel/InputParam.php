<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Spatie\LaravelData\Data;

class InputParam extends Data
{
    public string $title;

    /**
     * @var string $type
     * @autocompleteFunction types
     */
    public string $type;

    public ?bool $mandatory = true;

    public ?string $validation_rules = '';

    public static function types() {
        return [
            'string',
            'bool',
            'int',
            'float',
            'array',
        ];
    }

    /**
     * @var array|InputParam[] $childrenParams
     * @maxRecursion 3
     */
    public ?array $childrenParams = [];
}
