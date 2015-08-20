<?php

return [

    // Configuração de dependências (Ex.: "Usuarios.Simples" -> tipo: usuario, modulo: simples)
    "dependencias" => [
		"Roots.Templates",
	],

	// Configuração de incompatibilidade entre os modulos (Segue o mesmo modelo da configuração de competencias)
	"conflitos" => [
		"Usuarios",
	],

    // Configurações do Laravel a ser mudadas
    "configuracoes" => [
            "auth.table" => "usuarios",
            "auth.model" => "\\App\\Usuario"
    ],

	// Versão
	"versao" => "0.1b",

];