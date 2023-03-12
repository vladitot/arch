<?php

namespace Vladitot\Architect\YamlComponents;


use Vladitot\Architect\AbstractGenerator;
use Vladitot\Architect\NamespaceAndPathGeneratorYaml;
use Vladitot\Architect\Yaml\Laravel\Repository;
use Vladitot\Architect\Yaml\Module;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class RepositoryGenerator extends AbstractGenerator
{

    public function generate(Module $module)
    {

        $repositories = $module->repositories;

        foreach ($repositories as $repository) {
            $this->generateOneRepository($repository, $module);
        }

    }

    public function generateOneRepository(Repository $repository, Module $module)
    {
        $filePathOfRepository = NamespaceAndPathGeneratorYaml::generateRepositoryPath(
            $module->title,
            $repository->title.'Repository',
        );

        if (file_exists($filePathOfRepository)) {
            $class = \Nette\PhpGenerator\ClassType::fromCode(file_get_contents($filePathOfRepository));
        } else {
            $class = new ClassType($repository->title.'Repository');
        }

        foreach ($class->getMethods() as $method) {
            if ($method->isPublic()) {
                $method->setComment('@deprecated');
            }
        }
        $class->setComment('@architect'."\n".$repository->comment ?? '');

        $namespace = new PhpNamespace(
            NamespaceAndPathGeneratorYaml::generateRepositoryNamespace(
                $module->title,
            )
        );

        $namespace->add($class);

        foreach ($module->models as $model) {
            $namespace->addUse(
                NamespaceAndPathGeneratorYaml::generateModelNamespace(
                    $module->title,
                ).'\\'.$model->title
            );
        }
        $renderedMethods = [];
        foreach ($repository->methods as $method) {
            if ($class->hasMethod($method->title)) {
                $codedMethod = $class->getMethod($method->title);
                $codedMethod->setParameters([]);
                $codedMethod->setReturnType(null);
            } else {
                $codedMethod = $class->addMethod($method->title);
            }

            $codedMethod->setComment('@architect '."\n".$method->comment ?? '');

            $aiQuery = 'PHP Laravel. Method name: "'.$method->title.'". ';

            $dtoNamespace = NamespaceAndPathGeneratorYaml::generateRepositoryDTONamespace(
                $module->title,
            );
            $dtoDirname = dirname(NamespaceAndPathGeneratorYaml::generateRepositoryDTOPath(
                $module->title,
                'any'
            ));
            $this->fillMethodWithParametersAndTypesOrDtos($method, $codedMethod, $namespace, $dtoNamespace, $dtoDirname);

            $aiQuery.=$this->prepareAiQueryForInputDtoParams($method);

            $aiQuery.= rtrim($method->comment, '.').'. ';
            $aiQuery.= 'Write code of method body.';
            $codedMethod->addComment($aiQuery);
            if ($codedMethod->getBody()==='') {
                $methodBody = $this->queryAiForAnswer($aiQuery);
                $matches = [];
                preg_match('/{(.*)}/s', $methodBody, $matches);
                $codedMethod->setBody(
                    "throw new \Exception('You forget to check this repository method');"."\n".
                    $matches[1]
                );
            }

            $preparedToTestsMethod = clone $codedMethod;
            $preparedToTestsMethod->setComment('');
            $renderedMethods[$method->title] = (string) $preparedToTestsMethod;
        }

        $fileContent = $this->putNamespaceToFile($namespace, $filePathOfRepository);

        $this->generateTestsForRepository($repository, $fileContent, $renderedMethods, $module);

        return $filePathOfRepository;

    }

//    /**
//     * @param string $paramTitle
//     * @param string $methodName
//     * @param Ddv1OutputParam[] $childParams
//     * @return string
//     * @deprecated
//     */
//    private function createOutputSubDto(string $paramTitle, string $methodName, \Traversable $childParams): string {
//        $class = new ClassType(ucfirst($methodName).ucFirst($paramTitle).'ListOutDto');
//        $constructor = $class->addMethod('__construct')
//            ->setPublic();
//        $constructorBody = '';
//        foreach ($childParams as $childParam) {
//            if ($childParam->ddv1ChildrenParams()->count()>0) {
//                //тут надо открыть рекурсию
//                $currentType = $this->createOutputSubDto($paramTitle.ucfirst($childParam->title), $methodName, $childParam->ddv1ChildrenParams);
//                $class->addProperty($paramTitle.ucfirst($childParam->title))
//                    ->setType('array')
//                    ->setReadOnly(true)
//                    ->addComment('@param '.$currentType.'[] $'.$paramTitle.ucfirst($childParam->title))
//                    ->setPublic();
//
//                $constructor->addParameter($paramTitle.ucfirst($childParam->title))
//                    ->setType('array');
//                $constructorBody .= '$this->'.$paramTitle.ucfirst($childParam->title).' = $'.$paramTitle.ucfirst($childParam->title).';'."\n";
//
//            } else {
//                $class->addProperty($paramTitle.ucfirst($childParam->title))
//                    ->setType($childParam->ddv1VariableType->title)
//                    ->setReadOnly(true)
//                    ->setPublic();
//                $constructor->addParameter($paramTitle.ucfirst($childParam->title))
//                    ->setType($childParam->ddv1VariableType->title);
//                $constructorBody .= '$this->'.$paramTitle.ucfirst($childParam->title).' = $'.$paramTitle.ucfirst($childParam->title).';'."\n";
//            }
//        }
//        $constructor->setBody($constructorBody);
//
//        $namespace = new PhpNamespace(NamespaceAndPathGeneratorYaml::generateRepositoryDTONamespace(
//
//            $this->architectureTitle,
//            $this->moduleTitle,
//        ));
//
//        $namespace->add($class);
//
//        $path = NamespaceAndPathGeneratorYaml::generateRepositoryDTOPath($this->projectTitle,
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

    private function generateTestsForRepository(Repository $repository, string $classFile, array $methodsText, Module $module): void
    {
        $serviceTestClassPath = NamespaceAndPathGeneratorYaml::generateRepositoryTestPath(
            $module->title,
            $repository->title.'RepositoryTest');

        $serviceTestNamespace = new PhpNamespace( NamespaceAndPathGeneratorYaml::generateRepositoryTestNamespace(
            $module->title,
        ));

        $serviceTestNamespace->addUse(NamespaceAndPathGeneratorYaml::generateRepositoryNamespace($module->title).'\\'.$repository->title.'Repository');

        if (file_exists($serviceTestClassPath)) {
            $testClass = \Nette\PhpGenerator\ClassType::fromCode(file_get_contents($serviceTestClassPath));
        } else {
            $testClass = new ClassType($repository->title.'RepositoryTest');
        }

        $serviceTestNamespace->add($testClass);

        $testClass->setExtends('\\Tests\\TestCase');
        $serviceTestNamespace->addUse('\\Tests\\TestCase');

        $matches = [];
        preg_match('/^(.*?){.*?/s', $classFile, $matches);
        $fileHeader = $matches[1];
        $responseTestMethods = [];
        foreach ($repository->methods as $testableRepositoryMethod) {
            if ($testClass->hasMethod('test'.ucfirst($testableRepositoryMethod->title))) continue;

            $aiQuery = 'PHP Laravel. Write Tests and dataProviders for method below, connect dataProviders via annotations.'."\n"
                .'Mock Dependencies. Put result class into namespace: \\'
                .NamespaceAndPathGeneratorYaml::generateRepositoryTestNamespace($module->title)." Add to start of each test method line: \"throw new \Exception('You forget to check this test');\"\n\n";
            $aiQuery .= $fileHeader."\n";
            $aiQuery .= $methodsText[$testableRepositoryMethod->title]."\n\n";
            $aiQuery .='}'. "\n\n";

            $aiResult = $this->queryAiForAnswer($aiQuery);
            preg_match_all('/use\s.*?;/s', $aiResult, $uses);
            foreach ($uses[0] as $use) {
                $use = str_replace('use ', '\\', $use);
                $use = str_replace(';', '', $use);
                $serviceTestNamespace->addUse($use);
            }
            preg_match('/{(.*)}/s', $aiResult, $body);
            $responseTestMethods[] = $body[1];

        }

        $file = new \Nette\PhpGenerator\PhpFile;
        $file->addComment('This file is generated by architect.');
        $file->addNamespace($serviceTestNamespace);
        $file = (string) $file;

        $file = preg_replace('/}\s*$/s', '', $file);

        $file = $file."\n\n".implode("\n\n", $responseTestMethods)."\n\n}\n";

        @mkdir(dirname($serviceTestClassPath), recursive: true);
        file_put_contents($serviceTestClassPath, $file);

    }




//    /**
//     * @param Method $method
//     * @param Ddv1OutputParam[] $outputParams
//     * @param string $methodName
//     * @return void
//     * @deprecated
//     */
//    private function createOutputDtoAndFillMethod(\Traversable $outputParams, string $methodName):string
//    {
//        $outputType = new ClassType(ucfirst($methodName).'OutDto');
//        $constructor = $outputType->addMethod('__construct')
//            ->setPublic();
//        $constructorBody = '';
//        foreach ($outputParams as $param) {
//            if ($param->ddv1ChildrenParams()->count()==0) { //проверяем, есть ли дочерние параметры
//                if ($outputType->hasProperty($param->title)) {
//                    $property = $outputType->getProperty($param->title);
//                } else {
//                    $property = $outputType->addProperty($param->title);
//                }
//                $property
//                    ->setType($param->ddv1VariableType->title)
//                    ->setReadOnly(true)
//                    ->setPublic();
//
//                $constructor->addParameter($param->title)
//                    ->setType($param->ddv1VariableType->title);
//                $constructorBody .= '$this->'.$param->title.' = $'.$param->title.";\n";
//            } else {
//                //если есть дочерние, то нужно создать subDTO
//                $subDtoType = $this->createOutputSubDto($param->title, $methodName, $param->ddv1ChildrenParams);
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
//
//                $constructor->addParameter($param->title)
//                    ->setType('array');
//                $constructorBody .= '$this->'.$param->title.' = $'.$param->title.";\n";
//            }
//        }
//        $constructor->setBody($constructorBody);
//
//        $namespace = new PhpNamespace(NamespaceAndPathGeneratorYaml::generateRepositoryDTONamespace(
//            $this->architectureTitle,
//            $this->moduleTitle,
//        ));
//
//        $namespace->add($outputType);
//        $outputType->setExtends('\\Spatie\\LaravelData\\Data');
//        $namespace->addUse('Spatie\\LaravelData\\Data');
//
//        $path = NamespaceAndPathGeneratorYaml::generateRepositoryDTOPath($this->projectTitle,
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


}
