<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModuleLoadComentariosFloor extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		// Insert some stuff
		DB::table('project_modules')->insert(
			array(
				'module_name' => 'Comentarios.Floor',
			)
		);
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		\DB::table('project_modules')->where('module_name', '=', 'Comentarios.Floor')->delete();
	}

}
