<?
class Actionform
{
	use Singleton;
	
	private $config = array();
	
	private $values = array();
	private $values_default = array();
	private $validated_values = array();
	
	public  $validation_results = array();
	
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
	 * キーを削除する
	 */
	function delete($name)
	{
		//Log::coredebug("[af] unset $name",$this);
		if(array_key_exists($name,$this->values))
			unset($this->values[$name]);
		if(array_key_exists($name,$this->values_default))
			unset($this->values_default[$name]);
		if(array_key_exists($name,$this->validated_values))
			unset($this->validated_values[$name]);
		if(array_key_exists($name,$this->validation_results))
			unset($this->validation_results[$name]);
		
		return $this;
	}
	function __unset($name)
	{
		return $this->delete($name);
	}
	
	//public function save($name,$model_list = array())
	public function save($name,$model = NULL)
	{
		$this->validate($name);
		//Log::coredebug("validated_values = ",$this->validated_values);
		
		$preset = Config::get('form.preset.'.$name);
		if( ! $preset )
			throw new MkException('invalid preset name');
		
		if( ! $model ){
			$model = Arr::get($preset,'model');
			if( ! $model )
				throw new MkException('model not found');
		}
		if(is_array($model))
			$model = reset($model);
		
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
			if(empty($obj)){
				$obj = new $model;
			}
		}
		else{
			$obj = $model;
		}
		
		//Log::coredebug("[af save] keys=",$obj->columns(),$obj);
		foreach($obj->columns() as $key){
			if(array_key_exists($key,$this->validated_values[$name])){
				//Log::coredebug("[af save] set $key",$this->validated_values[$name][$key]);
				$obj->$key = $this->validated_values[$name][$key];
			}
		}
		$obj->save();
		
		return $obj;
	}
	
	private function get_validation_rules($name = NULL)
	{
		if( ! $name ){
			$validation = $this->get_config('global');
		}
		else{
			$validation = $this->get_config('preset.'.$name);
			$parent_name = Arr::get($validation, 'inherit');
			if($parent_name){
				$parent = $this->get_config('preset.'.$parent_name);
				$validation = Arr::merge($parent, $validation);
				//Log::coredebug("merged rules = ",$validation);
			}
		}
		if( ! $validation )
			throw new MkException("empty validation rules ($name)");
		return $validation;
	}
	public function validate($name = NULL)
	{
		$validation = $this->get_validation_rules($name);
		
		$this->validated_values[$name] = $validated_values = array();
		$validation_results[$name] = $validation_results = array();
		$is_error = false;
		
		$default_rules = Arr::get($validation,'default_rules',array());
		
		foreach($validation['key'] as $key => $rules){
			if( ! is_array($rules) ){
				$key = $rules;
				$rules = array();
			}
			if($default_rules)
				$rules = array_merge($default_rules,$rules);
			//Log::coredebug("[af] validate $key","key_exists = ".$this->key_exists($key,true).'/'.$this->key_exists($key),$rules);
			
			// only_existsがtrueの場合、キーがデータに存在しない場合に一切の処理を行わない
			if(Arr::get($rules,'only_exists') === true){
				if( ! $this->key_exists($key) )
					continue;
			}
			
			try {
				// デフォルトが設定されていて、キーがデータに存在しない場合はデフォルトをset()する。キーのチェックはvalue_defaultを省く。
				if( ! $this->key_exists($key,true) && array_key_exists('default',$rules) ){
					$this->set($key,$rules['default']);
				}
				
				$value = $this->get($key);
				//Log::coredebug("[af] target value=",$value);
				
				// 値がデータに存在しない場合はフィルタを適用しない
				if($this->key_exists($key,true)){
					//Log::coredebug("rules filter (key=$key) ",$rules['filter']);
					if(isset($rules['filter']) && is_array($rules['filter'])){
						foreach($rules['filter'] as $filter => $option){
							if( is_numeric($filter) ){
								$filter = $option;
								$option = array();
							}
							
							// フィルタ名が配列として指定されている場合は
							// 値が配列の場合にのみ適用し、フィルタには値を配列のまま渡す
							if(is_array($filter)){
								$filter = array_pop($filter);
								$value = static::unit_filter($value,$filter,$option);
							}
							else{
								// 値が配列の場合は各要素に対してフィルタを適用する
								if(is_array($value)){
									foreach($value as $value_key => $value_item)
										$value[$value_key] = static::unit_filter($value_item,$filter,$option);
								}
								else
									$value = static::unit_filter($value,$filter,$option);
								//Log::coredebug("[af] filter $filter",$value);
							}
						}
					}
					$this->set($key,$value);
					//Log::coredebug("[af] set $key",$value);
				}
				
				if(isset($rules['validation'])){
					foreach($rules['validation'] as $validation => $option){
						static::unit_validate($value,$validation,$option,Arr::get($rules,'ignore_validation',[]));
					}
				}
				
				// 値がデータに存在しない場合はvalidated_valuesに代入しない
				if($this->key_exists($key)){
					//Log::coredebug("store validated_values $key",$value);
					$validated_values[$key] = $value;	//配列の場合がある
				}
				$validation_results[$key] = NULL;
			} catch(ValidateErrorException $e){
				Log::coredebug("[af] validation error key=[$key] msg=".$e->getMessage());
				$validation_results[$key] = [
					'key' => $key,
					'rules' => $rules,
					'message' => $e->getMessage(),
				];
				$is_error = true;
			}
		}
		$this->validated_values[$name] = $validated_values;
		$this->validation_results[$name] = $validation_results;
		$this->validation_error = $is_error;
		if($is_error){
			throw new ValidateErrorException();
		}
		
		//Log::coredebug("[af validate] validated_values=",$this->validated_values,$this->values);
		return $this;
	}
	public function validation_results()
	{
		return $this->validation_results;
	}
	
	public static function load($filename)
	{
		return include $filename;
	}
	public static function unit_filter($value,$filter,$option = [])
	{
		//Log::coredebug("filter $filter", $value, $option);
		
		$func = static::load("actionform/filter/".strtolower($filter).".php");
		$value = $func($value,$option);	//返り値は配列の可能性がある
		return $value;
	}
	
	/**
	 * 単体のバリデーションを実行する
	 *
	 * @param バリデーション対象の値
	 * @param バリデーションルール
	 * @param オプション
	 * @param 無視リスト(バリデーション名がこのリストの要素にあれば処理を行わない)
	 * @throws ValidateErrorException
	 */
	public static function unit_validate($value,$validation,$option = [],$ignore = [])
	{
		if( is_array($validation) ){
			foreach($validation as $validation_key => $validation_value){
				static::unit_validate($value,$validation_key,$validation_value,$ignore);
			}
		}
		else{
			if(is_array($value)){
				foreach($value as $value_key => $value_item){
					static::unit_validate($value_item,$validation,$option,$ignore);
				}
			}
			else{
				if(is_numeric($validation) && $option){
					$validation = $option;
					$option = [];
				}
				
				if( ! is_string($validation) && is_callable($validation) ){	//is_callable()だけだと'date'などの標準関数と同じ名前だと標準関数が呼ばれてしまう
					// validationがコードの場合、実行して必要なvalidation名を戻してもらう
					$af = static::instance();
					$add_validation = call_user_func($validation,$af);
					if($add_validation){
						if( ! is_array($add_validation) ){
							$add_validation = [$add_validation,[]];
						}
						Log::coredebug("[af] add_validation = ",$add_validation);
						call_user_func_array("static::unit_validate", array_merge([$value],$add_validation));
					}
				}
				else{
					if( ! in_array($validation,$ignore) ){
						$func = static::load("actionform/validation/".strtolower($validation).".php");
						call_user_func($func,$value,$option);
					}
				}
			}
		}
	}
	
	/**
	 * コンストラクタ
	 *
	 * @param boolean $clean_init trueにするとフォーム入力($_GET, $_POST, $_FILES)を自動的に読み込まない
	 */
	function __construct($clean_init = false)
	{
		$this->config = Config::get('form',[]);
		
		if( ! $clean_init ){
			$this->values = array_merge($_GET ?: array(),$_POST ?: array());
			//echo "<PRE>values = "; print_r($this->values); echo "</PRE>";
			//$this->af_filter = new \Model_ActionformFilter;
			//$this->request_method = Arr::get($_SERVER,'REQUEST_METHOD','');
			$this->referer = Arr::get($_SERVER,'HTTP_REFERER');
			$this->useragent = Arr::get($_SERVER,'HTTP_USER_AGENT');
			$this->server_vars = $_SERVER;
			
			// アップロードされたファイル
			if(is_array($_FILES) && count($_FILES)){
				foreach($_FILES as $file_key => $file){
					if(is_array($file['tmp_name'])){
						$files = [];
						foreach($file['tmp_name'] as $file_index => $file_tmpname){
							if(is_uploaded_file($file_tmpname) ){
								$files[$file_index] = [
									'tmp_name' => $file_tmpname,
									'name'  => Arr::get($file,"name.{$file_index}"),
									'type'  => Arr::get($file,"type.{$file_index}"),
									'error' => Arr::get($file,"error.{$file_index}"),
									'size'  => Arr::get($file,"size.{$file_index}"),
								];
							}
						}
						$this->set( $file_key, $files );
					}
					else{
						if(is_uploaded_file($file['tmp_name']) )
							$this->set( $file_key, $file );
					}
				}
			}
			
			if($this->get_config('import_db_schemas')){
				foreach(Database_Schema::get() as $table_name => $table){
					foreach(Arr::get($table,'columns') as $col_name => $col){
						$rule = [
							'name' => Arr::get($col,'desc'),
							'filter' => '',
							'typecat' => Arr::get($col,'type_cat'),
						];
						switch($rule['typecat']){
							case 'N':
								$rule['filter'] = ['hankaku','only0to9'];
								
								$this->set_config('global.key.'.$col_name.'_from', [
									'name' => $rule['name'].' FROM',
									'filter' => $rule['filter'],
								]);
								$this->set_config('global.key.'.$col_name.'_to', [
									'name' => $rule['name'].' TO',
									'filter' => $rule['filter'],
								]);
								break;
							case 'D':
								$rule['filter'] = ['hankaku','hantozen','trim'];
								
								$this->set_config('global.key.'.$col_name.'_from', [
									'name' => $rule['name'].' FROM',
									'filter' => $rule['filter'],
								]);
								$this->set_config('global.key.'.$col_name.'_to', [
									'name' => $rule['name'].' TO',
									'filter' => $rule['filter'],
								]);
								break;
							case 'B':
							case 'S':
							case 'A':
								$rule['filter'] = ['hankaku','hantozen','trim','empty2null'];
								break;
							case 'U':	//ユーザ定義型
								break;
							default:
								Log::coredebug("[af] Unknown {$col_name} typecat: ",Arr::get($col,'type_cat'));
						}
						$this->set_config('global.key.'.$col_name, $rule);
					}
				}
			}
		}
		
		return $this;
	}
	public function get_config($name)
	{
		return Config::get('form.'.$name);
		//return Arr::get($this->config,$name);
	}
	private function set_config($name,$value)
	{
		Config::set('form.'.$name, $value);
		//Arr::set($this->config,$name,$value);
		return $this;
	}
	public function referer()
	{
		return $this->referer;
	}
	public static function method()
	{
		return strtoupper( Arr::get($_SERVER,'REQUEST_METHOD','') );
	}
	public static function request_uri()
	{
		return Arr::get($_SERVER,'REQUEST_URI','');
	}
	function __set($name,$value)
	{
		$this->set($name,$value);
		return $this;
	}
	function set($name,$value = NULL,$set_default = false)
	{
		if( is_array($name) || $name instanceof ArrayAccess ){
			foreach($name as $key => $value)
				$this->set($key,$value,$set_default);
		}
		else{
			//echo "<PRE>[af] set $name (default:$set_default)"; var_dump($value); print_r(debug_backtrace(NULL,3));
			
			if($set_default)
				$this->values_default[$name] = $value;
			else
				$this->values[$name] = $value;
		}
		
		return $this;
	}
	function get_default($name = NULL)
	{
		if( ! $name )
			return $this->values_default;
		else
			return Arr::get($this->values_default,$name);
	}
	function set_default($name,$value = NULL)
	{
		return $this->set($name,$value,true);
	}
	function __get($name)
	{
		return $this->get($name);
	}
	function get($name,$default = NULL)
	{
		if(array_key_exists($name,$this->values))
			return $this->values[$name];
		else if(array_key_exists($name,$this->values_default))
			return $this->values_default[$name];
		else
			return $default;
	}
	function as_array()
	{
		return array_merge($this->values_default,$this->values);
	}
	/**
	 * validated_valuesを得る
	 *
	 * @param string キー (省略した場合はvalidated_values全体を返す)
	 */
	function get_validated_values($name = NULL)
	{
		return $name ? Arr::get($this->validated_values,$name,[]) : $this->validated_values;
	}
	
	/**
	 * 値データに指定キーが存在するか調べる
	 *
	 * @params string キー
	 * @params boolean true=valuesのみ調べる / false=values_defaultも調べる
	 */
	function key_exists($name,$only_values = false)
	{
		if($only_values)
			return array_key_exists($name,$this->values);
		else
			return (array_key_exists($name,$this->values) || array_key_exists($name,$this->values_default));
	}
	function value_exists($name)
	{
		return key_exists($name);
	}
	
	function is_mobiledevice()
	{
		if($this->useragent){
			return Cache::get($this->useragent,'ismobiledevice_by_ua',function($useragent){
				$browser = get_browser($useragent);
				if(is_object($browser)){
					return $browser->ismobiledevice;
				}
			});
		}
		return NULL;
	}
	
	//配列対応htmlspecialchars
	function _htmlspecialchars($value)
	{
		if(is_array($value)){
			array_walk($value,array($this,"_htmlspecialchars"));
		}
		else
			$value = htmlspecialchars($value);
		return $value;
	}
	
	function server_vars($name)
	{
		return Arr::get($this->server_vars,$name);
	}
	
	function is_ssl()
	{
		// #3271 AWS対応
		return ($this->server_vars('HTTPS') === 'on' || $this->server_vars('HTTP_X_FORWARDED_PROTO') === 'https');
	}
	
	function is_ajax_request()
	{
		return strtolower($this->server_vars('HTTP_X_REQUESTED_WITH')) == 'xmlhttprequest';
	}
	
	function set_message($type,$message)
	{
		$messages = Session::get_flash('messages');
		if( ! $messages || ! is_array($messages) )
			$messages = array();
		
		$messages[$type][] = $message;
		Log::coredebug("set message($type) : $message");
		
		Session::set_flash('messages',$messages);
	}
}
