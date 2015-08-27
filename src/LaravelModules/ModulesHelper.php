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
	 * @param array $explodedLoadedModules
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return array|bool
	 */
	public static function setModuleAsLoaded(array $explodedLoadedModules, $moduleType, $moduleName){
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

}