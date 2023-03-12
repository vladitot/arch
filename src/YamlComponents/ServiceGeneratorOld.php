<?php
//
//namespace Vladitot\Architect\YamlComponents;
//
//use Vladitot\Architect\AddArrayConvertingToClass;
//use Vladitot\Architect\NamespaceAndPathGenerator;
//use App\Models\Ddv1Module;
//use App\Models\Ddv1Service;
//use App\Models\Ddv1ServiceInputParam;
//use App\Models\Ddv1ServiceOutputParam;
//use Nette\PhpGenerator\ClassType;
//use Nette\PhpGenerator\Method;
//use Nette\PhpGenerator\PhpNamespace;
//
//class ServiceGeneratorOld extends AbstractGenerator
//{
//
//    public function generate()
//    {
//
//        $services = Ddv1Module::whereId($this->moduleId)->first()->ddv1Services;
//
//        foreach ($services as $service) {
//            $this->generateOneService($service);
//        }
//
//    }
//
//    public function generateOneService(Ddv1Service $service)
//    {
//        $filePathOfService = NamespaceAndPathGenerator::generateServicePath(
//            $this->projectTitle,
//            $this->architectureTitle,
//            $this->moduleTitle,
//            $service->title.'Service',
//        );
//
//        if (file_exists($filePathOfService)) {
//            $class = \Nette\PhpGenerator\ClassType::fromCode(file_get_contents($filePathOfService));
//        } else {
//            $class = new ClassType($service->title.'Service');
//        }
//        foreach ($class->getMethods() as $method) {
//            if ($method->isPublic()) {
//                $method->setComment('@deprecated');
//            }
//        }
//        $namespace = new PhpNamespace(
//            NamespaceAndPathGenerator::generateServiceNamespace(
//                $this->architectureTitle,
//                $this->moduleTitle,
//            )
//        );
//
//        if (!$service->is_hyper && $service->ddv1InjectedServices()->count()>0) {
//            throw new \Exception('Cannot inject services into non-hyper service '.$service->title.'Service');
//        }
//
//        if ($service->is_hyper && $service->ddv1InjectedServices()->count()>0) {
//            if ($class->hasMethod('__construct')) {
//                $constructor = $class->getMethod('__construct');
//                $constructor->setParameters([]);
//            } else {
//                $constructor = $class->addMethod('__construct');
//            }
//
//            foreach ($service->ddv1InjectedServices as $injectionService) {
//                $fullInjectableServiceName = '\\'.$namespace->getName().'\\'.$injectionService->title.'Service';
//                $constructor->addParameter($injectionService->title.'Service')
//                    ->setType($fullInjectableServiceName);
//
//                $namespace->addUse($fullInjectableServiceName);
//
//                $class->addProperty(lcfirst($injectionService->title).'Service')
//                    ->setVisibility('private')
//                    ->setType($fullInjectableServiceName);
//
//                $constructor->addBody('$this->'.lcfirst($injectionService->title).'Service = $'.$injectionService->title.'Service;');
//            }
//        }
//
//        if ($service->is_hyper && $service->ddv1Repositories()->count()>0) {
//            throw new \Exception('Cannot inject repositories into hyper service '.$service->title.'Service');
//        }
//        if (!$service->is_hyper && $service->ddv1Repositories()->count()>0) {
//            if ($class->hasMethod('__construct')) {
//                $constructor = $class->getMethod('__construct');
//                $constructor->setParameters([]);
//            } else {
//                $constructor = $class->addMethod('__construct');
//            }
//
//            foreach ($service->ddv1Repositories as $repository) {
//                $fullInjectableRepositoryName = '\\'.NamespaceAndPathGenerator::generateRepositoryNamespace(
//                        $this->architectureTitle,
//                        $this->moduleTitle,
//                    ).'\\'.$repository->title.'Repository';
//                $constructor->addParameter(lcfirst($repository->title).'Repository')
//                    ->setType($fullInjectableRepositoryName);
//                $namespace->addUse($fullInjectableRepositoryName);
//                $class->addProperty(lcfirst($repository->title).'Repository')
//                    ->setVisibility('private')
//                    ->setType($fullInjectableRepositoryName);
//                $constructor->addBody('$this->'.lcfirst($repository->title).'Repository = $'.lcfirst($repository->title).'Repository;');
//            }
//        }
//
//        $class->setComment('@architect serviceId:'.$service->id."\n".$service->comment ?? '');
//
//        $namespace->add($class);
//
//        foreach ($service->ddv1ServiceMethods as $method) {
//            if ($class->hasMethod($method->title)) {
//                $codedMethod = $class->getMethod($method->title);
//                $codedMethod->setParameters([]);
//                $codedMethod->setReturnType(null);
//            } else {
//                $codedMethod = $class->addMethod($method->title);
//            }
//
//            $class->setComment('@architect serviceMethodId:'.$method->id."\n".$method->comment ?? '');
//
//            if ($method->ddv1ServiceInputParams->count()>1) {
////                $type = $this->createInputDto($method->ddv1ServiceInputParams, $method->title);
//                $namespace->addUse($type);
//                $codedMethod->addParameter('inDto')
//                    ->setType(
//                        $type
//                    );
//
//            }
//            if ($method->ddv1ServiceInputParams->count()==1) {
//                /** @var Ddv1ServiceInputParam $paramFromDb */
//                $paramFromDb = $method->ddv1ServiceInputParams->first();
//                $codedMethod->addParameter($paramFromDb->title)
//                    ->setType($paramFromDb->ddv1VariableTypes->title);
//            }
//
//            if ($method->ddv1ServiceOutputParams()->count()>1) {
//                $returnType = $this->createOutputDtoAndFillMethod($method->ddv1ServiceOutputParams, $method->title);
//                $namespace->addUse($returnType);
//                $codedMethod->setReturnType(
//                    $returnType
//                );
//            }
//            if ($method->ddv1ServiceOutputParams->count()==1) {
//                /** @var Ddv1ServiceOutputParam $paramFromDb */
//                $paramFromDb = $method->ddv1ServiceOutputParams->first();
//                $codedMethod->setReturnType($paramFromDb->ddv1VariableTypes->title);
//            }
//            if ($method->ddv1ServiceOutputParams->count()==0) {
//                $codedMethod->setReturnType('void');
//            }
//        }
//
//        $file = new \Nette\PhpGenerator\PhpFile;
//        $file->addComment('This file is generated by architect.');
//        $file->addNamespace($namespace);
//
//        @mkdir(dirname($filePathOfService), recursive: true);
//        file_put_contents($filePathOfService, $file);
//
//        $this->generateTestsForService($service);
//
//        return $filePathOfService;
//
//    }
//
//    /**
//     * @param string $paramTitle
//     * @param string $methodName
//     * @param Ddv1ServiceInputParam[]|\Traversable $childParams
//     * @return string
//     */
//    protected function createInputSubObject(string $paramTitle, string $methodName, \Traversable $childParams): string {
//        $class = new ClassType(ucfirst($methodName).ucFirst($paramTitle).'ListInDto');
//
//        foreach ($childParams as $childParam) {
//            if ($childParam->ddv1ServiceInputParams->count()>0) {
//                //тут надо открыть рекурсию
//                $currentType = $this->createInputSubObject($paramTitle.ucfirst($childParam->title), $methodName, $childParam->ddv1ServiceInputParams);
//                if (!$class->hasProperty($paramTitle.ucfirst($childParam->title))) {
//                    $property = $class->addProperty($paramTitle.ucfirst($childParam->title));
//                } else {
//                    $property = $class->getProperty($paramTitle.ucfirst($childParam->title));
//                }
//                $property
//                    ->setType('array')
//                    ->setReadOnly(true)
//                    ->addComment('@param '.$currentType.'[] $'.$paramTitle.ucfirst($childParam->title))
//                    ->setPublic();
//            } else {
//                if (!$class->hasProperty($paramTitle.ucfirst($childParam->title))) {
//                    $property = $class->addProperty($paramTitle.ucfirst($childParam->title));
//                } else {
//                    $property = $class->getProperty($paramTitle.ucfirst($childParam->title));
//                }
//                $property
//                    ->setType($childParam->ddv1VariableTypes->title)
//                    ->setReadOnly(true)
//                    ->setPublic();
//            }
//        }
//
//        $namespace = new PhpNamespace(NamespaceAndPathGenerator::generateServiceDTONamespace(
//            $this->architectureTitle,
//            $this->moduleTitle,
//        ));
//
//        $namespace->add($class);
//
//        $path = NamespaceAndPathGenerator::generateServiceDTOPath($this->projectTitle,
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
//
//    /**
//     * @param string $paramTitle
//     * @param string $methodName
//     * @param Ddv1ServiceOutputParam[] $childParams
//     * @return string
//     */
//    private function createOutputSubDto(string $paramTitle, string $methodName, \Traversable $childParams): string {
//        $class = new ClassType(ucfirst($methodName).ucFirst($paramTitle).'ListOutDto');
//        $keysArrays = [];
//        foreach ($childParams as $childParam) {
//            if ($childParam->ddv1ServiceOutputParams->count()>0) {
//                //тут надо открыть рекурсию
//                $currentType = $this->createOutputSubDto($paramTitle.ucfirst($childParam->title), $methodName, $childParam->ddv1ServiceOutputParams);
//                $class->addProperty($paramTitle.ucfirst($childParam->title))
//                    ->setType('array')
//                    ->setReadOnly(true)
//                    ->addComment('@param '.$currentType.'[] $'.$paramTitle.ucfirst($childParam->title))
//                    ->setPublic();
//                $keysArrays[$paramTitle] = '\\'.$currentType;
//            } else {
//                if ($class->hasProperty($paramTitle.ucfirst($childParam->title))) {
//                    $property = $class->getProperty($paramTitle.ucfirst($childParam->title));
//                } else {
//                    $property = $class->addProperty($paramTitle.ucfirst($childParam->title));
//                }
//                $property
//                    ->setType($childParam->ddv1VariableTypes->title)
//                    ->setReadOnly(true)
//                    ->setPublic();
//            }
//        }
//        AddArrayConvertingToClass::addToArrayMethodsToClass($class, $keysArrays);
//        $namespace = new PhpNamespace(NamespaceAndPathGenerator::generateServiceDTONamespace(
//            $this->architectureTitle,
//            $this->moduleTitle,
//        ));
//
//        $namespace->add($class);
//
//        $path = NamespaceAndPathGenerator::generateServiceDTOPath($this->projectTitle,
//            $this->architectureTitle,
//            $this->moduleTitle,
//            ucfirst($methodName).ucFirst($paramTitle).'ListOutDto');
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
//
//    /**
//     * @param Method $method
//     * @param Ddv1ServiceInputParam[] $inputParams
//     * @return void
//     * @deprecated
//     */
////    protected function createInputDto(\Traversable $inputParams, string $methodName): string
////    {
////        $inType = new ClassType(ucfirst($methodName).'InDto');
////        $keysArrays = [];
////        foreach ($inputParams as $param) {
////            if ($param->ddv1ServiceInputParams->count()==0) { //проверяем, есть ли дочерние параметры
////                if ($inType->hasProperty($param->title)) {
////                    $property = $inType->getProperty($param->title);
////                } else {
////                    $property = $inType->addProperty($param->title);
////                }
////                $property
////                    ->setType($param->ddv1VariableTypes->title)
////                    ->setReadOnly(true)
////                    ->setPublic();
////            } else {
////                //если есть дочерние, то нужно создать subDTO
////                $subDtoType = $this->createInputSubObject($param->title, $methodName, $param->ddv1ServiceInputParams);
////                if ($inType->hasProperty($param->title)) {
////                    $property = $inType->getProperty($param->title);
////                } else {
////                    $property = $inType->addProperty($param->title);
////                }
////                $property
////                    ->setType('array')
////                    ->setReadOnly(true)
////                    ->addComment('@param '.$subDtoType.'[] $'.$param->title)
////                    ->setPublic();
////                $keysArrays[$param->title] = '\\'.$subDtoType;
////            }
////        }
////        AddArrayConvertingToClass::addToArrayMethodsToClass($inType, $keysArrays);
////        $namespace = new PhpNamespace(NamespaceAndPathGenerator::generateServiceDTONamespace(
////            $this->architectureTitle,
////            $this->moduleTitle,
////        ));
////
////        $namespace->add($inType);
////
////        $path = NamespaceAndPathGenerator::generateServiceDTOPath($this->projectTitle,
////            $this->architectureTitle,
////            $this->moduleTitle,
////            ucfirst($methodName).'InDto');
////
////        $file = new \Nette\PhpGenerator\PhpFile;
////        $file->addComment('This file is generated by architect.');
////        $file->addNamespace($namespace);
////
////        @mkdir(dirname($path), recursive: true);
////        file_put_contents($path, $file);
////
////        return $namespace->getName().'\\'.$inType->getName();
////
////    }
//
//    /**
//     * @param Method $method
//     * @param Ddv1ServiceOutputParam[] $outputParams
//     * @param string $methodName
//     * @return void
//     */
//    private function createOutputDtoAndFillMethod(\Traversable $outputParams, string $methodName):string
//    {
//        $outputType = new ClassType(ucfirst($methodName).'OutDto');
//        $keysArrays = [];
//        foreach ($outputParams as $param) {
//            if ($param->ddv1ServiceOutputParams->count()==0) { //проверяем, есть ли дочерние параметры
//                if ($outputType->hasProperty($param->title)) {
//                    $property = $outputType->getProperty($param->title);
//                } else {
//                    $property = $outputType->addProperty($param->title);
//                }
//                $property
//                    ->setType($param->ddv1VariableTypes->title)
//                    ->setReadOnly(true)
//                    ->setPublic();
//            } else {
//                //если есть дочерние, то нужно создать subDTO
//                $subDtoType = $this->createOutputSubDto($param->title, $methodName, $param->ddv1ServiceOutputParams);
//                if ($outputType->hasProperty($param->title)) {
//                    $property = $outputType->getProperty($param->title);
//                } else {
//                    $property = $outputType->addProperty($param->title);
//                }
//                $property
//                    ->setType('array')
//                    ->setReadOnly(true)
//                    ->addComment('@param '.$subDtoType.'[] $'.$param->title)
//                    ->setPublic();
//                $keysArrays[$param->title] = '\\'.$subDtoType;
//            }
//        }
//        AddArrayConvertingToClass::addToArrayMethodsToClass($outputType, $keysArrays);
//        $namespace = new PhpNamespace(NamespaceAndPathGenerator::generateServiceDTONamespace(
//            $this->architectureTitle,
//            $this->moduleTitle,
//        ));
//
//        $namespace->add($outputType);
//
//        $path = NamespaceAndPathGenerator::generateServiceDTOPath($this->projectTitle,
//            $this->architectureTitle,
//            $this->moduleTitle,
//            ucfirst($methodName).'OutDto');
//
//        $file = new \Nette\PhpGenerator\PhpFile;
//        $file->addComment('This file is generated by architect.');
//        $file->addNamespace($namespace);
//
//        @mkdir(dirname($path), recursive: true);
//        file_put_contents($path, $file);
//
//        return $namespace->getName().'\\'.$outputType->getName();
//
//    }
//
//    private function generateTestsForService(Ddv1Service $service): void
//    {
//        $serviceTestClassPath = NamespaceAndPathGenerator::generateServiceTestPath($this->projectTitle,
//            $this->architectureTitle,
//            $this->moduleTitle,
//            $service->title.'ServiceTest');
//
//        $serviceTestNamespace = new PhpNamespace( NamespaceAndPathGenerator::generateServiceTestNamespace($this->architectureTitle,
//            $this->moduleTitle));
//
//        if (file_exists($serviceTestClassPath)) {
//            $testClass = \Nette\PhpGenerator\ClassType::fromCode(file_get_contents($serviceTestClassPath));
//        } else {
//            $testClass = new ClassType($service->title.'ServiceTest');
//        }
//
//        $serviceTestNamespace->add($testClass);
//
//        $testClass->setExtends('\\Tests\\TestCase');
//        $serviceTestNamespace->addUse('\\Tests\\TestCase');
//
//
//        foreach ($service->ddv1ServiceMethods as $testableServiceMethod) {
//            if ($testClass->hasMethod('test'.ucfirst($testableServiceMethod->title))) continue;
//
//            $testMethod = $testClass->addMethod('test'.ucfirst($testableServiceMethod->title));
//            $testMethod->addComment('@test');
//            $testMethod->addComment('@dataProvider '.$testableServiceMethod->title.'DataProvider');
//
//            if ($testableServiceMethod->ddv1ServiceInputParams()->count()==1) {
//                $testMethod->addParameter($testableServiceMethod->ddv1ServiceInputParams()->first()->title)
//                    ->setType($testableServiceMethod->ddv1ServiceInputParams()->first()->ddv1VariableTypes->title);
//            } else {
//                if ($testableServiceMethod->ddv1ServiceInputParams()->count()>1) {
//                    $fullArgumentType = '\\'.NamespaceAndPathGenerator::generateServiceDTONamespace(
//                            $this->architectureTitle,
//                            $this->moduleTitle,
//                        ).'\\'.ucfirst($testableServiceMethod->title).'InDto';
//                    $testMethod->addParameter('inDto')
//                        ->setType($fullArgumentType);
//                    $serviceTestNamespace->addUse($fullArgumentType);
//                }
//            }
//
//            $providerMethod = $testClass->addMethod($testableServiceMethod->title.'DataProvider');
//            $providerMethod->addComment('@return array');
//            $providerMethod->setReturnType('array');
//
//            $providerMethodBody = 'return ['."\n";
//            foreach ($this->getTestCasesForServiceMethod() as $testCase) {
//                $providerMethodBody .= '    "'.$testCase.'"=>['."\n\n\t".'], '."\n";
//            }
//            $providerMethodBody .= "\n];\n";
//            $providerMethod->setBody($providerMethodBody);
//
//            $testMethodBody = '';
//
//            if ($service->is_hyper && $service->ddv1InjectedServices()->count()>0) {
//                foreach ($service->ddv1InjectedServices as $injectedService) {
//                    $fullInjectedServiceName = '\\'.NamespaceAndPathGenerator::generateServiceNamespace(
//                            $this->architectureTitle,
//                            $this->moduleTitle,
//                        ).'\\'.$injectedService->title.'Service';
//                    $testMethodBody .= '$'.lcfirst($injectedService->title).'Service = $this->createMock('.$injectedService->title.'Service'.'::class);'."\n";
//                    $serviceTestNamespace->addUse($fullInjectedServiceName);
//                    $testMethodBody .= '$this->app->instance('.$injectedService->title.'Service'.'::class, $'.lcfirst($injectedService->title).'Service);'."\n";
//
//                    foreach ($injectedService->ddv1ServiceMethods as $injectedServiceMethod) {
//                        $testMethodBody .= '$'.lcfirst($injectedService->title).'Service->method(\''.$injectedServiceMethod->title.'\')->willReturn(null);'."\n";
//                    }
//                }
//            }
//
//            if (!$service->is_hyper && $service->ddv1Repositories()->count()>0) {
//                foreach ($service->ddv1Repositories as $injectedRepository) {
//                    $fullInjectedRepositoryName = '\\'.NamespaceAndPathGenerator::generateRepositoryNamespace(
//                            $this->architectureTitle,
//                            $this->moduleTitle,
//                        ).'\\'.$injectedRepository->title.'Repository';
//                    $testMethodBody .= '$'.lcfirst($injectedRepository->title).'Repository = $this->createMock('.$injectedRepository->title.'Repository'.'::class);'."\n";
//                    $serviceTestNamespace->addUse($fullInjectedRepositoryName);
//                    $testMethodBody .= '$this->app->instance('.$injectedRepository->title.'Repository'.'::class, $'.lcfirst($injectedRepository->title).'Repository);'."\n";
//
//                    foreach ($injectedRepository->ddv1RepositoryMethods as $injectedRepositoryMethod) {
//                        $testMethodBody .= '$'.lcfirst($injectedRepository->title).'Repository->method(\''.$injectedRepositoryMethod->title.'\')->willReturn(null);'."\n";
//                    }
//                }
//            }
//            $testMethod->setBody($testMethodBody);
//        }
//
//        $file = new \Nette\PhpGenerator\PhpFile;
//        $file->addComment('This file is generated by architect.');
//        $file->addNamespace($serviceTestNamespace);
//
//        @mkdir(dirname($serviceTestClassPath), recursive: true);
//        file_put_contents($serviceTestClassPath, $file);
//
//    }
//
//    private function getTestCasesForServiceMethod() {
//        return [
//          'ok',
//            'error'
//        ];
//    }
//}
