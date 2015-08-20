<?php namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use \App\Usuario;
use Illuminate\Support\Facades\Auth;

class UsuarioController extends Controller {

	public function getCadastrar(){
		return view("Usuarios_Simples.cadastro");
	}

	public function postCadastrar(Request $request){
		$this->validate($request, array(
				"email" => "required|email",
				"senha" => "required|confirmed"
		));

			$usuario = Usuario::create($request->all());

			$usuario->password = bcrypt($request["senha"]);
			$usuario->save();

			$credentials = [
				"email" => $request["email"],
				"password" => $request["senha"]
			];

			Auth::attempt($credentials);

			// Hard coded o return para home
			return redirect()->action("PageController@getHome");
	}

	public function getLogin(){

		return view("Usuarios_Simples.login");

	}

	public function postLogin(Request $request)
	{
		$this->validate($request, array(
				"email" => "required|email",
				"senha" => "required"
		));

		$credentials = [
			"email" => $request["email"],
			"password" => $request["senha"]
		];

		Auth::attempt($credentials);

		// Hard coded o return para home
		return redirect()->action("PageController@getHome");
	}

}
