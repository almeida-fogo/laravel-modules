<?php

namespace AlmeidaFogo\LaravelModules\Commands;

use Illuminate\Console\Command;

use AlmeidaFogo\LaravelModules\LaravelModules\Configs;
use AlmeidaFogo\LaravelModules\LaravelModules\ModulesHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\PathHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\Strings;


class ListModules extends Command
{

	public static $errors;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mostra modulos carregados no projeto';

    /**
     * Create a new command instance.
     *
     * @return ListModule
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		//Modulos ja carregados
		$oldLoadedModules = Configs::getConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_LOADED_MODULES);

		if($oldLoadedModules != Strings::EMPTY_STRING){
			//Pega modulos carredos em forma de array
			$explodedLoadedModules = ModulesHelper::getLoadedModules($oldLoadedModules);

			foreach ($explodedLoadedModules as $key => $module){
				$this->info($key.' - '.$module);
			}

		}else{
			$this->info(Strings::STATUS_NO_MODULES_LOADED);
		}
	}

}
