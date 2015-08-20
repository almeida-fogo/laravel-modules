<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModuleRootsTemplates extends Migration {

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
				'module_name' => 'Roots.Templates',
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
		\DB::table('project_modules')->where('module_name', '=', 'Roots.Templates')->delete();
	}

}
