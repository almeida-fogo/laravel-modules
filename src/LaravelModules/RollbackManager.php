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
    private static function transformRollbackFileToRollbackArrayIfNeeded(&$rollback){
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
	 * Faz o rollback dos arquivos antigos das migrations
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
	 * Faz o rollback dos arquivos do modulo
	 *
	 * @param array $rollback
	 * @return array|bool
	 */
	private static function runModuleFilesRollback($rollback)
	{
		$errors = [ ];

		if (array_key_exists(Strings::ROLLBACK_ORDINARY_FILE_COPY_TAG, $rollback)) {
			foreach($rollback[Strings::ROLLBACK_ORDINARY_FILE_COPY_TAG] as $path=>$fileContent){
				if($fileContent == Strings::EMPTY_STRING){
					$explodePath = explode(Strings::PATH_SEPARATOR, $path);
					if(strtoupper($explodePath[count($explodePath)-1]) != strtoupper(Strings::GIT_KEEP_FILE_NAME)){
						if(unlink($path) == false){
							$errors[] = Strings::ERROR_MODULE_FILES_ROLLBACK;
						}
					}
				}else{
					if (file_put_contents($path, str_replace(file_get_contents($path), EscapeHelper::decode($fileContent), file_get_contents($path))) == false) {
						$errors[] = Strings::ERROR_MODULE_FILES_REPLACE;
					}
				}
			}
		}


		return !empty($errors) ? $errors : true;
	}

	/**
	 * Faz o rollback das configurações feitas pelo modulo
	 *
	 * @param array $rollback
	 * @return array|bool
	 */
	private static function runModuleConfigsRollback($rollback)
	{
		$errors = [ ];

		if (array_key_exists(Strings::ROLLBACK_MODULE_CONFIGS_TAG, $rollback)) {
			$revertedConfigs = array_reverse($rollback[Strings::ROLLBACK_MODULE_CONFIGS_TAG]);
			foreach($revertedConfigs as $configName=>$fileContent){
				$path = explode(Strings::MODULE_CONFIG_CONFIGS_SEPARATOR_REPLACEMENT,$configName);
				if ($path != array()){
					array_pop($path);
					$path = PathHelper::getConfigDir(implode(Strings::PATH_SEPARATOR, $path).Strings::PHP_EXTENSION);
					if (file_put_contents($path, str_replace(file_get_contents($path), EscapeHelper::decode($fileContent[$path]), file_get_contents($path))) == false) {
						$errors[] = Strings::ERROR_MODULE_CONFIG_ROLLBACK;
					}
				}else{
					$errors[] = Strings::ERROR_INVALID_CONFIG_ROLLBACK;
				}
			}
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * Remove o modulo da lista de modulos carregados
	 *
	 * @param array $rollback
	 * @return array|bool
	 */
	private static function moduleUnregister($rollback)
	{
		$errors = [ ];

		if (array_key_exists(Strings::ROLLBACK_LOADED_MODULE_TAG, $rollback)) {
			$loadedModules = Configs::getConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_LOADED_MODULES);
			if($loadedModules != false){
				$explodedModules = explode(Strings::MODULE_SEPARATOR, $loadedModules);
				if(($key = array_search($rollback[Strings::ROLLBACK_LOADED_MODULE_TAG], $explodedModules)) !== false) {
					unset($explodedModules[$key]);
					$implodedNewModules = implode(Strings::MODULE_SEPARATOR, $explodedModules);
					if(Configs::setConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_LOADED_MODULES, $implodedNewModules) == false){
						$errors[] = Strings::ERROR_ALTER_CONFIG_ROLLBACK;
					}
				}
			}else{
				$errors[] = Strings::ERROR_MODULE_LIST_GET;
			}
		}

		return !empty($errors) ? $errors : true;
	}
	
	/**
	 * Faz rollback do arquivo de rollback do modulo
	 *
	 * @param array $rollback
	 * @return array|bool
	 */
	private static function runModuleRollbackFileRollback($rollback)
	{
		$errors = [ ];

		if (array_key_exists(Strings::ROLLBACK_OLD_ROLLBACK_TAG, $rollback)) {
			if (array_key_exists(Strings::ROLLBACK_LOADED_MODULE_TAG, $rollback) == false && array_key_exists(Strings::ROLLBACK_REFRESH_MODULE_NAME, $rollback) == false) {
				$errors[] = Strings::ERROR_GET_MODULE_NAME;
			}else{
				if (array_key_exists(Strings::ROLLBACK_LOADED_MODULE_TAG, $rollback)){
					$explodedModulePathNTitle = explode(Strings::MODULE_TYPE_NAME_SEPARATOR, $rollback[Strings::ROLLBACK_LOADED_MODULE_TAG]);
					var_dump("normal rollback");
				}else{
					$explodedModulePathNTitle = explode(Strings::MODULE_TYPE_NAME_SEPARATOR, $rollback[Strings::ROLLBACK_REFRESH_MODULE_NAME]);
					var_dump("soft rollback");
				}
				var_dump($explodedModulePathNTitle);
				if (is_array($explodedModulePathNTitle) && count($explodedModulePathNTitle) >= 2){
					$oldRollback = EscapeHelper::decode($rollback[Strings::ROLLBACK_OLD_ROLLBACK_TAG]);
					if (file_put_contents(PathHelper::getModuleRollbackFile($explodedModulePathNTitle[0], $explodedModulePathNTitle[1]),
							str_replace(file_get_contents(PathHelper::getModuleRollbackFile($explodedModulePathNTitle[0], $explodedModulePathNTitle[1])),
								$oldRollback,
								file_get_contents(PathHelper::getModuleRollbackFile($explodedModulePathNTitle[0], $explodedModulePathNTitle[1]))))
						== false
					) {
						$errors[] = Strings::ERROR_WRITE_ROLLBACK_FILE;
					}
				}else{
					$errors[] = Strings::ERROR_INVALID_MODULE_NAME;
				}
			}
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * Faz rollback do arquivo de rollback do modulo
	 *
	 * @param array $rollback
	 * @return array|bool
	 */
	private static function removeModuleFolders($rollback)
	{
		$errors = [ ];

		if (array_key_exists(Strings::ROLLBACK_DIR_CREATED_TAG, $rollback)) {
			$createdDirs = $rollback[Strings::ROLLBACK_DIR_CREATED_TAG];
			if(is_array($createdDirs)){
				foreach($createdDirs as $dir){
					if(!FileManager::deleteDirectory($dir)){
						$errors[] = Strings::ERROR_ROLLBACK_MODULE_DIRS;
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
				$phpStringArray .= '\''.$key.'\' => '.RollbackManager::buildRollback($value, null, false).','.chr(13).chr(13);
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
	 * Faz rollback do arquivo de rollback do modulo
	 *
	 * @param array $rollback
	 * @return array|bool
	 */
	public static function execHardRollback($moduleType, $moduleName, Command $command)
	{
		$rollback = PathHelper::getModuleRollbackFile($moduleType,$moduleName);

		RollbackManager::transformRollbackFileToRollbackArrayIfNeeded($rollback);

		while(!empty($rollback)){
			$errors = [ ];

			$rollback = PathHelper::getModuleRollbackFile($moduleType,$moduleName);

			RollbackManager::transformRollbackFileToRollbackArrayIfNeeded($rollback);

			if(!RollbackManager::execSoftRollback($rollback, $command)){
				$errors[] = Strings::ERROR_EXEC_HARD_ROLLBACK;
				break;
			}

			RollbackManager::transformRollbackFileToRollbackArrayIfNeeded($rollback);
		}

		return !empty($errors) ? $errors : true;
	}


	/**
	 * Executa o soft rollback
	 *
	 * @param mixed $rollback
	 * @param Command $command
	 * @return bool
	 */
	public static function execSoftRollback($rollback, Command $command)
	{
		RollbackManager::$errors = [ ];

		$counterMigrationFilesDeleted = 0;

		//////////////////////////////////////////////TRANSFORM ROLLBACK FILE TO ARRAY//////////////////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors), function()use(&$rollback){
			return RollbackManager::transformRollbackFileToRollbackArrayIfNeeded
			(
					$rollback
			);},
				function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
				$command, Strings::STATUS_READING_ROLLBACK_FILE
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////////////////////CHECK IF MIGRATION DATABASE CONTROLL EXISTS///////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors), function(){
			return RollbackManager::verifyIfMigrationsControllDbExists
			(

			);},
				function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
				$command, Strings::STATUS_IF_MIGRATIONS_CONTROLL_DB_EXISTS
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        //////////////////////////////////////////////RUN MIGRATION ROLLBACK////////////////////////////////////////////
        RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback, $command){
            return RollbackManager::runMigrationRollback
            (
                    $rollback, $command
            );},
                function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
                $command, Strings::STATUS_RUNNING_MIGRATE_ROLLBACK
        );
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

        //////////////////////////////////////////////RUNNING ROUTEBUILDER FILE ROLLBACK////////////////////////////////
        RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
            return RollbackManager::routeBuilderFileRollback
            (
                    $rollback
            );},
                function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
                $command, Strings::STATUS_RUNNING_ROUTEBUILDER_ROLLBACK
        );
        ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		//////////////////////////////////////////////ROLLBACK DO ARQUIVO DE ROTAS//////////////////////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
			return RollbackManager::runRoutesRollback
			(
				$rollback
			);},
			function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
			$command, Strings::STATUS_RUNNING_ROUTES_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		///////////////////////////////////////////ROLLBACK DOS ARQUIVOS DE MIGRATION///////////////////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback, &$counterMigrationFilesDeleted){
			return RollbackManager::runMigrationFilesRollback
			(
				$rollback,
				$counterMigrationFilesDeleted
			);},
			function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
			$command, Strings::STATUS_RUNNING_MIGRATION_FILES_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		///////////////////////////////////////////ROLLBACK DO CONTADOR DE MIGRATION////////////////////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback, &$counterMigrationFilesDeleted){
			return RollbackManager::runMigrationCounterRollback
			(
				$rollback,
				$counterMigrationFilesDeleted
			);},
			function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
			$command, Strings::STATUS_RUNNING_MIGRATION_COUNTER_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		/////////////////////////////////////ROLLBACK DOS ARQUIVOS ANTIGOS DE MIGRATION/////////////////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
			return RollbackManager::runMigrationOldFilesRollback
			(
				$rollback
			);},
			function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
			$command, Strings::STATUS_RUNNING_MIGRATION_OLD_FILES_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		/////////////////////////////////////ROLLBACK DOS ARQUIVOS DO MODULEO///////////////////////////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
			return RollbackManager::runModuleFilesRollback
			(
				$rollback
			);},
			function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
		   $command, Strings::STATUS_MODULE_FILE_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		/////////////////////////////////////ROLLBACK DOS CONFIGURAÇÕES DO MODULO///////////////////////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
			return RollbackManager::runModuleConfigsRollback
			(
				$rollback
			);},
			function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
		   $command, Strings::STATUS_MODULE_CONFIG_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		/////////////////////////////////////ROLLBACK DO REGISTRO DO MODULO/////////////////////////////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
			return RollbackManager::moduleUnregister
			(
				$rollback
			);},
			function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
		   $command, Strings::STATUS_MODULE_UNREGISTER
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		/////////////////////////////////////ROLLBACK DO ARQUIVO DE ROLLBACK DO MODULO//////////////////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
			return RollbackManager::runModuleRollbackFileRollback
			(
				$rollback
			);},
			function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
		   $command, Strings::STATUS_MODULE_ROLLBACK_FILE_ROLLBACK
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		/////////////////////////////////////ROLLBACK DAS PASTAS CRIADAS PELO MODULO////////////////////////////////////
		RollbackManager::executeRollbackMethod(empty(RollbackManager::$errors) && is_array($rollback) && !empty($rollback), function() use ($rollback){
			return RollbackManager::removeModuleFolders
			(
				$rollback
			);},
			function($result){if ($result !== true){RollbackManager::$errors = array_merge( RollbackManager::$errors , $result );}},
		   $command, Strings::STATUS_REMOVING_FOLDERS
		);
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////

		////////////////////////////////////////////////RESPONSE (OUTPUT)///////////////////////////////////////////////
		if (empty(RollbackManager::$errors)){//Se os comandos rodarem com sucesso
			//Comentario comando executado com sucesso
			$command->info(Strings::SUCCESS_ROLLBACK);
			return true;
		}else{//Se ocorrer erro ao rodar os comandos
			foreach (RollbackManager::$errors as $error) {
				$command->error($error);
			}
			$command->info(Strings::ERROR_ROLLBACK);
			return false;
		}
		////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	}

}