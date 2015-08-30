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
	const MODULE_CONFIG_CONFIGS_SEPARATOR = ".";
	const MODULE_CONFIG_CONFIGS_SEPARATOR_REPLACEMENT = '-';
	const MODULE_CONFIG_CONFLICT_SEPARATOR = '.';

	//CONFIG LABEL
	const MODULE_CONFIG_CONFLICT = "conflitos";
	const MODULE_CONFIG_DEPENDENCIES = "dependencias";
	const CONFIG_LOADED_MODULES = "modulosCarregados";
	const CONFIG_MIGRATIONS_COUNTER = "migrationsCounter";

	//ROLLBACK
	const ROLLBACK_LOADED_MODULE_TAG = "LoadedModule";
	const ROLLBACK_MODULE_CONFIGS_TAG = "module-configs";
	const ROLLBACK_ORDINARY_FILE_COPY_TAG = "module-files";
	const ROLLBACK_DIR_CREATED_TAG = "dir-created";
	const ROLLBACK_MODULE_ORDINARY_FILE_COPY_TAG = "module-files";
	const ROLLBACK_MODULE_MIGRATION_FILE_TAG = "module-migration-files";
	const ROLLBACK_MODULE_MIGRATION_DELETED_FILE_TAG = "module-migration-deleted-files";
	const ROLLBACK_ROUTES_BUILDER_TAG = "routes-builder";
	const ROLLBACK_OLD_ROUTES_TAG = "old-routes";

	//MIGRATIONS
	const MIGRATIONS_WORD_SEPARATOR = "_";

	//STATUS
	const STATUS_SETING_AS_LOADED = "INFO: Carrendo no arquivo de configuracoes.";
	const STATUS_SETTING_MODULE_CONFIGS = "INFO: Alterando configuracoes requeridas pelo modulo.";
	const STATUS_COPYING_ORDINARY_FILES = "INFO: Copiando arquivos convencionais";
	const STATUS_COPYING_MIGRATION_FILES = "INFO: Copiando arquivos das migrations";
	const STATUS_BUILDING_ROUTES = "INFO: Constuindo rotas do modulo";

	//ERRORS
	const ERROR_ROUTES_FILE_SAVE = "ERRO: Problemas ao gerar o arquivo de rotas";
	const ERROR_ROUTES_BUILDER_SAVE = "ERRO: Problemas ao salvar RouterBuilder";
	const ERROR_INCLUDE_TO_ROUTES_BUILDER_SAVE = "ERRO: Problemas ao incluir rotas ao RouterBuilder";
	const ERROR_ROUTES_BUILDER_GEN = "ERRO: Problemas ao gerar RoutesBuilder Array";

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
	public static function moduleSpecificConflictError( $conflito )
	{
		return "ERRO: Existe um conflito com o modulo '$conflito' que esta carregado";
	}

	/**
	 * Retorna mensagem de conflito entre tipos de modulos
	 *
	 * @param string $conflitoType
	 * @return string
	 */
	public static function moduleTypeConflictError( $conflitoType )
	{
		return "ERRO: Existe um conflito com o tipo do modulo '$conflitoType' que esta carregado";
	}

	/**
	 * Retorna mensagem de erro de dependencia entre modulos
	 *
	 * @param string $dependencia
	 * @return string
	 */
	public static function moduleSpecificDependencyError( $dependencia )
	{
		return "ERRO: Dependencia '$dependencia' faltando";
	}

	/**
	 * Retorna mensagem de erro de tipo de dependencia entre modulos
	 *
	 * @param string $dependenciaType
	 * @return string
	 */
	public static function moduleTypeDependencyError( $dependenciaType )
	{
		return "ERRO: Dependencia do Tipo '$dependenciaType' faltando";
	}

	/**
	 * Retorna mensagem de erro quando não foi possivel setar o modulo como carregado
	 *
	 * @param string $module
	 * @return string
	 */
	public static function cantSetModuleAsLoadedError( $module )
	{
		return "ERRO: Nao foi possivel definir o modulo '$module' como carregado";
	}

	/**
	 * Retorna mensagem de erro quando o modulo já esta carregado
	 *
	 * @param string $module
	 * @return string
	 */
	public static function moduleAlreadySetError( $module )
	{
		return "ERRO: O modulo '$module' ja esta carregado";
	}


	public static function cantMakeModuleConfig( $module , $configuration )
	{
		return "ERRO: Erro ao fazer configuração '$configuration' requerida pelo modulo '$module'";
	}

	/**
	 * Retorna mensagem de erro ao copiar um arquivo convencional do modulo
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function ordinaryFileCopyError( $fileName )
	{
		return "ERRO: Não foi possivel copiar o arquivo $fileName";
	}

	/**
	 * Retorna mensagem de arquivo convencional do modulo copiado com sucesso
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function ordinaryFileCopySuccess( $fileName )
	{
		return "INFO: Arquivo $fileName copiado com sucesso";
	}

	/**
	 * Retorna pergunta para o usuario se o arquivo deve ser substituido
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function replaceOrdinaryFiles( $fileName )
	{
		return "O arquivo '" . $fileName . "' tem certeza que deseja substitui-lo? (y = yes, n = no, a = all, c = cancel)";
	}

	/**
	 * Retorna mensagem de erro ao substituir um arquivo por um arquivo convencional do modulo
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function ordinaryFileReplaceError( $fileName )
	{
		return "ERRO: Não foi possivel substituir o arquivo $fileName";
	}

	/**
	 * Retorna mensagem de comando abortado pelo usuario
	 *
	 * @param string $commandName
	 * @return string
	 */
	public static function userRequestedAbort( $commandName = null )
	{
		$commandName = empty( $commandName ) ? '' : $commandName . ' ';
		return "ALERTA: O comando " . $commandName . "foi abortado pelo usuario";
	}

	/**
	 * Timestamp string padding
	 *
	 * @param string $time
	 * @return string
	 */
	public static function timestampPadding( $time = "0" )
	{
		return "0000_00_00_" . str_pad( $time , 6 , "0" , STR_PAD_LEFT );
	}

	/**
	 * Retorna mensagem de arquivo de migration copiado com erro
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function migrationsFileCopyError( $fileName )
	{
		return "ERRO: Não foi possivel copiar o arquivo da migration $fileName";
	}

	/**
	 * Retorna pergunta ao usuario a respeito de substituir uma migration do projeto por outra do modulo
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function replaceMigrationFiles( $fileName )
	{
		return "O arquivo '".$fileName."' já existe, tem certeza que deseja substitui-lo? (y = yes, n = no, a = all, c = cancel)";
	}

	/**
	 * Retorna mensagem de arquivo de migration deletado com erro
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function migrationsFileDeleteError( $fileName )
	{
		return "ERRO: Não foi possivel deletar o arquivo da migration $fileName";
	}



    /**
     * Retorna mensagem perguntando qual dos modulos o usuário deseja carregar para esse tipo de módulo
     *
     * @param string $moduleType
     * @return string
     */
    public static function moduleNameForThisType($moduleType){
        return "Qual o nome do modulo do tipo \"".$moduleType."\" deseja carregar?";
    }

}