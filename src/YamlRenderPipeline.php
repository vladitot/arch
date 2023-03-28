<?php
namespace Vladitot\Architect;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\TraitType;
use Nette\PhpGenerator\TraitUse;
use Vladitot\Architect\Yaml\Memory;
use Vladitot\Architect\Yaml\Module;
use Vladitot\Architect\Yaml\Project;
use Vladitot\Architect\YamlComponents\ControllerGenerator;
use Vladitot\Architect\YamlComponents\FactoryGenerator;
use Vladitot\Architect\YamlComponents\JsApiClientGenerator;
use Vladitot\Architect\YamlComponents\MigrationGenerator;
use Vladitot\Architect\YamlComponents\ModelGenerator;
use Vladitot\Architect\YamlComponents\RepositoryGenerator;
use Vladitot\Architect\YamlComponents\RoutesGenerator;
use Vladitot\Architect\YamlComponents\ServiceAggregatorGenerator;
use Vladitot\Architect\YamlComponents\ServiceGenerator;
use Vladitot\Architect\YamlComponents\ServiceProviderGenerator;

class YamlRenderPipeline
{

    private function unregisterServices() {
        $basePath = base_path();
        $appPhp = file_get_contents($basePath.'/config/app.php');
        $appPhp = preg_replace('/$\s*Packages\\\\.*ServiceProvider::class,/m', '', $appPhp);
        file_put_contents($basePath.'/config/app.php', $appPhp);
    }

    public function registerService(string $fullServiceName) {
        $basePath = base_path();
        $appPhp = file_get_contents($basePath.'/config/app.php');
        $appPhp = str_replace('App\\Providers\\AppServiceProvider::class,',
            'App\\Providers\\AppServiceProvider::class,'."\n\t\t".$fullServiceName.'::class,',
            $appPhp
        );
        file_put_contents($basePath.'/config/app.php', $appPhp);
    }

    public function generate()
    {
        if (count(Memory::$modules)>0) $this->unregisterServices();

            foreach (Memory::$modules as $module) {
                if ($module->title!=='') {
                    $modelGenerator = new ModelGenerator();
                    $modelGenerator->generate($module);

                    $migrationGenerator = new MigrationGenerator();
                    $migrationGenerator->generate($module);

                    $factoryGenerator = new FactoryGenerator();
                    $factoryGenerator->generate($module);

                    $repositoryGenerator = new RepositoryGenerator();
                    $repositoryGenerator->generate($module);

                    $serviceGenerator = new ServiceGenerator();
                    $serviceGenerator->generate($module);

                    $serviceAggregatorGenerator = new ServiceAggregatorGenerator();
                    $serviceAggregatorGenerator->generate($module);

                    $controllerGenerator = new ControllerGenerator();
                    $controllerGenerator->generate($module);

                    $routesGenerator = new RoutesGenerator();
                    $routesGenerator->generate($module);

                    $jsGenerator = new JsApiClientGenerator();
                    $jsGenerator->generate($module);

                    $serviceProviderGenerator = new ServiceProviderGenerator();
                    $serviceProviderGenerator->generate($module);

                    $this->registerService(
                        NamespaceAndPathGeneratorYaml::generateServiceProviderNamespace(
                            $module->title,
                        ).'\\'.ucfirst($module->title).'ServiceProvider');

                }
                $this->putTestsFoldersIntoComposerJson(Memory::$modules);
                $this->putMigrationCommandsToComposerJson(Memory::$modules);
                $this->putPackageNamespaceToComposerJson(Memory::$modules);
                $this->putDatabaseConfigurationIntoDatabaseConfig(Memory::$modules);
                $this->createCustomDatabaseRefresher(Memory::$modules);
            }
    }

    /**
     * @param array|Module[] $modules
     * @return void
     */
    private function createCustomDatabaseRefresher(array $modules) {

        $class = new TraitType('CustomRefreshDatabase');
        $namespace = new PhpNamespace('Tests');

        $class->setTraits([new TraitUse(RefreshDatabase::class)]);
        $namespace->addUse(RefreshDatabase::class);
        $namespace->addUse(RefreshDatabaseState::class);
        $namespace->addUse(\Illuminate\Contracts\Console\Kernel::class);
        $namespace->add($class);

        $body = 'if (! RefreshDatabaseState::$migrated) {'."\n";
        foreach ($modules as $module) {
            $body.='$this->artisan(\'migrate:fresh\', array_merge($this->migrateFreshUsing(), [\'--database\'=>\'pgsql-'.strtolower($module->title).'\', \'--path\'=>\'Packages/'.ucfirst($module->title).'/Migrations\']));'."\n\t\t";
        }

        $body.='
            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();';
        $class->addMethod('refreshDatabase')
            ->setReturnType('void')
            ->setBody($body);

        $file = new \Nette\PhpGenerator\PhpFile;
        $file->addComment('This file is generated by architect.');
        $file->addNamespace($namespace);

        file_put_contents(base_path('tests/'.$class->getName().'.php'), $file);

    }

    /**
     * @param array|Module[] $modules
     * @return void
     */
    public function putPackageNamespaceToComposerJson(array $modules): void {
        $composerJson = json_decode(file_get_contents(base_path().'/composer.json'), true);
        foreach ($composerJson['autoload']['psr-4'] as $namespace=>$value) {
            if (str_contains($namespace, 'Packages')) {
                unset($composerJson['autoload']['psr-4'][$namespace]);
            }
        }

        foreach ($modules as $module) {
            if ($module->title==='') continue;
            $composerJson['autoload']['psr-4']['Packages\\'.$module->title.'\\'] = 'Packages/'.ucfirst($module->title).'/';
        }

        file_put_contents(base_path().'/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }

    public function putMigrationCommandsToComposerJson(array $modules): void {
        $composerJson = json_decode(file_get_contents(base_path().'/composer.json'), true);
        $composerJson['scripts']['migrate'] = [];
        foreach ($modules as $module) {
            $composerJson['scripts']['migrate'][] = '@php artisan migrate  --database=pgsql-'.strtolower($module->title).' --path=Packages/'.ucfirst($module->title).'/Migrations';
        }

        file_put_contents(base_path().'/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array|Module[] $modules
     * This method should read composer.json into array, remove all tests autoload starting from namespace "Packages"
     * and add new autoload for tests from namespace "Packages" into composer.json
     */
    public function putTestsFoldersIntoComposerJson(array $modules): void {
        $composerJson = json_decode(file_get_contents(base_path().'/composer.json'), true);
        foreach ($composerJson['autoload-dev']['psr-4'] as $namespace=>$value) {
            if (str_contains($namespace, 'Packages')) {
                unset($composerJson['autoload-dev']['psr-4'][$namespace]);
            }
        }

        foreach ($modules as $module) {
            if ($module->title==='') continue;
            $serviceTestNamespace = NamespaceAndPathGeneratorYaml::generateServiceTestNamespace($module->title).'\\';
            $serviceTestPath = dirname(NamespaceAndPathGeneratorYaml::generateServiceTestPath(
                $module->title, 'any'));
            $serviceTestPath = str_replace(base_path(), '', $serviceTestPath);
            $serviceTestPath = ltrim($serviceTestPath, '/').'/';
            $composerJson['autoload-dev']['psr-4'][$serviceTestNamespace] = $serviceTestPath;

            $serviceAggregatorTestNamespace = NamespaceAndPathGeneratorYaml::generateServiceAggregatorTestNamespace($module->title).'\\';
            $serviceAggregatorTestPath = dirname(NamespaceAndPathGeneratorYaml::generateServiceAggregatorTestPath(
                $module->title, 'any'));
            $serviceAggregatorTestPath = str_replace(base_path(), '', $serviceAggregatorTestPath);
            $serviceAggregatorTestPath = ltrim($serviceAggregatorTestPath, '/').'/';
            $composerJson['autoload-dev']['psr-4'][$serviceAggregatorTestNamespace] = $serviceAggregatorTestPath;


            $controllerTestNamespace = NamespaceAndPathGeneratorYaml::generateControllerTestNamespace($module->title).'\\';
            $controllerTestPath = dirname(NamespaceAndPathGeneratorYaml::generateControllerTestPath(
                $module->title, 'any'));
            $controllerTestPath = str_replace(base_path(), '', $controllerTestPath);
            $controllerTestPath = ltrim($controllerTestPath, '/').'/';
            $composerJson['autoload-dev']['psr-4'][$controllerTestNamespace] = $controllerTestPath;


            $repositoryTestNamespace = NamespaceAndPathGeneratorYaml::generateRepositoryTestNamespace($module->title).'\\';
            $repositoryTestPath = dirname(NamespaceAndPathGeneratorYaml::generateRepositoryTestPath(
                $module->title, 'any'));
            $repositoryTestPath = str_replace(base_path(), '', $repositoryTestPath);
            $repositoryTestPath = ltrim($repositoryTestPath, '/').'/';
            $composerJson['autoload-dev']['psr-4'][$repositoryTestNamespace] = $repositoryTestPath;

        }

        file_put_contents(base_path().'/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }

//
//    private function createHttpApplication(Project $project, array $routes) {
//
//        $environmentsData = [
//          'local'=>[
//              'image'=>$project->image.$project->php_version.':'.$project->infra_version.'-local',
//              'tls'=>false,
//              'domain'=>$project->title.'.localhost',
//          ],
//          'local-xdebug'=>[
//              'image'=>$project->image.$project->php_version.':'.$project->infra_version.'-local-xdebug',
//              'envs'=>[
//                  'XDEBUG_CONFIG'=>'client_host=${XDEBUG_HOST}',
//                  'PHP_IDE_CONFIG'=>'serverName='.strtolower($project->title),
//              ],
//              'tls'=>false,
//              'domain'=>$project->title.'.localhost',
//          ],
//          'prod'=>[
//              'image'=>'shouldBeOverwrittenOnPipeline',
//              'tls'=>true,
//              'domain'=>'prod.'.$project->base_domain,
//          ],
//          'review'=>[
//              'image'=>'shouldBeOverwrittenOnPipeline',
//              'tls'=>true,
//              'domain'=>'shouldBeOverwrittenOnPipeline',
//          ],
//          'rc'=>[
//              'image'=>'shouldBeOverwrittenOnPipeline',
//              'tls'=>true,
//              'domain'=>'rc.'.$project->base_domain,
//          ],
//        ];
//
//        $chart = [];
//        $chart['containers']=[
//            [
//                'name'=>'http-app',
//            ]
//        ];
//        $chart['service'] = [
//            'ports'=>[
//                'port'=>8080,
//            ]
//        ];
//
//        foreach ($environmentsData as $environmentName=>$environmentData) {
//
//            if (count($routes)>0) {
//                $chart['ingress']= [
//                    'tls'=>$environmentData['tls'],
//                    'rules'=>[]
//                ];
//
//                foreach ($routes as $route) {
//                    $chart['ingress']['rules'][] = [
//                        'host'=>strtolower($environmentData['domain']),
//                        'path'=>$route,
//                    ];
//                }
//            }
//
//            $chart['containers'][0]['image'] = $environmentData['image'];
//            $chart['containers'][0]['command'] = '/usr/local/bin/rr';
//            $chart['containers'][0]['args'] = ['serve', '-c', '/var/www/infra/rr/.rr.http.'.$environmentName.'.yaml'];
//            @mkdir(base_path().'/infra/values', 0777, true);
//            yaml_emit_file(base_path().'/infra/values/values.http.'.$environmentName.'.yaml', $chart);
//
//            $this->createRoadRunnerHttp($project, $environmentName, $environmentData);
//
//        }
//
//        $this->generateMakefile($environmentsData);
//
//        $this->createDevspaceHttpYaml($project);
//        $this->generateInfraEnvs($project);
//    }
//
//    private function generateInfraEnvs(Project $project) {
//        $infraEnvFilePath = base_path().'/infraEnv.yaml';
//        if (file_exists($infraEnvFilePath)) {
//            $infraEnvFile = yaml_parse_file($infraEnvFilePath);
//        } else {
//            $infraEnvFile = [];
//        }
//        $infraEnvFile['APP_K8S_ENV']='local';
//        $infraEnvFile['NAMESPACE']=strtolower($project->title);
//        $infraEnvFile['XDEBUG_HOST']='192.168.65.2';
//        yaml_emit_file($infraEnvFilePath, $infraEnvFile);
//
//        $infraEnvFileCommittedPath = base_path().'/infraEnvCommitted.yaml';
//        if (file_exists($infraEnvFileCommittedPath)) {
//            $infraEnvFileCommitted = yaml_parse_file($infraEnvFileCommittedPath);
//        } else {
//            $infraEnvFileCommitted = [];
//        }
//
//        $infraEnvFileCommitted['APP_K8S_ENV']='local';
//        $infraEnvFileCommitted['NAMESPACE']=strtolower($project->title);
//        $infraEnvFileCommitted['XDEBUG_HOST']='192.168.65.2';
//        yaml_emit_file($infraEnvFileCommittedPath, $infraEnvFileCommitted);
//
//        $gitignoreFile = file_get_contents(base_path().'/.gitignore');
//        if (str_contains($gitignoreFile, 'infraEnv.yaml')===false) {
//            $gitignoreFile.="\n".'infraEnv.yaml';
//            file_put_contents(base_path().'/.gitignore', $gitignoreFile);
//        }
//
//    }
//
//    private function generateMakefile(array $environmentsData) {
//        $makefile = '';
//
//        $makefile.='up:'."\n";
//        $makefile .= "\t".'devspace purge'."\n";
//        $makefile .= 'yq e -i \'.APP_K8S_ENV = "local"\' infraEnv.yaml';
//        $makefile .= "\tdevspace dev\n\n";
//
//        $makefile.='down:'."\n";
//        $makefile .= "\tdevspace purge\n\n";
//
//        $makefile.='xdebug:'."\n";
//        $makefile .= "\t".'devspace purge'."\n";
//        $makefile .= 'yq e -i \'.APP_K8S_ENV = "local-xdebug"\' infraEnv.yaml';
//        $makefile .= "\tdevspace dev\n\n";
//
//
//        file_put_contents(base_path().'/Makefile.devspace', $makefile);
//    }
//
//    private function createRoadRunnerHttp(Project $project, string $environmentName, array $environmentData) {
//        if (in_array($environmentName, ['local', 'local-xdebug'])) {
//            $rrConfig = yaml_parse_file(dirname(__FILE__).'/Templates/RR/rr.http.local.yaml');
//        } else {
//            $rrConfig = yaml_parse_file(dirname(__FILE__).'/Templates/RR/rr.http.yaml');
//        }
//        //upgrade something in default config
//
//        @mkdir(base_path().'/infra/rr', 0777, true);
//        yaml_emit_file(base_path().'/infra/rr/.rr.http.'.$environmentName.'.yaml', $rrConfig);
//    }
//
//    private function createDevspaceHttpYaml(Project $project) {
//        $devspace = [];
//        $devspace['version'] = 'v2beta1';
//        $devspace['name'] = strtolower($project->title);
//        $devspace['vars'] = [
//            'APP_K8S_ENV' => [
//                'command'=> "bash",
//                'args' => [ "-c", "./infra/readVar.sh APP_K8S_ENV" ]
//            ],
//            'XDEBUG_HOST' => [
//                'command'=> "bash",
//                'args' => [ "-c", "./infra/readVar.sh XDEBUG_HOST" ]
//            ],
//            'NAMESPACE' => [
//                'command'=> "bash",
//                'args' => [ "-c", "./infra/readVar.sh NAMESPACE" ]
//            ],
//        ];
//        $devspace['dev'] = [
//            'app'=>[
//                'imageSelector'=> $project->image.$project->php_version,
//                'sync'=>[
//                    [
//                        'path'=>'./:/var/www',
//                        'excludePaths'=>[
//                            'node_modules/',
//                        ]
//                    ]
//                ]
//            ]
//        ];
//
//        $devspace['deployments'] = [
//            'app'=>[
//                'namespace'=>'${NAMESPACE}',
//                'helm'=>[
//                    'chart'=>[
//                        'name'=>'component-chart',
//                        'repo'=>'https://charts.devspace.sh',
//                    ],
//                    'disableDependencyUpdate'=>false,
//                    'valuesFiles'=>[
//                        'values-${APP_K8S_ENV}.yaml',
//                        'values-custom.yaml',
//                    ],
//                    'releaseName'=>strtolower($project->title).'-${APP_K8S_ENV}',
//                    'upgradeArgs'=>[ "--wait", "--history-max", "6" ]
//                ]
//            ]
//        ];
//        yaml_emit_file(base_path().'/devspace.yaml', $devspace);
//    }




//    /**
//     * @param int $projectId
//     * @return void
//     * This function should call gitlab api and close all merge requests with branches called like infra-*
//     */
//    public function closePreviousGitlabMergeRequests(int $projectId): void
//    {
//        /** @var Project $project */
//        $project = Project::findOrFail($projectId);
//        $gitlab = new \Gitlab\Client();
//        $gitlab->authenticate($project->gitlab_api_token, \Gitlab\Client::AUTH_OAUTH_TOKEN);
//        $gitlabProject = $gitlab->projects()->show($project->gitlab_project_id);
//
//        $mergeRequests = $gitlab->mergeRequests()->all($gitlabProject['id'], [
//            'state' => 'opened'
//        ]);
//        foreach ($mergeRequests as $mergeRequest) {
//            if (str_contains($mergeRequest['source_branch'], 'infra-')) {
//                $gitlab->mergeRequests()->update($gitlabProject['id'], $mergeRequest['iid'], [
//                    'state_event' => 'close'
//                ]);
//            }
//        }
//    }

    /**
     * @param array|Module[] $modules
     * @return void
     */
    private function putDatabaseConfigurationIntoDatabaseConfig(array $modules)
    {
        $dbConfig = file_get_contents(config_path('database.php'));

        $dbConfig = preg_replace("/\s+'pgsql-.*?'\s+=>\s+\[.*?],/s", '', $dbConfig);

        foreach ($modules as $module) {
            $dbConfig = str_replace("'pgsql' => [",'\'pgsql-'.strtolower($module->title).'\' => [
            \'driver\' => \'pgsql\',
            \'url\' => env(\'DATABASE_URL_'.strtoupper($module->title).'\', env(\'DATABASE_URL\')),
            \'host\' => env(\'DB_HOST_'.strtoupper($module->title).'\', env(\'DB_HOST\')),
            \'port\' => env(\'DB_PORT_'.strtoupper($module->title).'\', env(\'DB_PORT\')),
            \'database\' => env(\'DB_DATABASE_'.strtoupper($module->title).'\', \'forge\'),
            \'username\' => env(\'DB_USERNAME_'.strtoupper($module->title).'\', env(\'DB_USERNAME\')),
            \'password\' => env(\'DB_PASSWORD_'.strtoupper($module->title).'\', env(\'DB_PASSWORD\')),
            \'charset\' => env(\'DB_CHARSET_'.strtoupper($module->title)."', 'utf8'),".'
            \'prefix\' => env(\'DB_PREFIX_'.strtoupper($module->title)."', ''),".'
            \'prefix_indexes\' => env(\'DB_PREFIX_INDEXES_'.strtoupper($module->title)."', true),".'
            \'search_path\' => env(\'DB_SEARCH_PATH_'.strtoupper($module->title)."', 'public'),".'
            \'sslmode\' => env(\'DB_SSLMODE_'.strtoupper($module->title)."', 'prefer'),".'
        ],'."\n\n\t\t'pgsql' => [", $dbConfig);
        }

        file_put_contents(config_path('database.php'), $dbConfig);
    }
}
