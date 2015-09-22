<?php

namespace AlmeidaFogo\LaravelModules\LaravelModules;

use Illuminate\Console\Command;

class FileManager{

	/**
	 * Copia recurssivamente os arquivos e diretorios de um array de paths
	 *
	 * @param array $errors
	 * @param bool $copyAll
	 * @param array $rollback
	 * @param Command $command
	 * @param array $paths
	 */
	public static function recursiveCopy(array &$errors, &$copyAll, array &$rollback, Command $command, array $paths) {
		//loop em todos os diretorios de destino
		foreach($paths as $key => $value){
			if(!is_dir($value)){//Se o diretorio não existir
				//Cria o diretorio que não existe
				if (mkdir($value)){
					//Cria registro no rollback dizendo uma pasta foi criada
					$rollback[Strings::ROLLBACK_DIR_CREATED_TAG][] = $value;
				}
			}
		}

		//Loop em todas as pastas
		foreach($paths as $key => $value)
		{
			if ( empty( $errors ) )
			{//Se os comandos anteriores rodarem com sucesso
				//Copia lista de arquivos no diretorio para variavel arquivos
				$arquivos = scandir( $key );
				//Loop em todos os arquivos do modulo
				for ( $i = Constants::FIRST_FILE ; $i < count( $arquivos ) ; $i++ )
				{
					if ( empty( $errors ) )
					{
						if (!is_dir( $key . $arquivos[ $i ] )){
							//Se os comandos anteriores rodarem com sucesso e o arquivo não for uma pasta
							$explodedFileName = explode( Strings::PATH_SEPARATOR , $value.$arquivos[$i]);

							$filename = $explodedFileName[ count( $explodedFileName ) - 1 ];

							//Verifica se o arquivo existe
							if ( !file_exists( $value . $arquivos[ $i ] ) )
							{
								//Cria registro no rollback dizendo que o arquivo foi copiado
								$rollback[ Strings::ROLLBACK_MODULE_ORDINARY_FILE_COPY_TAG ][ EscapeHelper::encode( $value . $arquivos[ $i ] ) ] = Strings::EMPTY_STRING;
								//verifica se a copia ocorreu com sucesso
								if ( copy( $key . $arquivos[ $i ] , $value . $arquivos[ $i ] ) == false )
								{
									//Printa msg de erro
									$errors[ ] = ( Strings::ordinaryFileCopyError( $value . $arquivos[ $i ] ) );
								}
							}
							else if ( strtoupper( $filename ) != strtoupper( Strings::GIT_KEEP_FILE_NAME ) )
							{//Caso ja exista um arquivo com o mesmo nome no diretorio de destino
								//Inicializa variavel que vai receber resposta do usuario dizendo o que fazer
								// com o conflito
								$answer = Strings::EMPTY_STRING;
								//Enquanto o usuario não devolver uma resposta valida
								while ( $copyAll != true && $answer != Strings::SHORT_YES && $answer != Strings::SHORT_NO && $answer != Strings::SHORT_ALL && $answer != Strings::SHORT_CANCEL )
								{
									//Faz pergunta para o usuario de como proceder
									$answer = $command->ask( Strings::replaceOrdinaryFiles( $value . $arquivos[ $i ] ) ,
															 false );
								}
								//Se a resposta for sim, ou all
								if ( strtolower( $answer ) == Strings::SHORT_YES || strtolower( $answer ) == Strings::SHORT_ALL || $copyAll == true )
								{
									//se a resposta for all
									if ( strtolower( $answer ) == Strings::SHORT_ALL )
									{
										//seta variavel all para true
										$copyAll = true;
									}
									//Faz backup do arquivo que será substituido
									$rollback[ Strings::ROLLBACK_MODULE_ORDINARY_FILE_COPY_TAG ][ EscapeHelper::encode( $value . $arquivos[ $i ] ) ] = EscapeHelper::encode( file_get_contents( $value . $arquivos[ $i ] ) );
									//verifica se a substituição ocorreu com sucesso
									if ( copy( $key . $arquivos[ $i ] , $value . $arquivos[ $i ] ) == false )
									{//Se houver erro ao copiar arquivo
										//Printa msg de erro
										$errors[ ] = ( Strings::ordinaryFileReplaceError( $value . $arquivos[ $i ] ) );
									}
								}
								else if ( strtolower( $answer ) == Strings::SHORT_CANCEL )
								{//se a resposta foi cancelar
									//Printa msg de erro
									$errors[ ] = ( Strings::userRequestedAbort() );
									//break the file loop
									break( 2 );
								}
							}
						}else{
							$newPath = [$key.$arquivos[$i]."/" => $value.$arquivos[$i]."/"];

							self::recursiveCopy($errors, $copyAll, $rollback, $command, $newPath);
						}
					}
				}
			}
		}
	}

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
			if ($item == Strings::CURRENT_DIR_SYMBOL || $item == Strings::PARENT_DIR_SYMBOL) {
				continue;
			}

			if (!self::deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
				return false;
			}

		}

		return rmdir($dir);
	}

}