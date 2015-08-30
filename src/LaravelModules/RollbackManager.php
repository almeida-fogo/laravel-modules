<?php

namespace AlmeidaFogo\LaravelModules\LaravelModules;

use Illuminate\Console\Command;

class RollbackManager {

	/**
	 * Escreve arquivo de rollback do modulo
	 *
	 * @param array $rollbackArray
	 * @param string $rollbackFile
	 * @param bool $buildPHPHeader
	 * @return bool|int|string
	 */
	public static function buildRollback(array $rollbackArray, $rollbackFile, $buildPHPHeader = true){
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
				$phpStringArray .= '\''.$key.'\' => '.self::buildRollback($value, null, false).','.chr(13).chr(13);
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

	/**
	 * Executa o rollback
	 *
	 * @param mixed $rollback
	 * @param Command $handleContext
	 * @return bool
	 */
	public static function execRollback($rollback, Command $handleContext)
	{
		//TODO: Refatorar esse codigo

		$success = true;

		if ( !is_array($rollback) && file_exists($rollback)) {
			$handleContext->info("INFO: Executando Rollback");
			$rollback = Configs::getConfig($rollback);
			$handleContext->info("INFO: Transformando RollbackFile em Array.");
			if ($rollback == false) {
				$handleContext->info("ERRO: Nao Foi Possivel Ler o Arquivo de Rollback.");
				$success = false;
			}
		}

		try{
			if (!(count(\DB::select(\DB::raw("SHOW TABLES LIKE 'project_modules';")))>0)){
				$handleContext->info("INFO: Verificando Se o BD de Migrations Existe.");
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
			if (array_key_exists("routes-builder", $rollback)) {
				$handleContext->info("INFO: Rollback do RouteBuilder.");
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

			if (array_key_exists("old-routes", $rollback)) {
				$handleContext->info("INFO: Rollback das Routes.");
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

			if (array_key_exists("module-migration-files", $rollback)) {
				$handleContext->info("INFO: Rollback dos Arquivos de Migration.");
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
				$migrationCounterBeforeRollback = Configs::getConfig(base_path()."/app/Modulos/configs.php", "migrationsCounter");
				if ($migrationCounterBeforeRollback != false){
					$handleContext->info("INFO: Rollback do Contador de Migrations.");
					if (Configs::setConfig(base_path()."/app/Modulos/configs.php", "migrationsCounter", $migrationCounterBeforeRollback - $counterFilesDeleted) == false){
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

		if (array_key_exists("module-files", $rollback)) {
			$handleContext->info("INFO: Rollback dos Arquivos do Modulo (Views, Controllers, Models, CSS, etc).");
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

		if (array_key_exists("module-configs", $rollback)) {
			$handleContext->info("INFO: Rollback das Configuracoes Feitas Pelo Modulo.");
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

		if (array_key_exists("LoadedModule", $rollback)) {
			$handleContext->info("INFO: Remove Modulo da Lista de Modulos Carregados.");
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

		if (array_key_exists("old-rollback", $rollback)) {
			$handleContext->info("INFO: Rollback do Rollback do Modulo.");
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

		if (array_key_exists("dir-created", $rollback)) {
			$handleContext->info("INFO: Removendo Pastas Criadas.");
			$createdDirs = $rollback["dir-created"];
			if(is_array($createdDirs)){
				foreach($createdDirs as $dir){
					if(!FileManager::deleteDirectory($dir)){
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

}