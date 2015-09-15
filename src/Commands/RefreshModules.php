<?php

namespace AlmeidaFogo\LaravelModules\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

class RefreshModule extends Command
{

	public static $errors;

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'module:refresh {type?} {name?}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Atualiza os arquivos de um modulo de app para a aplicacao (nao atualiza migrations ja rodadas)';

	/**
	 * Create a new command instance.
	 *
	 * @return RefreshModule
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		//saved instace of this
		$that = $this;

		//Tipo do modulo
		$moduleType = $this->argument("type");

		//Nome do modulo
		$moduleName = $this->argument("name");

		//Inicializa variavel erros
		RefreshModule::$errors = [];

		//Prepara variavel de rollback caso aja erro
		$rollback = [];

		//TODO: fazer esse comando

		return false;
	}
}
