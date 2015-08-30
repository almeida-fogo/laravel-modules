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

		//Seta status inicial para True
		$success = true;

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

		//Cria table de verificação das migrations
		$success = ModulesHelper::createMigrationsCheckTable($this);

		//Modulos ja carregados
		$oldLoadedModules = Configs::getConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_LOADED_MODULES);

		//Inicializa variavel de array dos modulos carregados
		$explodedLoadedModules = null;

		//Inicializa variavel de array dos tipos de modulos carregados
		$explodedLoadedTypes = null;

		//Seta override de todos os arquivos para false
		$copyAll = false;

		///////////////////////////////////PEGA MODULOS CARREGADOS EM FORMA DE ARRAY////////////////////////////////////
		if($success)
		{
			//Pega modulos carredos em forma de array
			$explodedLoadedModules = ModulesHelper::getLoadedModules($oldLoadedModules, $moduleType , $moduleName );
			//se houver erro
			if ( is_null($explodedLoadedModules) )
			{
				//Adiciona o erro para o array de erros
				$errors[ ] = Strings::MODULE_NOT_FOUND;
				$success = false;
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//TODO: checar se o modulo ja esta carregado

		/////////////////////////////////CHECA POR CONFLITOS ENTRE OS MODULOS///////////////////////////////////////////
		if($success){
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
				$success = false;
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////CHECA POR ERROS DE DEPENDENCIA ENTRE OS MODULOS///////////////////////////////////
		if($success){
			//Dependencias do modulo
			$dependencias = Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName), Strings::MODULE_CONFIG_DEPENDENCIES);

			//Checa dependencias de modulos
			$tmpErrors = ModulesHelper::checkModuleDependencies($dependencias, $explodedLoadedModules, $explodedLoadedTypes);

			//Se houverem conflitos
			if ($tmpErrors != false)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
				$success = false;
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////MARCA O MODULO COMO CARREGADO///////////////////////////////////////////////
		if($success)
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
				$success = false;
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////APLICA AS CONFIGURAÇÕES REQUERIDAS PELO MODULO////////////////////////////////////
		if ($success){
			$this->comment(Strings::STATUS_SETTING_MODULE_CONFIGS);

			//Faz configurações requeridas pelo modulo
			$tmpErrors = ModulesHelper::makeModuleConfigs($moduleType, $moduleName, $rollback);

			//Se existir algum problema
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
				$success = false;
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////////////////////ORDINARY FILE COPY////////////////////////////////////////////////
		if ($success){
			$this->comment(Strings::STATUS_COPYING_ORDINARY_FILES);

			//Faz copia de arquivos do modulo
			$tmpErrors = ModulesHelper::makeOrdinaryCopies($moduleType, $moduleName, $copyAll, $rollback, $this);

			//Se existir algum problema
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
				$success = false;
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////MIGRATION FILES COPY////////////////////////////////////////////////
		if ($success){
			$this->comment(Strings::STATUS_COPYING_MIGRATION_FILES);

			//Faz copia de arquivos do modulo
			$tmpErrors = ModulesHelper::makeMigrationsCopies($moduleType, $moduleName, $copyAll, $rollback, $this);

			//Se existir algum problema
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
				$success = false;
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////ROUTE_BUILDER///////////////////////////////////////////////////
		if ($success){
			$this->comment(Strings::STATUS_BUILDING_ROUTES);

			//Gera arquivo de rotas
			$tmpErrors = ModulesHelper::buildRoutes($moduleType, $moduleName, $rollback);

			//Se existir algum problema
			if ($tmpErrors != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $tmpErrors);
				$success = false;
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

							/////////////////////////////////////MIGRATIONS/////////////////////////////////////////////////
							//Talvez tenhamos problemas com os timestamps das migrations
							if ($success){//Se os comandos anteriores rodarem com sucesso
								$this->comment("INFO: Roda migrations.");
								try{
									//Roda dump autoload
									shell_exec("composer dump-autoload");
									//Tenta Rodar a migration
									$this->call("migrate");
									//Roda dump autoload
									shell_exec("composer dump-autoload");
									//Seta a flag de migrations para true no rollback
									$rollback["migration"] = "true";
									/////VERIFICAR SE MIGRATE RODOU DE FORMA ADEQUADA//////
									if(!(count( DB::table('project_modules')->where('module_name', $moduleType.'.'.$moduleName)->first())>0)){
										$this->comment("ERRO: Erro ao Rodar Migration.");
										//seta flag de erro para true
										$success = false;
									}
									///////////////////////////////////////////////////////
								}catch(\Exception $e){
									//Se houver um erro sinaliza para que o comando seja desfeito ao fim do codigo
									$success = false;
								}
							}
							////////////////////////////////////////////////////////////////////////////////////////////////

							/////////////////////////////////////ARQUIVO DE ROLLBACK////////////////////////////////////////
							if ($success){
								$this->comment("INFO: Constroi Arquivo de Rollback.");
								//diretorio para o arquivo de rotas do modulo
								$rollbackPath = base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Rollback/rollback.php';
								//Cria registro no rollback dizendo que o arquivo foi copiado
								$rollback["old-rollback"] = htmlentities(file_get_contents($rollbackPath), ENT_QUOTES, "UTF-8");
								if (RollbackManager::buildRollback($rollback, PathHelper::getModuleRollbackFile($moduleType, $moduleName), true) == false){
									$success = false;
								}
							}
							////////////////////////////////////////////////////////////////////////////////////////////////

							///////////////////////////////////////RESPONSE (OUTPUT)////////////////////////////////////////
							var_dump($errors);
							if ($success){//Se os comandos rodarem com sucesso
								//Comentario comando executado com sucesso
								$this->comment(
									'Comando executado com sucesso. '.
									$moduleType.
									'.'.
									$moduleName.
									' '.
									Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName),"versao")
								);
							}else{//Se ocorrer erro ao rodar os comandos
								//Comentario comando executado com erro
								$this->comment(
									'ERRO: Erro ao executar o comando em '.
									$moduleType.
									'.'.
									$moduleName.
									' '.
									Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName),"versao")
								);

								/////////////////////////////////////ARQUIVO DE ROLLBACK////////////////////////////////////////
								RollbackManager::execRollback($rollback, $this);
								////////////////////////////////////////////////////////////////////////////////////////////////
							}
							////////////////////////////////////////////////////////////////////////////////////////////////
//						}else{//arquivo de configurações não existe
//							$this->comment("ERRO: O Arquivo de Config de Modulos nao Existe.");
//						}
//					}else{//Se ja tiver sido carregado
//						$this->comment( "ERRO: Modulo ja carregado, execute 'php artisan module:remove' para remove-lo." );
//					}
//				}else{//Dependencia faltando
//					$this->comment("DICA: Rode o comando 'php artisan module:load' para cada um dos modulos faltantes.");
//				}
//			}else{//Conflito existente
//				$this->comment("DICA: Rode o comando 'php artisan module:loaded' visualizar uma lista dos modulos carregados.");
//			}
//		}else{//Se o modulo não existir
//			$this->comment("ERRO: Modulo chamado nao existe.");
//		}
    }
}
