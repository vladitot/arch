<?php

namespace Vladitot\Architect\Commands;

use Vladitot\Architect\InfrastructureGenerator;
use Vladitot\Architect\Yaml\LoadModule;
use Vladitot\Architect\Yaml\Memory;
use Vladitot\Architect\Yaml\Module;
use Vladitot\Architect\Yaml\ModulePath;
use Vladitot\Architect\Yaml\Project;
use Illuminate\Console\Command;
use Vladitot\Architect\YamlRenderPipeline;

class GenerateArchitecture extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'architect:gen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Architecture';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $currentProjectConfig = yaml_parse_file(base_path('project.yaml'));

        $project = Project::from($currentProjectConfig);

        foreach ($project->modulePaths as &$modulePath) {
            $modulePath = ModulePath::from($modulePath);
        }
        $renderer = new YamlRenderPipeline();

        Memory::$project = $project;

        Memory::$modules = [];

        foreach ($project->modulePaths as $modulePathObject) {
            @mkdir($modulePathObject->path_to_dir, 0777, true);
            $filename = $modulePathObject->path_to_dir .'/'. $modulePathObject->module_name.'Module.yaml';
            if (!file_exists($filename)) {
                touch($filename);
            }
            $moduleData = yaml_parse_file($filename);
            if ($moduleData !== null) {
                $module = new Module();
                try {
                    LoadModule::createObjectAndFill($module, $moduleData);
                    Memory::$modules[$modulePathObject->module_name] = $module;
                } catch (\Throwable $e) {
                    Memory::$modules[$modulePathObject->module_name] = Module::from([]);
                    echo $e->getMessage() . "\n";
                }
            } else {
                Memory::$modules[$modulePathObject->module_name] = Module::from([]);
            }
        }

        $infraRenderer = new InfrastructureGenerator();
        $infraRenderer->generateInfra($project, Memory::$modules);

        $renderer->generate();
        return Command::SUCCESS;
    }
}
