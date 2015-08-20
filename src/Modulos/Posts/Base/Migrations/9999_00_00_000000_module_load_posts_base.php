<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModuleLoadPostsBase extends Migration {

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
				'module_name' => 'Posts.Base',
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
		\DB::table('project_modules')->where('module_name', '=', 'Posts.Base')->delete();
	}

}
