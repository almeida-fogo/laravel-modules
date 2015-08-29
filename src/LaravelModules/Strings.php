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
	const MODULE_CONFIG_CONFIGS_SEPARATOR = ".";
	const MODULE_CONFIG_CONFIGS_SEPARATOR_REPLACEMENT = '-';

	//ROLLBACK
	const ROLLBACK_LOADED_MODULE_TAG = "LoadedModule";
	const ROLLBACK_MODULE_CONFIGS_TAG = "module-configs";
	const ROLLBACK_ORDINARY_FILE_COPY_TAG = "module-files";
	const ROLLBACK_DIR_CREATED_TAG = "dir-created";
	const ROLLBACK_MODULE_ORDINARY_FILE_COPY_TAG = "module-files";

	//STATUS
	const STATUS_SETING_AS_LOADED = "INFO: Carrendo no arquivo de configuracoes.";
	const STATUS_SETTING_MODULE_CONFIGS = "INFO: Alterando configuracoes requeridas pelo modulo.";
	const STATUS_COPYING_ORDINARY_FILES = "INFO: Copiando arquivos convencionais";

	//ANSWERS
	const SHORT_YES = 'y';
	const SHORT_NO = 'n';
	const SHORT_ALL = 'a';
	const SHORT_CANCEL = 'c';

	//OTHER
	const EMPTY_STRING = '';
	const PATH_SEPARATOR = '/';
	const PHP_EXTENSION = '.php';
	const GIT_KEEP_FILE_NAME = '.gitkeep';

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

	public static function cantMakeModuleConfig($module, $configuration){
		return "ERRO: Erro ao fazer configuração '$configuration' requerida pelo modulo '$module'";
	}

	/**
	 * Retorna mensagem de erro ao copiar um arquivo convencional do modulo
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function ordinaryFileCopyError($fileName){
		return "ERRO: Não foi possivel copiar o arquivo $fileName";
	}

	/**
	 * Retorna mensagem de arquivo convencional do modulo copiado com sucesso
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function ordinaryFileCopySuccess($fileName){
		return "INFO: Arquivo $fileName copiado com sucesso";
	}

	/**
	 * Retorna pergunta para o usuario se o arquivo deve ser substituido
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function replaceOrdinaryFiles($fileName){
		return "O arquivo '".$fileName."' tem certeza que deseja substitui-lo? (y = yes, n = no, a = all, c = cancel)";
	}

	/**
	 * Retorna mensagem de erro ao substituir um arquivo por um arquivo convencional do modulo
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function ordinaryFileReplaceError($fileName){
		return "ERRO: Não foi possivel substituir o arquivo $fileName";
	}

	/**
	 * Retorna mensagem de comando abortado pelo usuario
	 *
	 * @param string $commandName
	 * @return string
	 */
	public static function userRequestedAbort($commandName = null){
		$commandName = empty($commandName) ? '' : $commandName.' ';
		return "ALERTA: O comando ".$commandName."foi abortado pelo usuario";
	}


}