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

		//Tipo do modulo
		$moduleType = $this->argument("type");

		//Nome do modulo
		$moduleName = $this->argument("name");

		//Inicializa variavel erros
		$errors = [];

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

        //Cria table de verificação das migrations
        $errors = ModulesHelper::createMigrationsCheckTable();

        //TODO: checar se o modulo ja esta carregado

		/////////////////////////////////CHECA POR CONFLITOS ENTRE OS MODULOS///////////////////////////////////////////
		if(empty($errors)){
			//Separa os tipos dos modulos carregados em um array
			$explodedLoadedTypes = ModulesHelper::explodeTypes( $explodedLoadedModules );
			//Pega configuração de conflitos do modulo
			$conflitos = Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName) , Strings::MODULE_CONFIG_CONFLICT);

			//Checa conflitos de modulos
			$tmpErrors = ModulesHelper::checkModuleConflicts($conflitos, $explodedLoadedModules, $explodedLoadedTypes);

			//Se houverem conflitos
			if ($tmpErrors != false)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////CHECA POR ERROS DE DEPENDENCIA ENTRE OS MODULOS///////////////////////////////////
		if(empty($errors)){
			//Dependencias do modulo
			$dependencias = Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName), Strings::MODULE_CONFIG_DEPENDENCIES);

			//Checa dependencias de modulos
			$tmpErrors = ModulesHelper::checkModuleDependencies($dependencias, $explodedLoadedModules, $explodedLoadedTypes);

			//Se houverem conflitos
			if ($tmpErrors != false)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////MARCA O MODULO COMO CARREGADO///////////////////////////////////////////////
		if(empty($errors))
		{
			//Retorna status
			$this->comment(Strings::STATUS_SETING_AS_LOADED);

			//Checa dependencias de modulos
			$tmpErrors = ModulesHelper::setModuleAsLoaded($explodedLoadedModules, $moduleType, $moduleName, $rollback);

			//Se houverem conflitos
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////APLICA AS CONFIGURAÇÕES REQUERIDAS PELO MODULO////////////////////////////////////
		if (empty($errors)){
			$this->comment(Strings::STATUS_SETTING_MODULE_CONFIGS);

			//Faz configurações requeridas pelo modulo
			$tmpErrors = ModulesHelper::makeModuleConfigs($moduleType, $moduleName, $rollback);

			//Se existir algum problema
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////////////////////ORDINARY FILE COPY////////////////////////////////////////////////
		if (empty($errors)){
			$this->comment(Strings::STATUS_COPYING_ORDINARY_FILES);

			//Faz copia de arquivos do modulo
			$tmpErrors = ModulesHelper::makeOrdinaryCopies($moduleType, $moduleName, $copyAll, $rollback, $this);

			//Se existir algum problema
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////MIGRATION FILES COPY////////////////////////////////////////////////
		if (empty($errors)){
			$this->comment(Strings::STATUS_COPYING_MIGRATION_FILES);

			//Faz copia de arquivos de migrations do modulo
			$tmpErrors = ModulesHelper::makeMigrationsCopies($moduleType, $moduleName, $copyAll, $rollback, $this);

			//Se existir algum problema
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////ROUTE_BUILDER///////////////////////////////////////////////////
		if (empty($errors)){
			$this->comment(Strings::STATUS_BUILDING_ROUTES);

			//Gera arquivo de rotas
			$tmpErrors = ModulesHelper::buildRoutes($moduleType, $moduleName, $rollback);

			//Se existir algum problema
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////RUN MODULE MIGRATIONS///////////////////////////////////////////
		if (empty($errors)){
			$this->comment(Strings::STATUS_RUNING_MIGRATIONS);

			//Roda os arquivos de migrations
			$tmpErrors = ModulesHelper::runMigrations($moduleType, $moduleName, $rollback, $this);

			//Se existir algum problema
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////GENERATE ROLLBACK FILE//////////////////////////////////////////
		if (empty($errors)){
			$this->comment(Strings::STATUS_GEN_ROLLBACK);

			//Gera arquivo de rollback
			$tmpErrors = ModulesHelper::createRollbackFile($moduleType, $moduleName, $rollback);

			//Se existir algum problema
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////RESPONSE (OUTPUT)///////////////////////////////////////////////
		if (empty($errors)){//Se os comandos rodarem com sucesso
			//Comentario comando executado com sucesso
			$this->comment(Strings::successfulyRunModuleLoad($moduleType, $moduleName));
		}else{//Se ocorrer erro ao rodar os comandos
            foreach ($errors as $error) {
                $this->error($error);
            }
            /////////////////////////////////////ARQUIVO DE ROLLBACK////////////////////////////////////////
			RollbackManager::execRollback($rollback, $this);
			////////////////////////////////////////////////////////////////////////////////////////////////
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    }
}
