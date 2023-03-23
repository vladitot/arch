<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Spatie\LaravelData\Data;

class Model extends Data
{
    public string $title;
    public ?string $comment = '';
    public bool $generate_migration;

    /**
     * @var array|ModelField[] $model_fields;
     */
    public array $model_fields;


    /**
     * @var array|AdditionalMigration[] $additional_migrations;
     */
    public ?array $additional_migrations = [];
}
