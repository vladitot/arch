<?php

namespace Vladitot\Architect\Yaml;

class SchemaGenerator
{
    public const PRIMITIVE_TYPES = ['string'=>'string','int'=>'number', 'bool'=>'boolean'];


    private array $typesRecursivelyResolvedTimes = [];


    public function generateProjectFileSchema()
    {
        $smallProperties = $this->generatePropertiesArrayRecursively(Project::class, []);

        $schema = [ //hardcode for now
            '$schema' => 'http://json-schema.org/draft-06/schema#',
            'type' => 'object',
            "title" => "Project",
            'properties' => $smallProperties['properties'],
        ];
        if (array_key_exists( 'required', $smallProperties) && count($smallProperties['required'])>0) {
            $schema['required'] = $smallProperties['required'];
        }

        return $schema;
    }


    public function generateModuleSchema()
    {
        //сюда придет DomainProblem::class

        $smallProperties = [
            'type'=>'object',
            'properties'=>[]
        ];

        $smallProperties = $this->generatePropertiesArrayRecursively(Module::class, $smallProperties);


        $schema = [
            '$schema'=>'http://json-schema.org/draft-06/schema#',
            'type'=>$smallProperties['type'],
            "title"=> "Module",
            'properties'=> $smallProperties['properties'],
        ];
        if (array_key_exists( 'required', $smallProperties) && count($smallProperties['required'])>0) {
            $schema['required'] = $smallProperties['required'];
        }

        return $schema;
    }


    public function generatePropertiesArrayRecursively(string $className, $wholeProperties)
    {
        $propertiesInSchema = $wholeProperties['properties'] ?? [];
        $required = [];

        $reflectionClass = new \ReflectionClass($className);
        $reflectionProperties = $reflectionClass->getProperties();

        foreach ($reflectionProperties as $reflectionProperty) {
            $docComment = $reflectionProperty->getDocComment();

            if (!$reflectionProperty->isPublic()) {
                continue;
            }

            if (str_contains($docComment, '@exclude')) {
                continue;
            }

            $matches = [];
            preg_match('/@var\s*(.*?)\s/', $docComment, $matches);
            if (!isset($matches[1])) {
                if (!$reflectionProperty->getType()) {
                    throw new \Exception("Cant read variable type ".$className.", sorry");
                } else {
                    $type = $reflectionProperty->getType()->getName();
                    if (str_contains($type, 'array|')) {
                        $type = explode('|', $type)[1];
                    }
                }
            } else {
                $type = $matches[1];
                if (str_contains($type, 'array|')) {
                    $type = explode('|', $type)[1];
                }
            }

            if (str_contains($docComment, '@autocompleteFunction')) {
                preg_match('/@autocompleteFunction\s+(.*?)\s/', $docComment, $matches);
                $variants = call_user_func($className.'::'.$matches[1]);
                    if ($type=='array' || str_contains($type, '[]')) {
                        $propertiesInSchema[$reflectionProperty->getName()] = [
                            'type'=>'array',
                            'items'=>[
                                'enum'=> $variants
                            ]
                        ];
                    } else {
                        $propertiesInSchema[$reflectionProperty->getName()] = [
                            'enum'=> $variants
                        ];
                    }
                continue;
            }

            $matches = [];
            preg_match('/@description\s*(.*?)\s*\*/', $docComment, $matches);
            $description = trim($matches[1]??"");

            if (!$reflectionProperty->getType()->allowsNull()) {
//            if (str_contains($docComment, '@required')) {
                $required[] = $reflectionProperty->getName();
            }

            if (in_array($type, array_keys(self::PRIMITIVE_TYPES))) {
                $propertiesInSchema[$reflectionProperty->getName()] = [
                    'description'=>$description,
                    'type'=>self::PRIMITIVE_TYPES[$type]
                ];
                continue;
            }

            if (preg_match('/\[]$/', $type)) {
                //it is array of type
                $type = substr($type, 0, strlen($type)-2);
                if (in_array($type, array_keys(self::PRIMITIVE_TYPES))) {
                    $propertiesInSchema[$reflectionProperty->name] = [
                        'type'=>'array',
                        'description'=>$description,
                        'items'=>[
                            'type'=>self::PRIMITIVE_TYPES[$type]
                        ]
                    ];
                    continue;
                }

                preg_match('/@maxRecursion\s(.*?)\s/', $docComment, $matches);
                if (array_key_exists(1, $matches)) {
                    if (!array_key_exists($type, $this->typesRecursivelyResolvedTimes)) $this->typesRecursivelyResolvedTimes[$type]=0;
                    if ($this->typesRecursivelyResolvedTimes[$type] >= $matches[1]) {
                        $this->typesRecursivelyResolvedTimes[$type] = 0;
                        continue;
                    }
                    $this->typesRecursivelyResolvedTimes[$type] = array_key_exists($type, $this->typesRecursivelyResolvedTimes)
                        ? $this->typesRecursivelyResolvedTimes[$type]+1
                        : 1;
                }

                $type = $this->resolveTypeName($reflectionClass, $type);
                $smallProperties = [
                    'type'=>'object',
                    'properties'=>[]
                ];
                $smallProperties = $this->generatePropertiesArrayRecursively($type, $smallProperties);
                $dynamicProperties = [];
                if (method_exists($type, 'generateAdditionalProperties')) {
                    $dynamicProperties = call_user_func($type.'::generateAdditionalProperties');
                }
                $smallProperties['properties'] = array_merge($smallProperties['properties'], $dynamicProperties);
                $propertiesInSchema[$reflectionProperty->getName()] = [
                    'type'=>'array',
                    'description'=>$description,
                    'items'=>$smallProperties
                ];
                continue;
            }
//
//            //ну и теперь наконец мы узнали, что там в поле хранится объект, но один.
            preg_match('/@maxRecursion\s(.*?)\s/', $docComment, $matches);
            if (array_key_exists(1, $matches)) {
                if (!array_key_exists($type, $this->typesRecursivelyResolvedTimes)) $this->typesRecursivelyResolvedTimes[$type]=0;
                if ($this->typesRecursivelyResolvedTimes[$type] >= $matches[1]) {
                    $this->typesRecursivelyResolvedTimes[$type] = 0;
                    continue;
                }
                $this->typesRecursivelyResolvedTimes[$type] = array_key_exists($type, $this->typesRecursivelyResolvedTimes)
                    ? $this->typesRecursivelyResolvedTimes[$type]+1
                    : 1;
            }
            $type = $this->resolveTypeName($reflectionClass, $type);
            $newObjectProperties = $this->generatePropertiesArrayRecursively($type, []);

            $dynamicProperties = [];
            if (method_exists($type, 'generateAdditionalProperties')) {
                $dynamicProperties = call_user_func($type.'::generateAdditionalProperties');
            }
            $newObjectProperties['properties'] = array_merge($newObjectProperties['properties'], $dynamicProperties);
            $propertiesInSchema[$reflectionProperty->getName()] = [
                'type'=>'object',
                'description'=>$description,
                'properties'=>$newObjectProperties
            ];
            continue;
        }
        $wholeProperties['properties'] = $propertiesInSchema;
        if (count($required)>0) {
            $wholeProperties['required'] = $required;
        }
        return $wholeProperties;
    }

    private function resolveTypeName(\ReflectionClass $class, string $searchableClassName)
    {
        if (str_contains($searchableClassName, '\\')) {
            return $searchableClassName;
        } //nothing to upgrade

        $fileContent = file_get_contents($class->getFileName());
        $matches = [];
        preg_match('/use\s+(.*\\\\'.$searchableClassName.');/', $fileContent, $matches);
        if (isset($matches[1])) {
            return '\\'.$matches[1];
        }

        $matches = [];
        preg_match('/use\s+(.*)\s+as\s+'.$searchableClassName.';/', $fileContent, $matches);

        if (isset($matches[1])) {
            return '\\'.$matches[1];
        }

        //будем считать, что этот тип в том же пространстве имен

        return '\\'.$class->getNamespaceName().'\\'.$searchableClassName;
    }
}
