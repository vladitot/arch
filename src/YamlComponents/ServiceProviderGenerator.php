<?php

namespace Vladitot\Architect\YamlComponents;

use AbstractGenerator;
use Vladitot\Architect\Yaml\Module;
use NamespaceAndPathGeneratorYaml;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class ServiceProviderGenerator extends AbstractGenerator
{

    public function generate(Module $module)
    {

        $class = new ClassType(ucfirst($module->title).'ServiceProvider');
        $class->setExtends('\\Illuminate\\Support\\ServiceProvider');

        $registerMethod = $class->addMethod('register');
        $bootMethod = $class->addMethod('boot');

        $bootBody = '';

        $bootBody = $this->addRoutesToBootMethod($module, $bootBody);

        $bootMethod->setBody($bootBody);

        $namespace = new PhpNamespace(NamespaceAndPathGeneratorYaml::generateServiceProviderNamespace(
            $module->title
        ));
        $namespace->addUse('\\Illuminate\\Support\\ServiceProvider');
        $namespace->addUse('\\Illuminate\\Support\\Facades\\Route');

        $namespace->add($class);

        $serviceProviderPath = NamespaceAndPathGeneratorYaml::generateServiceProvidersPath(
            $module->title,
            ucfirst($module->title).'ServiceProvider'
        );

        $file = new \Nette\PhpGenerator\PhpFile;
        $file->addComment('This file is generated by architect.');
        $file->addNamespace($namespace);

        @mkdir(dirname($serviceProviderPath), recursive: true);
        file_put_contents($serviceProviderPath, $file);

        return $namespace->getName().'\\'.ucfirst($module->title).'ServiceProvider';
    }


    public function addRoutesToBootMethod(Module $module, string $bootBody): string {
        foreach ($module->service_aggregators as $serviceAggregator) {
            foreach ($serviceAggregator->methods as $aggregatorMethod) {
                if (!isset($aggregatorMethod->controller_fields)) continue;

                $filePathOfRouteFile = NamespaceAndPathGeneratorYaml::generateRoutesPath(
                    $module->title,
                    $serviceAggregator->title.'Routes',
                );

                $bootBody.="Route::middleware('api')\n";
                $filePathOfRouteFile = NamespaceAndPathGeneratorYaml::generateRoutesPath(
                    $module->title,
                    $serviceAggregator->title.'Routes',
                );
                $filePathOfRouteFile = str_replace(base_path(), '', $filePathOfRouteFile);
                $filePathOfRouteFile = trim($filePathOfRouteFile, '/');
                $bootBody.="\t".'->group(base_path()."/'.$filePathOfRouteFile.'");'."\n";
                break;
            }
        }
        return $bootBody;
    }
}
