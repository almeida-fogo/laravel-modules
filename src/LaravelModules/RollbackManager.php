<?php

namespace AlmeidaFogo\LaravelModules\LaravelModules;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Mockery\Exception;


class RollbackManager {

    public static $errors;

	/**
	 * Metodo de atalho para execução padronizada dos metodos dessa classse
	 *
	 * @param bool $startCondition
	 * @param callable $rollbackMethod
	 * @param callable $afterCallBack
	 * @param Command $command
	 * @param string $message
	 */
	public static function executeRollbackMethod(
			$startCondition = true,
			Callable $rollbackMethod,
			Callable $afterCallBack = null,
			Command $command = null,
			$message = null
	){
		if ($startCondition && $rollbackMethod != null){
			if ($command != null && $message != null){
				$command->info($message);
			}

			$result = $rollbackMethod();

			if ($afterCallBack != null){
				$afterCallBack($result);
			}
		}
	}

    /**
     * Transforma um arquivo de Rollback para um array de Rollback caso seja necessário
     *
     * @param array $rollback
     * @return array|bool
     */
    private static function transformRollbackFileToRollbackArrayIfNeeded($rollback){
        $errors = [ ];

        if ( !is_array($rollback) && file_exists($rollback)) {
            $rollback = Configs::getConfig($rollback);
            if ($rollback == false) {
				$errors[] = Strings::ERROR_CANT_CONVERT_ARRAY_TO_ROLLBACK_FILE;
            }
        }

        return !empty($errors) ? $errors : true;
    }

    /**
     * Verifica se a migration do modulo existe na table de modulos carregados
     *
     * @return array|bool
     */
    private static function verifyIfMigrationsControllDbExists(){
        $errors = [ ];

        try{
            if (!(count(DB::select(DB::raw("SHOW TABLES LIKE '".Strings::TABLE_PROJECT_MODULES."';")))>0)){
                $errors[] = Strings::ERROR_MIGRATIONS_CONTROLL_DB_DOESNT_EXISTS;
            }
        }catch(Exception $e){
            $errors[] = Strings::ERROR_DATABASE_CONECTION;
        }

        return !empty($errors) ? $errors : true;
    }

    /**
     * Executa rollback das migrations do modulo
     *
     * @param array $rollback
     * @param Command $command
     * @return array|bool
     */
    private static function runMigrationRollback(array $rollback, Command $command){
        $errors = [ ];

        if (array_key_exists(Strings::ROLLBACK_MIGRATE, $rollback)) {
            if ($rollback[Strings::ROLLBACK_MIGRATE] == true) {
                try{
                    $command->call(Strings::COMMAND_ROLLBACK);
                    if (array_key_exists(Strings::ROLLBACK_LOADED_MODULE_TAG, $rollback)) {
                        if((count(DB::table(Strings::TABLE_PROJECT_MODULES)->where(Strings::TABLE_PROJECT_MODULES_NAME, $rollback[Strings::ROLLBACK_LOADED_MODULE_TAG])->first())>0)){
                            $errors[] = Strings::ERROR_MIGRATE_ROLLBACK;
                        }
                    }else{
                        $errors[] = Strings::ERROR_GET_MODULE_NAME_FROM_DB;
                    }
                }catch(Exception $e){
                    $errors[] = Strings::ERROR_DATABASE_CONECTION;
                }
            }
        }

        return !empty($errors) ? $errors : true;
    }

	/**
	 * Executa rollback das rotas do modulo
	 *
	 * @param array $rollback
	 * @return array|bool
	 */
	private static function runRoutesRollback(array $rollback){
		$errors = [ ];

		if (array_key_exists(Strings::ROLLBACK_OLD_ROUTES_TAG, $rollback)) {
			if (file_put_contents(PathHelper::getLaravelRoutesPath(),
				str_replace(file_get_contents(PathHelper::getLaravelRoutesPath()),
				EscapeHelper::decode($rollback[Strings::ROLLBACK_OLD_ROUTES_TAG]),
				file_get_contents(PathHelper::getLaravelRoutesPath())))
				== false
			) {
				$errors[ ] = Strings::ERROR_WRITE_ROUTES_FILE;
			}
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * Executa rollback dos arquivos de migration do modulo
	 *
	 * @param array $rollback
	 * @param int $counterMigrationFilesDeleted
	 * @return array|bool
	 */
	private static function runMigrationFilesRollback(array $rollback, &$counterMigrationFilesDeleted)
	{
		$errors = [ ];

		if ( array_key_exists( Strings::ROLLBACK_MODULE_MIGRATION_FILE_TAG , $rollback ) )
		{
			$counterMigrationFilesDeleted = 0;

			foreach ( $rollback[ Strings::ROLLBACK_MODULE_MIGRATION_FILE_TAG ] as $value )
			{
				$explodePath = explode( Strings::PATH_SEPARATOR , $value );
				if ( strtoupper( $explodePath[ count( $explodePath ) - 1 ] ) != strtoupper(Strings::GIT_KEEP_FILE_NAME) )
				{
					if ( unlink( $value ) != false )
					{
						$counterMigrationFilesDeleted++;
					}
					else
					{
						$errors[] = Strings::ERROR_REMOVING_MIGRATION_FILES;
						break;
					}
				}
			}
		}

		return !empty($errors) ? $errors : true;
	}


	/**
     * Faz o rollback do arquivo de Routebuilder
     *
     * @param array $rollback
     * @return array|bool
     */
    private static function routeBuilderFileRollback($rollback){
        $errors = [ ];

        if (array_key_exists(Strings::ROLLBACK_ROUTES_BUILDER_TAG, $rollback)) {

            $oldRoutesBuilder = EscapeHelper::decode($rollback[Strings::ROLLBACK_ROUTES_BUILDER_TAG]);

            if (file_put_contents(PathHelper::getRouteBuilderPath(),
                                  str_replace(file_get_contents(PathHelper::getRouteBuilderPath()),
                                              $oldRoutesBuilder,
                                              file_get_contents(PathHelper::getRouteBuilderPath())))
                    == false
            ) {
                $errors[] = Strings::ERROR_WRITE_ROUTEBUILDER_FILE;
            }

        }

        return !empty($errors) ? $errors : true;
    }

	/**
	 * Faz o rollback no contador de migrations
	 *
	 * @param array $rollback
	 * @param int $counterMigrationFilesDeleted
	 * @return array|bool
	 */
	private static function runMigrationCounterRollback($rollback, &$counterMigrationFilesDeleted){
		$errors = [ ];

		if (is_array($rollback) && !empty($rollback)) {
			if (array_key_exists(Strings::ROLLBACK_MODULE_MIGRATION_FILE_TAG, $rollback))
			{

				$migrationCounterBeforeRollback = Configs::getConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_MIGRATIONS_COUNTER);
				if ($migrationCounterBeforeRollback != false){
					if (Configs::setConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_MIGRATIONS_COUNTER, $migrationCounterBeforeRollback - $counterMigrationFilesDeleted) == false){
						$errors[ ] = Strings::ERROR_REDEFINING_MIGRATIONS_COUNTER;
					}
				}else{
					$errors[ ] = Strings::ERROR_GETTING_MIGRATIONS_COUNTER_CONFIG;
				}
			}
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * Faz o rollback dos arquivo antigosd das migrations
	 *
	 * @param array $rollback
	 * @return array|bool
	 */
	private static function runMigrationOldFilesRollback($rollback)
	{
		$errors = [ ];

		if ( is_array( $rollback ) && !empty( $rollback ) )
		{
			if ( array_key_exists( Strings::ROLLBACK_MODULE_MIGRATION_DELETED_FILE_TAG , $rollback ) )
			{
				foreach ( $rollback[ Strings::ROLLBACK_MODULE_MIGRATION_DELETED_FILE_TAG ] as $path => $fileContent )
				{
					$migration = fopen( $path , Strings::WRITE_FILE_TAG );
					if ( $migration == false || fwrite( $migration ,
														EscapeHelper::decode( $fileContent ) ) == false || fclose( $migration ) == false
					)
					{
						$errors[ ] = Strings::ERROR_ROLLBACK_OLD_MIGRATION_FILES;
					}
				}
			}
		}

		return !empty($errors) ? $errors : true;
	}


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
			}catch(Exception $e){
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
	 * @param Command $command
	 * @return bool
	 */
	public static function execRollback($rollback, Command $command)
	{
		self::$errors = [ ];

		$counterMigrationFilesDeleted = 0;

		//////////////////////////////////////////////TRANSFORM ROLLBACK FILE TO ARRAY//////////////////////////////////
		self::executeRollbackMethod(empty(self::$errors), function()use($rollback){
			return self::transformRollbackFileToRollbackArrayIfNeeded
			(
					$rollback
			);},
				function($result){if ($result !== true){self::$errors = array_merge( self::$errors , $result );}},
				$command, Strings::STATUS_READING_ROLLBACK_FILE
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////////////////////CHECK IF MIGRATION DATABASE CONTROLL EXISTS///////////////////////
		self::executeRollbackMethod(empty(self::$errors), function(){
			return self::verifyIfMigrationsControllDbExists
			(

			);},
				function($result){if ($result !== true){self::$errors = array_merge( self::$errors , $result );}},
				$command, Strings::STATUS_IF_MIGRATIONS_CONTROLL_DB_EXISTS
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        //////////////////////////////////////////////RUN MIGRATION ROLLBACK////////////////////////////////////////////
        self::executeRollbackMethod(empty(self::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback, $command){
            return self::runMigrationRollback
            (
                    $rollback, $command
            );},
                function($result){if ($result !== true){self::$errors = array_merge( self::$errors , $result );}},
                $command, Strings::STATUS_RUNNING_MIGRATE_ROLLBACK
        );
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        //////////////////////////////////////////////RUNNING ROUTEBUILDER FILE ROLLBACK////////////////////////////////
        self::executeRollbackMethod(empty(self::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
            return self::routeBuilderFileRollback
            (
                    $rollback
            );},
                function($result){if ($result !== true){self::$errors = array_merge( self::$errors , $result );}},
                $command, Strings::STATUS_RUNNING_ROUTEBUILDER_ROLLBACK
        );
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////////////////////ROLLBACK DO ARQUIVO DE ROTAS//////////////////////////////////////
		self::executeRollbackMethod(empty(self::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
			return self::runRoutesRollback
			(
				$rollback
			);},
			function($result){if ($result !== true){self::$errors = array_merge( self::$errors , $result );}},
			$command, Strings::STATUS_RUNNING_ROUTES_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		///////////////////////////////////////////ROLLBACK DOS ARQUIVOS DE MIGRATION///////////////////////////////////
		self::executeRollbackMethod(empty(self::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback, $counterMigrationFilesDeleted){
			return self::runMigrationFilesRollback
			(
				$rollback,
				$counterMigrationFilesDeleted
			);},
			function($result){if ($result !== true){self::$errors = array_merge( self::$errors , $result );}},
			$command, Strings::STATUS_RUNNING_MIGRATION_FILES_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		///////////////////////////////////////////ROLLBACK DOS ARQUIVOS DE MIGRATION///////////////////////////////////
		self::executeRollbackMethod(empty(self::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback, $counterMigrationFilesDeleted){
			return self::runMigrationCounterRollback
			(
				$rollback,
				$counterMigrationFilesDeleted
			);},
			function($result){if ($result !== true){self::$errors = array_merge( self::$errors , $result );}},
			$command, Strings::STATUS_RUNNING_MIGRATION_COUNTER_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		/////////////////////////////////////ROLLBACK DOS ARQUIVOS ANTIGOS DE MIGRATION/////////////////////////////////
		self::executeRollbackMethod(empty(self::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
			return self::runMigrationOldFilesRollback
			(
				$rollback
			);},
			function($result){if ($result !== true){self::$errors = array_merge( self::$errors , $result );}},
			$command, Strings::STATUS_RUNNING_MIGRATION_OLD_FILES_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		if (array_key_exists("module-files", $rollback)) {
			$command->info("INFO: Rollback dos Arquivos do Modulo (Views, Controllers, Models, CSS, etc).");
			foreach($rollback["module-files"] as $path=>$fileContent){
				if($fileContent == ""){
					$explodePath = explode("/", $path);
					if(strtoupper($explodePath[count($explodePath)-1]) != strtoupper(".gitkeep")){
						if(unlink($path) == false){
							$command->info("ERRO: Erro ao Deletar Arquivos do Modulo.");
							$success = false;
							break;
						}
					}
				}else{
					if (file_put_contents($path, str_replace(file_get_contents($path), html_entity_decode($fileContent, ENT_QUOTES, "UTF-8"), file_get_contents($path))) == false) {
						$command->info("ERRO: Erro ao Restaurar Arquivos de Modulo Substituidos.");
						$success = false;
						break;
					}
				}
			}
		}

		if (array_key_exists("module-configs", $rollback)) {
			$command->info("INFO: Rollback das Configuracoes Feitas Pelo Modulo.");
			$revertedConfigs = array_reverse($rollback["module-configs"]);
			foreach($revertedConfigs as $configName=>$fileContent){
				$path = explode('-',$configName);
				if ($path != array()){
					array_pop($path);
					$path = base_path().'/config/'.implode("/", $path).'.php';
					if (file_put_contents($path, str_replace(file_get_contents($path), html_entity_decode($fileContent[$path], ENT_QUOTES, "UTF-8"), file_get_contents($path))) == false) {
						$command->info("ERRO: Erro ao Restaurar Arquivos de Configuracoes Feitas Pelo Modulo.");
						$success = false;
						break;
					}
				}else{
					$command->info("ERRO: Rollback de Configuracao Invalida.");
					$success = false;
					break;
				}
			}
		}

		if (array_key_exists("LoadedModule", $rollback)) {
			$command->info("INFO: Remove Modulo da Lista de Modulos Carregados.");
			$loadedModules = getConfig(base_path()."/app/Modulos/configs.php", "modulosCarregados");
			if($loadedModules != false){
				$explodedModules = explode(" & ", $loadedModules);
				if(($key = array_search($rollback["LoadedModule"], $explodedModules)) !== false) {
					unset($explodedModules[$key]);
					$implodedNewModules = implode(" & ", $explodedModules);
					if(setConfig(base_path()."/app/Modulos/configs.php", "modulosCarregados", $implodedNewModules) == false){
						$command->info("ERRO: Erro ao Alterar Configuracao da Lista de Modulos Carregados.");
						$success = false;
					}
				}else{
					$command->info("ERRO: Modulo Não Encontrado na Lista de Modulos Carregados.");
					$success = false;
				}
			}else{
				$command->info("ERRO: Problemas ao Capturar Configuracao da Lista de Modulos Carregados.");
				$success = false;
			}
		}

		if (array_key_exists("old-rollback", $rollback)) {
			$command->info("INFO: Rollback do Rollback do Modulo.");
			$command->info("INFO: Executando Rollback do Arquivo de Rollback.");
			if (array_key_exists("LoadedModule", $rollback)) {
				$explodedModulePathNTitle = explode(".", $rollback["LoadedModule"]);
				if (is_array($explodedModulePathNTitle) && count($explodedModulePathNTitle) >= 2){
					//diretorio para o arquivo de rollback do modulo
					$rollbackFilePath = base_path().'/app/Modulos/'.$explodedModulePathNTitle[0]."/".$explodedModulePathNTitle[1]."/Rollback/rollback.php";
					$oldRollback = html_entity_decode($rollback["old-rollback"], ENT_QUOTES, "UTF-8");
					$command->info("INFO: Escrevendo Arquivo de Rollback.");
					if (file_put_contents($rollbackFilePath,
										  str_replace(file_get_contents($rollbackFilePath),
													  $oldRollback,
													  file_get_contents($rollbackFilePath)))
						== false
					) {
						$command->info("ERRO: Erro ao Escrever Arquivo de Rollback.");
						$success = false;
					}
				}else{
					$command->info("ERRO: Erro o Nome do Modulo Nao é um Array.");
					$success = false;
				}
			}else{
				$command->info("ERRO: Erro ao Capturar Nome do Modulo.");
				$success = false;
			}
		}

		if (array_key_exists("dir-created", $rollback)) {
			$command->info("INFO: Removendo Pastas Criadas.");
			$createdDirs = $rollback["dir-created"];
			if(is_array($createdDirs)){
				foreach($createdDirs as $dir){
					if(!FileManager::deleteDirectory($dir)){
						$command->info("ERRO: Erro ao Deletar os Diretorios Criados.");
						$success = false;
						break;
					}
				}
			}
		}

		if ($success){
			$command->info("INFO: Rollback Efetuado com Sucesso.");
			return true;
		}else{
			$command->info("ERRO: Erro ao Executar o Rollback.");
			return false;
		}
	}

}