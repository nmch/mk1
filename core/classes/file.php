<?php

class File
{
	/**
	 * DBのデータをCSVにエクスポートする
	 *
	 * @param Database_Resultset $data
	 * @param array              $headers
	 * @param string             $csv_filename
	 * @param array              $funcs
	 *
	 * @return Response_File|null
	 * @throws Exception
	 */
	static function respond_obj_as_csv($data, $headers, $csv_filename = null, $funcs = [])
	{
		$columns = [];
		if( $data instanceof Database_Resultset ){
			$model_name = $data->get_fetch_as();
			if( $model_name ){
				/** @var Model $model */
				$model   = new $model_name;
				$columns = $model->schema();
			}
		}
		elseif( is_array($data) ){
			// nop
		}
		else{
			throw new Exception('invalid data type');
		}
		//Log::debug($columns);
		
		$tmp_filepath = tempnam(sys_get_temp_dir(), 'CSV');
		$fp           = fopen($tmp_filepath, 'w+t');
		
		/**
		 * $dataがModel_Queryからのデータだった場合はモデルからカラム定義を取得して補完する
		 */
		if( $columns ){
			foreach($headers['columns'] as $header_key => $header){
				//Log::debug($header);
				if( empty($header['label']) ){
					$headers['columns'][$header_key]['label'] = isset($header['col']) ? Arr::get($columns, "columns.{$header['col']}.desc") : null;
				}
			}
		}
		fputcsv($fp, array_column($headers['columns'], 'label'));
		//Log::debug(array_column($headers,'label'));
		
		//Log::debug('$headers=', $headers);
		foreach($data as $item){
			$line = [];
			if( isset($funcs['export_filter']) ){
				$r = $funcs['export_filter']($item);
				if( $r ){
					continue;
				}
			}
			foreach($headers['columns'] as $header){
				if( isset($header['col']) ){
					if( is_object($item) ){
						$line[] = $item->{$header['col']};
					}
					else{
						$line[] = Arr::get($item, $header['col']);
					}
				}
				else{
					$line[] = Arr::get($header, 'value');
				}
			}
			fputcsv($fp, $line);
			unset($line);
		}
		
		fclose($fp);
		
		$converted_filepath = static::convert_encoding($tmp_filepath, 'SJIS', 'UTF-8');
		unlink($tmp_filepath);
		
		if( ! $csv_filename ){
			$csv_filename = 'data.csv';
		}
		
		return new Response_File($converted_filepath, static::make_download_filename($csv_filename));
	}
	
	static function convert_encoding($filepath, $to = null, $from = null)
	{
		$fp = fopen($filepath, "rt");
		
		$tmp_filepath = tempnam(null, "CSV");
		$fw           = fopen($tmp_filepath, "w+t");
		
		while($line = fgets($fp)){
			fputs($fw, mb_convert_encoding($line, 'SJIS-win', 'UTF-8'));
		}
		
		fclose($fw);
		fclose($fp);
		
		return $tmp_filepath;
	}
	
	/**
	 * ダウンロードするファイル名を生成する
	 *
	 * [システムごとのヘッダ] + '-' + ファイル名(ベース) + -Ymd-His + .拡張子
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	static function make_download_filename($filename)
	{
		$filename = explode('.', $filename, 2);
		$filename = Config::get('app.system_prefix') . '-' . Arr::get($filename, 0) . date('-Ymd-His') . '.' . Arr::get($filename, 1);
		
		return $filename;
	}
	
	/**
	 * 再帰的にファイルまたはディレクトリを削除
	 */
	static function rm($filepath)
	{
		$filepath = rtrim($filepath, '/');
		//Log::coredebug("File::rm : $filepath");
		if( is_dir($filepath) ){
			$objects = scandir($filepath);
			foreach($objects as $object){
				if( $object == "." || $object == ".." ){
					continue;
				}
				
				static::rm($filepath . "/" . $object);
			}
			unset($objects);
			$r = rmdir($filepath);
			if( $r !== true ){
				throw new MkException("rmdir {$filepath} failed");
			}
		}
		else{
			$r = unlink($filepath);
			if( $r !== true ){
				throw new MkException("unlink {$filepath} failed");
			}
		}
	}
}
