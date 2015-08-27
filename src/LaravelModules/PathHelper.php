<?php

namespace AlmeidaFogo\LaravelModules\LaravelModules;

class PathHelper{

	/**
	 * Caminho para Configurações Gerais dos Modulos
	 *
	 * @return string
	 */
	public static function getModuleGeneralConfig(){
		return base_path().'/app/Modulos/configs.php';
	}

	/**
	 * Caminho para Arquivo de Rollbacks
	 *
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getModuleRollbackFile($moduleType, $moduleName){
		return base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Rollback/rollback.php';
	}

	/**
	 * Caminho do arquivo de configurações do modulo
	 *
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getModuleConfigPath($moduleType, $moduleName)
	{
		return base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/configs.php';
	}

}