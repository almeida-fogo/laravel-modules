<?php

return [

    // Configuração de dependências (Ex.: "Usuarios.Simples" -> tipo: usuario, modulo: simples)
    "dependencias" => [
		"Usuarios.Simples",
	],

	// Configuração de incompatibilidade entre os modulos (Segue o mesmo modelo da configuração de competencias)
	"conflitos" => [
		"Posts",
	],

    // Configurações do Laravel a ser mudadas
    "configuracoes" => [
    ],

	// Versão
	"versao" => "0.1b",

];