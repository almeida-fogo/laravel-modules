<?php

namespace AlmeidaFogo\LaravelModules\Commands;

use Illuminate\Console\Command;
use Mockery\CountValidator\Exception;

use AlmeidaFogo\LaravelModules\LaravelModules\Configs;
use AlmeidaFogo\LaravelModules\LaravelModules\ModulesHelper;
use AlmeidaFogo\LaravelModules\LaravelModules\RollbackManager;
use AlmeidaFogo\LaravelModules\LaravelModules\RouteBuilder;


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
    protected $description = 'Carrega um modulo de app para a aplicação.';

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

		//Seta status inicialde abort para false
		$abort = false;

		//Seta status inicial para True
		$success = true;

		//Prepara variavel de rollback caso aja erro
		$rollback = array();

		//Configurações Gerais dos Modulos
		$moduleGeneralConfig = base_path().'/app/Modulos/configs.php';

		//Arquivo de Rollbacks
		$moduleRollbackFile = base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Rollback/rollback.php';

		if(is_null($moduleType) && is_null($moduleName)){
			$moduleType = $this->ask('Qual tipo de módulo deseja carregar?');

			$moduleName = $this->ask("Qual o nome do módulo do tipo \"".$moduleType."\" deseja carregar?");
		}

		try{
			if (!(count(\DB::select(\DB::raw("SHOW TABLES LIKE 'project_modules';")))>0)){
				\DB::select(\DB::raw("
					CREATE TABLE project_modules
					(
						id			int NOT NULL PRIMARY KEY AUTO_INCREMENT,
						module_name	VARCHAR (255) UNIQUE NOT NULL
					)
				"));
				if(!(count(\DB::select(\DB::raw("SHOW TABLES LIKE 'project_modules';")))>0)){
					$this->info("ERRO: Erro ao Criar Table de Moculos Carregados.");
					return false;
				}
			}
		}catch (Exception $e){
			$this->info("ERRO: Erro ao Criar Table de Moculos Carregados.");
			return false;
		}

		//Modulos ja carregados
		$oldLoadedModules = Configs::getConfig($moduleGeneralConfig, "modulosCarregados");

		//Caminho do arquivo de configurações do modulo
		$configPath = base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/configs.php';

		//Module Exists
		if (file_exists($configPath))
		{
			//if MODULOS_CARREGADOS == null, carrega array vazio (EVITA QUE TENHA UM SEPARADOR NO INICIO)
			if ($oldLoadedModules == "")
			{
				//Carrega array vazio
				$explodedLoadedModules = array();
			}else{
				//Separa modulos carregados em um array
				$explodedLoadedModules = explode(" & ", $oldLoadedModules);
			}

			//Separa os tipos dos modulos carregados em um array
			$explodedLoadedTypes = ModulesHelper::explodeTypes($explodedLoadedModules);

			//Conflitos de modulo
			$conflitos = Configs::getConfig($configPath, "conflitos");
			$conflitosExistentes = false;
			foreach ($conflitos as $conflito){
				//Se for uma conflito valido
				if ($conflito != ""){
					//Conflito quebrado em tipo e nome
					$conflitoBroken = explode('.',$conflito);
					//Tipo do Conflito
					$conflitoType = $conflitoBroken[0];
					//Verifica se é um conflito especifico
					if (array_key_exists(1, $conflitoBroken)){
						//verifica se o modulo conflituoso esta carregado
						if(in_array($conflito, $explodedLoadedModules)){
							//marca como erro de conflito
							$conflitosExistentes = true;
							$this->comment("ERRO: Existe um conflito com o modulo ".$conflito." que esta carregado");
						}
					}else{//Verifica se é um conflito de tipo
						//verifica se o tipo conflituoso esta carregado
						if (in_array($conflitoType, $explodedLoadedTypes)){
							//marca como erro de conflito
							$conflitosExistentes = true;
							$this->comment("ERRO: Existe um conflito com o tipo do modulo ".$conflitoType." que esta carregado");
						}
					}
				}
			}

			if ($conflitosExistentes == false){
				//Dependencias do modulo
				$dependencias = Configs::getConfig($configPath, "dependencias");
				$missingDependency = false;
				foreach ($dependencias as $dependencia){
					//Se for uma dependencia válida
					if ($dependencia != ""){
						//Dependencia quebrada em tipo e nome
						$dependenciaBroken = explode('.',$dependencia);
						//Tipo da dependencia
						$dependenciaType = $dependenciaBroken[0];
						//Verifica se é uma dependencia especifica
						if (array_key_exists(1, $dependenciaBroken)){
							//verifica se a dependencia esta carregada
							if(!in_array($dependencia, $explodedLoadedModules)){
								//marca como erro de dependencia
								$missingDependency = true;
								$this->comment("ERRO: Dependencia ".$dependencia." faltando");
							}
						}else{//Verifica se é uma dependencia de tipo
							//verifica se a dependencia esta carregada
							if (!in_array($dependenciaType, $explodedLoadedTypes)){
								//marca como erro de dependencia
								$missingDependency = true;
								$this->comment("ERRO: Dependencia do Tipo ".$dependenciaType." faltando");
							}
						}
					}
				}

				//Se não existir erro de dependencia
				if ($missingDependency == false){
					//Verifica se o modulo ja esta carregado
					if (!in_array($moduleType.".".$moduleName, $explodedLoadedModules))
					{
						////////////////////////////////////Constroi array com novo modulo//////////////////////////////////
						array_push($explodedLoadedModules, $moduleType.".".$moduleName);
						$newLoadedModules = implode(" & ", $explodedLoadedModules);
						////////////////////////////////////////////////////////////////////////////////////////////////////

						//Verifica se o arquivo de configurações geral existe existe
						if (file_exists($moduleGeneralConfig))
						{
							/////////////////////SINALIZA NAS CONFIGS GERAIS QUE O MODULO FOI CARREGADO/////////////////////
							if ($success){//Se os comandos anteriores rodarem com sucesso
								$this->comment("INFO: Carrendo no Arquivo de Configuracoes.");

								//Adiciona para a lista de rollback
								$rollback[htmlentities("LoadedModule", ENT_QUOTES, "UTF-8")] = htmlentities($moduleType.".".$moduleName, ENT_QUOTES, "UTF-8");

								//Substitui conteudo da variavel pelo conteudo com o modulo novo
								if (Configs::setConfig($moduleGeneralConfig, "modulosCarregados",$newLoadedModules) == false){
									$success = false;
								}
							}
							////////////////////////////////////////////////////////////////////////////////////////////////

							//////////////////////////////////////Configurações/////////////////////////////////////////////
							if ($success){//Se os comandos anteriores rodarem com sucesso
								$this->comment("INFO: Alterando configuracoes.");

								//Pega configurações
								$configuracoes = Configs::getConfig( $configPath , "configuracoes" );

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
									if(!(count(\DB::table('project_modules')->where('module_name', $moduleType.'.'.$moduleName)->first())>0)){
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
								if (RollbackManager::buildRollback($rollback, $moduleRollbackFile, true) == false){
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
									Configs::getConfig($configPath,"versao"));
							}else{//Se ocorrer erro ao rodar os comandos
								if ($abort == false){//Se Não abortou
									//Comentario comando executado com erro
									$this->comment(
										'ERRO: Erro ao executar o comando em '.
										$moduleType.
										'.'.
										$moduleName.
										' '.
										Configs::getConfig($configPath,"versao"));
								}else{//Se abortou
									//Comentario comando executado com erro
									$this->comment(
										'ABORT: O comando foi abortado '.
										$moduleType.
										'.'.
										$moduleName.
										' '.
										Configs::getConfig($configPath,"versao"));
								}
								/////////////////////////////////////ARQUIVO DE ROLLBACK////////////////////////////////////////
								RollbackManager::execRollback($rollback, $this);
								////////////////////////////////////////////////////////////////////////////////////////////////
							}
							////////////////////////////////////////////////////////////////////////////////////////////////
						}else{//arquivo de configurações não existe
							$this->comment("ERRO: O Arquivo de Config de Modulos nao Existe.");
						}
					}else{//Se ja tiver sido carregado
						$this->comment( "ERRO: Modulo ja carregado, execute 'php artisan module:remove' para remove-lo." );
					}
				}else{//Dependencia faltando
					$this->comment("DICA: Rode o comando 'php artisan module:load' para cada um dos modulos faltantes.");
				}
			}else{//Conflito existente
				$this->comment("DICA: Rode o comando 'php artisan module:loaded' visualizar uma lista dos modulos carregados.");
			}
		}else{//Se o modulo não existir
			$this->comment("ERRO: Modulo chamado nao existe.");
		}
    }
}
