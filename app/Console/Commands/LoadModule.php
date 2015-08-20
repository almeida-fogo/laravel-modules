<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mockery\CountValidator\Exception;
use Symfony\Component\VarDumper\Caster\PdoCaster;

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
		//Remove directory
		function deleteDirectory($dir) {
			if (!file_exists($dir)) {
				return true;
			}

			if (!is_dir($dir)) {
				return unlink($dir);
			}

			foreach (scandir($dir) as $item) {
				if ($item == '.' || $item == '..') {
					continue;
				}

				if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
					return false;
				}

			}

			return rmdir($dir);
		}

		//Pega a configuração de um arquivo
		function getConfig($configPath, $index = null){
			//Roda o arquivo de configuração
			$value = eval(str_replace("<?php", "", file_get_contents($configPath)));
			if (is_array($value)){
				//verifica se o indice do array de configurações é nulo
				if ($index != null)
				{
					if (array_key_exists($index, $value)){
						//devolve o valor da configuração
						return $value[$index];
					}else{
						return null;
					}
				}else{//Se o indice for nulo
					//Devolve todas as configurações
					return $value;
				}
			}else{//Se não for um array
				//Retorna null
				return null;
			}
		}

		//Set Config
		function setConfig($path, $config, $value){
			//verifica se os parametros são validos
			if ($config != '\'\'' && $path != base_path().'/'.'.php'){
				//verifica se o arquvido onde estão as configs existe
				if (file_exists($path)){
					//pega no arquivo a posição onde esta a configuração que deve ser alterada
					$configPos = strpos(file_get_contents($path), $config, 0);
					//verifica se a configuração existe no arquivo
					if ($configPos != false){
						// pega a posição do operador seta (=>) apos a cofiguração
						$arrowPos = strpos(file_get_contents($path), '=>', $configPos)+2;
						//verifica se o operador seta apos a confiração existe
						if ($arrowPos != false){
							//captura a posição da proxima virgula apos o operador seta
							$commaPos = strpos(file_get_contents($path), ',', $arrowPos);
							//verifica se a virgula existe
							if ($commaPos != false){
								//pega o espaço entra a seta e a vigula e substitui pelo valor dado em $value
								return file_put_contents(
									$path,
									substr_replace(file_get_contents($path),
												   ' \''.$value.'\'',
												   $arrowPos,
												   $commaPos-$arrowPos
									)
								);
							}
						}
					}
				}
			}
			//retorna false caso o comando de substituição não seja executado
			return false;
		}

		//Quebra todos os modulos carregados em tipos carregados
		function explodeTypes($loadedModules){
			//inicializa um array vazio
			$types = array();
			//Loop em todos os modulos
			foreach ( $loadedModules as $module)
			{
				//Pega o tipo do modulo
				$type = explode(".", $module)[0];
				//Verifica se o tipo ja foi colocado na lista
				if (!in_array($type, $types)){
					//Adiciona a lista
					array_push($types, $type);
				}
			}
			return $types;
		}

		//get Routes Builder
		function getRoutesBuilder($routeBuilderFile){
			//Pega configurações salvas no arquivo route builder em um array
			return getConfig($routeBuilderFile, null);
		}

		//include ou altera uma rota no array de rotas dado
		function includeToRoutesBuilder($routeBuilderArray, $fileToInclude){
			//Verifica se o arquivo de rotas dado para ser incluido existe
			if (file_exists($fileToInclude)){
				//Pega o diretorio do arquivo e explode em um array
				$explodedFilePath = explode("/", $fileToInclude);
				//pega o tamanho do array explodido do nome do arquivo
				$explodedFilePathSize = count($explodedFilePath);
				//verifica se o diretorio explodido gerou um array valido
				if ($explodedFilePathSize >= 3){
					//em caso positivo captura o tipo e o nome do modulo a que o o arquivo de rotas pertence e salva em $key
					$key = $explodedFilePath[$explodedFilePathSize-3]."-".$explodedFilePath[$explodedFilePathSize-2];
					//pega o conteudo do arquivo de rota removendo somente o <?php
					$fileContent = file_get_contents($fileToInclude, null, null, 5);
					//retorna  o array de routeBuilder adicionado das novas rotas
					return array_add($routeBuilderArray, $key, $fileContent);
				}else{
					return false;
				}
			}else{
				return false;
			}
		}

		//remove rotas existentes no array de route builder
		function removeFromRoutesBuilder($routeBuilderArray, $typeDotName){
			//remove rotas do array
			unset($routeBuilderArray[$typeDotName]);
			//return
			return $routeBuilderArray;
		}

		//save Routes Builderarray file
		function saveRoutesBuilder($routeBuilderArray, $routeBuilderFile){
			//build php route header
			$phpStringArray =
				"<?php".chr(13).chr(13).
				"return".chr(13)
				.chr(13)
				."["
				.chr(13);
			//for eac h module loaded
			foreach ($routeBuilderArray as $key => $value)
			{
				//adiciona a rota domodulo e como chave tipo-nome
				$phpStringArray .= '"'.$key.'" => "'.$value.'",'.chr(13).chr(13);
			}
			//fecha o arrayde modulos
			$phpStringArray .= "];";

			//salva arquivo por cima do conteudo do arquivo anterior
			return file_put_contents(
				$routeBuilderFile,
				str_replace(
					file_get_contents($routeBuilderFile),
					$phpStringArray,
					file_get_contents($routeBuilderFile)
				)
			);
		}

		//Escreve aquivo de rotas definitivo
		function buildRoutes($routeBuilderArray){
			//creia cabeçalho do arquivode rotas
			$routes = "<?php".chr(13).chr(13).
				"//This is a RoutesBuilder generated routes file"
				.chr(13).chr(13);

			//Faz um loop ema todos os itens do arquivo array do raouteBuilder
			foreach ( $routeBuilderArray as $module => $moduleRoute)
			{
				//Constroi rotas para o modulo
				$routes .= "//".$module./*diz de que modulo vieram as rotas*/
					$moduleRoute.chr(13).chr(13).chr(13).chr(13);/*Adiciona as rotas do modulo*/
			}

			//rota para o arquivo definitivo de rotas
			$routesFile = base_path().'/app/Http/routes.php';

			//substitui o conteudo do arquivo de rotas pelo novo conteudo
			return file_put_contents(
				$routesFile,
				str_replace(
					file_get_contents($routesFile),
					$routes,
					file_get_contents($routesFile)
				));
		}

		//Set Laravel Config
		function setLaravelConfig($config, $value){
			$path = explode('.',$config);
			if ($path != array()){
				$variable = "'".array_pop($path)."'";
				$path = base_path().'/config/'.implode("/", $path).'.php';
				if ($variable != '\'\'' && $path != base_path().'/'.'.php'){
					if (file_exists($path)){
						return setConfig($path, $variable, $value);
					}
				}
			}
			return false;
		}

		//Escreve aquivo de rollback
		function buildRollback($rollbackArray, $rollbackFile, $buildPHPHeader){
			if ($buildPHPHeader){
				//build php route header
				$phpStringArray =
					"<?php".chr(13).chr(13).
					"return".chr(13)
					.chr(13)
					."["
					.chr(13);
			}else{
				//Recursive fix
				$phpStringArray = "[".chr(13);
			}

			//for each module loaded
			foreach ($rollbackArray as $key => $value)
			{
				if (!is_array($value)){//Se value não for um array
					//adiciona a rota domodulo e como chave tipo-nome
					$phpStringArray .= '\''.$key.'\' => \''.$value.'\','.chr(13).chr(13);
				}else{//Se value for um array
					$phpStringArray .= '\''.$key.'\' => '.buildRollback($value, null, false).','.chr(13).chr(13);
				}
			}
			if ($buildPHPHeader){
				//fecha o array de rollback
				$phpStringArray .= "];";

				try{
					//salva arquivo por cima do conteudo do arquivo anterior
					return file_put_contents(
						$rollbackFile,
						str_replace(
							file_get_contents($rollbackFile),
							$phpStringArray,
							file_get_contents($rollbackFile)
						)
					);
				}catch(\Exception $e){
					return false;
				}
			}else{
				//fecha o array de rollback
				$phpStringArray .= "]".chr(13);

				return $phpStringArray;
			}
		}

        //Roda o rollback
        function execRollback($rollback, $handleContext)
        {
			$success = true;

            $handleContext->info("INFO: Executando Rollback");
            if ( !is_array($rollback) && file_exists($rollback)) {
                $rollback = getConfig($rollback);
				$handleContext->info("INFO: Transformando RollbackFile em Array.");
                if ($rollback == false) {
					$handleContext->info("ERRO: Nao Foi Possivel Ler o Arquivo de Rollback.");
                    $success = false;
                }
            }

			try{
				$handleContext->info("INFO: Verificando Se o BD de Migrations Existe.");
				if (!(count(\DB::select(\DB::raw("SHOW TABLES LIKE 'project_modules';")))>0)){
					$handleContext->info("ERRO: Arquivo de Controle de Migrations nao Existe.");
					$success = false;
				}
			}catch(Exception $e){
				$handleContext->info("ERRO: Arquivo de Controle de Migrations nao Existe.");
				$success = false;
			}

            if (is_array($rollback) && !empty($rollback)) {
				$handleContext->info("INFO: Rollback das Migrations.");
                if (array_key_exists("migration", $rollback)) {
					$handleContext->info("INFO: Verificando Se as Migrations Foram Rodadas.");
                    if ($rollback["migration"] == true) {
                        try{
							$handleContext->info("INFO: Executando Migration Rollback.");
							$handleContext->call("migrate:rollback");
							/////VERIFICAR SE MIGRATE RODOU DE FORMA ADEQUADA//////
							$handleContext->info("INFO: Executando Rollback do Arquivo de Rollback.");
							if (array_key_exists("LoadedModule", $rollback)) {
								if((count(\DB::table('project_modules')->where('module_name', $rollback["LoadedModule"])->first())>0)){
									$handleContext->comment("ERRO: Erro ao Rodar Rollback das Migrations.");
									//seta flag de erro para true
									$success = false;
								}
							}else{
								$handleContext->info("ERRO: Erro ao Capturar Nome do Modulo.");
								$success = false;
							}
							///////////////////////////////////////////////////////
						}catch(Exception $e){
							$handleContext->info("ERRO: Nao Foi Possivel Rodar o Rollback das Migrations.");
							$success = false;
						}
                    }
                }
				$handleContext->info("INFO: Rollback do RouteBuilder.");
                if (array_key_exists("routes-builder", $rollback)) {
					$handleContext->info("INFO: Executando RouteBuilder Rollback.");
                    $oldRoutesBuilder = html_entity_decode($rollback["routes-builder"], ENT_QUOTES, "UTF-8");
                    $routesBuilderFile = base_path()."/app/Modulos/RouteBuilder.php";
					$handleContext->info("INFO: Escrevendo Arquivo do RouteBuilder.");
                    if (file_put_contents($routesBuilderFile,
                                          str_replace(file_get_contents($routesBuilderFile),
                                                      $oldRoutesBuilder,
                                                      file_get_contents($routesBuilderFile)))
                            == false
                    ) {
						$handleContext->info("ERRO: Erro ao Escrever Arquivo de RouteBuilder.");
                        $success = false;
                    }

                }

				$handleContext->info("INFO: Rollback das Routes.");
				if (array_key_exists("old-routes", $rollback)) {
					$handleContext->info("INFO: Executando Routes Rollback.");
					//diretorio para o arquivo de rotas do modulo
					$routesPath = base_path().'/app/Http/routes.php';
					$oldRoutesBuilder = html_entity_decode($rollback["old-routes"], ENT_QUOTES, "UTF-8");
					$handleContext->info("INFO: Escrevendo Arquivo de Rotas.");
					if (file_put_contents($routesPath,
										  str_replace(file_get_contents($routesPath),
													  $oldRoutesBuilder,
													  file_get_contents($routesPath)))
						== false
					) {
						$handleContext->info("ERRO: Erro ao Escrever Arquivo de Rotas.");
						$success = false;
					}
				}

				$handleContext->info("INFO: Rollback dos Arquivos de Migration.");
                if (array_key_exists("module-migration-files", $rollback)) {
                    $counterFilesDeleted = 0;

					$handleContext->info("INFO: Deletando Migrations Copiadas.");
                    foreach($rollback["module-migration-files"] as $value){
                        $explodePath = explode("/", $value);
                        if(strtoupper($explodePath[count($explodePath)-1]) != strtoupper(".gitkeep")) {
                            if (unlink($value) != false) {
                                $counterFilesDeleted++;
                            } else {
								$handleContext->info("ERRO: Problemas ao Deletar Arquivos de Migration.");
                                $success = false;
                                break;
                            }
                        }
                    }
					$handleContext->info("INFO: Capturando Contador de Migrations.");
                    $migrationCounterBeforeRollback = getConfig(base_path()."/app/Modulos/configs.php", "migrationsCounter");
                    if ($migrationCounterBeforeRollback != false){
						$handleContext->info("INFO: Rollback do Contador de Migrations.");
                        if (setConfig(base_path()."/app/Modulos/configs.php", "migrationsCounter", $migrationCounterBeforeRollback - $counterFilesDeleted) == false){
							$handleContext->info("ERRO: Problemas ao Definir Configuracao do Contador de Migrations.");
                            $success = false;
                        }
                    }else{
						$handleContext->info("ERRO: Problemas ao Capturar Configuracao do Contador de Migrations.");
                        $success = false;
                    }

					$handleContext->info("INFO: Rollback dos Arquivos de Migration Removidos.");
                    if (array_key_exists("module-migration-deleted-files", $rollback)) {
						$handleContext->info("INFO: Restaurando Arquivos de Migration Deletados.");
                        foreach($rollback["module-migration-deleted-files"] as $path=>$fileContent){
                            $migration = fopen($path, "w");
                            if ($migration == false ||
                                    fwrite($migration, html_entity_decode($fileContent, ENT_QUOTES, "UTF-8")) == false ||
                                    fclose($migration) == false){
								$handleContext->info("ERRO: Erro ao Restaurar Arquivos de Migration Anteriores.");
                                $success = false;
                                break;
                            }
                        }
                    }
                }
            }

			$handleContext->info("INFO: Rollback dos Arquivos do Modulo (Views, Controllers, Models, CSS, etc).");
            if (array_key_exists("module-files", $rollback)) {
                foreach($rollback["module-files"] as $path=>$fileContent){
                    if($fileContent == ""){
                        $explodePath = explode("/", $path);
                        if(strtoupper($explodePath[count($explodePath)-1]) != strtoupper(".gitkeep")){
                            if(unlink($path) == false){
								$handleContext->info("ERRO: Erro ao Deletar Arquivos do Modulo.");
                                $success = false;
                                break;
                            }
                        }
                    }else{
                        if (file_put_contents($path, str_replace(file_get_contents($path), html_entity_decode($fileContent, ENT_QUOTES, "UTF-8"), file_get_contents($path))) == false) {
							$handleContext->info("ERRO: Erro ao Restaurar Arquivos de Modulo Substituidos.");
                            $success = false;
                            break;
                        }
                    }
                }
            }

			$handleContext->info("INFO: Rollback das Configuracoes Feitas Pelo Modulo.");
            if (array_key_exists("module-configs", $rollback)) {
                $revertedConfigs = array_reverse($rollback["module-configs"]);
                foreach($revertedConfigs as $configName=>$fileContent){
                    $path = explode('-',$configName);
                    if ($path != array()){
                        array_pop($path);
                        $path = base_path().'/config/'.implode("/", $path).'.php';
                        if (file_put_contents($path, str_replace(file_get_contents($path), html_entity_decode($fileContent[$path], ENT_QUOTES, "UTF-8"), file_get_contents($path))) == false) {
							$handleContext->info("ERRO: Erro ao Restaurar Arquivos de Configuracoes Feitas Pelo Modulo.");
                            $success = false;
                            break;
                        }
                    }else{
						$handleContext->info("ERRO: Rollback de Configuracao Invalida.");
                        $success = false;
                        break;
                    }
                }
            }

			$handleContext->info("INFO: Remove Modulo da Lista de Modulos Carregados.");
            if (array_key_exists("LoadedModule", $rollback)) {
                $loadedModules = getConfig(base_path()."/app/Modulos/configs.php", "modulosCarregados");
                if($loadedModules != false){
                    $explodedModules = explode(" & ", $loadedModules);
                    if(($key = array_search($rollback["LoadedModule"], $explodedModules)) !== false) {
                        unset($explodedModules[$key]);
                        $implodedNewModules = implode(" & ", $explodedModules);
                        if(setConfig(base_path()."/app/Modulos/configs.php", "modulosCarregados", $implodedNewModules) == false){
							$handleContext->info("ERRO: Erro ao Alterar Configuracao da Lista de Modulos Carregados.");
                            $success = false;
                        }
                    }else{
						$handleContext->info("ERRO: Modulo Não Encontrado na Lista de Modulos Carregados.");
                        $success = false;
                    }
                }else{
					$handleContext->info("ERRO: Problemas ao Capturar Configuracao da Lista de Modulos Carregados.");
                    $success = false;
                }
            }

			$handleContext->info("INFO: Rollback do Rollback do Modulo.");
			if (array_key_exists("old-rollback", $rollback)) {
				$handleContext->info("INFO: Executando Rollback do Arquivo de Rollback.");
				if (array_key_exists("LoadedModule", $rollback)) {
					$explodedModulePathNTitle = explode(".", $rollback["LoadedModule"]);
					if (is_array($explodedModulePathNTitle) && count($explodedModulePathNTitle) >= 2){
						//diretorio para o arquivo de rollback do modulo
						$rollbackFilePath = base_path().'/app/Modulos/'.$explodedModulePathNTitle[0]."/".$explodedModulePathNTitle[1]."/Rollback/rollback.php";
						$oldRollback = html_entity_decode($rollback["old-rollback"], ENT_QUOTES, "UTF-8");
						$handleContext->info("INFO: Escrevendo Arquivo de Rollback.");
						if (file_put_contents($rollbackFilePath,
											  str_replace(file_get_contents($rollbackFilePath),
														  $oldRollback,
														  file_get_contents($rollbackFilePath)))
							== false
						) {
							$handleContext->info("ERRO: Erro ao Escrever Arquivo de Rollback.");
							$success = false;
						}
					}else{
						$handleContext->info("ERRO: Erro o Nome do Modulo Nao é um Array.");
						$success = false;
					}
				}else{
					$handleContext->info("ERRO: Erro ao Capturar Nome do Modulo.");
					$success = false;
				}
			}

			$handleContext->info("INFO: Removendo Pastas Criadas.");
			if (array_key_exists("dir-created", $rollback)) {
				$createdDirs = $rollback["dir-created"];
				if(is_array($createdDirs)){
					foreach($createdDirs as $dir){
						if(!deleteDirectory($dir)){
							$handleContext->info("ERRO: Erro ao Deletar os Diretorios Criados.");
							$success = false;
							break;
						}
					}
				}
			}

            if ($success){
                $handleContext->info("INFO: Rollback Efetuado com Sucesso.");
                return true;
            }else{
                $handleContext->info("ERRO: Erro ao Executar o Rollback.");
                return false;
            }
        }

		//		function copy($source, $dest, $rollback, $permissions = 0755)
		//		{
		//			// Check for symlinks
		//			if (is_link($source)) {
		//				return symlink(readlink($source), $dest);
		//			}
		//
		//			// Simple copy for a file
		//			if (is_file($source)) {
		//				return copy($source, $dest);
		//			}
		//
		//			// Make destination directory
		//			if (!is_dir($dest)) {
		//				if (mkdir($dest, $permissions) == false){
		//					//Cria registro no rollback dizendo uma pasta foi criada
		//					$rollback["dir-created"][] = $dest;
		//				}
		//			}
		//
		//			// Loop through the folder
		//			$dir = dir($source);
		//			while (false !== $entry = $dir->read()) {
		//				// Skip pointers
		//				if ($entry == '.' || $entry == '..') {
		//					continue;
		//				}
		//
		//				// Deep copy directories
		//				copy("$source/$entry", "$dest/$entry", $rollback, $permissions);
		//			}
		//
		//			// Clean up
		//			$dir->close();
		//			return true;
		//		}

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
		$oldLoadedModules = getConfig($moduleGeneralConfig, "modulosCarregados");

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
			$explodedLoadedTypes = explodeTypes($explodedLoadedModules);

			//Conflitos de modulo
			$conflitos = getConfig($configPath, "conflitos");
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
				$dependencias = getConfig($configPath, "dependencias");
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
								if (setConfig($moduleGeneralConfig, "modulosCarregados",$newLoadedModules) == false){
									$success = false;
								}
							}
							////////////////////////////////////////////////////////////////////////////////////////////////

							//////////////////////////////////////Configurações/////////////////////////////////////////////
							if ($success){//Se os comandos anteriores rodarem com sucesso
								$this->comment("INFO: Alterando configuracoes.");

								//Pega configurações
								$configuracoes = getConfig( $configPath , "configuracoes" );

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
										if ( setLaravelConfig( $configuracao , $valor ) == false )
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
											$migrationCounter = getConfig($migrationConfigPath, "migrationsCounter");
											setConfig($migrationConfigPath, "migrationsCounter", $migrationCounter+1);
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
												$migrationCounter = getConfig($migrationConfigPath, "migrationsCounter");
												//Atualiza o contador de migrations
												setConfig($migrationConfigPath, "migrationsCounter", $migrationCounter+1);

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
								$routeBuilder = getRoutesBuilder($routesBuilderPath);
								//verifica se foi construido um array valido
								if ($routeBuilder !== false){
									//inclui as novas rotas ao array do routeBuilder
									$routeBuilder = includeToRoutesBuilder($routeBuilder, $routesPath);
									//verifica se o array de rotas continua válido
									if ($routeBuilder !== false){
										//tenta salvar o novo array do routesBuilder
										if (saveRoutesBuilder($routeBuilder, $routesBuilderPath) != false){
											//Cria registro no rollback dizendo que o arquivo foi copiado
											$rollback["old-routes"] = htmlentities(file_get_contents($universalRoutesPath), ENT_QUOTES, "UTF-8");
											//tenta construir o arquivo de rotas gera baseado no array savo do routesBuilder
											if (buildRoutes($routeBuilder) === false){
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
								if (buildRollback($rollback, $moduleRollbackFile, true) == false){
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
									getConfig($configPath,"versao"));
							}else{//Se ocorrer erro ao rodar os comandos
								if ($abort == false){//Se Não abortou
									//Comentario comando executado com erro
									$this->comment(
										'ERRO: Erro ao executar o comando em '.
										$moduleType.
										'.'.
										$moduleName.
										' '.
										getConfig($configPath,"versao"));
								}else{//Se abortou
									//Comentario comando executado com erro
									$this->comment(
										'ABORT: O comando foi abortado '.
										$moduleType.
										'.'.
										$moduleName.
										' '.
										getConfig($configPath,"versao"));
								}
								/////////////////////////////////////ARQUIVO DE ROLLBACK////////////////////////////////////////
								execRollback($rollback, $this);
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
