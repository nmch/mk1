<?
class Actionform
{
	static private $instance;
	
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
		//Log::coredebug($this->validated_values);
		
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
		
		if( ! is_object($model) )
			$obj = new $model;
		else
			$obj = $model;
		
		//Log::coredebug("[af save] keys=",$obj->columns(),$obj);
		foreach($obj->columns() as $key){
			if(array_key_exists($key,$this->validated_values[$name])){
				//Log::coredebug("[af save] set $key",$this->validated_values[$name][$key]);
				$obj->$key = $this->validated_values[$name][$key];
			}
		}
		$obj->save();
		
		return $obj;
		
		/*
		if( ! $model_list )
			$model_list = Arr::get($preset,'model',array());
		foreach($model_list as $model){
			if( ! is_object($model) ){
				// todo : make a instance
			}
			//Log::coredebug($model->keys(),$this->validated_values[$name]);
			foreach($model->keys() as $key){
				if(array_key_exists($key,$this->validated_values[$name])){
					//Log::coredebug("[af save] set $key",$this->validated_values[$name][$key]);
					$model->$key = $this->validated_values[$name][$key];
				}
			}
			if($model->get_diff())
				$model->save();
		}
		*/
	}
	
	public function validate($name)
	{
		$config_key = 'form.preset.'.$name;
		$validation = Config::get($config_key);
		if( ! $validation )
			throw new MkException('invalid preset name '.$config_key);
		
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
			//Log::coredebug("[af] validate $key",$rules);
			
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
				if($this->key_exists($key)){
					if(isset($rules['filter'])){
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
						if( is_numeric($validation) ){
							$validation = $option;
							$option = array();
						}
						//Log::coredebug("[af] validation $validation");
						
						// 値が配列の場合は各要素に対してバリデーションを適用する
						if(is_array($value)){
							foreach($value as $value_key => $value_item)
								static::unit_validate($value_item,$validation,$option);
						}
						else
							static::unit_validate($value,$validation,$option);
					}
				}
				$validated_values[$key] = $value;	//配列の場合がある
				$validation_results[$key] = NULL;
			} catch(ValidateErrorException $e){
				Log::debug("[af] validation error key=[$key] msg=".$e->getMessage());
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
		if($is_error)
			throw new ValidateErrorException();
		
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
	public static function unit_validate($value,$validation,$option = [])
	{
		$func = static::load("actionform/validation/".strtolower($validation).".php");
		$func($value,$option);
	}


	public static function instance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self;
		}
		
		return self::$instance;
	}
	function __construct()
	{
		$this->values = array_merge($_GET ?: array(),$_POST ?: array());
		//echo "<PRE>form_values = "; print_r($this->form_values); echo "</PRE>";
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
			//echo "[af] set $name (default:$set_default)"; var_dump($value);
			
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
	function get_validated_values($name)
	{
		return Arr::get($this->validated_values,$name,[]);
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
			$browser = get_browser($this->useragent);
			if(is_object($browser))
				return $browser->ismobiledevice;
		}
		return NULL;
	}
	
	/*
	function clear($name = NULL)
	{
		if(isset($name) && array_key_exists($name,$this->form_values)){
			unset($this->form_values[$name]);
		}
		else
			$this->form_values = array();
		return $this;
	}
	 * 
	 */
	/*
	function set_default_as_array($obj)
	{
		if(is_object($obj)){
			$obj_items = $this->_model_to_array($obj);
			$this->bulk_set_default($obj_items);
		}
		return $this;
	}
	function list_set_default_as_array($name,$list,$options = array())
	{
		if( ! is_array($list))
			return $this;
		//echo "<PRE>list = "; print_r($list); echo "</PRE>";
		$data = array();
		foreach($list as $list_key => $item){
			if(is_object($item)){
				$item_array = $this->_model_to_array($item);
				$data[$list_key] = $item_array;
			}
		}
		//echo "<PRE>data = "; var_dump($data); echo "</PRE>";
		$this->set_default($name,$data);
		
		return $this;
	}
	function _model_to_array($obj)
	{
		$data = array();
		
		$properties = array();
		$primary_key = "";
		//Modelもしくは\Orm\Modelの派生クラスだった場合は_propertiesを使う
		if(is_subclass_of($obj,"\Model") || is_subclass_of($obj,"\Orm\Model")){
			$properties = $obj->properties() ?: array();
			$primary_key = $obj->primary_key();
			if(is_array($primary_key))
				$primary_key = reset($primary_key);
		}
		//propertiesがある場合はそれをもとにオブジェクトのプロパティをdataに入れていく
		if($properties){
			foreach($properties as $property_name => $property){
				$value = $obj->$property_name;
				$data[$property_name] = $value;
			}
		}
		
		return $data;
	}
	
	function __call($name,$arguments)
	{
		//echo "[$arguments]";
		throw new Exception('undefined method');
	}
	 * 
	 */
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
	/*
	function populate()
	{
		$this->populated = true;
		return $this;
	}
	function add_model($model,$list_name = NULL)
	{
		if(is_string($model))
			$model = new $model;
		
		if($list_name){
			if( ! isset($this->list_models[$list_name]) || ! is_array($this->list_models[$list_name]))
				$this->list_models[$list_name] = array();
			$this->list_models[$list_name][] = $model;
			$this->handle_as_list($list_name);
		}
		else
			$this->models[] = $model;
		
		return $this;
	}
	*/
	/*
	function add_validation_model($model)
	{
		if(is_string($model))
			$model = new $model;
		
		$this->validation_rules[] = $model;
		
		return $this;
	}
	function list_add_validation_model($list_name,$model)
	{
		if( ! $list_name)
			return $this;
		if(is_string($model))
			$model = new $model;
		
		$this->list_validation_rules[$list_name][] = $model;
		//echo "<PRE>list_validation_rules = "; print_r($this->list_validation_rules); echo "</PRE>";
		
		return $this;
	}
	function save_using_model($model,$data = NULL)
	{
		if( ! is_string($model))
			return $this;
		if($data === NULL)
			$data = $this->form_values;
		if( ! is_array($data))
			return $this;
		
		//プライマリキー取得
		$pk = $model::primary_key();
		if(is_array($pk))
			$pk = array_shift($pk);
		if( ! $pk)
			return $this;
		//プロパティ取得
		$properties = $model::properties();
		if(!is_array($properties))
			$properties = array();
		
		//プライマリキーの値がなければスキップ
		if( !  isset($data[$pk]))
			return $this;
		
		$id = $data[$pk];
		$model_instance = $model::find($id);
		if( ! $model_instance)	//指定されたIDが存在しなければスキップ
			return $this;
		
		foreach($data as $key => $value){
			//プライマリキーはスキップ
			if($key == $pk)
				continue;
			//プロパティの定義があって、キーがプロパティになければスキップ
			if($properties && ! array_key_exists($key,$properties))
				continue;
			//値をセット
			$model_instance->$key = $value;
		}
		$model_instance->save();
		
		return $this;
	}
	function list_save_using_model($list_name,$model)
	{
		$list = $this->form_values[$list_name];
		if( ! is_string($model) || ! is_array($list))
			return $this;
		//echo "<PRE>list = "; print_r($list); echo "</PRE>";
		
		$model_skelton = new $model;
		$pk = $model_skelton->primary_key();
		if(is_array($pk))
			$pk = array_shift($pk);
		if( ! $pk)
			return $this;
		$properties = $model_skelton->properties();
		if(!is_array($properties))
			$properties = array();
		
		foreach($list as $id => $item){
			if( ! is_array($item))
				continue;
			if( ! array_key_exists($pk,$item))	//プライマリキーがセットされていなければスキップ
				continue;
			if( !  $item[$pk])	//プライマリキーの値がなければスキップ
				continue;
			
			$id = $item[$pk];
			$model_instance = $model::find($id);
			if( ! $model_instance)	//指定されたIDが存在しなければスキップ
				continue;
			
			foreach($item as $key => $value){
				//プライマリキーはスキップ
				if($key == $pk)
					continue;
				//プロパティの定義があって、キーがプロパティになければスキップ
				if($properties && ! array_key_exists($key,$properties))
					continue;
				//値をセット
				$model_instance->$key = $value;
			}
			$model_instance->save();
		}
		
		return $this;
	}
	function handle_as_list($name)
	{
		$this->handle_as_list[$name] = $name;
		return $this;
	}
	// フォーム値のバリデーションを行う
	// エラー=false / 成功=true
	function validate($with_filter = true)
	{
		//通常データ (リストではないデータ)
		$r = $this->_validate($this->form_values,array(
			'ignore_keys' => array_keys($this->list_validation_rules),
		));
		$this->validation_results = $r;
		
		//echo "<PRE>form_values = "; print_r($this->form_values); echo "</PRE>";
		//リスト扱いのデータ
		if($this->list_validation_rules && is_array($this->list_validation_rules)){
			foreach($this->list_validation_rules as $list_name => $list_validation_rule){
				//echo "list_validation_rule $list_name<BR>";
				//echo "<PRE>form_values = "; print_r($this->form_values[$list_name]); echo "</PRE>";
				//存在しない場合は空の配列を作る(requiredをひっかけるため)
				if( ! isset($this->form_values[$list_name]))
					$this->form_values[$list_name] = array();
				//配列ではない場合どうしようもないのでスキップ
				if( ! is_array($this->form_values[$list_name]))
					continue;
				//リストの各行に対して指定ルールでvalidate
				foreach($this->form_values[$list_name] as $list_key => $list_item){
					//echo "validate $list_name [$list_key]<BR>";
					$r = $this->_validate($this->form_values[$list_name][$list_key],array(
						'rules' => $list_validation_rule,
					));
					$this->validation_results[$list_name][$list_key] = $r;
				}
			}
		}
		//echo "<PRE>form_values = "; print_r($this->form_values); echo "</PRE>";
		
		//$this->validation_resultsのなかにひとつでも値falseと評価できるものがあるとvalidation_resultはfalseになる。
		$func_and = function($a,$b) use (&$func_and){
			if(is_array($b)){
				$b = array_reduce($b,$func_and,1);
			}
			return (boolean)$a & (boolean)$b;
		};
		$validation_result = array_reduce($this->validation_results,$func_and,1);
		
		//echo "validation_result=$validation_result"; echo "<PRE>validation_results = "; print_r($this->validation_results); echo "</PRE>";
		return $validation_result;
	}
	function _filter($filter_name,$data)
	{
		return Model_ActionformFilter::_filter($filter_name,$data);
	}
	function _validate(&$data,$options = array())
	{
		$ignore_keys = array();
		$rules = $this->validation_rules;	//ここは通常データのvalidateのためmodelからもってくる処理が必要
		if(is_array($options))
			extract($options);
		
		if( ! is_array($ignore_keys))
			$ignore_keys = array();
		if( ! is_array($rules))
			$rules = array();
		
		$results = array();
		foreach($rules as $rule){
			//●validationルール一覧の文字列をとってくる
			//['form_key' => ['af_validation' => 'rule,rule']] の形式になればok
			//Modelのプロパティがあればそのまま使える(はず)
			if(is_subclass_of($rule,"\Model") || is_subclass_of($rule,"\Orm\Model")){
				$properties = $rule->properties() ?: array();
				$rule = $properties;
			}
			if( ! is_array($rule))
				continue;
			
			foreach($rule as $form_key => $form_rule){
				unset($value);	//前のループの参照が残ったままNULLを代入してデータ破壊が起きるのを防ぐ
				if(array_key_exists($form_key,$data))
					$value = &$data[$form_key];
				else
					$value = NULL;
				
				$filters = empty($form_rule['af_filter']) ? NULL : $form_rule['af_filter'];
				$validations = empty($form_rule['af_validation']) ? NULL : $form_rule['af_validation'];
				$data_type = empty($form_rule['data_type']) ? NULL : $form_rule['data_type'];
				
				$r = $this->validate_with_filter($filters,$validations,$data_type,$value);
				$value = $r['value'];
				$results[$form_key] = $r['validate_results'];
				
			}
		}
		return $results;
	}
	function apply_filter($value,array $filters)
	{
		if( ! is_array($filters))
			throw new Exception('invalid filters');
			
		foreach($filters as $filter_name => $filter_options){
			$value = $this->_filter($filter_name,$value);
		}
		
		return $value;
	}
	
	function validate_with_filter($filters,$validations,$data_type,$value)
	{
		//Log::debug("validate_with_filter : filters=".print_r($filters,true)." / validations=".print_r($validations,true)." / data_type=".print_r($data_type,true)." / value=".print_r($value,true));
		
		$results = array(
			'value' => NULL,
			'validate_results' => array(),
		);
		
		//●filter	//@todo apply_filterへ置換
		if(is_array($filters)){
			foreach($filters as $filter_name => $filter_options){
				$value = $this->_filter($filter_name,$value);
			}
		}
		
		//●type filter
		if(isset($data_type)){
			$value = $this->af_filter->_filter_by_datatype($data_type,$value);
		}
		
		$results['value'] = $value;
		
		//●validate
		if(is_array($validations)){
			foreach($validations as $validation_name => $validation_options){
				$validation_function_name = "_validate_".$validation_name;
				if(method_exists($this,$validation_function_name)){
					if(is_array($value)){
						foreach($value as $key => $item){
							$r = (boolean)$this->$validation_function_name($item,$validation_options);
							$results['validate_results'][$key][$validation_name] = $r;
						}
					}
					else{
						$r = (boolean)$this->$validation_function_name($value,$validation_options);
						$results['validate_results'][$validation_name] = $r;
					}
				}
			}
		}
		
		return $results;
	}
	
	//登録されているルール、モデルからルール一覧を作成
	function _get_validation_rules($list_name = NULL)
	{
		if($list_name){
			$rules = isset($this->list_validation_rules[$list_name]) ? $this->list_validation_rules[$list_name] : array();
			foreach($this->list_models[$list_name] as $model){
				$properties = $model->properties();
				if($properties && is_array($properties))
					$rules += $properties;
			}
		}
		else{
			$rules = $this->validation_rules;
			foreach($this->models as $model){
				$properties = $model->properties();
				if($properties && is_array($properties))
					$rules += $properties;
			}
		}
		return $rules;
	}
	
	function _validate_required($value,$options)
	{
		return (isset($value) && strlen($value));
	}
	function _validate_tel($value,$options)
	{
		return preg_match('/^0[0-9]+-[0-9]+-[0-9]+$/',$value,$match);
	}
	function _validate_point($value,$options)
	{
		return preg_match('/\([-.0-9]+,[-.0-9]+\)/',$value,$match);
	}
	 * 
	 */
	/**
	 * テーブルのIDとして適切な形式か確認する
	 */
	/*
	static function is_valid_id_type($id)
	{
		if( $id && is_numeric($id) && ((int)$id == $id))
			return true;
		else
			return false;
	}
	 * 
	 */
	
	/**
	 * 操作可能なファイルかどうか調べる
	 */
	/*
	static function is_valid_file($filename)
	{
		if( empty($filename) || ! file_exists($filename) || ! is_file($filename) || ! is_readable($filename) || ! is_writable($filename) )
			return false;
		
		return true;
	}
	*/
	
	/**
	 * アップロードされたファイルとして適切かを調べる
	 *
	 * アップロードされたファイルで、エラーはなく、実在する通常ファイルで、読み書きが可能なもののみtrue
	 *
	 * @param $_FILES
	 *
	 */
	/*
	static function is_valid_uploaded_file($file)
	{
		if( ! empty($file['error']) || empty($file['tmp_name']) || ! self::is_valid_file($file['tmp_name']) || ! is_uploaded_file($file['tmp_name']) )
			return false;
		
		return true;
	}
	*/
	
	/**
	 * テンプレートからテキスト生成
	static function maketext_from_template($template,$data)
	{
		$af = self::instance();
		$data['af'] = $af;
		$data['base_url'] = Config::get('base_url');
		$view = View_Smarty::forge($template,$data);
		
		// default_modifiersをクリア
		$parser = View_Smarty::parser();
		$default_modifiers_backup = $parser->default_modifiers;
		//Log::debug("default_modifiers=".print_r($parser->default_modifiers,true));
		$parser->default_modifiers = array();
		// レンダリング
		$body = $view->render();
		// default_modifiersを戻す
		$parser->default_modifiers = $default_modifiers_backup;
		
		return $body;
	}
	 */
	
	function server_vars($name)
	{
		return Arr::get($this->server_vars,$name);
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
		Log::coredebug("set message($type) : $message",$messages);
		
		Session::set_flash('messages',$messages);
	}
}
