@extends("Roots_Templates.master")

@section("titulo")
	CADASTRO
@endsection

@section("css")
@endsection

@section("conteudo")
	<form id="loginform" class="form-horizontal form" role="form" action="{{action("UsuarioController@postCadastrar")}}" method="POST">
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

		<div class="senha-confirmation-container">
			@if ($errors->has("senha_confirmation"))
				<div class="alert alert-danger">{{$errors->first("senha_confirmation")}}</div>
			@endif
			<label class="sr-only" for="senha_confirmation">Confirmação da Senha</label>
			<div style="margin-bottom: 25px" class="input-group">
				<span class="input-group-addon"><i class="glyphicon icon-unlock"></i></span>
				<input class="form-control senha-confirmation" type="password" name="senha_confirmation" id="senha_confirmation" value="{{old("senha_confirmation")}}" placeholder="Confirmação de Senha"/>
			</div>
		</div>
		<div id="btn-form" class="form-group">
			<div class="container">
				<input class="btn btn-success" type="submit" value="Cadastrar"/>
				<input class="btn btn-warning" type="reset" value="Redefinir"/>
			</div>
		</div>
	</form>
@endsection

@section("js")
@endsection
