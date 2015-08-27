<?php

namespace AlmeidaFogo\LaravelModules\LaravelModules;

class EscapeHelper {

	/**
	 * Encoda caracteres especiais de uma string
	 *
	 * @param string $text
	 * @return string
	 */
	public static function encode($text){
		return htmlentities($text, ENT_QUOTES, "UTF-8");
	}

	/**
	 * Decodifica caracteres especiais de uma string
	 *
	 * @param string $text
	 * @return string
	 */
	public static function decode($text){
		return html_entity_decode($text, ENT_QUOTES, "UTF-8");
	}

}