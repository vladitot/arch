<?php

namespace Vladitot\Architect\YamlComponents;


use Vladitot\Architect\AbstractGenerator;
use Vladitot\Architect\NamespaceAndPathGeneratorYaml;
use Vladitot\Architect\Yaml\Laravel\ServiceAggregator;
use Vladitot\Architect\Yaml\Module;
use Nette\PhpGenerator\ClassLike;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class ServiceAggregatorGenerator extends AbstractGenerator
{

    public function generate(Module $module)
    {
        $services = $module->service_aggregators;

        foreach ($services as $service) {
            $this->generateOneServiceAggregator($service, $module);
        }
    }

    private function generateOneServiceAggregator(ServiceAggregator $service, Module $module)
    {
        $filePathOfService = NamespaceAndPathGeneratorYaml::generateServiceAggregatorPath(
            $module->title,
            $service->title.'ServiceAggregator',
        );

        if (file_exists($filePathOfService)) {
            $class = \Nette\PhpGenerator\ClassType::fromCode(file_get_contents($filePathOfService));
        } else {
            $class = new ClassType($service->title.'ServiceAggregator');
        }

        foreach ($class->getMethods() as $method) {
            if ($method->isPublic()) {
                $method->setComment('@deprecated');
            }
        }
        $class->setComment('@architect '."\n".$service->comment ?? '');

        $namespace = new PhpNamespace(
            NamespaceAndPathGeneratorYaml::generateServiceAggregatorNamespace(
                $module->title,
            )
        );

        $namespace->add($class);

        $injectedClasses = $this->injectServicesOrRepositories($class, $service, $namespace, $module);

        $renderedMethods = [];
        foreach ($service->methods as $method) {
            if ($class->hasMethod($method->title)) {
                $codedMethod = $class->getMethod($method->title);
                $codedMethod->setParameters([]);
                $codedMethod->setReturnType(null);
            } else {
                $codedMethod = $class->addMethod($method->title);
            }

            $codedMethod->setComment('@architect '."\n".$method->comment ?? '');

            $aiQuery = 'PHP Laravel. Method name: "'.$method->title.'". ';

            $dtoNamespace = NamespaceAndPathGeneratorYaml::generateServiceAggregatorDTONamespace(
                $module->title,
            );
            $dtoDirname = dirname(NamespaceAndPathGeneratorYaml::generateServiceAggregatorDTOPath(
                $module->title,
                'any'
            ));
            $this->fillMethodWithParametersAndTypesOrDtos($method, $codedMethod, $namespace, $dtoNamespace, $dtoDirname);

            $aiQuery.=$this->prepareAiQueryForInputDtoParams($method);

            $aiQuery.= rtrim($method->comment, '.').'. ';
            $aiQuery.= 'Write code of method body. Do not call Eloquent or Query Builder here.'."\n";
            $aiQuery.= 'Use some of this injected Classes and theirs methods:'."\n";

            foreach ($injectedClasses as $injectedClass => $allowedMethods) {
                foreach ($allowedMethods as $allowedMethod) {
                    $aiQuery.= ' - '.$injectedClass.'::'.$allowedMethod."\n";
                }
            }
            $codedMethod->addComment($aiQuery);

            if ($codedMethod->getBody()==='') {
                $methodBody = $this->queryAiForAnswer($aiQuery);
                $matches = [];
                preg_match('/{(.*)}/s', $methodBody, $matches);
                $codedMethod->setBody($matches[1]);
            }

            $preparedToTestsMethod = clone $codedMethod;
            $preparedToTestsMethod->setComment('');
            $renderedMethods[$method->title] = (string) $preparedToTestsMethod;
        }

        $fileContent = $this->putNamespaceToFile($namespace, $filePathOfService);

        $this->generateTestsForService($service, $fileContent, $renderedMethods, $module);

        return $filePathOfService;
    }

    private function injectServicesOrRepositories(ClassType|ClassLike $class, ServiceAggregator $serviceAggregator, PhpNamespace $namespace, Module $module): array {
        $injectedClasses = [];
//        if (!$service->is_hyper && $service->ddv1InjectedServices()->count()>0) {
//            throw new \Exception('Cannot inject services into non-hyper service '.$service->title.'Service');
//        }
//
        if (count($serviceAggregator->services)>0) {
            if ($class->hasMethod('__construct')) {
                $constructor = $class->getMethod('__construct');
                $constructor->setParameters([]);
                $constructor->setBody('');
            } else {
                $constructor = $class->addMethod('__construct');
            }

            $injectableServiceNamespace = NamespaceAndPathGeneratorYaml::generateServiceNamespace(
                $module->title,
            );

            foreach ($serviceAggregator->services as $injectionServiceName) {

                foreach ($module->services as $injectionService) {
                    if ($injectionService->title !== $injectionServiceName) {
                        continue;
                    }
                    $fullInjectableServiceName = '\\' . $injectableServiceNamespace . '\\' . ucfirst($injectionService->title) . 'Service';

                    foreach ($injectionService->methods as $method) {
                        $injectedClasses[ucfirst($injectionService->title) . 'Service'][] = $method->title;
                    }
                    $constructor->addParameter(lcfirst($injectionService->title) . 'Service')
                        ->setType($fullInjectableServiceName);

                    $namespace->addUse($fullInjectableServiceName);

                    $property = $class->hasProperty(lcfirst($injectionService->title) . 'Service')
                        ? $class->getProperty(lcfirst($injectionService->title) . 'Service')
                        : $class->addProperty(lcfirst($injectionService->title) . 'Service');

                    $property
                        ->setVisibility('private')
                        ->setType($fullInjectableServiceName);

                    $constructor->addBody('$this->' . lcfirst($injectionService->title) . 'Service = $' . lcfirst($injectionService->title) . 'Service;');
                }
            }
        }


        if (count($serviceAggregator->repositories)>0) {
            if ($class->hasMethod('__construct')) {
                $constructor = $class->getMethod('__construct');
                $constructor->setParameters([]);
                $constructor->setBody('');
            } else {
                $constructor = $class->addMethod('__construct');
            }

            foreach ($serviceAggregator->repositories as $repositoryName) {

                foreach ($module->repositories as $repository) {
                    if ($repository->title !== $repositoryName) {
                        continue;
                    }
                    $fullInjectableRepositoryName = '\\'.NamespaceAndPathGeneratorYaml::generateRepositoryNamespace(
                            $module->title,
                        ).'\\'.ucfirst($repository->title.'Repository');
                    foreach ($repository->methods as $method) {
                        $injectedClasses[ucfirst($repository->title).'Repository'][] = $method->title;
                    }
                    $constructor->addParameter(lcfirst($repository->title).'Repository')
                        ->setType($fullInjectableRepositoryName);
                    $namespace->addUse($fullInjectableRepositoryName);
                    $property = $class->hasProperty(lcfirst($repository->title).'Repository')
                        ? $class->getProperty(lcfirst($repository->title).'Repository')
                        : $class->addProperty(lcfirst($repository->title).'Repository');

                    $property
                        ->setVisibility('private')
                        ->setType($fullInjectableRepositoryName);
                    $constructor->addBody('$this->'.lcfirst($repository->title).'Repository = $'.lcfirst($repository->title).'Repository;');
                    break;
                }
            }
        }
        return $injectedClasses;
    }

    private function generateTestsForService(ServiceAggregator $serviceAggregator, ?string $classFile, array $methodsText, Module $module)
    {
        $serviceTestClassPath = NamespaceAndPathGeneratorYaml::generateServiceAggregatorTestPath(
            $module->title,
            $serviceAggregator->title.'ServiceTest');

        $serviceTestNamespace = new PhpNamespace( NamespaceAndPathGeneratorYaml::generateServiceAggregatorTestNamespace(
            $module->title,
        ));

        $serviceTestNamespace->addUse(NamespaceAndPathGeneratorYaml::generateServiceAggregatorNamespace($module->title).'\\'.$serviceAggregator->title.'ServiceAggregator');

        if (file_exists($serviceTestClassPath)) {
            $testClass = \Nette\PhpGenerator\ClassType::fromCode(file_get_contents($serviceTestClassPath));
        } else {
            $testClass = new ClassType($serviceAggregator->title.'ServiceAggregatorTest');
        }

        $serviceTestNamespace->add($testClass);

        $testClass->setExtends('\\Tests\\TestCase');
        $serviceTestNamespace->addUse('\\Tests\\TestCase');

        $matches = [];
        preg_match('/^(.*?){.*?/s', $classFile, $matches);
        $fileHeader = $matches[1];
        $responseTestMethods = [];
        foreach ($serviceAggregator->methods as $testableServiceMethod) {
            if ($testClass->hasMethod('test'.ucfirst($testableServiceMethod->title))) continue;

            $aiQuery = 'PHP Laravel. Write Tests and dataProviders for method below, connect dataProviders via annotations.'."\n"
                .'Mock Dependencies. Put result class into namespace: \\'
                .NamespaceAndPathGeneratorYaml::generateServiceAggregatorTestNamespace($module->title)." Add to start of each test method line: \"throw new \Exception('You forget to check this test');\"\n\n";
            $aiQuery .= $fileHeader."\n";
            $aiQuery .= $methodsText[$testableServiceMethod->title]."\n\n";
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
}
