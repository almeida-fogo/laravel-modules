<?php

namespace AlmeidaFogo\LaravelModules\LaravelModules;

class Configs{

	/**
	 * Pega a configuração de um arquivo
	 *
	 * @param string $configPath
	 * @param mixed $index
	 * @return mixed|null
	 */
	public static function getConfig($configPath, $index = null){
		//Roda o arquivo de configuração
		$value = eval(str_replace(Strings::PHP_TAG, Strings::EMPTY_STRING, file_get_contents($configPath)));
		if (is_array($value)){
			//verifica se o indice do array de configurações é nulo
			if ($index != null)
			{
				if (array_key_exists($index, $value)){
					//devolve o valor da configuração
					return $value[$index];
				}else{
					return null;
				}
			}else{//Se o indice for nulo
				//Devolve todas as configurações
				return $value;
			}
		}else{//Se não for um array
			//Retorna null
			return null;
		}
	}

	/**
	 * Escreve configuração em um arquivo
	 *
	 * @param string $path
	 * @param mixed $config
	 * @param mixed $value
	 * @return int|bool
	 */
	public static function setConfig($path, $config, $value){
		//verifica se os parametros são validos
		if ($config != '\'\'' && $path != base_path().Strings::PATH_SEPARATOR.Strings::PHP_EXTENSION){
			//verifica se o arquvido onde estão as configs existe
			if (file_exists($path)){
				//pega no arquivo a posição onde esta a configuração que deve ser alterada
				$configPos = strpos(file_get_contents($path), $config, 0);
				//verifica se a configuração existe no arquivo
				if ($configPos != false){
					// pega a posição do operador seta (=>) apos a cofiguração
					$arrowPos = strpos(file_get_contents($path), Strings::ARRAY_ASSIGN, $configPos)+2;
					//verifica se o operador seta apos a confiração existe
					if ($arrowPos != false){
						//captura a posição da proxima virgula apos o operador seta
						$commaPos = strpos(file_get_contents($path), ',', $arrowPos);
						//verifica se a virgula existe
						if ($commaPos != false){
							//pega o espaço entra a seta e a vigula e substitui pelo valor dado em $value
							return file_put_contents(
								$path,
								substr_replace(file_get_contents($path),
											   ' \''.$value.'\'',
											   $arrowPos,
											   $commaPos-$arrowPos
								)
							);
						}
					}
				}
			}
		}
		//retorna false caso o comando de substituição não seja executado
		return false;
	}


	/**
	 * Escreve configuração em um arquivo usando padrão do Laravel
	 *
	 * @param mixed $config
	 * @param mixed $value
	 * @return bool|int
	 */
	public static function setLaravelConfig($config, $value){
		$path = explode(Strings::MODULE_CONFIG_CONFIGS_SEPARATOR,$config);
		if ($path != array()){
			$variable = "'".array_pop($path)."'";
			$path = PathHelper::getConfigDir(implode(Strings::PATH_SEPARATOR, $path).Strings::PHP_EXTENSION);
			if ($variable != '\'\'' && $path != base_path().Strings::PATH_SEPARATOR.Strings::PHP_EXTENSION){
				if (file_exists($path)){
					return self::setConfig($path, $variable, $value);
				}
			}
		}
		return false;
	}


}