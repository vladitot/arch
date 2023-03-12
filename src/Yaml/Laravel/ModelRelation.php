<?php

namespace Vladitot\Architect\Yaml\Laravel;

use Vladitot\Architect\Yaml\Memory;
use Spatie\LaravelData\Data;

class ModelRelation extends Data
{
    public string $relation_left_title;

    public string $relation_right_title;

    /**
     * @var string $left_model_name
     * @autocompleteFunction chooseLeftModel
     */
    public string $left_model_name;

    /**
     * @var string $right_model_name
     * @autocompleteFunction chooseRightModel
     */
    public string $right_model_name;

    /**
     * @var string $relation_type
     * @autocompleteFunction getRelationTypeEnums
     */
    public string $relation_type;

    public static function chooseLeftModel() {
        $currentModuleName = Memory::$currentModuleName;
        $currentModule = Memory::$modules[$currentModuleName];
        $result = [];
        if ($currentModule === null || $currentModule->models === null) {
            return $result;
        }
        foreach ($currentModule->models as $model) {
            $result[] = $model->title;
        }
        return $result;
    }

    public static function chooseRightModel()
    {
        $currentModulePath = Memory::$currentModuleName;
        $currentModule = Memory::$modules[$currentModulePath];
        $result = [];
        if ($currentModule === null || $currentModule->models === null) {
            return $result;
        }
        foreach ($currentModule->models as $model) {
            $result[] = $model->title;
        }
        return $result;
    }


    public static function getRelationTypeEnums(): array
    {
        return [
            'HasOne',
            'HasMany',
            'BelongsTo',
            'BelongsToMany',
        ];
    }
}
