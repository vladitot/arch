<?php


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

        $this->unregisterServices();

            foreach (Memory::$modules as $module) {
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
                    \NamespaceAndPathGeneratorYaml::generateServiceProviderNamespace(
                        $module->title,
                ).'\\'.ucfirst($module->title).'ServiceProvider');
            }

        $this->putTestsFoldersIntoComposerJson(Memory::$modules);

    }

    /**
     * @param array|Module[] $modules
     * This method should read composer.json into array, remove all tests autoload starting from namespace "Packages"
     * and add new autoload for tests from namespace "Packages" into composer.json
     */
    public function putTestsFoldersIntoComposerJson(array $modules): void {
        $composerJson = json_decode(file_get_contents(base_path().'/composer.json'), true);
        foreach ($composerJson['autoload-dev']['psr-4'] as $namespace=>$value) {
            if (str_contains($namespace, 'Packages')) unset($composerJson['autoload']['autoload-dev'][$namespace]);
        }

            foreach ($modules as $module) {
                $serviceTestNamespace = \NamespaceAndPathGeneratorYaml::generateServiceTestNamespace($module->title).'\\';
                $serviceTestPath = dirname(NamespaceAndPathGeneratorYaml::generateServiceTestPath(
                    $module->title, 'any'));
                $serviceTestPath = str_replace(base_path(), '', $serviceTestPath);
                $serviceTestPath = ltrim($serviceTestPath, '/').'/';
                $composerJson['autoload-dev']['psr-4'][$serviceTestNamespace] = $serviceTestPath;

                $serviceAggregatorTestNamespace = \NamespaceAndPathGeneratorYaml::generateServiceAggregatorTestNamespace($module->title).'\\';
                $serviceAggregatorTestPath = dirname(NamespaceAndPathGeneratorYaml::generateServiceAggregatorTestPath(
                    $module->title, 'any'));
                $serviceAggregatorTestPath = str_replace(base_path(), '', $serviceAggregatorTestPath);
                $serviceAggregatorTestPath = ltrim($serviceAggregatorTestPath, '/').'/';
                $composerJson['autoload-dev']['psr-4'][$serviceAggregatorTestNamespace] = $serviceAggregatorTestPath;


                $controllerTestNamespace = \NamespaceAndPathGeneratorYaml::generateControllerTestNamespace($module->title).'\\';
                $controllerTestPath = dirname(NamespaceAndPathGeneratorYaml::generateControllerTestPath(
                    $module->title, 'any'));
                $controllerTestPath = str_replace(base_path(), '', $controllerTestPath);
                $controllerTestPath = ltrim($controllerTestPath, '/').'/';
                $composerJson['autoload-dev']['psr-4'][$controllerTestNamespace] = $controllerTestPath;


                $repositoryTestNamespace = \NamespaceAndPathGeneratorYaml::generateRepositoryTestNamespace($module->title).'\\';
                $repositoryTestPath = dirname(NamespaceAndPathGeneratorYaml::generateRepositoryTestPath(
                    $module->title, 'any'));
                $repositoryTestPath = str_replace(base_path(), '', $repositoryTestPath);
                $repositoryTestPath = ltrim($repositoryTestPath, '/').'/';
                $composerJson['autoload-dev']['psr-4'][$repositoryTestNamespace] = $repositoryTestPath;

            }

        file_put_contents(base_path().'/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param int $projectId
     * @return void
     * This function should call gitlab api and close all merge requests with branches called like infra-*
     */
    public function closePreviousGitlabMergeRequests(int $projectId): void
    {
        /** @var Project $project */
        $project = Project::findOrFail($projectId);
        $gitlab = new \Gitlab\Client();
        $gitlab->authenticate($project->gitlab_api_token, \Gitlab\Client::AUTH_OAUTH_TOKEN);
        $gitlabProject = $gitlab->projects()->show($project->gitlab_project_id);

        $mergeRequests = $gitlab->mergeRequests()->all($gitlabProject['id'], [
            'state' => 'opened'
        ]);
        foreach ($mergeRequests as $mergeRequest) {
            if (str_contains($mergeRequest['source_branch'], 'infra-')) {
                $gitlab->mergeRequests()->update($gitlabProject['id'], $mergeRequest['iid'], [
                    'state_event' => 'close'
                ]);
            }
        }
    }
}
