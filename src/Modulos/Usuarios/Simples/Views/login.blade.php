@extends("Roots_Templates.master")

@section("titulo")
	LOGIN
@endsection

@section("css")
@endsection

@section("conteudo")
	<form id="loginform" class="form-horizontal form" role="form" action="{{action("UsuarioController@postLogin")}}" method="POST">
		{!!csrf_field()!!}
		@if ($errors->has("email"))
			<div class="alert alert-danger">{{$errors->first("email")}}</div>
		@endif
		<label class="sr-only" for="email">E-mail</label>
		<div style="margin-bottom: 25px" class="input-group">
			<span class="input-group-addon"><i class="glyphicon glyphicon-globe"></i></span>
			<input class="form-control" type="text" name="email" id="email" value="{{old("email")}}" placeholder="E-mail"/>
		</div>

		@if ($errors->has("senha"))
			<div class="alert alert-danger">{{$errors->first("senha")}}</div>
		@endif
		<label class="sr-only" for="senha">Senha</label>
		<div style="margin-bottom: 25px" class="input-group">
			<span class="input-group-addon"><i class="glyphicon icon-lock"></i></span>
			<input class="form-control senha-input" type="password" name="senha" id="senha" value="{{old("senha")}}" placeholder="Senha"/>
			<span class="input-group-addon"><input class="senha-check" name="senha_checked" type="checkbox" checked/></span>
		</div>

		<div id="btn-form" class="form-group">
			<div class="container">
				<input class="btn btn-success" type="submit" value="Login"/>
			</div>
		</div>
	</form>
@endsection

@section("js")
@endsection
