<?php namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use \App\Usuario;
use Illuminate\Support\Facades\Auth;

class PageController extends Controller {

	public function getHome(){
		return view("Usuarios_Simples.home");
	}

}
