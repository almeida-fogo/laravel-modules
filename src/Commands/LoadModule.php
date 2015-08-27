<?php

namespace AlmeidaFogo\LaravelModules\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mockery\CountValidator\Exception;

use AlmeidaFogo\LaravelModules\LaravelModules\Configs;
use AlmeidaFogo\LaravelModules\LaravelModules\ModulesHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\RollbackManager;
use AlmeidaFogo\LaravelModules\LaravelModules\RouteBuilder;
use AlmeidaFogo\LaravelModules\LaravelModules\PathHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\Strings;
use AlmeidaFogo\LaravelModules\LaravelModules\EscapeHelper;


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
			$moduleType = $this->ask('Qual tipo de modulo deseja carregar?');
			//pede o nome do modulo
			$moduleName = $this->ask("Qual o nome do modulo do tipo \"".$moduleType."\" deseja carregar?");
		}

		//Cria table de verificação das migrations
		$success = ModulesHelper::createMigrationsCheckTable($this);

		//Modulos ja carregados
		$oldLoadedModules = Configs::getConfig(PathHelper::getModuleGeneralConfig(), "modulosCarregados");

		//Inicializa variavel de array dos modulos carregados
		$explodedLoadedModules = null;

		//Inicializa variavel de array dos tipos de modulos carregados
		$explodedLoadedTypes = null;

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

		/////////////////////////////////CHECA POR CONFLITOS ENTRE OS MODULOS///////////////////////////////////////////
		if($success){
			//Separa os tipos dos modulos carregados em um array
			$explodedLoadedTypes = ModulesHelper::explodeTypes( $explodedLoadedModules );
			//Pega configuração de conflitos do modulo
			$conflitos = Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName) , Strings::MODULE_CONFIG_CONFLICT);

			//Checa conflitos de modulos
			$tmpErrors = ModulesHelper::checkModuleConflicts($conflitos, $explodedLoadedModules, $explodedLoadedTypes);

			//Se houverem conflitos
			if ($tmpErrors == false)
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
			if ($tmpErrors == false)
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
			$loaded = ModulesHelper::setModuleAsLoaded($explodedLoadedModules, $moduleType, $moduleName);

			//Se houverem conflitos
			if ($loaded != true)
			{
				//Adiciona os erros para o array de erros
				$errors = array_merge($errors, $loaded);
				$success = false;
			}
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////



							//////////////////////////////////////Configurações/////////////////////////////////////////////
							if ($success){//Se os comandos anteriores rodarem com sucesso
								$this->comment("INFO: Alterando configuracoes.");

								//Pega configurações
								$configuracoes = Configs::getConfig( PathHelper::getModuleConfigPath($moduleType, $moduleName) , "configuracoes" );

								//Inicia o Rollback de arquivos configurados
								$rollback["module-configs"] = array();

								foreach ( $configuracoes as $configuracao => $valor )
								{
									if ( $valor != "" )
									{
										$path = explode('.',$configuracao);
										array_pop($path);
										$path = base_path().'/config/'.implode("/", $path).'.php';

										$configName = str_replace(".", "-", $configuracao);

										//Inicia o Rollback de arquivos configurados
										$rollback["module-configs"][htmlentities($configName, ENT_QUOTES, "UTF-8")] = array();

										//Adiciona para a lista de rollback
										$rollback["module-configs"][htmlentities($configName, ENT_QUOTES, "UTF-8")][htmlentities($path, ENT_QUOTES, "UTF-8")] = htmlentities(file_get_contents($path), ENT_QUOTES, "UTF-8");

										//Se ao tentar configurar temos um erro, então:
										if ( Configs::setLaravelConfig( $configuracao , $valor ) == false )
										{
											//Sinaliza na flag
											$success = false;
										}
									}
								}
							}
							////////////////////////////////////////////////////////////////////////////////////////////////

							////////////////////////////////////FILE COPY (EXCEPT MIGRATIONS)///////////////////////////////
							//Seta override de todos os arquivos para false
							$all = false;
							if ($success){//Se os comandos anteriores rodarem com sucesso
								$this->comment("INFO: Copia arquivos.");

								//Inicia o Rollback de arquivos copiados
								$rollback["module-files"] = array();

								//Cria array de quais pastas e arquivos devem ser copiados para onde
								$paths = [
										base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Controllers/' => base_path().'/app/Http/Controllers/',
										base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Models/' => base_path().'/app/',
										base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Views/' => base_path().'/resources/views/'.$moduleType.'_'.$moduleName.'/',
										base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Public/' => base_path().'/public/'.$moduleType.'_'.$moduleName.'/',
										base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Public/css/' => base_path().'/public/'.$moduleType.'_'.$moduleName.'/css/',
										base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Public/js/' => base_path().'/public/'.$moduleType.'_'.$moduleName.'/js/',
										base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Public/imagens/' => base_path().'/public/'.$moduleType.'_'.$moduleName.'/imagens/',
								];

								//loop em todos os diretorios de destino
								foreach($paths as $key => $value){
									if(!is_dir($value)){//Se o diretorio não existir
										//Cria o diretorio que não existe
										if (mkdir($value)){
											//Cria registro no rollback dizendo uma pasta foi criada
											$rollback["dir-created"][] = $value;
										}
									}
								}

								//Loop em todas as pastas
								foreach($paths as $key => $value){
									if ($success){//Se os comandos anteriores rodarem com sucesso
										//Copia lista de arquivos no diretorio para variavel arquivos
										$arquivos = scandir($key);
										//Loop em todos os arquivos do modulo
										for( $i = 2; $i < count($arquivos); $i++){
											if ($success && !is_dir($value.$arquivos[$i])){//Se os comandos anteriores rodarem com sucesso e o arquivo não for uma pasta

												$explodedFileName  = explode("/", $value.$arquivos[$i]);
												$filename = $explodedFileName[count($explodedFileName)-1];

												//Verifica se o arquivo existe
												if (!file_exists($value.$arquivos[$i])){
													//Cria registro no rollback dizendo que o arquivo foi copiado
													$rollback["module-files"][htmlentities($value.$arquivos[$i], ENT_QUOTES, "UTF-8")] = "";
													//verifica se a copia ocorreu com sucesso
													if (copy($key.$arquivos[$i], $value.$arquivos[$i]) == false){
														//Se der erro seta a variavel $sucess para false
														$success = false;
														//Printa msg de erro
														$this->comment("ERRO: Não foi possivel copiar o arquivo ".$value.$arquivos[$i].".");
													}else{
														//Printa no terminal que o arquivo foi copiado
														$this->comment("INFO: Arquivo ".$value.$arquivos[$i]." copiado com sucesso.");
													}
												}else if (strtoupper($filename) != strtoupper('.gitkeep')){//Caso ja exista um arquivo com o mesmo nome no diretorio de destino
													//Inicializa variavel que vai receber resposta do usuario dizendo o que fazer
													// com o conflito
													$answer = "";
													//Enquanto o usuario não devolver uma resposta valida
													while ($all != true && $answer != 'y' && $answer != 'n' && $answer !=
														'a' && $answer != 'c'){
														//Faz pergunta para o usuario de como proceder
														$answer = $this->ask("O arquivo '".$value.$arquivos[$i]."' tem certeza que deseja substitui-lo? (y = yes, n = no, a = all, c = cancel)", false);
													}
													//Se a resposta for sim, ou all
													if (strtolower($answer) == "y" || strtolower($answer) == "a" || $all == true){
														//se a resposta for all
														if (strtolower($answer) == "a"){
															//seta variavel all para true
															$all = true;
														}
														//Faz backup do arquivo que será substituido
														$rollback["module-files"][htmlentities($value.$arquivos[$i], ENT_QUOTES, "UTF-8")] = htmlentities(file_get_contents($value.$arquivos[$i]), ENT_QUOTES, "UTF-8");
														//verifica se a substituição ocorreu com sucesso
														if (copy($key.$arquivos[$i], $value.$arquivos[$i]) == false){//Se houver erro ao copiar arquivo
															//Se der erro seta a variavel $sucess para false
															$success = false;
															//Printa msg de erro
															$this->comment("ERRO: Não foi possivel substituir o arquivo ".$key.$arquivos[$i].".");
														}else{
															//Printa no terminal que o arquivo foi substituido
															$this->comment("INFO: Arquivo ".$key.$arquivos[$i]." substituido com sucesso.");
														}
													}else if (strtolower($answer) == "n"){//se a resposta for não
														//Printa no terminal qu o arquivo foi pulado
														$this->comment("INFO: Pulando arquivo ".$key.$arquivos[$i].".");
													}else if (strtolower($answer) == "c"){//se a resposta foi cancelar
														//Se for abortado seta a variavel $sucess para false
														$success = false;
														//Se for abortado seta a variavel $abort para true
														$abort = true;
														//break the file loop
														break(2);
													}
												}
											}
										}
									}
								}
							}
							////////////////////////////////////////////////////////////////////////////////////////////////

							////////////////////////////////////FILE COPY (MIGRATIONS)//////////////////////////////////////
							if ($success){//Se os comandos anteriores rodarem com sucesso
								$this->comment("INFO: Copia migrations.");

								//Inicia o Rollback de arquivos copiados
								$rollback["module-migration-files"] = array();
								//Inicia o Rollback de arquivos deletados
								$rollback["module-migration-deleted-files"] = array();

								$migrationConfigPath = base_path().'/app/Modulos/configs.php';
								$migrationModulePath = base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Migrations/';
								$migrationPath = base_path().'/database/migrations/';

								//Copia lista de arquivos no diretorio de migrations para variavel arquivos
								$arquivos = scandir($migrationModulePath);
								//Loop em todos os arquivos do modulo
								for( $i = 2; $i < count($arquivos); $i++){
									$explodedModuleMigrationName = explode("_", $arquivos[$i]);
									$SimplifiedModuleMigrationName = implode("_",array_slice($explodedModuleMigrationName, 4));

									$migrationPos = false;
									$migrationFiles = scandir($migrationPath);
									foreach ($migrationFiles as $migrationIndex => $migrationFile){
										$explodedMigrationFileName = explode("_", $migrationFile);
										$SimplifiedMigratioFileName = implode("_",array_slice($explodedMigrationFileName, 4));
										if ($SimplifiedMigratioFileName == $SimplifiedModuleMigrationName){
											$migrationPos = $migrationIndex;
											break;
										}
									}

									$explodedFileName  = explode("/", $migrationModulePath.$arquivos[$i]);
									$filename = $explodedFileName[count($explodedFileName)-1];
									if (strtoupper($filename) != strtoupper('.gitkeep')){
										if ($migrationPos == false){//Se o arquivo não existir
											$migrationCounter = Configs::getConfig($migrationConfigPath, "migrationsCounter");
											Configs::setConfig($migrationConfigPath, "migrationsCounter", $migrationCounter+1);
											copy($migrationModulePath.$arquivos[$i], $migrationPath."0000_00_00_".str_pad($migrationCounter, 6, "0", STR_PAD_LEFT).'_'.$SimplifiedModuleMigrationName);
											//Sinaliza o no arquivo copiado
											$rollback["module-migration-files"][] = htmlentities($migrationPath."0000_00_00_".str_pad($migrationCounter, 6, "0", STR_PAD_LEFT).'_'.$SimplifiedModuleMigrationName, ENT_QUOTES, "UTF-8");
										}else{//Se o arquivo ja existir
											//Inicializa variavel que vai receber resposta do usuario dizendo o que fazer
											// com o conflito
											$answer = "";
											//Enquanto o usuario não devolver uma resposta valida
											while ($all != true && $answer != 'y' && $answer != 'n' && $answer !=
												'a' && $answer != 'c'){
												//Faz pergunta para o usuario de como proceder
												$answer = $this->ask("O arquivo '".$migrationModulePath.$arquivos[$i]."' tem certeza que deseja substitui-lo? (y = yes, n = no, a = all, c = cancel)", false);
											}
											//Se a resposta for sim, ou all
											if (strtolower($answer) == "y" || strtolower($answer) == "a" || $all == true){
												//se a resposta for all
												if (strtolower($answer) == "a"){
													//seta variavel all para true
													$all = true;
												}

												//Captura o numero da migration
												$migrationCounter = Configs::getConfig($migrationConfigPath, "migrationsCounter");
												//Atualiza o contador de migrations
												Configs::setConfig($migrationConfigPath, "migrationsCounter", $migrationCounter+1);

												//Sinaliza o no arquivo copiado
												$rollback["module-migration-files"][] = htmlentities($migrationPath."0000_00_00_".str_pad($migrationCounter, 6, "0", STR_PAD_LEFT).'_'.$SimplifiedModuleMigrationName, ENT_QUOTES, "UTF-8");

												//Faz backup do arquivo que será substituido
												$rollback["module-migration-deleted-files"][htmlentities($migrationPath.$migrationFiles[$migrationPos], ENT_QUOTES, "UTF-8")] = htmlentities(file_get_contents($migrationPath.$migrationFiles[$migrationPos]), ENT_QUOTES, "UTF-8");

												//Deletar o arquivo antigo
												unlink($migrationPath.$migrationFiles[$migrationPos]);

												//verifica se a substituição ocorreu com sucesso
												if (copy($migrationModulePath.$arquivos[$i], $migrationPath."0000_00_00_".str_pad($migrationCounter, 6, "0", STR_PAD_LEFT).'_'.$SimplifiedModuleMigrationName) == false){//Se houver erro ao copiar arquivo
													//Se der erro seta a variavel $sucess para false
													$success = false;
													//Printa msg de erro
													$this->comment("ERRO: Não foi possivel substituir o arquivo ".$migrationPath.$arquivos[$i].".");
												}else{
													//Printa no terminal que o arquivo foi substituido
													$this->comment("INFO: Arquivo ".$migrationPath.$arquivos[$i]." substituido com sucesso.");
												}
											}else if (strtolower($answer) == "n"){//se a resposta for não
												//Printa no terminal qu o arquivo foi pulado
												$this->comment("INFO: Pulando arquivo ".$migrationPath.$arquivos[$i].".");
											}else if (strtolower($answer) == "c"){//se a resposta foi cancelar
												//Se for abortado seta a variavel $sucess para false
												$success = false;
												//Se for abortado seta a variavel $abort para true
												$abort = true;
												//break the file loop
												break;
											}
										}
									}
								}
							}
							////////////////////////////////////////////////////////////////////////////////////////////////

							/////////////////////////////////////ROUTES/////////////////////////////////////////////////////
							if ($success){
								//Printa que esta lidando com o arquivo de rotas
								$this->comment("INFO: Copia rotas.");

								//diretorio para o arquivo de rotas gerais
								$universalRoutesPath = base_path().'/app/Http/routes.php';
								//diretorio para o arquivo de rotas do modulo
								$routesPath = base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/routes.php';
								//diretorio parao arquivo de rotas do projeto
								$routesBuilderPath = base_path().'/app/Modulos/RouteBuilder.php';

								//Cria registro no rollback dizendo que o arquivo foi copiado
								$rollback["routes-builder"] = htmlentities(file_get_contents($routesBuilderPath), ENT_QUOTES, "UTF-8");

								//constroi o array do routesBuilder
								$routeBuilder = RouteBuilder::getRoutesBuilder($routesBuilderPath);
								//verifica se foi construido um array valido
								if ($routeBuilder !== false){
									//inclui as novas rotas ao array do routeBuilder
									$routeBuilder = RouteBuilder::includeToRoutesBuilder($routeBuilder, $routesPath);
									//verifica se o array de rotas continua válido
									if ($routeBuilder !== false){
										//tenta salvar o novo array do routesBuilder
										if (RouteBuilder::saveRoutesBuilder($routeBuilder, $routesBuilderPath) != false){
											//Cria registro no rollback dizendo que o arquivo foi copiado
											$rollback["old-routes"] = htmlentities(file_get_contents($universalRoutesPath), ENT_QUOTES, "UTF-8");
											//tenta construir o arquivo de rotas gera baseado no array savo do routesBuilder
											if (RouteBuilder::buildRoutes($routeBuilder) === false){
												//Erro se n foi possivel gerar o novo arquivo de rotas
												$this->comment("ERRO: Problemas ao gerar o arquivo de rotas.");
												//seta flag de erro para true
												$success = false;
											}
										}else{
											$this->comment("ERRO: Problemas ao salvar RouterBuilder.");
											//seta flag de erro para true
											$success = false;
										}
									}else{
										$this->comment("ERRO: Problemas ao incluir rotas ao RouterBuilder.");
										//seta flag de erro para true
										$success = false;
									}
								}else{
									$this->comment("ERRO: Problemas ao gerar RoutesBuilder Array.");
									//seta flag de erro para true
									$success = false;
								}
							}
							////////////////////////////////////////////////////////////////////////////////////////////////

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
							if ($success){//Se os comandos rodarem com sucesso
								//Comentario comando executado com sucesso
								$this->comment(
									'Comando executado com sucesso. '.
									$moduleType.
									'.'.
									$moduleName.
									' '.
									Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName),"versao"));
							}else{//Se ocorrer erro ao rodar os comandos
								if ($abort == false){//Se Não abortou
									//Comentario comando executado com erro
									$this->comment(
										'ERRO: Erro ao executar o comando em '.
										$moduleType.
										'.'.
										$moduleName.
										' '.
										Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName),"versao"));
								}else{//Se abortou
									//Comentario comando executado com erro
									$this->comment(
										'ABORT: O comando foi abortado '.
										$moduleType.
										'.'.
										$moduleName.
										' '.
										Configs::getConfig(PathHelper::getModuleConfigPath($moduleType, $moduleName),"versao"));
								}
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
