<?php

namespace Vladitot\Architect\YamlComponents;


use Vladitot\Architect\AbstractGenerator;
use Vladitot\Architect\NamespaceAndPathGeneratorYaml;
use Vladitot\Architect\Yaml\Laravel\ServiceAggregator;
use Vladitot\Architect\Yaml\Module;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;

class RoutesGenerator extends AbstractGenerator
{

    public function generate(Module $module)
    {
        $serviceAggregators = $module->service_aggregators;

        foreach ($serviceAggregators as $serviceAggregator) {
            foreach ($serviceAggregator->methods as $method) {
                if (!isset($method->controller_fields)) continue;
            }
            $this->generateRoutesForOneController($serviceAggregator, $module);
        }
    }

    public function generateRoutesForOneController(ServiceAggregator $serviceAggregator, Module $module)
    {
        $filePathOfRouteFile = NamespaceAndPathGeneratorYaml::generateRoutesPath(
            $module->title,
            $serviceAggregator->title.'Routes',
        );

        $controllerNamespace = NamespaceAndPathGeneratorYaml::generateControllerNamespace(
            $module->title,
        );

        $filePathOfRoutesHelperClass = NamespaceAndPathGeneratorYaml::generateRoutesHelperPath(
            $module->title,
            $serviceAggregator->title.'RoutesHelper',
        );

        $routesHelperNamespace = new PhpNamespace(NamespaceAndPathGeneratorYaml::generateRoutesHelpersNamespace(
            $module->title,
        ));

        if (file_exists($filePathOfRoutesHelperClass)) {
            $class = \Nette\PhpGenerator\ClassType::fromCode(file_get_contents($filePathOfRoutesHelperClass));
        } else {
            $class = new ClassType($serviceAggregator->title.'RoutesHelper');
        }

        foreach ($class->getMethods() as $method) {
            $method->addComment('@deprecated');
        }

        $routesFileBody = '';


        foreach ($serviceAggregator->methods as $method) {
            if (!isset($method->controller_fields)) continue;
            if ($class->hasMethod($method->title)) {
                $class->getMethod($method->title)->setComment('You can change body of this route helper methods.');
            } else {
                $routeMethod = $class->addMethod($method->title)
                    ->setStatic()
                    ->setPublic();
                $routeMethodBody = 'Route::' . $method->controller_fields->http_method . '("'
                    . $method->controller_fields->route . '", [\\' . $controllerNamespace . '\\' . ucfirst($serviceAggregator->title) . 'Controller::class, "' . $method->title . '"])' . "\n";
                $routeMethodBody .= "\t->name('" . lcfirst($serviceAggregator->title) . '.' . $method->title . "');";
                $routeMethod->setBody($routeMethodBody);
            }
            $routesFileBody.= '\\'.$routesHelperNamespace->getName().'\\'.$class->getName().'::'.$method->title.'();'."\n";
        }

        $routesHelperNamespace->addUse(\Illuminate\Support\Facades\Route::class);
//        $body = '';
//
        $routesFileBody='<?php'."\n".'use Illuminate\Support\Facades\Route;'."\n\n\n".$routesFileBody;
//
//        foreach ($serviceAggregator->ddv1ControllerMethods as $method) {
//            $body.='Route::'.$method->http_method.'("'
//                .$method->route.'", [\\'.$controllerNamespace.'\\'.ucfirst($serviceAggregator->title).'Controller::class, "'.$method->title.'"])'."\n";
//            $body.="\t->name('".lcfirst($serviceAggregator->title).'.'.$method->title."')";
//            foreach ($method->ddv1ControllerMiddlewares as $middleware) {
//                $body.="\n\t->middleware(".$middleware->class_name."::class, '".$middleware->default_params."')";
//            }
//            $body.=';'."\n";
//        }

        $routesHelperNamespace->add($class);

        $this->putNamespaceToFile($routesHelperNamespace, $filePathOfRoutesHelperClass);

        @mkdir(dirname($filePathOfRouteFile), recursive: true);
        file_put_contents($filePathOfRouteFile, $routesFileBody);



    }
}
