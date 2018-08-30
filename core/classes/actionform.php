<?php

class Actionform implements ArrayAccess
{
	use Singleton;
	
	public  $validation_results = [];
	private $config             = [];
	private $values             = [];
	private $values_default     = [];
	private $validated_values   = [];
	private $request_method;
	private $useragent;
	private $referer;
	private $server_vars;
	
	/*
	private $target_array_key;
	private $models = array();
	private $list_models = array();
	private $handle_as_list = array();
	private $populated;
	private $validation_rules = array();
	private $list_validation_rules = array();
	private $af_filter;
	var $validation_results = array();
	*/
	
	/**
	 * コンストラクタ
	 *
	 * @param boolean $clean_init trueにするとフォーム入力($_GET, $_POST, $_FILES)を自動的に読み込まない
	 */
	function __construct($clean_init = false)
	{
		$this->config = Config::get('form', []);
		
		if( ! $clean_init ){
			$this->values = array_merge($_GET ?: [], $_POST ?: [], $_REQUEST ?: []);
			//echo "<PRE>values = "; print_r($this->values); echo "</PRE>";
			//$this->af_filter = new \Model_ActionformFilter;
			//$this->request_method = Arr::get($_SERVER,'REQUEST_METHOD','');
			$this->referer     = Arr::get($_SERVER, 'HTTP_REFERER');
			$this->useragent   = Arr::get($_SERVER, 'HTTP_USER_AGENT');
			$this->server_vars = $_SERVER;
			
			// アップロードされたファイル
			if( is_array($_FILES) && count($_FILES) ){
				foreach($_FILES as $file_key => $file){
					if( is_array($file['tmp_name']) ){
						$files = [];
						foreach($file['tmp_name'] as $file_index => $file_tmpname){
							if( is_array($file_tmpname) ){
								/* たとえば name="upload_files[1][]" という2次元配列にしたときは下のコードは動かなくなる。
								   2次元以上の配列にするときは様々な構造があり得るので、mk1ではサポートしないことにする。*/
								continue;
							}
							if( ! $file_tmpname ){
								continue;
							}
							if( is_uploaded_file($file_tmpname) ){
								$files[$file_index] = [
									'tmp_name' => $file_tmpname,
									'name'     => Arr::get($file, "name.{$file_index}"),
									'type'     => Arr::get($file, "type.{$file_index}"),
									'error'    => Arr::get($file, "error.{$file_index}"),
									'size'     => Arr::get($file, "size.{$file_index}"),
								];
							}
						}
						$this->set($file_key, $files);
					}
					else{
						if( $file['tmp_name'] && is_uploaded_file($file['tmp_name']) ){
							$this->set($file_key, $file);
						}
					}
				}
			}
			
			if( $this->get_config('import_db_schemas') ){
				$autoconfig = Cache::get('af_autoconfig', 'core_db', function (){
					$autoconfig = [];
					foreach(Database_Schema::get() as $table_name => $table){
						foreach(Arr::get($table, 'columns') as $col_name => $col){
							$rule = [
								'name'    => Arr::get($col, 'desc'),
								'filter'  => '',
								'typecat' => Arr::get($col, 'type_cat'),
							];
							/** @see https://www.postgresql.jp/document/9.3/html/catalog-pg-type.html */
							switch($rule['typecat']){
								// 数値型
								case 'N':
									$rule['filter'] = ['hankaku', 'only0to9'];
									
									$autoconfig['global.key.' . $col_name . '_from'] = [
										'name'   => $rule['name'] . ' FROM',
										'filter' => $rule['filter'],
									];
									$autoconfig['global.key.' . $col_name . '_to']   = [
										'name'   => $rule['name'] . ' TO',
										'filter' => $rule['filter'],
									];
									break;
								// 日付時刻型
								case 'D':
									$rule['filter'] = ['hankaku', 'hantozen', 'trim'];
									
									$autoconfig['global.key.' . $col_name . '_from'] = [
										'name'   => $rule['name'] . ' FROM',
										'filter' => $rule['filter'],
									];
									$autoconfig['global.key.' . $col_name . '_to']   = [
										'name'   => $rule['name'] . ' TO',
										'filter' => $rule['filter'],
									];
									break;
								
								case 'B': // 論理値型
								case 'S': // 文字列型
								case 'A': // 配列型
									$rule['filter'] = ['hankaku', 'hantozen', 'trim', 'empty2null'];
									break;
								
								case 'C': // 複合型
								case 'U': // ユーザ定義型
								case 'E': // 列挙型
								case 'G': // 幾何学型
								case 'P': // 仮想型
								case 'I': // ネットワークアドレス型
								case 'R': // 範囲型
								case 'T': // 時間間隔型
								case 'V': // ビット列型
									break;
								default:
									Log::coredebug("[af] Unknown {$col_name} typecat: ", Arr::get($col, 'type_cat'), $col);
							}
							$autoconfig['global.key.' . $col_name] = $rule;
						}
					}
					
					return $autoconfig;
				});
				foreach($autoconfig as $key => $value){
					$this->set_config($key, $value);
				}
			}
		}
		
		return $this;
	}
	
	/**
	 * @param string|array $name
	 * @param mixed        $value
	 * @param bool         $set_default
	 *
	 * @return Actionform
	 */
	function set($name, $value = null, $set_default = false)
	{
		if( is_array($name) || $name instanceof ArrayAccess ){
			foreach($name as $key => $value){
				$this->set($key, $value, $set_default);
			}
		}
		else{
			//echo "<PRE>[af] set $name (default:$set_default)"; var_dump($value); print_r(debug_backtrace(NULL,3));
			
			if( $set_default ){
				$this->values_default[$name] = $value;
			}
			else{
				$this->values[$name] = $value;
			}
		}
		
		return $this;
	}
	
	//public function save($name,$model_list = array())
	
	public function get_config($name, $default = [])
	{
		$config = Config::get('form.' . $name, $default);
		
		if( is_array($config) ){
			// Modelの定義があった場合はModel内のformコンフィグもマージする
			if( $model_name = Arr::get($config, 'model') ){
				/** @see \Model::form() */
				$r = call_user_func([$model_name, 'form']);
				if( is_array($r) ){
					$config = Arr::merge($config, $r);
				}
			}
		}
		
		return $config;
	}
	
	private function set_config($name, $value)
	{
		Config::set('form.' . $name, $value);
		
		//Arr::set($this->config,$name,$value);
		return $this;
	}
	
	public static function method()
	{
		return strtoupper(Arr::get($_SERVER, 'REQUEST_METHOD', ''));
	}
	
	public static function request_uri()
	{
		return Arr::get($_SERVER, 'REQUEST_URI', '');
	}
	
	function __unset($name)
	{
		return $this->delete($name);
	}
	
	/**
	 * キーを削除する
	 *
	 * @param string $name キー
	 *
	 * @return Actionform
	 */
	function delete($name)
	{
		//Log::coredebug("[af] unset $name",$this);
		if( array_key_exists($name, $this->values) ){
			unset($this->values[$name]);
		}
		if( array_key_exists($name, $this->values_default) ){
			unset($this->values_default[$name]);
		}
		if( array_key_exists($name, $this->validated_values) ){
			unset($this->validated_values[$name]);
		}
		if( array_key_exists($name, $this->validation_results) ){
			unset($this->validation_results[$name]);
		}
		
		return $this;
	}
	
	public function save($name, $model = null)
	{
		$this->validate($name);
		//Log::coredebug("validated_values = ",$this->validated_values);
		
		$preset = Config::get('form.preset.' . $name, []);
		// Modelの定義があった場合はModel内のformコンフィグもマージする
		if( class_exists($name) && is_subclass_of($name, 'Model') ){
			/** @see \Model::form() */
			$r = call_user_func([$name, 'form']);
			if( is_array($r) ){
				$preset = Arr::merge($preset, $r);
			}
		}
		if( ! $preset ){
			throw new MkException('invalid preset name');
		}
		
		if( ! $model ){
			$model = Arr::get($preset, 'model');
			if( ! $model ){
				throw new MkException('model not found');
			}
		}
		if( is_array($model) ){
			$model = reset($model);
		}
		
		if( ! is_object($model) ){
			if( ! class_exists($model) ){
				throw new MkException('model not defined');
			}
			if( ! is_subclass_of($model, 'Model') ){
				throw new MkException('specified name is not subclass of Model');
			}
			$primary_key = $model::primary_key();
			if( ! $primary_key ){
				throw new MkException('primary key is not defined');
			}
			if( $this->$primary_key ){
				try {
					$obj = $model::find($this->$primary_key);
				} catch(RecordNotFoundException $e){
				}
			}
			if( empty($obj) ){
				$obj = new $model;
			}
		}
		else{
			$obj = $model;
		}
		
		//Log::coredebug("[af save] keys=",$obj->columns(),$obj);
		foreach($obj->columns() as $key){
			if( array_key_exists($key, $this->validated_values[$name]) ){
				//Log::coredebug("[af save] set $key",$this->validated_values[$name][$key]);
				$obj->$key = $this->validated_values[$name][$key];
			}
		}
		$obj->save();
		
		return $obj;
	}
	
	/**
	 * バリデーション実行
	 *
	 * @param string $name
	 *
	 * @return Actionform
	 * @throws MkException
	 * @throws ValidateErrorException
	 */
	public function validate($name = null)
	{
		$validation = $this->get_validation_rules($name);
		
		$this->validated_values[$name] = $validated_values = [];
		$validation_results[$name]     = $validation_results = [];
		$is_error                      = false;
		
		$default_rules = Arr::get($validation, 'default_rules', []);
		
		foreach($validation['key'] as $key => $rules){
			if( ! is_array($rules) ){
				$key   = $rules;
				$rules = [];
			}
			if( $default_rules ){
				$rules = array_merge($default_rules, $rules);
			}
			//Log::coredebug("[af] validate $key","key_exists = ".$this->key_exists($key,true).'/'.$this->key_exists($key),$rules);
			
			// only_existsがtrueの場合、キーがデータに存在しない場合に一切の処理を行わない
			if( Arr::get($rules, 'only_exists') === true ){
				if( ! $this->key_exists($key) ){
					continue;
				}
			}
			
			try {
				// デフォルトが設定されていて、キーがデータに存在しない場合はデフォルトをset()する。キーのチェックはvalue_defaultを省く。
				if( ! $this->key_exists($key, true) && array_key_exists('default', $rules) ){
					$this->set($key, $rules['default']);
				}
				
				$value = $this->get($key);
				//Log::coredebug("[af] target value=",$value);
				
				// 値がデータに存在しない場合はフィルタを適用しない
				if( $this->key_exists($key, true) ){
					//Log::coredebug("rules filter (key=$key) ",$rules['filter']);
					if( isset($rules['filter']) && is_array($rules['filter']) ){
						foreach($rules['filter'] as $filter => $option){
							if( is_numeric($filter) ){
								$filter = $option;
								$option = [];
							}
							
							// フィルタ名が配列として指定されている場合は
							// 値が配列の場合にのみ適用し、フィルタには値を配列のまま渡す
							if( is_array($filter) ){
								$filter = array_pop($filter);
								$value  = static::unit_filter($value, $filter, $option);
							}
							else{
								// 値が配列の場合は各要素に対してフィルタを適用する
								if( is_array($value) ){
									foreach($value as $value_key => $value_item){
										$value[$value_key] = static::unit_filter($value_item, $filter, $option);
									}
								}
								else{
									$value = static::unit_filter($value, $filter, $option);
								}
								//Log::coredebug("[af] filter $filter",$value);
							}
						}
					}
					$this->set($key, $value);
					//Log::coredebug("[af] set $key",$value);
				}
				
				if( isset($rules['validation']) ){
					foreach($rules['validation'] as $validation => $option){
						static::unit_validate($value, $validation, $option, Arr::get($rules, 'ignore_validation', []));
					}
				}
				
				// 値がデータに存在しない場合はvalidated_valuesに代入しない
				if( $this->key_exists($key) ){
					//Log::coredebug("store validated_values $key",$value);
					$validated_values[$key] = $value;    //配列の場合がある
				}
				$validation_results[$key] = null;
			} catch(ValidateErrorException $e){
				Log::coredebug("[af] validation error key=[$key] msg=" . $e->getMessage());
				$validation_results[$key] = [
					'key'     => $key,
					'rules'   => $rules,
					'message' => $e->getMessage(),
				];
				$is_error                 = true;
			}
		}
		$this->validated_values[$name]   = $validated_values;
		$this->validation_results[$name] = $validation_results;
		$this->validation_error          = $is_error;
		if( $is_error ){
			$exception = new ValidateErrorException();
			$exception->set_af($this);
			throw $exception;
		}
		
		//Log::coredebug("[af validate] validated_values=",$this->validated_values,$this->values);
		return $this;
	}
	
	private function get_validation_rules($name = null)
	{
		if( ! $name ){
			$validation = $this->get_config('global');
		}
		else{
			$validation  = $this->get_config('preset.' . $name);
			$parent_name = Arr::get($validation, 'inherit');
			if( $parent_name ){
				$parent     = $this->get_config('preset.' . $parent_name);
				$validation = Arr::merge($parent, $validation);
				//Log::coredebug("merged rules = ",$validation);
			}
		}
		
		// Modelの定義があった場合はModel内のformコンフィグもマージする
		if( class_exists($name) && is_subclass_of($name, 'Model') ){
			/** @see \Model::form() */
			$r = call_user_func([$name, 'form']);
			if( is_array($r) ){
				$validation = Arr::merge($validation, $r);
			}
		}
		
		if( ! $validation ){
			throw new MkException("empty validation rules ($name)");
		}
		
		return $validation;
	}
	
	/**
	 * 値データに指定キーが存在するか調べる
	 *
	 * @param string  $name        キー
	 * @param boolean $only_values true=valuesのみ調べる / false=values_defaultも調べる
	 *
	 * @return bool
	 */
	function key_exists($name, $only_values = false)
	{
		if( $only_values ){
			return array_key_exists($name, $this->values);
		}
		else{
			return (array_key_exists($name, $this->values) || array_key_exists($name, $this->values_default));
		}
	}
	
	function get($name, $default = null)
	{
		if( array_key_exists($name, $this->values) ){
			return $this->values[$name];
		}
		else{
			if( array_key_exists($name, $this->values_default) ){
				return $this->values_default[$name];
			}
			else{
				return $default;
			}
		}
	}
	
	/**
	 * Actionformフィルタを値に対して実行
	 *
	 * @param string $value
	 * @param string $filter
	 * @param array  $option
	 *
	 * @throws MkException
	 * @return string
	 */
	public static function unit_filter($value, $filter, $option = [])
	{
		//Log::coredebug("filter $filter", $value, $option);
		
		$func = static::load("actionform/filter/" . strtolower($filter) . ".php");
		if( ! is_callable($func) ){
			throw new MkException("illegal filter");
		}
		$value = call_user_func($func, $value, $option);    //返り値は配列の可能性がある
		
		return $value;
	}
	
	public static function load($filename)
	{
		return include $filename;
	}
	
	/**
	 * 単体のバリデーションを実行する
	 *
	 * @param mixed $value      バリデーション対象の値
	 * @param mixed $validation バリデーションルール
	 * @param array $option     オプション
	 * @param array $ignore     無視リスト (バリデーション名がこのリストの要素にあれば処理を行わない)
	 */
	public static function unit_validate($value, $validation, $option = [], $ignore = [])
	{
		if( is_array($validation) ){
			foreach($validation as $validation_key => $validation_value){
				static::unit_validate($value, $validation_key, $validation_value, $ignore);
			}
		}
		else{
			if( is_array($value) ){
				foreach($value as $value_key => $value_item){
					static::unit_validate($value_item, $validation, $option, $ignore);
				}
			}
			else{
				if( is_numeric($validation) && $option ){
					$validation = $option;
					$option     = [];
				}
				
				if( ! is_string($validation) && is_callable($validation) ){    //is_callable()だけだと'date'などの標準関数と同じ名前だと標準関数が呼ばれてしまう
					// validationがコードの場合、実行して必要なvalidation名を戻してもらう
					$af             = static::instance();
					$add_validation = call_user_func($validation, $af);
					if( $add_validation ){
						if( ! is_array($add_validation) ){
							$add_validation = [$add_validation, []];
						}
						Log::coredebug("[af] add_validation = ", $add_validation);
						call_user_func_array("static::unit_validate", array_merge([$value], $add_validation));
					}
				}
				else{
					if( ! in_array($validation, $ignore) ){
						$func = static::load("actionform/validation/" . strtolower($validation) . ".php");
						call_user_func($func, $value, $option);
					}
				}
			}
		}
	}
	
	function get_by_path($name, $default = null)
	{
		return Arr::get($this->values, $name, Arr::get($this->values_default, $name, $default));
	}
	
	/**
	 * @param string $name
	 *
	 * @return array
	 */
	public function get_validation_result_messages($name = null): array
	{
		$messages = [];
		
		foreach($this->validation_results as $key => $validation_results){
			if( $name && $name !== $key ){
				continue;
			}
			
			foreach($validation_results as $validation_result){
				if( ! is_array($validation_result) ){
					$validation_result = [];
				}
				$message = Arr::get($validation_result, 'message');
				
				if( $message ){
					$item_name  = Arr::get($validation_result, 'name')
					              ?? Arr::get($validation_result, 'rules.name')
					                 ?? Arr::get($validation_result, 'key');
					$message    = "{$item_name} : {$message}";
					$messages[] = $message;
				}
			}
			
		}
		
		return $messages;
	}
	
	public function validation_results($results = null)
	{
		if( is_array($results) ){
			$this->validation_results = $results;
		}
		
		return $this->validation_results;
	}
	
	public function referer()
	{
		return $this->referer;
	}
	
	function get_default($name = null)
	{
		if( ! $name ){
			return $this->values_default;
		}
		else{
			return Arr::get($this->values_default, $name);
		}
	}
	
	function set_default($name, $value = null)
	{
		return $this->set($name, $value, true);
	}
	
	function __get($name)
	{
		return $this->get($name);
	}
	
	function __set($name, $value)
	{
		$this->set($name, $value);
		
		return $this;
	}
	
	/**
	 * Arr::get()を使って値を得る
	 */
	function getarr($name, $default = null)
	{
		return Arr::get($this->as_array(), $name, $default);
	}
	
	function as_array()
	{
		return array_merge($this->values_default, $this->values);
	}
	
	/**
	 * validated_valuesを得る
	 *
	 * @param string $name キー (省略した場合はvalidated_values全体を返す)
	 *
	 * @return array|mixed
	 */
	function get_validated_values($name = null)
	{
		return $name ? Arr::get($this->validated_values, $name, []) : $this->validated_values;
	}
	
	function value_exists($name)
	{
		return key_exists($name);
	}
	
	function useragent()
	{
		return $this->useragent;
	}
	
	function is_mobiledevice()
	{
		// CloudFront対応
		if( Arr::get($_SERVER, "HTTP_CLOUDFRONT_IS_MOBILE_VIEWER") === 'true' ){
			return true;
		}
		
		if( $this->useragent ){
			return Cache::get($this->useragent, 'ismobiledevice_by_ua', function ($useragent){
				$browser = get_browser($useragent);
				if( is_object($browser) ){
					return $browser->ismobiledevice;
				}
			}
			);
		}
		
		return null;
	}
	
	/**
	 * 配列対応htmlspecialchars
	 *
	 * @param mixed $value
	 *
	 * @return array|string
	 */
	function _htmlspecialchars($value)
	{
		if( is_array($value) ){
			array_walk($value, [$this, "_htmlspecialchars"]);
		}
		else{
			$value = htmlspecialchars($value);
		}
		
		return $value;
	}
	
	function is_ssl()
	{
		// #3271 AWS対応
		return ($this->server_vars('HTTPS') === 'on' || $this->server_vars('HTTP_X_FORWARDED_PROTO') === 'https');
	}
	
	function server_vars($name)
	{
		return Arr::get($this->server_vars, $name);
	}
	
	function is_ajax_request()
	{
		return strtolower($this->server_vars('HTTP_X_REQUESTED_WITH')) == 'xmlhttprequest';
	}
	
	function set_messages(array $messages)
	{
		foreach($messages as $item){
			$this->set_message(Arr::get($item, 'type'), Arr::get($item, 'message'));
		}
		
		return $this;
	}
	
	function set_message($type, $message)
	{
		$messages = Session::get_flash('messages');
		if( ! $messages || ! is_array($messages) ){
			$messages = [];
		}
		
		$messages[$type][] = $message;
		Log::coredebug("set message($type) : $message");
		
		Session::set_flash('messages', $messages);
	}
	
	/**
	 * 与えられたModelのカラムと同じ名前の配列全てを統合し、レコードと同じ形の配列にする
	 *
	 * @param Model $model モデル名
	 *
	 * @return array
	 */
	function make_records_array(Model $model, $default_items = [])
	{
		$list = [];
		foreach($model->columns() as $col){
			if( ! $this->key_exists($col) || ! is_array($this->$col) ){
				continue;
			}
			foreach($this->$col as $index => $value){
				$list[$index][$col] = $value;
			}
		}
		if( $default_items ){
			foreach($list as $key => $item){
				$list[$key] = $item + $default_items;
				
			}
		}
		
		return $list;
	}
	
	public function offsetSet($offset, $value)
	{
		$this->set($offset, $value);
	}
	
	public function offsetGet($offset)
	{
		return $this->get($offset);
	}
	
	public function offsetExists($offset)
	{
		return $this->key_exists($offset);
	}
	
	public function offsetUnset($offset)
	{
		$this->delete($offset);
	}
}
