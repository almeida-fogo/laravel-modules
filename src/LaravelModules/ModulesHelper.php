<?php
/**
 * Created by PhpStorm.
 * User: Raphael
 * Date: 26/08/2015
 * Time: 23:34
 */

namespace AlmeidaFogo\LaravelModules\LaravelModules;


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

}