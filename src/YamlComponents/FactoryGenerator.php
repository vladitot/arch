<?php

namespace Vladitot\Architect\YamlComponents;


use Vladitot\Architect\AbstractGenerator;
use Vladitot\Architect\NamespaceAndPathGeneratorYaml;
use Vladitot\Architect\Yaml\Laravel\Model;
use Vladitot\Architect\Yaml\Module;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class FactoryGenerator extends AbstractGenerator
{

    public function generate(Module $module)
    {
        $models = $module->models;

        foreach ($models as $model) {
            $this->generateOneFactory($model, $module);
        }
    }

    /**
     * @param string $title
     * @return array|string|string[]
     * @deprecated because of duplication between generators
     */
    private function createFactoryName(string $title) {
        $title = str_replace('.','',
            str_replace(' ', '', ucfirst($title))
        );
        return $title;
    }

    public function generateOneFactory(Model $model, Module $module)
    {
        $filePathOfFactory = NamespaceAndPathGeneratorYaml::generateFactoryPath(
            $module->title,
            $model->title.'Factory',
        );
        if (file_exists($filePathOfFactory)) {
            unlink($filePathOfFactory);
        }


        $namespace = new PhpNamespace(
            NamespaceAndPathGeneratorYaml::generateFactoryNamespace(
                $module->title,
            )
        );
        $class = new ClassType($this->createFactoryName($model->title).'Factory');

        $modelFullNameWithNamespace = NamespaceAndPathGeneratorYaml::generateModelNamespace(
                $module->title
            ).'\\'.$model->title;
        $class->setExtends(\Illuminate\Database\Eloquent\Factories\Factory::class);
        $namespace->addUse(\Illuminate\Database\Eloquent\Factories\Factory::class);
        $namespace->addUse($modelFullNameWithNamespace);
        $class->addProperty('model')
            ->setProtected()
            ->setValue('\\'.$modelFullNameWithNamespace);

        $method = $class->addMethod('definition')
            ->setPublic();

        $methodBody = 'return ['."\n";

        foreach ($model->model_fields as $field) {
            if ($field->field_type=='id') continue;
            if (!isset($field->faker_value)) {
                $methodBody.="'".$field->title."' => ".'null,'."\n";
                continue;
            }
            $methodBody.="'".$field->title."' => ".'$this->faker->'.$field->faker_value;
            if (isset($field->faker_arguments) && $field->faker_arguments!=null && trim($field->faker_arguments)!='') {
                $methodBody.=$field->faker_arguments.','."\n";
            } else {
                $methodBody.=','."\n";
            }
        }
        foreach ($module->model_relations as $relation) {
            if ($relation->left_model_name!=$model->title) continue;
            if ($relation->relation_type=='BelongsTo') {
                $fieldName = Str::snake($relation->right_model_name).'_id';
                $factoryCall = '\\'.NamespaceAndPathGeneratorYaml::generateModelNamespace(
                        $module->title
                    ).'\\'.$relation->right_model_name.'::factory(),';

                $methodBody.="'".$fieldName."' => ".$factoryCall."\n";
            }
        }

        foreach ($module->model_relations as $relation) {
            if ($relation->right_model_name!=$model->title) continue;
            if ($relation->relation_type=='HasMany' || $relation->relation_type=='HasOne') {
                $fieldName = Str::snake($relation->left_model_name).'_id';
                $factoryCall = '\\'.NamespaceAndPathGeneratorYaml::generateModelNamespace(
                        $module->title
                    ).'\\'.$relation->left_model_name.'::factory(),';

                $methodBody.="'".$fieldName."' => ".$factoryCall."\n";
            }
        }

        $methodBody.="\n".'];';
        $method->setBody($methodBody);

        $namespace->add($class);

        $file = new \Nette\PhpGenerator\PhpFile;
        $file->addComment('This file is generated by architect.');
        $file->addNamespace($namespace);

        @mkdir(dirname($filePathOfFactory), recursive: true);
        file_put_contents($filePathOfFactory, $file);

        return $filePathOfFactory;
    }
}
