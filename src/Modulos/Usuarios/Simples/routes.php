<?php

Route::get('home', 'PageController@getHome');

Route::get('cadastrar', 'UsuarioController@getCadastrar');
Route::post('cadastrar', 'UsuarioController@postCadastrar');

Route::get('login', 'UsuarioController@getLogin');
Route::post('login', 'UsuarioController@postLogin');
