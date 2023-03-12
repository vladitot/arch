<?php


use Vladitot\Architect\Yaml\Laravel\AggregatorMethod;
use Vladitot\Architect\Yaml\Laravel\InputParam;
use Vladitot\Architect\Yaml\Laravel\OutputParam;
use Vladitot\Architect\Yaml\Module;
use App\Models\Ddv1InputParam;
use Nette\PhpGenerator\ClassLike;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;

abstract class AbstractGenerator
{

    abstract function generate(Module $module);

    /**
     * @param iterable|InputParam[] $inputParams
     * @param ClassLike|ClassType $class
     * @param bool $skipValidateSimple
     * @return void
     */
    protected function putValidatorInObject(iterable $inputParams, ClassLike|ClassType $class, bool $skipValidateSimple = false) {
        $rulesBody = 'return ['."\n";
        $rulesBody .= $this->generateIncludedFieldsRulesList($inputParams);
        $rulesBody .= '];';

        $class->addMethod('rules')
            ->setBody($rulesBody)
            ->setReturnType('array')
            ->setPrivate();

        if (!$skipValidateSimple) {
            $class->addMethod('validateSimple')
                ->setPublic()
                ->setReturnType('void')
                ->setBody('
                $validator = Validator::make($this->toArray(), $this->rules());
                $validator->validate();');
        }

    }


    protected function prepareAiQueryForInputDtoParams(\Vladitot\Architect\Yaml\Laravel\Method|AggregatorMethod $method) {
        $aiQuery = '';
        if (count($method->inputParams)>1) {
            $type = ucfirst($method->title).'InDto';
            $aiQuery.= 'Argument with type is DTO named inDto('.$type.'). ';
            $aiQuery.= 'Input DTO fields with types: ';
            foreach ($method->inputParams as $param) {
                $aiQuery.= $param->title.'('.$param->type.'), ';
            }
            $aiQuery = substr($aiQuery, 0, -2).'. Use Input DTO Fields With "Where".';
        }
        if (count($method->inputParams)==1) {
            $paramFromDb = $method->inputParams[0];
            $aiQuery.= 'Argument with type: '.$paramFromDb->title.'('.$paramFromDb->type.'). ';
        }

        if (count($method->outputParams)>1) {
            $returnType = ucfirst($method->title).'OutDto';
            $aiQuery.= 'Output type is DTO with type: '.$returnType.'. ';
            $aiQuery.= 'Output DTO fields (and also Output DTO constructor parameters) with types: ';
            foreach ($method->outputParams as $param) {
                $aiQuery.= $param->title.'('.$param->type.'), ';
            }
            $aiQuery = substr($aiQuery, 0, -2).'. ';
        }
        if (count($method->outputParams)==1) {
            $paramFromDb = $method->outputParams[0];
            $aiQuery.= 'Output type is '.$paramFromDb->type.'. ';
        }
        if (count($method->outputParams)==0) {
            $aiQuery.= 'Output type is void. ';
        }
        return $aiQuery;
    }


    /**
     * @actual
     * @param \Vladitot\Architect\Yaml\Laravel\Method|AggregatorMethod $method
     * @param Method $codedMethod
     * @param PhpNamespace $namespace
     * @param string $dtoNamespace
     * @param string $dtoDirname
     * @return void
     */
    protected function fillMethodWithParametersAndTypesOrDtos(\Vladitot\Architect\Yaml\Laravel\Method|AggregatorMethod $method, Method $codedMethod, PhpNamespace $namespace, string $dtoNamespace, string $dtoDirname): void
    {
        if (count($method->inputParams)>1 || $method instanceof AggregatorMethod) {
            $type = $this->createDtoRecursively(
                params: $method->inputParams,
                methodName: $method->title,
                namespace: $dtoNamespace,
                dtoDirname: $dtoDirname,
                postfix: 'InDto'
            );
            $namespace->addUse($type);
            $codedMethod->addParameter('inDto')
                ->setType(
                    $type
                );
        } elseif (count($method->inputParams)==1) {
            $paramFromDb = $method->inputParams[0];
            $codedMethod->addParameter($paramFromDb->title)
                ->setType($paramFromDb->type);
        }
        ////end of input

        ////output
        if (count($method->outputParams)>1 || $method instanceof AggregatorMethod) {
            $returnType = $this->createDtoRecursively(
                params: $method->outputParams,
                methodName: $method->title,
                namespace: $dtoNamespace,
                dtoDirname: $dtoDirname,
                postfix: 'OutDto'
            );
            $namespace->addUse($returnType);
            $codedMethod->setReturnType(
                $returnType
            );
        } elseif (count($method->outputParams)==1) {
            $paramFromDb = $method->outputParams[0];
            $codedMethod->setReturnType($paramFromDb->type);
        } elseif (count($method->outputParams)==0) {
            $codedMethod->setReturnType('void');
        }
        ////end of output
    }

    /**
     * @param iterable|InputParam[] $inputParams
     * @param string $parameterPrefix
     * @return string
     */
    private function generateIncludedFieldsRulesList(iterable $inputParams, string $parameterPrefix=''): string {
        $rulesBody = '';
        foreach ($inputParams as $param) {
            if (count($param->childrenParams)>0) {
                $newParameterPrefix = ltrim( $parameterPrefix.'.'.$param->title, '.');
                $rulesBody.=$this->generateIncludedFieldsRulesList($param->childrenParams, $newParameterPrefix);
            } else {
                if ($param->validation_rules === null) {
                    continue;
                }
                if (str_starts_with($param->validation_rules, '[')) {
                    $rule = $param->validation_rules;
                } else {
                    $rule = "'".$param->validation_rules."'";
                }
                $rulesBody.="'".ltrim($parameterPrefix.'.'.$param->title, '.')."'=>".$rule.",\n";
            }
        }
        return $rulesBody;
    }


    /**
     * @param string $question
     * @return mixed
     */
    protected function queryAiForAnswer(string $question) {
        $client = OpenAI::client('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');

        try {
            $result = $client->completions()->create([
//            'model' => 'code-davinci-002',
                'model' => 'text-davinci-003',
                'temperature'=> 0,
                'prompt' => $question,
                'max_tokens'=> 1000,
            ]);
        } catch (\Throwable $e) {
            $result = $client->completions()->create([
//            'model' => 'code-davinci-002',
                'model' => 'text-davinci-003',
                'temperature'=> 0,
                'prompt' => $question,
                'max_tokens'=> 1000,
            ]);
        }

        return $result['choices'][0]['text'];
    }

    /**
     * @param iterable|InputParam[]|OutputParam[] $params
     * @param string $methodName
     * @param string $namespace
     * @param string $dtoDirname
     * @param string $postfix
     * @param string $middlefix
     * @return mixed
     */
    protected function createDtoRecursively(iterable $params, string $methodName, string $namespace, string $dtoDirname, string $postfix, string $middlefix='') {
        $class = new ClassType(ucfirst($methodName).$middlefix.$postfix);

        $constructor = $class->addMethod('__construct')
            ->setPublic();
        $constructorBody = '';

        foreach ($params as $param) {
            if (count($param->childrenParams)>0) {
                //открываем рекурсию
                $newPostfix = str_contains('InDto', $postfix) ?'ListInDto':'ListOutDto';
                $subType = $this->createDtoRecursively($param->childrenParams, $methodName, $namespace, $dtoDirname, $newPostfix, $middlefix.ucfirst($param->title));

                if (!$class->hasProperty($param->title)) {
                    $property = $class->addProperty(lcfirst($param->title));
                } else {
                    $property = $class->getProperty(lcfirst($param->title));
                }
                $property
                    ->setType($param->mandatory?'array':'?array')
                    ->setReadOnly(true)
                    ->addComment('@param '.$subType.'[] $'.lcfirst($param->title))
                    ->setPublic();

            } else {
                if (!$class->hasProperty(lcfirst($param->title))) {
                    $property = $class->addProperty(lcfirst($param->title));
                } else {
                    $property = $class->getProperty(lcfirst($param->title));
                }
                $property
                    ->setType($param->mandatory?$param->type:'?'.$param->type)
                    ->setReadOnly(true)
                    ->setPublic();

                $constructorParameter = $constructor->addParameter(lcfirst($param->title))
                    ->setType($param->type);
                if (!$param->mandatory) {
                    $constructorParameter->setType('?'.$param->type);
                    $constructorParameter->setNullable();
                }

                $constructorBody.= '$this->'.lcfirst($param->title).' = $'.lcfirst($param->title).';'."\n";
            }
        }
        $constructor->setBody($constructorBody);

        if (str_ends_with($postfix, 'InDto')){
            $this->putValidatorInObject($params, $class);
        }

        $namespaceObject = new PhpNamespace($namespace);
        $namespaceObject->add($class);

        $namespaceObject->addUse(\Illuminate\Support\Facades\Validator::class);

        $namespaceObject->add($class);
        $class->setExtends('\\Spatie\\LaravelData\\Data');
        $namespaceObject->addUse('Spatie\\LaravelData\\Data');

        $pathToObject = $dtoDirname.'/'.ucfirst($methodName).$middlefix.$postfix.'.php';

        $this->putNamespaceToFile($namespaceObject, $pathToObject);

        return '\\'.$namespaceObject->getName().'\\'.$class->getName();
    }

    /**
     * @param PhpNamespace $namespace
     * @param string $path
     * @return void
     */
    protected function putNamespaceToFile(PhpNamespace $namespace, string $path): string {
        $file = new \Nette\PhpGenerator\PhpFile;
        $file->addComment('This file is generated by architect.');
        $file->addNamespace($namespace);

        @mkdir(dirname($path), recursive: true);
        file_put_contents($path, $file);

        return $file;
    }


    /////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////








//    /**
//     * @param \Traversable $inputParams
//     * @param string $methodName
//     * @return string
//     * @deprecated
//     */
//    protected function createInputDto(\Traversable $inputParams, string $methodName): string {
//        return $this->createInputObject($inputParams,
//            $methodName,
//            '\\Spatie\\LaravelData\\Data',
//            dirname(NamespaceAndPathGenerator::generateRepositoryDTOPath(
//                $this->projectTitle,
//                $this->architectureTitle,
//                $this->moduleTitle,
//                'any')),
//            NamespaceAndPathGenerator::generateRepositoryDTONamespace(
//                $this->architectureTitle,
//                $this->moduleTitle,
//            ),
//            ucfirst($methodName).'InDto',
//
//        );
//    }

//    /**
//     * @param Method $method
//     * @param Ddv1InputParam[] $inputParams
//     * @return void
//     * @deprecated
//     */
//    protected function createInputObject(
//        \Traversable $inputParams,
//        string $methodName,
//        string $whatToExtend,
//        string $destinationDir,
//        string $namespaceString,
//        string $objectName,
//    ): string
//    {
//        $inType = new ClassType($objectName);
//        $inType->setExtends($whatToExtend);
//
//        $constructor = $inType->addMethod('__construct');
//        $constructorBody = '';
//        foreach ($inputParams as $param) {
//            if ($param->ddv1ChildrenParams()->count()==0) { //проверяем, есть ли дочерние параметры
//                $constructorBody = $this->fillPropertiesAndConstructorBodyIfNoChildrenParams($inType, $param, $constructor, $constructorBody);
//            } else {
//                $constructorBody = $this->fillPropertiesIfChildrenParamsArePresent($inType, $param, $constructor, $constructorBody, $methodName);
//            }
//        }
//        $constructor->setBody($constructorBody);
//
//        $this->putValidatorInObject($inputParams, $inType);
//
//        $namespace = new PhpNamespace($namespaceString);
//        $namespace->addUse(\Illuminate\Support\Facades\Validator::class);
//        $namespace->add($inType);
//        $namespace->addUse($whatToExtend);
//
//        $path = $destinationDir.'/'
//            .ucfirst($methodName).'InDto';
//
//        $this->putNamespaceToFile($namespace, $path);
//
//        return $namespace->getName().'\\'.$inType->getName();
//    }


    /**
     * @param ClassLike|ClassType $inType
     * @param Ddv1InputParam $param
     * @param Method $constructor
     * @param string $constructorBody
     * @return string
     * @deprecated
     */
//    private function fillPropertiesAndConstructorBodyIfNoChildrenParams(ClassLike|ClassType $inType, Ddv1InputParam $param, Method $constructor, string $constructorBody): string {
//        if ($inType->hasProperty($param->title)) {
//            $property = $inType->getProperty($param->title);
//        } else {
//            $property = $inType->addProperty($param->title);
//        }
//        $property
//            ->setReadOnly(true)
//            ->setPublic();
//        if (!$param->mandatory) {
//            $property->setType('?'.$param->ddv1VariableType->title);
//        } else {
//            $property->setType($param->ddv1VariableType->title);
//        }
//
//        $constructor
//            ->addParameter($param->title)
//            ->setType($param->ddv1VariableType->title);
//        return  $constructorBody. '$this->'.$param->title.' = $'.$param->title.";\n";
//    }


    /**
     * @param ClassLike|ClassType $inType
     * @param Ddv1InputParam $param
     * @param Method $constructor
     * @param string $constructorBody
     * @param string $methodName
     * @return string
     * @deprecated
     */
//    private function fillPropertiesIfChildrenParamsArePresent(ClassLike|ClassType $inType, Ddv1InputParam $param, Method $constructor, string $constructorBody, string $methodName): string {
//        $subDtoType = $this->createInputSubObject($param->title, $methodName, $param->ddv1ChildrenParams);
//        if ($inType->hasProperty($param->title)) {
//            $property = $inType->getProperty($param->title);
//        } else {
//            $property = $inType->addProperty($param->title);
//        }
//        $property
//            ->setReadOnly(true)
//            ->addComment('@param '.$subDtoType.'[] $'.$param->title)
//            ->setPublic();
//
//        $property
//            ->setReadOnly(true)
//            ->setPublic();
//        if (!$param->mandatory) {
//            $property->setType('?array');
//        } else {
//            $property->setType('array');
//        }
//
//        $constructor
//            ->addParameter($param->title)
//            ->setType('array');
//        return $constructorBody.'$this->'.$param->title.' = $'.$param->title.";\n";
//    }









    /**
     * @param string $paramTitle
     * @param string $methodName
     * @param \Traversable|Ddv1InputParam[] $childParams
     * @return string
     * @deprecated
     */
//    protected function createInputSubObject(string $paramTitle, string $methodName, \Traversable $childParams): string {
//        $class = new ClassType(ucfirst($methodName).ucFirst($paramTitle).'ListInDto');
//
//        $constructor = $class->addMethod('__construct')
//            ->setPublic();
//        $constructorBody = '';
//        foreach ($childParams as $childParam) {
//            if ($childParam->ddv1ChildrenParams()->count()>0) {
//                //тут надо открыть рекурсию
//                $currentType = $this->createInputSubObject($paramTitle.ucfirst($childParam->title), $methodName, $childParam->ddv1ChildrenParams);
//                if (!$class->hasProperty($paramTitle.ucfirst($childParam->title))) {
//                    $property = $class->addProperty($paramTitle.ucfirst($childParam->title));
//                } else {
//                    $property = $class->getProperty($paramTitle.ucfirst($childParam->title));
//                }
//                $property
//                    ->setType($childParam->mandatory?'array':'?array')
//                    ->setReadOnly(true)
//                    ->addComment('@param '.$currentType.'[] $'.$paramTitle.ucfirst($childParam->title))
//                    ->setPublic();
//
//                $constructor->addParameter($paramTitle.ucfirst($childParam->title))
//                    ->setType('array');
//                $constructorBody.= '$this->'.$paramTitle.ucfirst($childParam->title).' = $'.$paramTitle.ucfirst($childParam->title).';'."\n";
//            } else {
//                if (!$class->hasProperty($paramTitle.ucfirst($childParam->title))) {
//                    $property = $class->addProperty($paramTitle.ucfirst($childParam->title));
//                } else {
//                    $property = $class->getProperty($paramTitle.ucfirst($childParam->title));
//                }
//                $property
//                    ->setType($childParam->mandatory?$childParam->ddv1VariableType->title:'?'.$childParam->ddv1VariableType->title)
//                    ->setReadOnly(true)
//                    ->setPublic();
//
//                $constructorParameter = $constructor->addParameter($paramTitle.ucfirst($childParam->title))
//                    ->setType($childParam->ddv1VariableType->title);
//                if (!$childParam->mandatory) {
//                    $constructorParameter->setType('?'.$childParam->ddv1VariableType->title);
//                    $constructorParameter->setNullable();
//                }
//
//                $constructorBody.= '$this->'.$paramTitle.ucfirst($childParam->title).' = $'.$paramTitle.ucfirst($childParam->title).';'."\n";
//            }
//        }
//        $constructor->setBody($constructorBody);
//
//        $this->putValidatorInObject($childParams, $class);
//
//        $namespace = new PhpNamespace(NamespaceAndPathGenerator::generateRepositoryDTONamespace(
//
//            $this->architectureTitle,
//            $this->moduleTitle,
//        ));
//        $namespace->addUse(\Illuminate\Support\Facades\Validator::class);
//
//        $namespace->add($class);
//        $class->setExtends('\\Spatie\\LaravelData\\Data');
//        $namespace->addUse('Spatie\\LaravelData\\Data');
//
//        $path = NamespaceAndPathGenerator::generateRepositoryDTOPath($this->projectTitle,
//            $this->architectureTitle,
//            $this->moduleTitle,
//            ucfirst($methodName).ucFirst($paramTitle).'ListInDto');
//
//        $file = new \Nette\PhpGenerator\PhpFile;
//        $file->addComment('This file is generated by architect.');
//        $file->addNamespace($namespace);
//
//        @mkdir(dirname($path), recursive: true);
//        file_put_contents($path, $file);
//
//        return '\\'.$namespace->getName().'\\'.$class->getName();
//    }
}
