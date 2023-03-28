<?php
namespace Vladitot\Architect;
use Illuminate\Support\ServiceProvider;
use Vladitot\Architect\Commands\GenerateArchitecture;
use Vladitot\Architect\Commands\SchemaGenerate;

class ArchitectServiceProvider extends ServiceProvider
{
    public function register()
    {

    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateArchitecture::class,
                SchemaGenerate::class,
            ]);
        }
    }
}
