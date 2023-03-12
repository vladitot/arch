<?php

namespace Vladitot\Architect\Yaml;

use Vladitot\Architect\Yaml\Laravel\Model;
use Vladitot\Architect\Yaml\Laravel\ModelRelation;
use Vladitot\Architect\Yaml\Laravel\Repository;
use Vladitot\Architect\Yaml\Laravel\Service;
use Vladitot\Architect\Yaml\Laravel\ServiceAggregator;
use Spatie\LaravelData\Data;

class Module extends Data
{
    public string $title;
    /**
     * @var array|Model[] $models;
     */
    public ?array $models = [];

    /**
     * @var array|ModelRelation[] $model_relations;
     */
    public ?array $model_relations = [];

    /**
     * @var array|Repository[] $repositories;
     */
    public ?array $repositories = [];

    /**
     * @var array|Service[] $services;
     */
    public ?array $services = [];

    /** @var array|ServiceAggregator[]  */
    public ?array $service_aggregators = [];

}
