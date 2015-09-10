<?php
/**
 * Created by PhpStorm.
 * User: Raphael
 * Date: 26/08/2015
 * Time: 23:34
 */

namespace AlmeidaFogo\LaravelModules\LaravelModules;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Mockery\Exception;

class ModulesHelper {

	/**
	 * Metodo de atalho para execução padronizada dos metodos dessa classse
	 *
	 * @param bool $startCondition
	 * @param callable $helperMethod
	 * @param callable $afterCallBack
	 * @param Command $command
	 * @param string $message
	 */
	public static function executeHelperMethod(
		$startCondition = true,
		Callable $helperMethod,
		Callable $afterCallBack = null,
		Command $command = null,
		$message = null
	){
		if ($startCondition && $helperMethod != null){
			if ($command != null && $message != null){
				$command->info($message);
			}

			$result = $helperMethod();

			if ($afterCallBack != null){
				$afterCallBack($result);
			}
		}
	}

	/**
	 * Quebra todos os modulos carregados em tipos carregados
	 *
	 * @param array $loadedModules
	 * @return array
	 */
	public static function explodeTypes(array $loadedModules){
		//inicializa um array vazio
		$types = array();
		//Loop em todos os modulos
		foreach ( $loadedModules as $module)
		{
			//Pega o tipo do modulo
			$type = explode(".", $module)[0]; //TODO: Adicionar ao arquivo de strings
			//Verifica se o tipo ja foi colocado na lista
			if (!in_array($type, $types)){
				//Adiciona a lista
				array_push($types, $type);
			}
		}
		return $types;
	}

	/**
	 * Cria a tabela de verificação de migrations
	 *
	 * @return array|bool
     */
    public static function createMigrationsCheckTable(){
		try{
			$errors = [];

			if (!(count(DB::select(DB::raw("SHOW TABLES LIKE '".Strings::TABLE_PROJECT_MODULES."';")))>0)){ //TODO: Refatorar para usar statemet
				DB::select( DB::raw("											        					".
									"CREATE TABLE ".Strings::TABLE_PROJECT_MODULES."    					".
									"(											        					".
									"id			int NOT NULL PRIMARY KEY AUTO_INCREMENT,					".
									Strings::TABLE_PROJECT_MODULES_NAME."	VARCHAR (255) UNIQUE NOT NULL	".
									")																		"
				));
				if(!(count( DB::select( DB::raw("SHOW TABLES LIKE '".Strings::TABLE_PROJECT_MODULES."';")))>0)){
					$errors[] = Strings::ERROR_CREATE_MIGRATION_CHECK_TABLE;
				}
			}
		}catch (Exception $e){
            $errors[] = Strings::ERROR_CREATE_MIGRATION_CHECK_TABLE;
		}

        return $errors;
	}

	/**
	 * Pega os modulos carregados em forma de array
	 *
	 * @param string $oldLoadedModules
	 * @return array
	 */
	public static function getLoadedModules($oldLoadedModules){
		//if MODULOS_CARREGADOS == "", carrega array vazio (EVITA QUE TENHA UM SEPARADOR NO INICIO)
		if ( empty($oldLoadedModules) )
		{
			//Carrega array vazio
			$explodedLoadedModules = [];
		}else{
			//Separa modulos carregados em um array
			$explodedLoadedModules = explode( Strings::MODULE_SEPARATOR, $oldLoadedModules );
		}

		return $explodedLoadedModules;
	}

	/**
	 *  Checa se existem erros de conflitos e retorna os modulos conflitantes
	 *
	 * @param array $conflitos
	 * @param array $explodedLoadedModules
	 * @param array $explodedLoadedTypes
	 * @return array|bool
	 */
	public static function checkModuleConflicts(array $conflitos, array $explodedLoadedModules, array $explodedLoadedTypes){
		$errors = [];

		foreach ( $conflitos as $conflito )
		{
			//Se for uma conflito valido
			if (!empty($conflito))
			{
				//Conflito quebrado em tipo e nome
				$conflitoBroken = explode( Strings::MODULE_CONFIG_CONFLICT_SEPARATOR , $conflito );
				//Tipo do Conflito
				$conflitoType = $conflitoBroken[ 0 ];
				//Verifica se é um conflito especifico
				if ( array_key_exists( 1 , $conflitoBroken ) )
				{
					//verifica se o modulo conflituoso esta carregado
					if ( in_array( $conflito , $explodedLoadedModules ) )
					{
						//Adiciona o erro para o array de erros
						$errors[ ] = Strings::moduleSpecificConflictError($conflito);
					}
				}
				else
				{//Verifica se é um conflito de tipo
					//verifica se o tipo conflituoso esta carregado
					if ( in_array( $conflitoType , $explodedLoadedTypes ) )
					{
						//Adiciona o erro para o array de erros
						$errors[ ] = Strings::moduleTypeConflictError($conflitoType);
					}
				}
			}
		}

		return !empty($errors) ? $errors : false;
	}


	/**
	 *  Checa se existem erros de dependencia e retorna os modulos requeridos
	 *
	 * @param array $dependencias
	 * @param array $explodedLoadedModules
	 * @param array $explodedLoadedTypes
	 * @return array|bool
	 */
	public static function checkModuleDependencies(array $dependencias, array $explodedLoadedModules, array $explodedLoadedTypes)
	{
		$errors = [ ];

		foreach ( $dependencias as $dependencia )
		{
			//Se for uma dependencia válida
			if ( !empty( $dependencia ) )
			{
				//Dependencia quebrada em tipo e nome
				$dependenciaBroken = explode( '.' , $dependencia ); //TODO: Adicionar ao arquivo de strings
				//Tipo da dependencia
				$dependenciaType = $dependenciaBroken[ 0 ];
				//Verifica se é uma dependencia especifica
				if ( array_key_exists( 1 , $dependenciaBroken ) )
				{
					//verifica se a dependencia esta carregada
					if ( !in_array( $dependencia , $explodedLoadedModules ) )
					{
						//Adiciona o erro para o array de erros
						$errors[ ] = Strings::moduleSpecificDependencyError( $dependencia );
					}
				}
				else
				{//Verifica se é uma dependencia de tipo
					//verifica se a dependencia esta carregada
					if ( !in_array( $dependenciaType , $explodedLoadedTypes ) )
					{
						//Adiciona o erro para o array de erros
						$errors[ ] = Strings::moduleTypeDependencyError( $dependenciaType );
					}
				}
			}
		}

		return !empty($errors) ? $errors : false;
	}

	/**
	 * Define um modulo como carregado nas configurações de modulos
	 *
	 * @param array $explodedLoadedModules
	 * @param string $moduleType
	 * @param string $moduleName
	 * @param array $rollback
	 * @return array|bool
	 */
	public static function setModuleAsLoaded(array $explodedLoadedModules, $moduleType, $moduleName, array &$rollback){
		$errors = [ ];

		//Verifica se o modulo não esta carregado
		if ( !in_array( $moduleType . Strings::MODULE_TYPE_NAME_SEPARATOR . $moduleName , $explodedLoadedModules ) )
		{
			array_push( $explodedLoadedModules , $moduleType . Strings::MODULE_TYPE_NAME_SEPARATOR . $moduleName );
			$newLoadedModules = implode( Strings::MODULE_SEPARATOR , $explodedLoadedModules );

			//Adiciona para a lista de rollback
			$rollback[Strings::ROLLBACK_LOADED_MODULE_TAG] = EscapeHelper::encode( $moduleType . Strings::MODULE_TYPE_NAME_SEPARATOR . $moduleName );

			//Substitui conteudo da variavel pelo conteudo com o modulo novo
			if (Configs::setConfig( PathHelper::getModuleGeneralConfig(), Strings::CONFIG_LOADED_MODULES , $newLoadedModules ) == false)
			{
				//Adiciona o erro para o array de erros
				$errors[ ] = Strings::cantSetModuleAsLoadedError($moduleType . Strings::MODULE_TYPE_NAME_SEPARATOR . $moduleName);
			}
		}else{
			//Adiciona o erro para o array de erros
			$errors[ ] = Strings::moduleAlreadySetError($moduleType . Strings::MODULE_TYPE_NAME_SEPARATOR . $moduleName);
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * Efetua as configurações do modulo passado
	 *
	 * @param $moduleType
	 * @param $moduleName
	 * @param array $rollback
	 * @return array|bool
	 */
	public static function makeModuleConfigs($moduleType, $moduleName, array &$rollback){
		$errors = [ ];

		//Pega configurações
		$configuracoes = Configs::getConfig( PathHelper::getModuleConfigPath($moduleType, $moduleName) , "configuracoes" );

		//Inicia o Rollback de arquivos configurados
		$rollback["module-configs"] = []; //TODO: Adicionar ao arquivo de strings

		foreach ( $configuracoes as $configuracao => $valor )
		{
			if ( $valor != Strings::EMPTY_STRING )
			{
				$path = explode(Strings::MODULE_CONFIG_CONFIGS_SEPARATOR,$configuracao);
				array_pop($path);

				$path = PathHelper::getConfigDir(implode(Strings::PATH_SEPARATOR, $path).Strings::PHP_EXTENSION);

				$configName = str_replace(Strings::MODULE_CONFIG_CONFIGS_SEPARATOR, Strings::MODULE_CONFIG_CONFIGS_SEPARATOR_REPLACEMENT, $configuracao);

				//Inicia o Rollback de arquivos configurados
				$rollback[Strings::ROLLBACK_MODULE_CONFIGS_TAG][EscapeHelper::encode($configName)] = [];

				//Adiciona para a lista de rollback
				$rollback[Strings::ROLLBACK_MODULE_CONFIGS_TAG][EscapeHelper::encode($configName)][EscapeHelper::encode($path)] = EscapeHelper::encode(file_get_contents($path));

				//Se ao tentar configurar temos um erro, então:
				if ( Configs::setLaravelConfig( $configuracao , $valor ) == false )
				{
					//Adiciona o erro para o array de erros
					$errors[ ] = Strings::cantMakeModuleConfig($moduleType.'.'.$moduleName, $configuracao); //TODO: Adicionar ao arquivo de strings
				}
			}
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * Copia arquivos convencionais do modulo (qualquer coisa exceto migrations) para as respectivas pastas
	 * TODO: Tornar essa copia recurssiva para copiar outras pastas principalmente dentro de public
	 *
	 * @param string $moduleType
	 * @param string $moduleName
	 * @param string $copyAll
	 * @param array $rollback
	 * @param Command $command
	 * @return array|bool
	 */
	public static function makeOrdinaryCopies($moduleType, $moduleName, &$copyAll, array &$rollback, Command $command){
		$errors = [ ];

		//Inicia o Rollback de arquivos copiados
		$rollback[Strings::ROLLBACK_ORDINARY_FILE_COPY_TAG] = [];

		//Cria array de quais pastas e arquivos devem ser copiados para onde
		$paths = [
			PathHelper::getModuleControllersPath($moduleType, $moduleName) => PathHelper::getLaravelControllersPath(),
			PathHelper::getModuleModelsPath($moduleType, $moduleName) => PathHelper::getLaravelModelsPath(),
			PathHelper::getModuleViewsPath($moduleType, $moduleName) => PathHelper::getLaravelViewsPath($moduleType, $moduleName),
			PathHelper::getModulePublicPath($moduleType, $moduleName) => PathHelper::getLaravelPublicPath($moduleType, $moduleName),
			PathHelper::getModulePublicPath($moduleType, $moduleName, '/css') => PathHelper::getLaravelPublicPath($moduleType, $moduleName, '/css'), 			//TODO: Remover ao adicionar copia recurssiva
			PathHelper::getModulePublicPath($moduleType, $moduleName, '/imagens') => PathHelper::getLaravelPublicPath($moduleType, $moduleName, '/imagens'),	//TODO: Remover ao adicionar copia recurssiva
			PathHelper::getModulePublicPath($moduleType, $moduleName, '/js') => PathHelper::getLaravelPublicPath($moduleType, $moduleName, '/js'),				//TODO: Remover ao adicionar copia recurssiva
		];

		//loop em todos os diretorios de destino
		foreach($paths as $key => $value){
			if(!is_dir($value)){//Se o diretorio não existir
				//Cria o diretorio que não existe
				if (mkdir($value)){
					//Cria registro no rollback dizendo uma pasta foi criada
					$rollback[Strings::ROLLBACK_DIR_CREATED_TAG][] = $value;
				}
			}
		}

		//Loop em todas as pastas
		foreach($paths as $key => $value){
			if (empty($errors)){//Se os comandos anteriores rodarem com sucesso
				//Copia lista de arquivos no diretorio para variavel arquivos
				$arquivos = scandir($key);
				//Loop em todos os arquivos do modulo
				for( $i = Constants::FIRST_FILE; $i < count($arquivos); $i++){
					if (empty($errors) && !is_dir($value.$arquivos[$i])){//Se os comandos anteriores rodarem com sucesso e o arquivo não for uma pasta

						$explodedFileName  = explode(Strings::PATH_SEPARATOR, $value.$arquivos[$i]);
						$filename = $explodedFileName[count($explodedFileName)-1];

						//Verifica se o arquivo existe
						if (!file_exists($value.$arquivos[$i])){
							//Cria registro no rollback dizendo que o arquivo foi copiado
							$rollback[Strings::ROLLBACK_MODULE_ORDINARY_FILE_COPY_TAG][EscapeHelper::encode($value.$arquivos[$i])] = Strings::EMPTY_STRING;
							//verifica se a copia ocorreu com sucesso
							if (copy($key.$arquivos[$i], $value.$arquivos[$i]) == false){
								//Printa msg de erro
								$errors[] = (Strings::ordinaryFileCopyError($value.$arquivos[$i]));
							}
						}else if (strtoupper($filename) != strtoupper(Strings::GIT_KEEP_FILE_NAME)){//Caso ja exista um arquivo com o mesmo nome no diretorio de destino
							//Inicializa variavel que vai receber resposta do usuario dizendo o que fazer
							// com o conflito
							$answer = Strings::EMPTY_STRING;
							//Enquanto o usuario não devolver uma resposta valida
							while ($copyAll != true && $answer != Strings::SHORT_YES && $answer != Strings::SHORT_NO && $answer !=
								Strings::SHORT_ALL && $answer != Strings::SHORT_CANCEL){
								//Faz pergunta para o usuario de como proceder
								$answer = $command->ask(Strings::replaceOrdinaryFiles($value.$arquivos[$i]), false);
							}
							//Se a resposta for sim, ou all
							if (strtolower($answer) == Strings::SHORT_YES || strtolower($answer) == Strings::SHORT_ALL || $copyAll == true){
								//se a resposta for all
								if (strtolower($answer) == Strings::SHORT_ALL){
									//seta variavel all para true
									$copyAll = true;
								}
								//Faz backup do arquivo que será substituido
								$rollback[Strings::ROLLBACK_MODULE_ORDINARY_FILE_COPY_TAG][EscapeHelper::encode($value.$arquivos[$i])] = EscapeHelper::encode(file_get_contents($value.$arquivos[$i]));
								//verifica se a substituição ocorreu com sucesso
								if (copy($key.$arquivos[$i], $value.$arquivos[$i]) == false){//Se houver erro ao copiar arquivo
									//Printa msg de erro
									$errors[] = (Strings::ordinaryFileReplaceError($value.$arquivos[$i]));
								}
							}else if (strtolower($answer) == Strings::SHORT_CANCEL){//se a resposta foi cancelar
								//Printa msg de erro
								$errors[] = (Strings::userRequestedAbort());
								//break the file loop
								break(2);
							}
						}
					}
				}
			}
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * Copia arquivos convencionais do modulo (qualquer coisa exceto migrations) para as respectivas pastas
	 *
	 * @param string $moduleType
	 * @param string $moduleName
	 * @param string $copyAll
	 * @param array $rollback
	 * @param Command $command
	 * @return array|bool
	 */
	public static function makeMigrationsCopies($moduleType, $moduleName, &$copyAll, array &$rollback, Command $command){
		$errors = [ ];

		//Inicia o Rollback de arquivos copiados
		$rollback[Strings::ROLLBACK_MODULE_MIGRATION_FILE_TAG] = array();
		//Inicia o Rollback de arquivos deletados
		$rollback[Strings::ROLLBACK_MODULE_MIGRATION_DELETED_FILE_TAG] = array();

		//Copia lista de arquivos no diretorio de migrations para variavel arquivos
		$arquivos = scandir(PathHelper::getModuleMigrationsPath($moduleType, $moduleName));
		//Loop em todos os arquivos do modulo
		for( $i = Constants::FIRST_FILE; $i < count($arquivos); $i++){
			//Quebra as palavras  da migration dentro de um array
			$explodedModuleMigrationName = explode(Strings::MIGRATIONS_WORD_SEPARATOR, $arquivos[$i]);
			//Pega remove a parte do nome referente ao timestamp
			$SimplifiedModuleMigrationName = implode(Strings::MIGRATIONS_WORD_SEPARATOR,array_slice($explodedModuleMigrationName, Constants::MIGRATION_FILE_NAME_ARRAY_START));

			//Flag que indica se o arquivo existe
			$migrationPos = false;
			//Pega migrations do projeto
			$migrationFiles = scandir(PathHelper::getLaravelMigrationsPath());
			foreach ($migrationFiles as $migrationIndex => $migrationFile){
				//Quebra as palavras  da migration dentro de um array
				$explodedMigrationFileName = explode(Strings::MIGRATIONS_WORD_SEPARATOR, $migrationFile);
				//Pega remove a parte do nome referente ao timestamp
				$SimplifiedMigratioFileName = implode(Strings::MIGRATIONS_WORD_SEPARATOR,array_slice($explodedMigrationFileName, Constants::MIGRATION_FILE_NAME_ARRAY_START));
				//Verifica se a migration já existe
				if ($SimplifiedMigratioFileName == $SimplifiedModuleMigrationName){
					//marca o arquivo de migration existente migration
					$migrationPos = $migrationIndex;
					//quebra o loop
					break;
				}
			}

			$explodedFileName  = explode(Strings::PATH_SEPARATOR, PathHelper::getModuleMigrationsPath($moduleType, $moduleName).$arquivos[$i]);
			$filename = $explodedFileName[count($explodedFileName)-1];
			if (strtoupper($filename) != strtoupper(Strings::GIT_KEEP_FILE_NAME)){
				if ($migrationPos == false){//Se o arquivo não existir
					$migrationCounter = Configs::getConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_MIGRATIONS_COUNTER);
					Configs::setConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_MIGRATIONS_COUNTER, $migrationCounter+1);
					if (copy(
						PathHelper::getModuleMigrationsPath($moduleType, $moduleName).$arquivos[$i],
						PathHelper::getLaravelMigrationsPath().Strings::timestampPadding($migrationCounter).Strings::MIGRATIONS_WORD_SEPARATOR.$SimplifiedModuleMigrationName
					) == false){
						$errors[] = Strings::migrationsFileCopyError($arquivos[$i]);
					}
					//Sinaliza o no arquivo copiado
					$rollback[Strings::ROLLBACK_MODULE_MIGRATION_FILE_TAG][] = EscapeHelper::encode(
						PathHelper::getLaravelMigrationsPath().Strings::timestampPadding($migrationCounter).Strings::MIGRATIONS_WORD_SEPARATOR.$SimplifiedModuleMigrationName
					);
				}else{//Se o arquivo ja existir
					//Inicializa variavel que vai receber resposta do usuario dizendo o que fazer
					// com o conflito
					$answer = Strings::EMPTY_STRING;
					//Enquanto o usuario não devolver uma resposta valida
					while ($copyAll != true && $answer != Strings::SHORT_YES && $answer != Strings::SHORT_NO && $answer != Strings::SHORT_ALL && $answer != Strings::SHORT_CANCEL){
						//Faz pergunta para o usuario de como proceder
						$answer = $command->ask(Strings::replaceMigrationFiles($arquivos[$i]), false);
					}
					//Se a resposta for sim, ou all
					if (strtolower($answer) == Strings::SHORT_YES || strtolower($answer) == Strings::SHORT_ALL || $copyAll == true){
						//se a resposta for all
						if (strtolower($answer) == Strings::SHORT_ALL){
							//seta variavel all para true
							$copyAll = true;
						}

						//Captura o numero da migration
						$migrationCounter = Configs::getConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_MIGRATIONS_COUNTER);
						//Atualiza o contador de migrations
						Configs::setConfig(PathHelper::getModuleGeneralConfig(), Strings::CONFIG_MIGRATIONS_COUNTER, $migrationCounter+1);

						//Sinaliza o no arquivo copiado
						$rollback[Strings::ROLLBACK_MODULE_MIGRATION_FILE_TAG][] = EscapeHelper::encode(
							PathHelper::getModuleMigrationsPath($moduleType, $moduleName).Strings::timestampPadding($migrationCounter).Strings::MIGRATIONS_WORD_SEPARATOR.$SimplifiedModuleMigrationName
						);

						//Faz backup do arquivo que será substituido
						$rollback[Strings::ROLLBACK_MODULE_MIGRATION_DELETED_FILE_TAG][EscapeHelper::encode(PathHelper::getLaravelMigrationsPath().$migrationFiles[$migrationPos])] = EscapeHelper::encode(
							file_get_contents(PathHelper::getLaravelMigrationsPath().$migrationFiles[$migrationPos])
						);

						//Deletar o arquivo antigo
						if (unlink(PathHelper::getLaravelMigrationsPath().$migrationFiles[$migrationPos]) == false){
							$errors[] = Strings::migrationsFileDeleteError($arquivos[$i]);
						}

						//verifica se a substituição ocorreu com sucesso
						if (copy(
								PathHelper::getModuleMigrationsPath($moduleType, $moduleName).$arquivos[$i],
								Strings::timestampPadding($migrationCounter).Strings::MIGRATIONS_WORD_SEPARATOR.$SimplifiedModuleMigrationName) == false){
							$errors[] = Strings::migrationsFileCopyError($arquivos[$i]);
						}
					}else if (strtolower($answer) == Strings::SHORT_CANCEL){//se a resposta foi cancelar
						//Printa msg de erro
						$errors[] = (Strings::userRequestedAbort());
						//break the file loop
						break;
					}
				}
			}
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * Constroi as rotas dos modulos
	 *
	 * @param string $moduleType
	 * @param string $moduleName
	 * @param array $rollback
	 * @return array|bool
	 */
	public static function buildRoutes($moduleType, $moduleName, array &$rollback){
		$errors = [ ];

		//Cria registro no rollback dizendo que o arquivo foi copiado
		$rollback[Strings::ROLLBACK_ROUTES_BUILDER_TAG] = EscapeHelper::encode(file_get_contents(PathHelper::getRouteBuilderPath()));

		//constroi o array do routesBuilder
		$routeBuilder = RouteBuilder::getRoutesBuilder(PathHelper::getRouteBuilderPath());
		//verifica se foi construido um array valido
		if ($routeBuilder !== false){
			//inclui as novas rotas ao array do routeBuilder
			$routeBuilder = RouteBuilder::includeToRoutesBuilder($routeBuilder, PathHelper::getModuleRoutesPath($moduleType, $moduleName));
			//verifica se o array de rotas continua válido
			if ($routeBuilder !== false){
				//tenta salvar o novo array do routesBuilder
				if (RouteBuilder::saveRoutesBuilder($routeBuilder,PathHelper::getRouteBuilderPath()) != false){
					//Cria registro no rollback dizendo que o arquivo foi copiado
					$rollback[Strings::ROLLBACK_OLD_ROUTES_TAG] = EscapeHelper::encode(file_get_contents(PathHelper::getLaravelRoutesPath()));
					//tenta construir o arquivo de rotas gera baseado no array savo do routesBuilder
					if (RouteBuilder::buildRoutes($routeBuilder) === false){
						$errors[] = Strings::ERROR_ROUTES_FILE_SAVE;
					}
				}else{
					$errors[] = Strings::ERROR_ROUTES_BUILDER_SAVE;
				}
			}else{
				$errors[] = Strings::ERROR_INCLUDE_TO_ROUTES_BUILDER_SAVE;
			}
		}else{
			$errors[] = Strings::ERROR_ROUTES_BUILDER_GEN;
		}

		return !empty($errors) ? $errors : true;
	}

	/**
	 * Constroi as rotas dos modulos
	 *
	 * @param string $moduleType
	 * @param string $moduleName
	 * @param array $rollback
	 * @param Command $command
	 * @return array|bool
	 */
	public static function runMigrations($moduleType, $moduleName, array &$rollback, Command $command)
	{
		try {
			$errors = [ ];

			//Roda dump autoload
			shell_exec(Strings::COMMAND_DUMP_AUTOLOAD);
			//Tenta Rodar a migration
			$command->call(Strings::COMMAND_MIGRATE);
			//Seta a flag de migrations para true no rollback
			$rollback[Strings::ROLLBACK_MIGRATE] = Strings::TRUE_STRING;
			/////VERIFICAR SE MIGRATE RODOU DE FORMA ADEQUADA//////
			if ( !( count(DB::table(Strings::TABLE_PROJECT_MODULES)
							->where(Strings::TABLE_PROJECT_MODULES_NAME,
									$moduleType . Strings::MODULE_TYPE_NAME_SEPARATOR . $moduleName)
							->first()) > 0 )
			) {
				$errors[] = Strings::ERROR_MIGRATE;
			}
			///////////////////////////////////////////////////////
		} catch (\Exception $e) {
			$errors[] = Strings::migrationException($e->getMessage());
		}

		return !empty($errors) ? $errors : true;
	}
	/**
	 * Constroi o arquivo de rollback
	 *
	 * @param string $moduleType
	 * @param string $moduleName
	 * @param array $rollback
	 * @return array|bool
	 */
	public static function createRollbackFile($moduleType, $moduleName, array &$rollback)
	{
		$errors = [];

		//Cria registro no rollback dizendo que o arquivo foi copiado
		$rollback[Strings::ROLLBACK_OLD_ROLLBACK_TAG] = EscapeHelper::encode(
				file_get_contents(PathHelper::getModuleRollbackFile($moduleType,$moduleName))
		);

		if (RollbackManager::buildRollback(
						$rollback,
						PathHelper::getModuleRollbackFile($moduleType, $moduleName)
				) == false
		) {
			$errors[] = Strings::ERROR_CREATE_ROLLBACK_FILE;
		}

		return !empty($errors) ? $errors : true;
	}

}