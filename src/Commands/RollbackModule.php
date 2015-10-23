<?php

namespace AlmeidaFogo\LaravelModules\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use AlmeidaFogo\LaravelModules\LaravelModules\Configs;
use AlmeidaFogo\LaravelModules\LaravelModules\ModulesHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\RollbackManager;
use AlmeidaFogo\LaravelModules\LaravelModules\PathHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\Strings;


class RollbackModule extends Command
{

	public static $errors;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:rollback  {--soft}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Descarrega o ultimo modulo da aplicacao';

    /**
     * Create a new command instance.
     *
     * @return RollbackModule
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
		$softRollback = $this->option('soft');

		//Modulos ja carregados
		$oldLoadedModules = Configs::getConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_LOADED_MODULES);

		if($oldLoadedModules != Strings::EMPTY_STRING){
			//Pega modulos carredos em forma de array
			$explodedLoadedModules = ModulesHelper::getLoadedModules($oldLoadedModules);

			if(is_array($explodedLoadedModules)){
				$lastModule = $explodedLoadedModules[count($explodedLoadedModules)-1];

				$lastModuleExploded = explode(Strings::MODULE_TYPE_NAME_SEPARATOR, $lastModule);

				if(is_array($lastModuleExploded)){
					$lastModuleType = $lastModuleExploded[0];
					$lastModuleName = $lastModuleExploded[1];

					$this->info(Strings::rollingBackModuleInfo($lastModuleExploded[0], $lastModuleExploded[1]));

					$lastModuleRollbackFile = PathHelper::getModuleRollbackFile($lastModuleType, $lastModuleName);

					if ($softRollback){
						RollbackManager::execSoftRollback($lastModuleRollbackFile, $this);
					}else{
						RollbackManager::execHardRollback($lastModuleType, $lastModuleName, $this);
					}
				}else{
					$this->error(Strings::ERROR_CANT_RESOLVE_MODULE_NAME);
				}
			}else{
				$this->error(Strings::ERROR_CANT_RESOLVE_LOADED_MODULES);
			}
		}else{
			$this->info(Strings::STATUS_NO_MODULES_LOADED);
		}
	}

}
