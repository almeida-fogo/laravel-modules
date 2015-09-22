<?php

namespace AlmeidaFogo\LaravelModules\Commands;

use AlmeidaFogo\LaravelModules\LaravelModules\Configs;
use AlmeidaFogo\LaravelModules\LaravelModules\ModulesHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\PathHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\RollbackManager;
use AlmeidaFogo\LaravelModules\LaravelModules\Strings;
use Illuminate\Console\Command;

class RefreshModule extends Command
{

	public static $errors;

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'module:refresh {type?} {name?}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Atualiza os arquivos de um modulo de app para a aplicacao (nao atualiza migrations ja rodadas)';

	/**
	 * Create a new command instance.
	 *
	 * @return RefreshModule
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
		//saved instace of this
		$that = $this;

		//Tipo do modulo
		$moduleType = $this->argument("type");

		//Nome do modulo
		$moduleName = $this->argument("name");

		//Inicializa variavel erros
		self::$errors = [];

		//Prepara variavel de rollback caso haja erro
		$rollback = [];

		//Verifica se foram passados os comandos inline
		if(is_null($moduleType) && is_null($moduleName)){
			//pede o tipo do modulo
			$moduleType = $this->ask(Strings::MODULE_TYPE);
			//pede o nome do modulo
			$moduleName = $this->ask(Strings::moduleNameForThisType($moduleType));
		}

		//Modulos ja carregados
		$oldLoadedModules = Configs::getConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_LOADED_MODULES);

		//Inicializa variavel de array dos modulos carregados
		$explodedLoadedModules = null;

		//Inicializa variavel de array dos tipos de modulos carregados
		$explodedLoadedTypes = null;

		//Seta override de todos os arquivos para false
		$copyAll = false;

		//Pega modulos carredos em forma de array
		$explodedLoadedModules = ModulesHelper::getLoadedModules($oldLoadedModules);

		//Separa os tipos dos modulos carregados em um array
		$explodedLoadedTypes = ModulesHelper::explodeTypes( $explodedLoadedModules );

		/////////////////////////////////CHECA PELA EXISTENCIA DO MODULO////////////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(RefreshModule::$errors), function()use($moduleType, $moduleName){
			return ModulesHelper::checkModuleExistence(
				$moduleType,
				$moduleName
			);},
			function($result){if ($result !== true){RefreshModule::$errors = array_merge( RefreshModule::$errors , $result );}},
			$this, Strings::checkModuleExistence($moduleType, $moduleName)
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		/////////////////////////////////CHECA SE O MODULO SELECIONADO ESTA CARREGADO///////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(RefreshModule::$errors), function()use($moduleType, $moduleName, $explodedLoadedModules){
			return ModulesHelper::checkIfModuleLoaded(
				$moduleType,
				$moduleName,
				$explodedLoadedModules
			);},
			function($result){if ($result !== true){RefreshModule::$errors = array_merge( RefreshModule::$errors , $result );}},
			$this, Strings::checkIfModuleLoaded($moduleType, $moduleName)
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////////////////////ORDINARY FILE COPY////////////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(RefreshModule::$errors), function()use($moduleType, $moduleName, $copyAll, &$rollback, $that){
			return ModulesHelper::makeOrdinaryCopies
			(
				$moduleType, $moduleName, $copyAll, $rollback, $that
			);},
			function($result){if ($result !== true){RefreshModule::$errors = array_merge( RefreshModule::$errors , $result );}},
			$this, Strings::STATUS_COPYING_ORDINARY_FILES
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////MIGRATION FILES COPY////////////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(RefreshModule::$errors), function()use($moduleType, $moduleName, $copyAll, &$rollback, $that){
			return ModulesHelper::makeMigrationsCopies
			(
				$moduleType, $moduleName, $copyAll, $rollback, $that, false
			);},
			function($result){if ($result !== true){RefreshModule::$errors = array_merge( RefreshModule::$errors , $result );}},
			$this, Strings::STATUS_COPYING_MIGRATION_FILES
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////ROUTE_BUILDER///////////////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(RefreshModule::$errors), function()use($moduleType,$moduleName, &$rollback){
			return ModulesHelper::buildRoutes
			(
				$moduleType, $moduleName, $rollback
			);},
			function($result){if ($result !== true){RefreshModule::$errors = array_merge( RefreshModule::$errors , $result );}},
			$this, Strings::STATUS_BUILDING_ROUTES
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////RUN MODULE MIGRATIONS///////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(RefreshModule::$errors), function()use($moduleType, $moduleName, &$rollback, $that){
			return ModulesHelper::runMigrations
			(
				$moduleType, $moduleName, $rollback, $that
			);},
			function($result){if ($result !== true){RefreshModule::$errors = array_merge( RefreshModule::$errors , $result );}},
			$this, Strings::STATUS_RUNING_MIGRATIONS
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////GENERATE ROLLBACK FILE//////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(RefreshModule::$errors), function()use($moduleType, $moduleName, &$rollback){
			return ModulesHelper::createRollbackFile
			(
				$moduleType, $moduleName, $rollback
			);},
			function($result){if ($result !== true){RefreshModule::$errors = array_merge( RefreshModule::$errors , $result );}},
			$this, Strings::STATUS_GEN_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////RESPONSE (OUTPUT)///////////////////////////////////////////////
		if (empty(RefreshModule::$errors)){//Se os comandos rodarem com sucesso
			//Comentario comando executado com sucesso
			$this->comment(Strings::successfullyRunModuleRefresh($moduleType, $moduleName));
			return true;
		}else{//Se ocorrer erro ao rodar os comandos
			foreach (RefreshModule::$errors as $error) {
				$this->error($error);
			}
			RollbackManager::execRollback($rollback, $this);
			return false;
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	}
}
