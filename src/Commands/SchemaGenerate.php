<?php

namespace Vladitot\Architect\Commands;

use Vladitot\Architect\InfrastructureGenerator;
use Vladitot\Architect\Yaml\LoadModule;
use Vladitot\Architect\Yaml\Memory;
use Vladitot\Architect\Yaml\Module;
use Vladitot\Architect\Yaml\ModulePath;
use Vladitot\Architect\Yaml\Project;
use Vladitot\Architect\Yaml\SchemaGenerator;
use Illuminate\Console\Command;
use Vladitot\Architect\YamlRenderPipeline;

class SchemaGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'architect:schema';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Schema';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        while (true) {


            $generator = new SchemaGenerator();
            $schema = $generator->generateProjectFileSchema();

            file_put_contents(
                base_path('projectSchema.json'),
                json_encode($schema, JSON_PRETTY_PRINT));

            if (!file_exists(base_path('project.yaml'))) {
                touch(base_path('project.yaml'));
                file_put_contents(base_path('.gitignore'), 'projectSchema.json', FILE_APPEND);
            }

            if (!file_exists(base_path('project.yaml')) || file_get_contents(base_path('project.yaml')) === '') {
                $currentProjectConfig = [];
            } else {
                $currentProjectConfig = yaml_parse_file(base_path('project.yaml'));
            }


            $project = Project::from($currentProjectConfig);
            $infraRenderer = new InfrastructureGenerator();
            $infraRenderer->generateInfra($project, Memory::$modules);
            if (isset($project->modulePaths)) {
                foreach ($project->modulePaths as &$modulePath) {
                    $modulePath = ModulePath::from($modulePath);
                }

                Memory::$project = $project;

                Memory::$modules = [];

                foreach ($project->modulePaths as $modulePathObject) {
                    @mkdir($modulePathObject->path_to_dir, 0777, true);
                    $filename = $modulePathObject->path_to_dir . '/' . $modulePathObject->module_name . 'Module.yaml';
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
//                        Memory::$modules[$modulePathObject->module_name] = Module::from([]);
                            echo 'Error with module scheme generation: ' . $modulePathObject->module_name . "\n";
                            echo $e->getMessage() . "\n";
                            echo $e->getFile() . "\n";
                            echo $e->getLine() . "\n";
                        }
                    } else {
                        Memory::$modules[$modulePathObject->module_name] = Module::from([]);
                    }
                }

                foreach (Memory::$modules as $name => $module) {
                    Memory::$currentModuleName = $name;
                    $schema = $generator->generateModuleSchema();
                    file_put_contents(
                        base_path() . '/Packages/' . $name . '/moduleSchema.json',
                        json_encode($schema, JSON_PRETTY_PRINT));
                }
            }
            echo 're-generated' . "\n";
            sleep(1);
        }

        return Command::SUCCESS;
    }
}
