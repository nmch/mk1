<?php

class File
{
	const ENCODING_SJIS = 'SJIS-win';
	const ENCODING_UTF8 = 'UTF-8';
	const EOL_LF        = "\n";
	const EOL_CRLF      = "\r\n";
	
	const MIME_CSV = 'text/csv';
	
	protected $filepath;
	protected $filename;
	protected $mime;
	
	function __construct($filepath, $filename = null, $mime = null)
	{
		if( ! file_exists($filepath) || ! is_file($filepath) || ! is_readable($filepath) ){
			throw new MkException("file not found");
		}
		$this->filepath = $filepath;
		$this->filename = $filename ?: basename($filepath);
		$this->mime     = $mime ?: "application/octet-stream";
	}
	
	static function create_from_uploaded_file(array $file)
	{
		$filename = $file['tmp_name'] ?? null;
		if( ! is_uploaded_file($filename) ){
			throw new Exception();
		}
		
		return new \File($filename);
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
	
	function get_filesize()
	{
		return filesize($this->get_filepath());
	}
	
	function get_contents()
	{
		return file_get_contents($this->get_filepath());
	}
	
	function hash($algo)
	{
		return hash_file($algo, $this->get_filepath());
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
	function read_as_csv($convert_encoding = null, $ignore_head_lines = 0, $csv_header = null, $pass_raw_column = false, $pass_raw_line = false)
	{
		/**
		 * エンコーディング自動検出
		 */
		$skip_bom = null;
		if( ! $convert_encoding ){
			$fp         = fopen($this->filepath, "rt");
			$fitst_line = fread($fp, 10 * 1024);
			// UTF-8 BOM
			if( substr($fitst_line, 0, 3) === "\xEF\xBB\xBF" ){
				$source_encoding = 'UTF-8';
				$skip_bom        = 3;
			}
			else{
				$source_encoding = mb_detect_encoding($fitst_line, [
					'ASCII',
					'UTF-8',
					'SJIS-win',
				]);
			}
			if( $source_encoding !== File::ENCODING_UTF8 ){
				$convert_encoding = $source_encoding;
			}
		}
		
		$src_file = $convert_encoding ? $this->convert_encoding(File::ENCODING_UTF8, $convert_encoding) : $this;
		
		$fp = $src_file->fopen();
		if( $skip_bom ){
			fseek($fp, $skip_bom);
		}
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
			
			if( $pass_raw_line ){
				$item = $line;
			}
			else{
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
			}
			
			yield $line_num => $item;
			
			unset($item);
		}
		
		fclose($fp);
	}
	
	static function get_csv_from_obj($data, $headers, $csv_filename = null, $funcs = [], $array_delimiter = ','): File
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
				if( array_key_exists('export', $header) && ! $header['export'] ){
					// export=falseが明示的に設定されていた場合は値をセットしない
					$line[] = '';
				}
				elseif( isset($funcs['get_value']) ){
					$line[] = $funcs['get_value']($item, $header);
				}
				else{
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
			}
			
			foreach($line as $line_key => $line_item){
				if( is_array($line_item) ){
					$line[$line_key] = implode($array_delimiter, $line_item);
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
		
		return $converted_file;
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
	static function respond_obj_as_csv($data, $headers, $csv_filename = null, $funcs = [], $array_delimiter = ',')
	{
		$converted_file = static::get_csv_from_obj($data, $headers, $csv_filename, $funcs, $array_delimiter);
		
		return new Response_File($converted_file->get_filepath(), static::make_download_filename($csv_filename));
	}
	
	function convert_encoding($to = File::ENCODING_SJIS, $from = File::ENCODING_UTF8): File
	{
		$convert_eol_from = null;
		$convert_eol_to   = null;
		if( $to === File::ENCODING_SJIS && $from === File::ENCODING_UTF8 ){
			// UTF-8からSJISへの変換時には改行コードもLFからCRLFに置換する
			$convert_eol_from = \File::EOL_LF;
			$convert_eol_to   = \File::EOL_CRLF;
		}
		if( $to === File::ENCODING_UTF8 && $from === File::ENCODING_SJIS ){
			// SJISからUTF-8への変換時には改行コードもCRLFからLFに置換する
			$convert_eol_from = \File::EOL_CRLF;
			$convert_eol_to   = \File::EOL_LF;
		}
		
		$fp = fopen($this->filepath, "rt");
		
		$tmp_filepath = tempnam(null, "CSV");
		$fw           = fopen($tmp_filepath, "w+t");
		
		while($line = fgets($fp)){
			if( $convert_eol_from && $convert_eol_to ){
				$line = str_replace($convert_eol_from, $convert_eol_to, $line);
			}
			$line = mb_convert_encoding($line, $to, $from);
			fputs($fw, $line);
			unset($line);
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
		if( is_array($filename) ){
			$filename = $filename['filename'] ?? '';
		}
		else{
			$exploded_filename = explode('.', $filename, 2);
			$filename          = '';
			if( $system_prefix = Config::get('app.system_prefix') ){
				$filename .= "{$system_prefix}-";
			}
			$filename .= ($exploded_filename[0] ?? '');
			$filename .= date('-Ymd-His.');
			$filename .= ($exploded_filename[1] ?? '');
		}
		
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
