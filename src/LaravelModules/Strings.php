<?php
/**
 * Created by PhpStorm.
 * User: Raphael
 * Date: 27/08/2015
 * Time: 00:28
 */

namespace AlmeidaFogo\LaravelModules\LaravelModules;

class Strings {

	//ERRORS
	const MODULE_NOT_FOUND = "Modulo inexistente ou corrompido";

	//CONFIG
	const MODULE_SEPARATOR = " & ";
	const MODULE_TYPE_NAME_SEPARATOR = '.';
	const MODULE_CONFIG_CONFLICT = "conflitos";
	const MODULE_CONFIG_DEPENDENCIES = "dependencias";
	const MODULE_CONFIG_CONFLICT_SEPARATOR = '.';
	const CONFIG_LOADED_MODULES = "modulosCarregados";

	//ROLLBACK
	const ROLLBACK_LOADED_MODULE_TAG = "LoadedModule";

	//STATUS
	const STATUS_SETING_AS_LOADED = "INFO: Carrendo no Arquivo de Configuracoes.";

	/**
	 * Retorna mensagem de conflito entre modulos
	 *
	 * @param string $conflito
	 * @return string
	 */
	public static function moduleSpecificConflictError($conflito){
		return "ERRO: Existe um conflito com o modulo '$conflito' que esta carregado";
	}

	/**
	 * Retorna mensagem de conflito entre tipos de modulos
	 *
	 * @param string $conflitoType
	 * @return string
	 */
	public static function moduleTypeConflictError($conflitoType){
		return "ERRO: Existe um conflito com o tipo do modulo '$conflitoType' que esta carregado";
	}

	/**
	 * Retorna mensagem de erro de dependencia entre modulos
	 *
	 * @param string $dependencia
	 * @return string
	 */
	public static function moduleSpecificDependencyError($dependencia){
		return "ERRO: Dependencia '$dependencia' faltando";
	}

	/**
	 * Retorna mensagem de erro de tipo de dependencia entre modulos
	 *
	 * @param string $dependenciaType
	 * @return string
	 */
	public static function moduleTypeDependencyError($dependenciaType){
		return "ERRO: Dependencia do Tipo '$dependenciaType' faltando";
	}

	/**
	 * Retorna mensagem de erro quando não foi possivel setar o modulo como carregado
	 *
	 * @param string $module
	 * @return string
	 */
	public static function cantSetModuleAsLoadedError($module){
		return "ERRO: Nao foi possivel definir o modulo '$module' como carregado";
	}

	/**
	 * Retorna mensagem de erro quando o modulo já esta carregado
	 *
	 * @param string $module
	 * @return string
	 */
	public static function moduleAlreadySetError($module){
		return "ERRO: O modulo '$module' ja esta carregado";
	}

}