<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Spatie\LaravelData\Data;

class AdditionalMigration extends Data
{
    public string $title;
    public ?string $comment;

}
