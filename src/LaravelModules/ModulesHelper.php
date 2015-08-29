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
use Psy\Util\Str;

class ModulesHelper {

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
			$type = explode(".", $module)[0];
			//Verifica se o tipo ja foi colocado na lista
			if (!in_array($type, $types)){
				//Adiciona a lista
				array_push($types, $type);
			}
		}
		return $types;
	}

	public static function createMigrationsCheckTable(Command $handleContext){
		try{
			if (!(count(DB::select(DB::raw("SHOW TABLES LIKE 'project_modules';")))>0)){
				DB::select( DB::raw(
<<<QUERY
										CREATE TABLE project_modules
					(
						id			int NOT NULL PRIMARY KEY AUTO_INCREMENT,
						module_name	VARCHAR (255) UNIQUE NOT NULL
					)
QUERY
				));
				if(!(count( DB::select( DB::raw("SHOW TABLES LIKE 'project_modules';")))>0)){
					$handleContext->info("ERRO: Erro ao Criar Table de Modulos Carregados.");
					return false;
				}
			}
		}catch (Exception $e){
			$handleContext->info("ERRO: Erro ao Criar Table de Moculos Carregados.");
			return false;
		}
		return true;
	}

	/**
	 * Pega os modulos carregados em forma de array
	 *
	 * @param string $oldLoadedModules
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return array|null
	 */
	public static function getLoadedModules($oldLoadedModules, $moduleType, $moduleName){
		$explodedLoadedModules = null;

		//Verifica se o arquivo de configuração do modulo não existe
		if (file_exists( PathHelper::getModuleConfigPath( $moduleType , $moduleName ) ))
		{
			//if MODULOS_CARREGADOS == "", carrega array vazio (EVITA QUE TENHA UM SEPARADOR NO INICIO)
			if ( empty($oldLoadedModules) )
			{
				//Carrega array vazio
				$explodedLoadedModules = array ();
			}
			else
			{
				//Separa modulos carregados em um array
				$explodedLoadedModules = explode( Strings::MODULE_SEPARATOR, $oldLoadedModules );
			}
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
				$dependenciaBroken = explode( '.' , $dependencia );
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
		$rollback["module-configs"] = [];

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
					$errors[ ] = Strings::cantMakeModuleConfig($moduleType.'.'.$moduleName, $configuracao);
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
			PathHelper::getModulePublicPath($moduleType, $moduleName, '/css') => PathHelper::getLaravelPublicPath($moduleType, $moduleName, '/css'),
			PathHelper::getModulePublicPath($moduleType, $moduleName, '/imagens') => PathHelper::getLaravelPublicPath($moduleType, $moduleName, '/imagens'),
			PathHelper::getModulePublicPath($moduleType, $moduleName, '/js') => PathHelper::getLaravelPublicPath($moduleType, $moduleName, '/js'),
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
				for( $i = 2; $i < count($arquivos); $i++){
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
}