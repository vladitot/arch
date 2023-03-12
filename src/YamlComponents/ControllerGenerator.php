<?php

namespace Vladitot\Architect\YamlComponents;

use Vladitot\Architect\AbstractGenerator;
use Vladitot\Architect\NamespaceAndPathGeneratorYaml;
use Vladitot\Architect\Yaml\Laravel\AggregatorMethod;
use Vladitot\Architect\Yaml\Laravel\OutputParam;
use Vladitot\Architect\Yaml\Laravel\ServiceAggregator;
use Vladitot\Architect\Yaml\Module;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class ControllerGenerator extends AbstractGenerator
{

    public function generate(Module $module)
    {
        $serviceAggregators = $module->service_aggregators;

        foreach ($serviceAggregators as $serviceAggregator) {
            foreach ($serviceAggregator->methods as $method) {
                if (isset($method->controller_fields)) {
                    $this->generateOneController($serviceAggregator, $module);
                    break;
                }
            }

        }
    }

    private function generateOneController(ServiceAggregator $serviceAggregator, Module $module)
    {
        $filePathOfController = NamespaceAndPathGeneratorYaml::generateControllerPath(
            $module->title,
            $serviceAggregator->title.'Controller',
        );

        if (file_exists($filePathOfController)) {
            $class = \Nette\PhpGenerator\ClassType::fromCode(file_get_contents($filePathOfController));
        } else {
            $class = new ClassType($serviceAggregator->title.'Controller');
        }

        foreach ($class->getMethods() as $method) {
            if ($method->isPublic()) {
                $method->setComment('@deprecated');
            }
        }
        $class->setComment('@architect');

        $namespace = new PhpNamespace(
            NamespaceAndPathGeneratorYaml::generateControllerNamespace(
                $module->title
            )
        );

        $namespace->add($class);
        $class->setExtends(\App\Http\Controllers\Controller::class);
        $namespace->addUse(\App\Http\Controllers\Controller::class);

        $renderedMethods = [];
        foreach ($serviceAggregator->methods as $method) {
            if ($class->hasMethod($method->title)) {
                $codedMethod = $class->getMethod($method->title);
                $codedMethod->setParameters([]);
                $codedMethod->setReturnType(null);
            } else {
                $codedMethod = $class->addMethod($method->title);
            }

            $codedMethod->setComment('@architect '."\n".$method->comment ?? '');

            $request = $this->createRequest($method, $module);
            $codedMethod->addParameter('request')
                ->setType($request);
            $namespace->addUse($request);

            $serviceAggregatorFullName = '\\'.NamespaceAndPathGeneratorYaml::generateServiceAggregatorNamespace(
                    $module->title,
                ).'\\'.$serviceAggregator->title.'ServiceAggregator';

            $codedMethod->addParameter(lcfirst($serviceAggregator->title).'ServiceAggregator')
                ->setType($serviceAggregatorFullName);

            $namespace->addUse($serviceAggregatorFullName);

            $aiQuery = 'PHP Laravel. Http controller method name: "'.$method->title.'". ';

            $aiQuery.=$this->prepareAiQueryForInputDtoParams($method);

            $aiQuery.= rtrim($method->comment, '.').'. ';
            $aiQuery.= 'Write code of controller method body. Do not call Eloquent or Query Builder here.'."\n";
            $aiQuery.= 'Use method of injected class:'."\n";
            $aiQuery.= $serviceAggregator->title.'ServiceAggregator::'.$method->title."\n";

            $codedMethod->addComment($aiQuery);
            if ($codedMethod->getBody()==='') {
                $methodBody = $this->queryAiForAnswer($aiQuery);
                $matches = [];
                preg_match('/{(.*)}/s', $methodBody, $matches);
                $codedMethod->setBody(
                    "throw new \Exception('You forget to check this controller method');"."\n".
                    $matches[1]
                );
            }

            $resource = $this->createResource($method, $module);
            $namespace->addUse($resource);
            $codedMethod->setReturnType($resource);

            $preparedToTestsMethod = clone $codedMethod;
            $preparedToTestsMethod->setComment('');
            $renderedMethods[$method->title] = (string) $preparedToTestsMethod;
        }

        $fileContent = $this->putNamespaceToFile($namespace, $filePathOfController);

        $this->generateTestsForController($serviceAggregator, $fileContent, $renderedMethods, $module);

        return $filePathOfController;
    }

    private function createResource(AggregatorMethod $method, Module $module): string {
        $class = new ClassType(ucfirst($method->title).'JsonResource');
        $namespace = new PhpNamespace(NamespaceAndPathGeneratorYaml::generateControllerResourceNamespace(
            $module->title,
        ));
        $class->setExtends('\\Illuminate\\Http\\Resources\\Json\\JsonResource');
        $namespace->addUse('\\Illuminate\\Http\\Resources\\Json\\JsonResource');

        $class->addProperty('wrap')
            ->setStatic()
            ->setPublic()
            ->setValue('result');

        $class->addProperty('with')
            ->setPublic()
            ->setValue(['error' => null]);

        $toArrayMethod = $class->addMethod('toArray')
            ->setPublic();
        $toArrayMethod
            ->addParameter('request');



        $toArrayBody = 'return $this->resource->toArray();'."\n";

//        $toArrayBody = 'return ['."\n";

//        $dtoResource = $this->prepareDtoForJsonResource($methodName, $outputParams);

        $fullAggregatedServiceOutputDtoName = NamespaceAndPathGeneratorYaml::generateServiceAggregatorDTONamespace(
                $module->title,
            ).'\\'.ucfirst($method->title).'OutDto';

        $class->addComment('@property \\'.$fullAggregatedServiceOutputDtoName.' $resource');

//        foreach ($outputParams as $param) {
//            $toArrayBody .= "'".$param->title."' => ".'$this->resource->'.$param->title.','."\n";
//        }

//        $toArrayBody .='];';

        $toArrayMethod->setBody($toArrayBody);

        $path = NamespaceAndPathGeneratorYaml::generateControllerResourcePath(
            $module->title,
            ucfirst($method->title).'JsonResource');

        $namespace->add($class);

        $this->putNamespaceToFile($namespace, $path);
        return '\\'.$namespace->getName().'\\'.$class->getName();
    }

    /**
     * @param string $methodName
     * @param \Traversable|array|OutputParam[] $outputParams
     * @param Module $module
     * @param $dtoPrefix
     * @return string
     */
    private function prepareDtoForJsonResource(string $methodName, \Traversable|array $outputParams, Module $module, $dtoPrefix = '')
    {
        $class = new ClassType(ucfirst($dtoPrefix).ucfirst($methodName).'ResourceDto');
        $namespace = new PhpNamespace(NamespaceAndPathGeneratorYaml::generateControllerResourceNamespace(
            $module->title,
        ));

        $namespace->add($class);
        $keysArrays = [];
        foreach ($outputParams as $param) {
            if (count($param->childrenParams)==0) {
                if ($class->hasProperty($param->title)) {
                    $property = $class->getProperty($param->title);
                } else {
                    $property = $class->addProperty($param->title);
                }
                $property
                    ->setPublic()
                    ->setType($param->type);
            } else {
                $type = $this->prepareDtoForJsonResource(
                    $methodName, $param->childrenParams, $module, $dtoPrefix.ucfirst($param->title)
                );
                $class->addProperty($param->title)
                    ->setPublic()
                    ->setType('array')
                    ->addComment('@param \\'.$type.'[] $'.$param->title);
                $keysArrays[$param->title] = '\\'.$type;
            }
        }

//        AddArrayConvertingToClass::addToArrayMethodsToClass($class, $keysArrays);

        $path = NamespaceAndPathGeneratorYaml::generateControllerResourcePath($module->title,
            ucfirst($dtoPrefix).ucfirst($methodName).'ResourceDto');

        $file = new \Nette\PhpGenerator\PhpFile;
        $file->addComment('This file is generated by architect.');
        $file->addNamespace($namespace);

        @mkdir(dirname($path), recursive: true);
        file_put_contents($path, $file);

        return $namespace->getName().'\\'.$class->getName();
    }

    private function createRequest(AggregatorMethod $method, Module $module) {
        $request = new ClassType(ucfirst($method->title).'Request');

        $request->setExtends('\\Illuminate\\Foundation\\Http\\FormRequest');

        $this->putValidatorInObject($method->inputParams, $request, true);

        $namespace = new PhpNamespace(NamespaceAndPathGeneratorYaml::generateControllerRequestNamespace(
            $module->title,
        ));

        $namespace->addUse('\\Illuminate\\Foundation\\Http\\FormRequest');

        $namespace->add($request);

        $path = NamespaceAndPathGeneratorYaml::generateControllerRequestPath(
            $module->title,
            ucfirst($method->title).'Request');

        $this->putNamespaceToFile($namespace, $path);

        return '\\'.$namespace->getName().'\\'.$request->getName();
    }

//    private function fillMethodWithRequestAndResponse(Ddv1Method $method, Method $codedMethod) {
//
//    }

    private function generateTestsForController(ServiceAggregator $serviceAggregator, ?string $classFile, array $methodsText, Module $module)
    {
        $controllerTestClassPath = NamespaceAndPathGeneratorYaml::generateControllerTestPath(
            $module->title,
            $serviceAggregator->title.'ControllerTest');

        $controllerTestNamespace = new PhpNamespace( NamespaceAndPathGeneratorYaml::generateControllerTestNamespace($module->title));

        $controllerTestNamespace->addUse(NamespaceAndPathGeneratorYaml::generateServiceAggregatorNamespace(
            $module->title
            ).'\\'.$serviceAggregator->title.'ServiceAggregator');

        if (file_exists($controllerTestClassPath)) {
            $testClass = \Nette\PhpGenerator\ClassType::fromCode(file_get_contents($controllerTestClassPath));
        } else {
            $testClass = new ClassType($serviceAggregator->title.'ControllerTest');
        }

        $controllerTestNamespace->add($testClass);

        $testClass->setExtends('\\Tests\\TestCase');
        $controllerTestNamespace->addUse('\\Tests\\TestCase');

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
                $controllerTestNamespace->addUse($use);
            }
            preg_match('/{(.*)}/s', $aiResult, $body);
            $responseTestMethods[] = $body[1];

        }

        $file = new \Nette\PhpGenerator\PhpFile;
        $file->addComment('This file is generated by architect.');
        $file->addNamespace($controllerTestNamespace);
        $file = (string) $file;

        $file = preg_replace('/}\s*$/s', '', $file);

        $file = $file."\n\n".implode("\n\n", $responseTestMethods)."\n\n}\n";

        @mkdir(dirname($controllerTestClassPath), recursive: true);
        file_put_contents($controllerTestClassPath, $file);
    }
}
