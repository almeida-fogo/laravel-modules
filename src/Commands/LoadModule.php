<?php

namespace AlmeidaFogo\LaravelModules\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use AlmeidaFogo\LaravelModules\LaravelModules\Configs;
use AlmeidaFogo\LaravelModules\LaravelModules\ModulesHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\RollbackManager;
use AlmeidaFogo\LaravelModules\LaravelModules\RouteBuilder;
use AlmeidaFogo\LaravelModules\LaravelModules\PathHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\Strings;


class LoadModule extends Command
{

	public static $errors;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:load {type?} {name?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Carrega um modulo de app para a aplicacao.';

    /**
     * Create a new command instance.
     *
     * @return LoadModule
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
		LoadModule::$errors = [];

		//Prepara variavel de rollback caso aja erro
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
        $explodedLoadedModules = ModulesHelper::getLoadedModules($oldLoadedModules, $moduleType , $moduleName );

		//Separa os tipos dos modulos carregados em um array
		$explodedLoadedTypes = ModulesHelper::explodeTypes( $explodedLoadedModules );

		//Cria table de verificação das migrations
        LoadModule::$errors = ModulesHelper::createMigrationsCheckTable();

        //TODO: checar se o modulo existe e se ja esta carregado

		/////////////////////////////////CHECA POR CONFLITOS ENTRE OS MODULOS///////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(LoadModule::$errors), function()use($moduleType, $moduleName, $explodedLoadedModules, $explodedLoadedTypes){
			return ModulesHelper::checkModuleConflicts
			(
				Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName), Strings::MODULE_CONFIG_CONFLICT),
				$explodedLoadedModules,
				$explodedLoadedTypes
			);},
			function($result){if ($result !== false){LoadModule::$errors = array_merge( LoadModule::$errors , $result );}}
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////CHECA POR ERROS DE DEPENDENCIA ENTRE OS MODULOS///////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(LoadModule::$errors), function()use($moduleType, $moduleName, $explodedLoadedModules, $explodedLoadedTypes){
			return ModulesHelper::checkModuleDependencies(
		   		Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName), Strings::MODULE_CONFIG_DEPENDENCIES),
		   		$explodedLoadedModules,
		   		$explodedLoadedTypes
			);},
			function($result){if ($result !== false){LoadModule::$errors = array_merge( LoadModule::$errors , $result );}}
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////MARCA O MODULO COMO CARREGADO///////////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(LoadModule::$errors), function()use($explodedLoadedModules, $moduleType, $moduleName, &$rollback){
			return ModulesHelper::setModuleAsLoaded(
 			    $explodedLoadedModules, $moduleType, $moduleName, $rollback
			);},
			function($result){if ($result !== true){LoadModule::$errors = array_merge( LoadModule::$errors , $result );}},
			$this, Strings::STATUS_SETING_AS_LOADED
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////APLICA AS CONFIGURAÇÕES REQUERIDAS PELO MODULO////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(LoadModule::$errors), function()use($moduleType, $moduleName, &$rollback){
			return ModulesHelper::makeModuleConfigs
			(
				$moduleType, $moduleName, $rollback
			);},
			function($result){if ($result !== true){LoadModule::$errors = array_merge( LoadModule::$errors , $result );}},
		    $this, Strings::STATUS_SETTING_MODULE_CONFIGS
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////////////////////ORDINARY FILE COPY////////////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(LoadModule::$errors), function()use($moduleType, $moduleName, $copyAll, &$rollback, $that){
			return ModulesHelper::makeOrdinaryCopies
			(
			    $moduleType, $moduleName, $copyAll, $rollback, $that
			);},
			function($result){if ($result !== true){LoadModule::$errors = array_merge( LoadModule::$errors , $result );}},
		    $this, Strings::STATUS_COPYING_ORDINARY_FILES
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////MIGRATION FILES COPY////////////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(LoadModule::$errors), function()use($moduleType, $moduleName, $copyAll, &$rollback, $that){
			return ModulesHelper::makeMigrationsCopies
			(
				$moduleType, $moduleName, $copyAll, $rollback, $that
			);},
			function($result){if ($result !== true){LoadModule::$errors = array_merge( LoadModule::$errors , $result );}},
			    $this, Strings::STATUS_COPYING_MIGRATION_FILES
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////ROUTE_BUILDER///////////////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(LoadModule::$errors), function()use($moduleType,$moduleName, &$rollback){
			return ModulesHelper::buildRoutes
			(
				$moduleType, $moduleName, $rollback
			);},
			function($result){if ($result !== true){LoadModule::$errors = array_merge( LoadModule::$errors , $result );}},
		    $this, Strings::STATUS_BUILDING_ROUTES
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////RUN MODULE MIGRATIONS///////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(LoadModule::$errors), function()use($moduleType, $moduleName, &$rollback, $that){
			return ModulesHelper::runMigrations
			(
			   	$moduleType, $moduleName, $rollback, $that
			);},
			function($result){if ($result !== true){LoadModule::$errors = array_merge( LoadModule::$errors , $result );}},
		   	$this, Strings::STATUS_RUNING_MIGRATIONS
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////GENERATE ROLLBACK FILE//////////////////////////////////////////
		ModulesHelper::executeHelperMethod(empty(LoadModule::$errors), function()use($moduleType, $moduleName, &$rollback){
			return ModulesHelper::createRollbackFile
			(
			   	$moduleType, $moduleName, $rollback
			);},
			function($result){if ($result !== true){LoadModule::$errors = array_merge( LoadModule::$errors , $result );}},
		    $this, Strings::STATUS_GEN_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////RESPONSE (OUTPUT)///////////////////////////////////////////////
		if (empty(LoadModule::$errors)){//Se os comandos rodarem com sucesso
			//Comentario comando executado com sucesso
			$this->comment(Strings::successfulyRunModuleLoad($moduleType, $moduleName));
		}else{//Se ocorrer erro ao rodar os comandos
            foreach (LoadModule::$errors as $error) {
                $this->error($error);
            }
			RollbackManager::execRollback($rollback, $this);
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    }
}
