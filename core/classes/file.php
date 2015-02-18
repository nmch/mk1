<?

class File
{
	/**
	 * 再帰的にファイルまたはディレクトリを削除
	 */
	static function rm($filepath)
	{
		$filepath = rtrim($filepath, '/');
		Log::debug("File::rm : $filepath");
		if( is_dir($filepath) ){
			$objects = scandir($filepath);
			foreach($objects as $object){
				if( $object == "." || $object == ".." ){
					continue;
				}

				static::rm($filepath . "/" . $object);
			}
			unset($objects);
			rmdir($filepath);
		}
		else{
			unlink($filepath);
		}
	}
}
