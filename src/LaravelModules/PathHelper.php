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

	/**
	 * Caminho da raiz do modulo
	 *
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getModuleRootPath($moduleType, $moduleName)
	{
		return base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName;
	}

	/**
	 * Retorna o caminho para o arquivo requisitado dentro da pasta config
	 *
	 * @param string $fileName
	 * @return string
	 */
	public static function getConfigDir($fileName){
		return base_path().'/config/'.$fileName;
	}

	/**
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getModuleControllersPath($moduleType, $moduleName)
	{
		return base_path() . '/app/Modulos/' . $moduleType . '/' . $moduleName . '/Controllers/';
	}

	/**
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getModuleModelsPath($moduleType, $moduleName)
	{
		return base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Models/';
	}

	/**
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getModuleViewsPath($moduleType, $moduleName)
	{
		return base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Views/';
	}

	/**
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getModuleAssetsPath($moduleType, $moduleName)
	{
		return base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Assets/';
	}

	/**
	 * @param string $moduleType
	 * @param string $moduleName
	 * @param string $more
	 * @return string
	 */
	public static function getModulePublicPath($moduleType, $moduleName, $more = '')
	{
		return base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Public/'.$more;
	}

	/**
	 * @return string
	 */
	public static function getLaravelControllersPath()
	{
		return base_path().'/app/Http/Controllers/';
	}

	/**
	 * @return string
	 */
	public static function getLaravelModelsPath()
	{
		return base_path().'/app/';
	}

	/**
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getLaravelViewsPath($moduleType, $moduleName)
	{
		return base_path().'/resources/views/'.$moduleType.'_'.$moduleName.'/';
	}

	/**
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getLaravelAssetsPath($moduleType, $moduleName)
	{
		return base_path().'/resources/assets/'.$moduleType.'_'.$moduleName.'/';
	}

	/**
	 * @param string $moduleType
	 * @param string $moduleName
	 * @param string $more
	 * @return string
	 */
	public static function getLaravelPublicPath($moduleType, $moduleName, $more = '')
	{
		return base_path().'/public/'.$moduleType.'_'.$moduleName.$more.'/';
	}

	/**
	 * @return string
	 */
	public static function getLaravelMigrationsPath()
	{
		return base_path().'/database/migrations/';
	}

	/**
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getModuleMigrationsPath($moduleType, $moduleName)
	{
		return base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/Migrations/';
	}

	/**
	 * @return string
	 */
	public static function getLaravelRoutesPath()
	{
		return base_path().'/app/Http/routes.php';
	}

	/**
	 * @param string $moduleType
	 * @param string $moduleName
	 * @return string
	 */
	public static function getModuleRoutesPath($moduleType, $moduleName)
	{
		return base_path().'/app/Modulos/'.$moduleType.'/'.$moduleName.'/routes.php';
	}

	/**
	 * @return string
	 */
	public static function getRouteBuilderPath()
	{
		return base_path().'/app/Modulos/RouteBuilder.php';
	}

}