<?php
namespace AlmeidaFogo\LaravelModules;

use Illuminate\Support\ServiceProvider;

class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/Modulos' => base_path().'/app/Modulos',
        ], 'modules');
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

		$this->app->singleton('command.almeida-fogo.rollbackmodule', function ($app) {
			return $app['AlmeidaFogo\LaravelModules\Commands\RollbackModule'];
		});
		$this->commands('command.almeida-fogo.rollbackmodule');

		$this->app->singleton('command.almeida-fogo.listmodules', function ($app) {
			return $app['AlmeidaFogo\LaravelModules\Commands\ListModules'];
		});
		$this->commands('command.almeida-fogo.listmodules');
    }
}