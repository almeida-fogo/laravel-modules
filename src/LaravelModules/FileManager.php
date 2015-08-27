<?php

namespace AlmeidaFogo\LaravelModules\LaravelModules;

class FileManager{

	/**
	 * Remove diretorio de forma recurssiva (remove mesmo com arquivos e pastas dentro)
	 *
	 * @param string $dir
	 * @return bool
	 */
	public static function deleteDirectory($dir) {
		if (!file_exists($dir)) {
			return true;
		}

		if (!is_dir($dir)) {
			return unlink($dir);
		}

		foreach (scandir($dir) as $item) {
			if ($item == '.' || $item == '..') {
				continue;
			}

			if (!self::deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
				return false;
			}

		}

		return rmdir($dir);
	}

	//		function copy($source, $dest, $rollback, $permissions = 0755)
	//		{
	//			// Check for symlinks
	//			if (is_link($source)) {
	//				return symlink(readlink($source), $dest);
	//			}
	//
	//			// Simple copy for a file
	//			if (is_file($source)) {
	//				return copy($source, $dest);
	//			}
	//
	//			// Make destination directory
	//			if (!is_dir($dest)) {
	//				if (mkdir($dest, $permissions) == false){
	//					//Cria registro no rollback dizendo uma pasta foi criada
	//					$rollback["dir-created"][] = $dest;
	//				}
	//			}
	//
	//			// Loop through the folder
	//			$dir = dir($source);
	//			while (false !== $entry = $dir->read()) {
	//				// Skip pointers
	//				if ($entry == '.' || $entry == '..') {
	//					continue;
	//				}
	//
	//				// Deep copy directories
	//				copy("$source/$entry", "$dest/$entry", $rollback, $permissions);
	//			}
	//
	//			// Clean up
	//			$dir->close();
	//			return true;
	//		}

}