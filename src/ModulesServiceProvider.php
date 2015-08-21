<?php
namespace AlmeidaFogo\LaravelModules;

class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerModuleLoad();
    }
    /**
     * Register the module:load command.
     */
    private function registerModuleLoad()
    {
        $this->app->singleton('command.almeida-fogo.loadmodule', function ($app) {
            return $app['AlmeidaFogo\LaravelModules\Commands\LoadModule'];
        });
        $this->commands('command.almeida-fogo.loadmodule');
    }
}