<?php

class File
{
	const ENCODING_SJIS = 'SJIS-win';
	const ENCODING_UTF8 = 'UTF-8';
	
	protected $filepath;
	protected $filename;
	protected $mime;
	
	function __construct($filepath, $filename = null, $mime = null)
	{
		if( ! file_exists($filepath) ){
			throw new MkException("file not found");
		}
		$this->filepath = $filepath;
		$this->filename = $filename ?: basename($filepath);
		$this->mime     = $mime ?: "application/octet-stream";
	}
	
	function fopen($mode = 'rt')
	{
		return fopen($this->filepath, $mode);
	}
	
	function unlink()
	{
		unlink($this->filepath);
	}
	
	function get_filepath()
	{
		return $this->filepath;
	}
	
	/**
	 * CSVとして読み込む
	 *
	 * @param string $convert_encoding
	 * @param int    $ignore_head_lines
	 * @param array  $csv_header
	 * @param bool   $pass_raw_column true=読み込んだデータをそのまま返す / false=改行を削除し、trimして返す
	 *
	 * @return Generator
	 * @throws AppException
	 */
	function read_as_csv($convert_encoding = null, $ignore_head_lines = 0, $csv_header = null, $pass_raw_column = false)
	{
		/**
		 * エンコーディング自動検出
		 */
		if( ! $convert_encoding ){
			$fp              = fopen($this->filepath, "rt");
			$fitst_line      = fgets($fp);
			$source_encoding = mb_detect_encoding($fitst_line, [
				'ASCII',
				'UTF-8',
				'SJIS-win',
			]);
			if( $source_encoding !== File::ENCODING_UTF8 ){
				$convert_encoding = $source_encoding;
			}
		}
		
		$src_file = $convert_encoding ? $this->convert_encoding(File::ENCODING_UTF8, $convert_encoding) : $this;
		
		$fp       = $src_file->fopen();
		$line_num = 0;
		
		// $ignore_head_linesのぶんだけ先頭行を捨てる
		for($c = 0; $c < $ignore_head_lines; $c++){
			$line_num++;
			fgets($fp);
		}
		
		if( $csv_header === null ){
			$line_num++;
			$csv_header = fgetcsv($fp);
			if( ! $csv_header ){
				throw new AppException('ヘッダがありません');
			}
		}
		
		while($line = fgetcsv($fp)){
			$line_num++;
			
			$item = [];
			foreach($csv_header as $header_key => $header){
				// headerにドットが入っているとArr::get()したときに1次元配列として扱えないため、アンダースコアに変換する
				$header = str_replace('.', '_', $header);
				
				$column = array_key_exists($header_key, $line) ? strval($line[$header_key]) : null;
				
				if( ! $pass_raw_column ){
					$column = str_replace(["\n", "\r"], '', $column);
					$column = trim($column);
				}
				
				$item[$header] = $column;
			}
			
			yield $line_num => $item;
			
			unset($item);
		}
		
		fclose($fp);
	}
	
	/**
	 * DBのデータをCSVにエクスポートする
	 *
	 * @param Database_Resultset|array $data
	 * @param array                    $headers
	 * @param string                   $csv_filename
	 * @param array                    $funcs
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
		
		$tmp_file       = new File($tmp_filepath);
		$converted_file = $tmp_file->convert_encoding();
		unlink($tmp_filepath);
		
		if( ! $csv_filename ){
			$csv_filename = 'data.csv';
		}
		
		return new Response_File($converted_file->get_filepath(), static::make_download_filename($csv_filename));
	}
	
	function convert_encoding($to = File::ENCODING_SJIS, $from = File::ENCODING_UTF8): File
	{
		$fp = fopen($this->filepath, "rt");
		
		$tmp_filepath = tempnam(null, "CSV");
		$fw           = fopen($tmp_filepath, "w+t");
		
		while($line = fgets($fp)){
			fputs($fw, mb_convert_encoding($line, $to, $from));
		}
		
		fclose($fw);
		fclose($fp);
		
		return new File($tmp_filepath);
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
