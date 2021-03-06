<?php

namespace AlmeidaFogo\LaravelModules\LaravelModules;

class RouteBuilder{

	/**
	 * Constroi array do RouteBuilder
	 *
	 * @param string $routeBuilderFilePath
	 * @return array|null
	 */
	public static function getRoutesBuilder($routeBuilderFilePath){
		//Pega configurações salvas no arquivo route builder em um array
		return Configs::getConfig($routeBuilderFilePath, null);
	}

	/**
	 * Inclui ou altera uma rota no array de RouterBuilder dado
	 *
	 * @param array $routeBuilderArray
	 * @param string $filePathToInclude
	 * @return array|bool
	 */
	public static function includeToRoutesBuilder(array $routeBuilderArray, $filePathToInclude){
		//Verifica se o arquivo de rotas dado para ser incluido existe
		if (file_exists($filePathToInclude)){
			//Pega o diretorio do arquivo e explode em um array
			$explodedFilePath = explode(Strings::PATH_SEPARATOR, $filePathToInclude);
			//pega o tamanho do array explodido do nome do arquivo
			$explodedFilePathSize = count($explodedFilePath);
			//verifica se o diretorio explodido gerou um array valido
			if ($explodedFilePathSize >= 3){
				//em caso positivo captura o tipo e o nome do modulo a que o o arquivo de rotas pertence e salva em $key
				$key = $explodedFilePath[$explodedFilePathSize-3].Strings::MODULE_CONFIG_CONFIGS_SEPARATOR_REPLACEMENT.$explodedFilePath[$explodedFilePathSize-2];
				//pega o conteudo do arquivo de rota removendo somente o <?php
				$fileContent = file_get_contents($filePathToInclude, null, null, 5);
				//retorna  o array de routeBuilder adicionado das novas rotas
				return array_add($routeBuilderArray, $key, $fileContent);
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	/**
	 * Remove rotas existentes no array do RouteBuilder
	 *
	 * @param array $routeBuilderArray
	 * @param string $moduleTypeDotModuleName
	 * @return array
	 */
	public static function removeFromRoutesBuilder(array $routeBuilderArray, $moduleTypeDotModuleName){
		//remove rotas do array
		unset($routeBuilderArray[$moduleTypeDotModuleName]);
		//return
		return $routeBuilderArray;
	}

	/**
	 * Save RouteBuilder array to file
	 *
	 * @param array $routeBuilderArray
	 * @param string $routeBuilderFile
	 * @return int|bool
	 */
	public static function saveRoutesBuilder(array $routeBuilderArray, $routeBuilderFile){
		//build php route header
		$phpStringArray =
			Strings::PHP_TAG.chr(13).chr(13).
			Strings::RETURN_TAG.chr(13)
			.chr(13)
			."["
			.chr(13);
		//for eac h module loaded
		foreach ($routeBuilderArray as $key => $value)
		{
			//adiciona a rota domodulo e como chave tipo-nome
			$phpStringArray .= '"'.$key.'" => "'.$value.'",'.chr(13).chr(13);
		}
		//fecha o arrayde modulos
		$phpStringArray .= "];";

		//salva arquivo por cima do conteudo do arquivo anterior
		return file_put_contents(
			$routeBuilderFile,
			str_replace(
				file_get_contents($routeBuilderFile),
				$phpStringArray,
				file_get_contents($routeBuilderFile)
			)
		);
	}

	/**
	 * Escreve aquivo de rotas definitivo apartir do RouteBuilder array
	 *
	 * @param array $routeBuilderArray
	 * @return int|bool
	 */
	public static function buildRoutes(array $routeBuilderArray){
		//creia cabeçalho do arquivode rotas
		$routes = Strings::PHP_TAG.chr(13).chr(13).
			Strings::ROUTER_BUILDER_GEN_FILE_STRING_HEADER
			.chr(13).chr(13);

		//Faz um loop ema todos os itens do arquivo array do raouteBuilder
		foreach ( $routeBuilderArray as $module => $moduleRoute)
		{
			//Constroi rotas para o modulo
			$routes .= "//".$module./*diz de que modulo vieram as rotas*/
				$moduleRoute.chr(13).chr(13).chr(13).chr(13);/*Adiciona as rotas do modulo*/
		}

		//substitui o conteudo do arquivo de rotas pelo novo conteudo
		return file_put_contents(
			PathHelper::getLaravelRoutesPath(),
			str_replace(
				file_get_contents(PathHelper::getLaravelRoutesPath()),
				$routes,
				file_get_contents(PathHelper::getLaravelRoutesPath())
			));
	}

}