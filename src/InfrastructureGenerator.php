<?php

namespace Vladitot\Architect;

use Vladitot\Architect\Yaml\Module;
use Vladitot\Architect\Yaml\Project;

class InfrastructureGenerator
{
    /**
     * @param Project $project
     * @param array|Module[] $modules
     * @return void
     */
    public function generateInfra(Project $project, array $modules) {
        $environments = [
            'local',
            'local-xdebug',
            'review',
            'rc',
            'prod',
        ];
        @mkdir(base_path().'/infra/rr', 0777, true);
        $this->prepareDevContainerRoadrunner($project, $modules);
        $this->prepareDevspaceFile($project, $modules);
        foreach ($environments as $environment) {
            if ($environment==='local') {
                $this->prepareLocalEnvironment($project, $modules);
                $this->prepareLocalRoadrunner($project, $modules);
                continue;
            }
            if ($environment==='local-xdebug') {
                $this->prepareLocalDebugEnvironment($project, $modules);
                $this->prepareLocalDebugRoadrunner($project, $modules);
            }
            if ($environment==='review') {
                $this->prepareReviewEnvironment($project, $modules);
                $this->prepareReviewRoadrunner($project, $modules);
            }
            if ($environment==='rc') {
                $this->prepareRcEnvironment($project, $modules);
                $this->prepareRcRoadrunner($project, $modules);
            }
            if ($environment==='prod') {
                $this->prepareProdEnvironment($project, $modules);
                $this->prepareProdRoadrunner($project, $modules);
            }
        }
    }

    private function prepareDevspaceFile(Project $project, array $modules) {
        $template = yaml_parse_file(dirname(__FILE__).'/../devspace/devspace.yaml');
        if (!file_exists(base_path().'/.env.infra')) {
            touch(base_path().'/.env.infra');
        }
        if (!file_exists(base_path().'/pullKey.yaml')) {
            touch(base_path().'/pullKey.yaml');
        }
        $gitignore = file_get_contents(base_path().'/.gitignore');
        if (!strpos($gitignore, '.env.infra')) {
            $gitignore.="\n.env.infra\n";
        }
        if (!strpos($gitignore, 'pullKey.yaml')) {
            $gitignore.="\npullKey.yaml\n";
        }

        if (!file_exists(base_path().'/customValuesCommitted.yaml')) {
            touch(base_path().'/customValuesCommitted.yaml');
        }

        if (!file_exists(base_path().'/customValues.yaml')) {
            touch(base_path().'/customValues.yaml');
        }

        if (!strpos($gitignore, 'customValues.yaml')) {
            $gitignore.="\ncustomValues.yaml\n";
        }

        file_put_contents(base_path().'/.gitignore', $gitignore);
        $template['name'] = $project->title ?? '';
        yaml_emit_file(base_path().'/devspace.yaml', $template);
    }

    private function prepareDevContainerRoadrunner(Project $project, array $modules) {
        $template = yaml_parse_file(dirname(__FILE__).'/../rrTemplates/rr.dev-container.template.yaml');
        yaml_emit_file(base_path().'/infra/rr/rr.dev-container.yaml', $template);
    }

    private function prepareLocalRoadrunner(Project $project, array $modules) {
        $template = yaml_parse_file(dirname(__FILE__).'/../rrTemplates/rr.template.yaml');
        yaml_emit_file(base_path().'/infra/rr/rr.local.yaml', $template);
    }

    private function prepareLocalDebugRoadrunner(Project $project, array $modules) {
        $template = yaml_parse_file(dirname(__FILE__).'/../rrTemplates/rr.template.yaml');
        yaml_emit_file(base_path().'/infra/rr/rr.local.xdebug.yaml', $template);
    }

    private function prepareReviewRoadrunner(Project $project, array $modules) {
        $template = yaml_parse_file(dirname(__FILE__).'/../rrTemplates/rr.template.yaml');
        unset($template['reload']);
        yaml_emit_file(base_path().'/infra/rr/rr.review.yaml', $template);
    }

    private function prepareRcRoadrunner(Project $project, array $modules) {
        $template = yaml_parse_file(dirname(__FILE__).'/../rrTemplates/rr.template.yaml');
        unset($template['reload']);
        yaml_emit_file(base_path().'/infra/rr/rr.rc.yaml', $template);
    }

    private function prepareProdRoadrunner(Project $project, array $modules) {
        $template = yaml_parse_file(dirname(__FILE__).'/../rrTemplates/rr.template.yaml');
        unset($template['reload']);
        yaml_emit_file(base_path().'/infra/rr/rr.prod.yaml', $template);
    }

    private function removeUnneededQuotasFromYamlFile(string $filePath) {
        $content = file_get_contents($filePath);
        $content = str_replace('"{{', '{{', $content);
        $content = str_replace('\'{{', '{{', $content);
        $content = str_replace('}}"', '}}', $content);
        $content = str_replace('}}\'', '}}', $content);
        file_put_contents($filePath, $content);
    }

    /**
     * @param Project $project
     * @param array|Module[] $modules
     * @return array
     */
    private function prepareRcEnvironment(Project $project, array $modules): array {
        $basicChart = yaml_parse_file(dirname(__FILE__).'/../valuesTemplates/basic.values.yaml');
        $weHavePublicHttpEndpoints = $this->checkDoWeHavePublicHttpEndpoints($modules);

        $values = [];

        if ($weHavePublicHttpEndpoints) {
            $values = $this->addHttpEndpointsToValues($basicChart, $values, $modules);
        }

        $values['configMaps']['app-config'] = $basicChart['configMaps']['app-config'];
        if (array_key_exists('deployments', $values)) {
            foreach ($values['deployments'] as &$deployment) {
                foreach ($deployment['containers'] as &$container) {
                    if ($container['name'] == 'roadrunner-http') {
                        $container['image'] = '{{ $.Values.image }}';
                        $container['imageTag'] = '{{ $.Values.imageTag }}';
                        $container['args'] = ["serve", "-c", "/var/www/infra/rr/rr.rc.yaml"];
                    }
                }
            }
        }

        $values = $this->addDatabase($basicChart, $values, $modules);

        @mkdir(base_path().'/infra/values', 0777, true);
        yaml_emit_file(base_path().'/infra/values/rc.values.yaml', $values);
        $this->removeUnneededQuotasFromYamlFile(base_path().'/infra/values/rc.values.yaml');
        return $values;
    }

    /**
     * @param Project $project
     * @param array|Module[] $modules
     * @return array
     */
    private function prepareProdEnvironment(Project $project, array $modules): array {
        $basicChart = yaml_parse_file(dirname(__FILE__).'/../valuesTemplates/basic.values.yaml');
        $weHavePublicHttpEndpoints = $this->checkDoWeHavePublicHttpEndpoints($modules);

        $values = [];

        if ($weHavePublicHttpEndpoints) {
            $values = $this->addHttpEndpointsToValues($basicChart, $values, $modules);
            foreach ($values['deployments'] as &$deployment) {
                foreach ($deployment['containers'] as &$container) {
                    if ($container['name']=='roadrunner-http') {
                        $container['image'] = '{{ $.Values.image }}';
                        $container['imageTag'] = '{{ $.Values.imageTag }}';
                        $container['args'] = ["serve", "-c", "/var/www/infra/rr/rr.prod.yaml"];
                    }
                }
            }
        }

        $values['configMaps']['app-config'] = $basicChart['configMaps']['app-config'];

        @mkdir(base_path().'/infra/values', 0777, true);
        yaml_emit_file(base_path().'/infra/values/prod.values.yaml', $values);
        $this->removeUnneededQuotasFromYamlFile(base_path().'/infra/values/prod.values.yaml');
        return $values;
    }

    /**
     * @param Project $project
     * @param array|Module[] $modules
     * @return array
     */
    private function prepareReviewEnvironment(Project $project, array $modules): array {
        $basicChart = yaml_parse_file(dirname(__FILE__).'/../valuesTemplates/basic.values.yaml');
        $weHavePublicHttpEndpoints = $this->checkDoWeHavePublicHttpEndpoints($modules);

        $values = [];

        if ($weHavePublicHttpEndpoints) {
            $values = $this->addHttpEndpointsToValues($basicChart, $values, $modules);
        }
        $values['configMaps']['app-config'] = $basicChart['configMaps']['app-config'];

        if (array_key_exists('deployments', $values)) {
            foreach ($values['deployments'] as &$deployment) {
                foreach ($deployment['containers'] as &$container) {
                    if ($container['name'] == 'roadrunner-http') {
                        $container['image'] = '{{ $.Values.image }}';
                        $container['imageTag'] = '{{ $.Values.imageTag }}';
                        $container['args'] = ["serve", "-c", "/var/www/infra/rr/rr.review.yaml"];
                    }
                }
            }
        }

        $values = $this->addDatabase($basicChart, $values, $modules);

        @mkdir(base_path().'/infra/values', 0777, true);
        yaml_emit_file(base_path().'/infra/values/review.values.yaml', $values);
        $this->removeUnneededQuotasFromYamlFile(base_path().'/infra/values/review.values.yaml');
        return $values;
    }

    private function addHttpEndpointsToValues(array $basicChart, array $values, $modules, $hostname = '') {
        $values['deployments']['http-app-deployment'] = $basicChart['deployments']['http-app-deployment'];
        $values['services']['http-app-service'] = $basicChart['services']['http-app-service'];
        $values['ingresses']['http-app-ingress'] = $basicChart['ingresses']['http-app-ingress'];
        $routes = $this->collectHttpRoutes($modules);

        $values['ingresses']['http-app-ingress']['hosts'][0]['paths'] = [];
        if ($hostname) {
            $values['ingresses']['http-app-ingress']['hosts'][0]['hostname'] = $hostname;
        }
        foreach ($routes as $route) {
            $values['ingresses']['http-app-ingress']['hosts'][0]['paths'][] = [
                'path' => '/api/'.ltrim($route, '/'),
                'serviceName'=> 'http-app-service',
                'servicePort'=> 8080
            ];
        }
        return $values;
    }

    /**
     * @param array|Module[] $modules
     * @return void
     */
    private function checkModulesWhoNeedsDataBase(array $modules): array {
        $result =  [];
        foreach ($modules as $module) {
            if (isset($module->models) && count($module->models)>0) {
                $result[] = $module->title;
            }
        }
        return $result;
    }

    private function addDatabase(array $basicChart, array $values, array $modules): array {
        $modulesNeededDatabase = $this->checkModulesWhoNeedsDataBase($modules);
        if (count($modulesNeededDatabase)>0) {
            $values['deployments']['database'] = $basicChart['deployments']['database'];
            $multipleDatabases = [
                'name'=>'POSTGRES_MULTIPLE_DATABASES',
                'value'=>''
            ];
            foreach ($modulesNeededDatabase as $moduleTitle) {
                if ($multipleDatabases['value']!=='') {
                    $multipleDatabases['value'] .= ',';
                }
                $multipleDatabases['value'] .= lcfirst($moduleTitle).',';
                $multipleDatabases['value'] .= lcfirst($moduleTitle).'_test';
            }
            $values['deployments']['database']['containers']['0']['env'][] = $multipleDatabases;
            $values['deployments']['database']['containers']['0']['env'][] = [
                'name'=>'POSTGRES_USER',
                'value'=>'postgres'
            ];
            $values['deployments']['database']['containers']['0']['env'][] = [
                'name'=>'POSTGRES_PASSWORD',
                'value'=>'postgres'
            ];
            $values['services']['db-service'] = $basicChart['services']['db-service'];
        }
        return $values;
    }

    private function addTestsAndDbConfigToPhpunitFile(array $modules) {

        $phpunitConf = file_get_contents(base_path().'/phpunit.xml');
        if (!preg_match('/<coverage>.*<directory\s+suffix="\.php">\.\/Packages<\/directory>/s', $phpunitConf)) {
            $phpunitConf = preg_replace(';<coverage>.*</coverage>;s', "<coverage>\n\t\t<include>\n\t\t\t<directory suffix=\".php\">./app</directory>\n\t\t\t<directory suffix=\".php\">./Packages</directory>\n\t\t</include>\n\t</coverage>", $phpunitConf);
        }

        $phpunitConf= preg_replace(';\s*<testsuites>.*</testsuites>;s', '', $phpunitConf);

        $testSuitsText = '<testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>';

        foreach ($modules as $module) {
            $testSuitsText .= '';

            $testSuitsText.= "\n\t\t".'<testsuite name="'.$module->title.'">
                <directory suffix="Test.php">./Packages/'.$module->title.'</directory>'."\n\t\t".'</testsuite>';
        }
        $testSuitsText = "<testsuites>\n\t".$testSuitsText."\n</testsuites>";
        $phpunitConf = str_replace('</phpunit>', $testSuitsText."\n</phpunit>", $phpunitConf);

        $phpunitConf = preg_replace(';\s+<env\s+name="DB_DATABASE_.*?>;s', '', $phpunitConf);
        $phpunitConf = preg_replace(';\s+<server\s+name="DB_DATABASE_.*?>;s', '', $phpunitConf);
        foreach ($modules as $module) {
            if (!str_contains($phpunitConf, '<env name="DB_DATABASE_'.strtoupper($module->title).'" value="'.strtolower($module->title).'_test"/>')) {
                $phpunitConf = str_replace('<php>',
                    '<php>'."\n\t\t".'<env name="DB_DATABASE_'.strtoupper($module->title).'" value="'.strtolower($module->title).'_test"/>',
                    $phpunitConf);

                $phpunitConf = str_replace('<php>',
                    '<php>'."\n\t\t".'<server name="DB_DATABASE_'.strtoupper($module->title).'" value="'.strtolower($module->title).'_test"/>',
                    $phpunitConf);
            }
        }

        file_put_contents(base_path().'/phpunit.xml', $phpunitConf);
    }

    /**
     * @param array|Module[] $modules
     * @return void
     */
    private function prepareLocalEnvironment(Project $project, array $modules): array  {
        $basicChart = yaml_parse_file(dirname(__FILE__).'/../valuesTemplates/basic.values.yaml');
        $weHavePublicHttpEndpoints = $this->checkDoWeHavePublicHttpEndpoints($modules);

        $values = [];

        if ($weHavePublicHttpEndpoints) {
            $values = $this->addHttpEndpointsToValues($basicChart, $values, $modules, $project->title.'.localhost');
        }
        $values['deployments']['dev-deployment'] = $basicChart['deployments']['dev-deployment'];
        unset($values['deployments']['dev-deployment']['containers']['0']['envsFromConfigmap']);
        unset($values['deployments']['http-app-deployment']['containers']['0']['envsFromConfigmap']);


        foreach ($values['deployments'] as &$deployment) {
            foreach ($deployment['containers'] as &$container) {
                if ($container['name']=='roadrunner-http') {
                    $container['image'] = $project->image;
                    $container['imageTag'] = $project->infra_version.'-local';
                    $container['command'] = 'tail';
                    $container['args'] = ["-f", "/dev/null"];
                }
                if ($container['name']=='roadrunner-dev') {
                    $container['image'] = $project->image;
                    $container['imageTag'] = $project->infra_version.'-local';
                    $container['command'] = 'tail';
                    $container['args'] = ["-f", "/dev/null"];
                }
            }
        }
        $this->addTestsAndDbConfigToPhpunitFile($modules);
        $values = $this->addDatabase($basicChart, $values, $modules);

        @mkdir(base_path().'/infra/values', 0777, true);
        yaml_emit_file(base_path().'/infra/values/local.values.yaml', $values);
        $this->removeUnneededQuotasFromYamlFile(base_path().'/infra/values/local.values.yaml');
        return $values;

    }

    /**
     * @param array|Module[] $modules
     * @return bool
     */
    private function checkDoWeHavePublicHttpEndpoints(array $modules): bool {
        foreach ($modules as $module) {
            foreach ($module->service_aggregators as $service_aggregator) {
                foreach ($service_aggregator->methods as $method) {
                    if (isset($method->controller_fields) && $method->controller_fields->public===true) {
                        //detected at least one public http method. We need service
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function prepareLocalDebugEnvironment(Project $project, array $modules) {
        $values = $this->prepareLocalEnvironment($project, $modules);
        if (array_key_exists('http-app-deployment', $values['deployments'])) {
            foreach ($values['deployments']['http-app-deployment'] as $container) {
                $container['env']['XDEBUG_CONFIG'] = 'remote_host={{ $.Values.XDEBUG_HOST }}';
                $container['env']['PHP_IDE_CONFIG'] = 'remote_host='.strtolower($project->title);
            }
        }

        foreach ($values['deployments'] as &$deployment) {
            foreach ($deployment['containers'] as &$container) {
                if ($container['name']=='roadrunner-http') {
                    $container['image'] = $project->image;
                    $container['imageTag'] = $project->infra_version.'-local-xdebug';
                    $container['command'] = 'tail';
                    $container['args'] = ["-f", "/dev/null"];
                }
                if ($container['name']=='roadrunner-dev') {
                    $container['image'] = $project->image;
                    $container['imageTag'] = $project->infra_version.'-local-xdebug';
                    $container['command'] = 'tail';
                    $container['args'] = ["-f", "/dev/null"];
                }
            }
        }

        @mkdir(base_path().'/infra/values', 0777, true);
        yaml_emit_file(base_path().'/infra/values/local-xdebug.values.yaml', $values);
        $this->removeUnneededQuotasFromYamlFile(base_path().'/infra/values/local-xdebug.values.yaml');
        return $values;
    }



//    /**
//     * @param Project $project
//     * @param array|Module[] $modules
//     * @return void
//     */
//    public function generateInfra(Project $project, array $modules) {
//
//        // 1. we need to detect, which infra modules should we have
//        // for example: ingress, cron, nats, http, grpc or smth.
//        // 2. lets take infra info from project object
//        // 3. lets generate devspace.yaml
//        // 4. lets generate values for all application combinations and environments
//
//        $weNeedHttpService = false;
//        foreach ($modules as $module) {
//            foreach ($module->service_aggregators as $service_aggregator) {
//                foreach ($service_aggregator->methods as $method) {
//                    if ($weNeedHttpService === false && isset($method->controller_fields) && $method->controller_fields->public===false) {
//                        //detected at least one private or public http method. We need service
//                        $weNeedHttpService = true;
//                    }
//                }
//            }
//        }
//        shell_exec('rm -rf '.base_path().'/values/*');
//        shell_exec('rm -rf '.base_path().'/devspace/*');
//
//        if ($weNeedHttpService) {
//            $this->createHttpApplication(
//                $project,
//                $this->collectHttpRoutes($modules)
//            );
//        }
//
//
//    }
//
//    /**
//     * @param array|Module[] $modules
//     * @return string[]
//     */
    private function collectHttpRoutes(array $modules): array {
        $routes = [];
        foreach ($modules as $module) {
            foreach ($module->service_aggregators as $service_aggregator) {
                foreach ($service_aggregator->methods as $method) {
                    if (isset($method->controller_fields->route) && isset($method->controller_fields->public)) {
                        if ($method->controller_fields->public===true) {
                            $routes[] = $method->controller_fields->route;
                        }
                    }
                }
            }
        }

        foreach ($routes as &$route) {
            $route = preg_replace('/{.*?}/', '*', $route);
        }

        return $routes;
    }
}
