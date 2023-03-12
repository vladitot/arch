<?php

namespace Vladitot\Architect\Yaml;

class LoadModule
{

    public const PRIMITIVE_TYPES = ['string','number', 'bool'];

    public static function createObjectAndFill(object $object, array $fields)
    {
        foreach ($fields as $key=>$value) {
            if (property_exists($object, $key)) {
                $class = new \ReflectionClass($object);
                $reflectionProperty = $class->getProperty($key);
                $docComment = $reflectionProperty->getDocComment();

                if (str_contains($docComment, '@exclude')) {
                    continue;
                }

                $matches = [];
                preg_match('/@var\s*(.*?)\s/', $docComment, $matches);
                if (!isset($matches[1])) {
                    if (!$reflectionProperty->getType()) {
                        throw new \Exception("Cant read variable type ".$object::class.", sorry");
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

                if (in_array($type, self::PRIMITIVE_TYPES)) {
                    $object->$key = $value;
                    continue;
                }
                if (preg_match('/\[]$/', $type)) {
                    //it is array of type
                    $type = substr($type, 0, strlen($type)-2);
                    if (in_array($type, self::PRIMITIVE_TYPES)) {
                        $object->$key = $value;
                        continue;
                    }
                    $type = self::resolveTypeName($class, $type);
                    $object->$key = [];
                    foreach ($value as $innerValue) {
                        $innerObject = new $type();
                        if (property_exists($innerObject, 'parent')) {
                            $innerObject->parent = $object;
                        }
                        $object->$key[] = self::createObjectAndFill($innerObject, $innerValue);
                    }
                    continue;
                }
                //проверили, что не простой тип, проверили, что не массив простых типов или массив непростых типов
                //теперь получается надо проверить вдруг ENUM
//                if (str_ends_with($type, 'Enum')) {
//                    $type = self::resolveTypeName($class, $type);
//                    foreach (call_user_func($type.'::cases') as $enumValue) {
//                        if ($enumValue->value==$value) {
//                            $object->$key = $enumValue;
//                            continue 2;
//                        }
//                    }
//                }

                //ну и теперь наконец мы узнали, что там в поле хранится объект, но один.
                $type = self::resolveTypeName($class, $type);
                $innerObject = new $type();
                if (property_exists($innerObject, 'parent')) {
                    $innerObject->parent = $object;
                }
                $object->$key = self::createObjectAndFill($innerObject, $value);
                continue;
            } else {
                echo "Trying to assing not existing property ".$key." to ".get_class($object)."\n";
//                if (method_exists($object, 'setAdditionalAttributes')) {
//                    $object->{'setAdditionalAttributes'}($value['properties']);
//                } else {
//                    throw new \Exception("Trying to assing not existing property");
//                }
            }
        }
        return $object;
    }

    private static function resolveTypeName(\ReflectionClass $class, string $searchableClassName)
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
