<?
/**
 * バリデーションエラー時にスローされる例外
 */
class ValidateException extends LogicException {}


class Actionform
{
	use Singleton;
	
	private $form_values = array();
	private $form_values_default = array();
	private $target_array_key;
	private $models = array();
	private $list_models = array();
	private $handle_as_list = array();
	private $populated;
	private $validation_rules = array();
	private $list_validation_rules = array();
	private $af_filter;
	protected static $_SERVER = array();
	protected static $browser = array();
	var $validation_results = array();
	
	function __construct()
	{
		$this->form_values = array_merge($_GET ?: array(),$_POST ?: array());
		//echo "<PRE>form_values = "; print_r($this->form_values); echo "</PRE>";
		$this->af_filter = new \Model_ActionformFilter;
		$this->request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : '';
		if(isset($_SERVER)){
			foreach($_SERVER as $key => $item)
				static::$_SERVER[strtolower($key)] = $item;
		}
		if(static::server('HTTP_USER_AGENT')){
			// static::$browser = get_browser(static::server('HTTP_USER_AGENT'),true);
		}
		return $this;
	}
	public static function is_mobiledevice()
	{
		//return static::browser('ismobiledevice');
		return Mobiledetect::isMobile();
	}

	/*
	public static function browser($name,$default = NULL)
	{
		$name = strtolower($name);
		return array_key_exists($name, static::$browser) ? static::$browser[$name] : $default;
	}
	*/

	public static function server($name,$default = NULL)
	{
		$name = strtolower($name);
		return array_key_exists($name, static::$_SERVER) ? static::$_SERVER[$name] : $default;
	}
			
			
	function clear($name = NULL)
	{
		if(isset($name) && array_key_exists($name,$this->form_values)){
			unset($this->form_values[$name]);
		}
		else
			$this->form_values = array();
		return $this;
	}
	function __set($name,$value)
	{
		$this->set($name,$value);
		return $this;
	}
	function set($name,$value)
	{
		if( ! is_scalar($name) )
			throw new Exception('invalid name');
		$this->form_values[$name] = $value;
		return $this;
	}
	function bulk_set($data)
	{
		foreach($data as $key => $value){
			$this->set($key,$value);
			//Log::debug("bulk_set $key -> ".print_r($value,true));
		}
		//Log::debug(print_r($this->form_values,true));
		
		return $this;
	}
	function set_default($name,$value)
	{
		if( ! $name)
			return;
		$this->form_values_default[$name] = $value;
		return $this;
	}
	function bulk_set_default($data)
	{
		foreach($data as $key => $value){
			Log::coredebug("[af set default] $key",$value);
			$this->set_default($key,$value);
		}
		
		return $this;
	}
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
	
	function __get($name)
	{
		return $this->get($name);
	}
	public static function __callStatic($name,$arguments)
	{
		$af = static::instance();
		return call_user_func_array(array($af,$name), $arguments);
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
	function get($name)
	{
		if(array_key_exists($name,$this->form_values))
			return $this->form_values[$name];
		else if(array_key_exists($name,$this->form_values_default))
			return $this->form_values_default[$name];
		else
			return null;
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
		/*
		$method_name = '_filter_'.$filter_name;
		if( ! method_exists('Model_ActionformFilter',$method_name) )
			throw new Exception("undefined filter method $method_name");
		//Log::debug("filter $method_name called");
		return call_user_func_array(array('Model_ActionformFilter',$method_name),$data);
		*/
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
				
				/*
				if( isset($rule['validation']) && is_array($rule['validation'])){
					foreach($rule['validation'] as $validation){
						$validate_function_name = "_validate_".$validation;
						if(method_exists($this,$validate_function_name)){
							$r = (boolean)$this->$validate_function_name($key,$data);
							$results[$key][$validation] = $r;
						}
					}
				}
				*/
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
	function _validate_kana_initial($value,$options)
	{
		$chars = [
			'ア','イ','ウ','エ','オ',
			'カ','キ','ク','ケ','コ',
			'サ','シ','ス','セ','ソ',
			'タ','チ','ツ','テ','ト',
			'ナ','ニ','ヌ','ネ','ノ',
			'ハ','ヒ','フ','ヘ','ホ',
			'マ','ミ','ム','メ','モ',
			'ヤ','ユ','ヨ',
			'ラ','リ','ル','レ','ロ',
			'ワ','ヲ','ン',
		];
		/*
アイウエオ
カキクケコ
サシスセソ
タチツテト
ナニヌネノ
ハヒフヘホ
マミムメモ
ヤユヨ
ラリルレロ
ワヲン
		*/
		return in_array($value,$chars);
	}
	
	/**
	 * テーブルのIDとして適切な形式か確認する
	 */
	static function is_valid_id_type($id)
	{
		if( $id && is_numeric($id) && ((int)$id == $id))
			return true;
		else
			return false;
	}
	
	/**
	 * 操作可能なファイルかどうか調べる
	 */
	static function is_valid_file($filename)
	{
		if( empty($filename) || ! file_exists($filename) || ! is_file($filename) || ! is_readable($filename) || ! is_writable($filename) )
			return false;
		
		return true;
	}
	
	/**
	 * アップロードされたファイルとして適切かを調べる
	 *
	 * アップロードされたファイルで、エラーはなく、実在する通常ファイルで、読み書きが可能なもののみtrue
	 *
	 * @param $_FILES
	 *
	 */
	static function is_valid_uploaded_file($file)
	{
		if( ! empty($file['error']) || empty($file['tmp_name']) || ! self::is_valid_file($file['tmp_name']) || ! is_uploaded_file($file['tmp_name']) )
			return false;
		
		return true;
	}
	
	/**
	 * テンプレートからテキスト生成
	 */
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
}
class Model_ActionformFilter
{
	/**
	 * @ignore
	 *
    public static function _filter___call($name, $arguments)
    {
		$method_name = '_filter_'.$name;
		if( ! method_exists($this,$method_name) )
			throw new Exception("undefined filter method $method_name");
		return call_user_func_array($this->$method_name,is_array($arguments) ? $arguments : [$arguments]);
    }
	*/
	
	/**
	 * 全角の英数字とスペースを半角変換
	 * @return string
	 */
	static function _filter_hankaku($value)
	{
		$new_value = mb_convert_kana($value,"sa");
		return $new_value;
	}
	/**
	 * 正負の整数 (0-9, -)
	 * 
	 * "12.34"は1234になります。<br>
	 * integerにキャストされるので、"-012a-345"は-12になります。<br>
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @param string
	 * @return integer
	 */
    static function _filter_integer($value)
    {
		if( ! is_scalar($value) )
			return NULL;
		
		$value = preg_replace('/[^-0-9]/','',$value);
		if(strlen($value) == 0)
			$value = NULL;
		else
			$value = (int)$value;
		return $value;
	}
	/**
	 * 複数行の正負の整数 (0-9, -, \n)
	 * 
	 * \nで分割したあと、それぞれに対してintegerフィルタを適用し<br>
	 * 再度\nで連結したものを返します
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
    static function _filter_integers($value)
    {
		if( ! is_scalar($value) )
			return NULL;
		
		$integers = explode("\n",$value);
		$integers = array_map("self::_filter_integer",$integers);
		return implode("\n",$integers);
	}
	/**
	 * 正の整数 (0-9)
	 * 
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return integer
	 */
    static function _filter_only0to9($value)
    {
		if( ! is_scalar($value) )
			return NULL;
		
		$value = preg_replace('/[^0-9]/','',$value);
		if(strlen($value) == 0)
			$value = NULL;
		else
			$value = (int)$value;
		return (int)$value;
	}
	/**
	 * 正負の実数 (0-9, -, .)
	 * 
	 * 不正確にならないよう、浮動小数点数型にはキャストしません。<br>
	 * そのため、"12.3-45-6"といった表現が返されることがあります。<br>
	 * 浮動小数点数として利用する場合は適切な型にキャストして下さい。<br>
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
    static function _filter_numeric($value)
    {
		if( ! is_scalar($value) )
			return NULL;
		
		$value = preg_replace('/[^-0-9\.]/','',$value);
		return $value;
	}
	/**
	 * integerへキャスト
	 * 
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return integer
	 */
	static function _filter_cast_int($value)
	{
		if( ! is_scalar($value) )
			return NULL;
		
		$value = (int)$value;
		return $value;
	}
	/**
	 * @ignore
	 * dateとして利用できる部分のみ取り出します
	 * 
	 * 'YYYY-MM-DD'もしくは'YYYY/MM/DD'を認識します<br>
	 * 日付の妥当性はチェックしません。
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
    static function _filter_date($value)
    {
		if( ! is_scalar($value) )
			return NULL;
		
		if(preg_match('/([0-9]{4}-[0-9]{1,2}-[0-9]{1,2})/',$value,$match) !== FALSE || 
			preg_match('/([0-9]{4}/[0-9]{1,2}/[0-9]{1,2})/',$value,$match) !== FALSE){
			return $match[1];
		}
		else
			return NULL;
	}
	 */
	
	/**
	 * ASCIIのみ (0x00 - 0x7F)
	 * 
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
    static function _filter_ascii($value)
    {
		if( ! is_scalar($value) )
			return NULL;
		
		$value = preg_replace('/[^\x00-\x7F]/','',$value);
		return $value;
	}
	/**
	 * アルファベットのみ(A-Z, a-z)
	 * 
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
    static function _filter_alphabet($value)
    {
		if( ! is_scalar($value) )
			return NULL;
		
		$value = preg_replace('/[^a-zA-Z]/','',$value);
		return $value;
	}
	/**
	 * スペースを削除
	 * 
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
    static function _filter_nospace($value)
    {
		if( ! is_scalar($value) )
			return NULL;
		
		$value = str_replace(' ','',$value);
		return $value;
	}
	/**
	 * 半角カナを全角へ変換
	 * 
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
	static function _filter_kana_hantozen($value)
	{
		if( ! is_scalar($value) )
			return NULL;
		
		return mb_convert_kana($value,'KV');
	}
	/**
	 * trim
	 * 
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
	static function _filter_trim($value)
	{
		if( ! is_scalar($value) )
			return NULL;
		
		return trim($value);
	}
	/**
	 * emailでよく間違える文字を正しく変換
	 * 
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
	static function _filter_email($value)
	{
		if( ! is_scalar($value) )
			return NULL;
		
		$value = preg_replace(
			array(
				'/,/'
			),
			array(
				'.'
			)
		,$value);
		return $value;
	}
	static function _filter_katakana($value)
	{
		$new_value = mb_convert_kana($value,"C");
		return $new_value;
	}
	/**
	 * 国内の電話番号パターンを切り出し (012-3456-7890)
	 * 
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
	static function _filter_tel($value)
	{
		if( ! is_scalar($value) )
			return NULL;
		
		if(preg_match('/([0-9]+-[0-9]+-[0-9]+)/',$value,$match))
			return $match[1];
		else
			return NULL;
		//$value = preg_replace('/[^0-9-]/','',$value);
		//return $value;
	}
	
	static function _filter($filter_name,$value)
	{
		if(is_array($value)){
			foreach($value as $key => $item){
				$value[$key] = self::_filter($filter_name,$item);
			}
			return $value;
		}
		
		$method_name = '_filter_'.$filter_name;
		if( ! method_exists('Model_ActionformFilter',$method_name) )
			throw new Exception("undefined filter method $method_name");
		return call_user_func_array(array('Model_ActionformFilter',$method_name),[$value]);
	}
	
	/**
	 * PostgreSQLのデータ型にマッチするよう(できるだけ)調整します
	 * 
	 * 空文字列はNULLに変換します<br>
	 * booleanは文字列表現である't'/'f'ではなく、PHPのbool型で返します<br>
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
	function _filter_by_datatype($data_type,$value)
	{
		if(is_array($data_type))
			$data_type = reset($data_type);
		if(is_array($value)){
			foreach($value as $key => $item){
				$value[$key] = $this->_filter_by_datatype($data_type,$item);
			}
			return $value;
		}
		
		switch($data_type){
			case 'smallint':
			case 'int2':
			case 'integer':
			case 'int4':
			case 'bigint':
			case 'int8':
			case 'numeric':
			case 'float':
			case 'float4':
			case 'float8':
				if( ! is_numeric($value) )
					$value = NULL;	//数値表現ではない値が与えられた場合にはNULL指定に強制
				break;
			case 'bool':
			case 'boolean':
				if($value !== NULL){
					if(is_string($value)){
						//文字列表現だった場合は先頭1文字で判別
						$value = strtolower($value[0]) == 't' ? true : false;
					}
					else{
						//文字列以外だった場合は真偽判定を式で判別
						$value = (boolean)$value;
					}
				}
				$field_enable = true;
				break;
			case 'text':
			case 'date':
			case 'timestamp':
			case 'timestamptz':
			case 'varchar':
			case 'cidr':
			case 'inet':
				$value = strlen($value) ? $value : NULL;
				break;
			default:
				throw new Exception("unknown data type $data_type");
		}
		
		return $value;
	}
	
	/**
	 * PostgreSQLのデータ型にマッチするよう(できるだけ)調整します
	 * @ignore
	 * 空文字列はNULLに変換します<br>
	 * integerとbigintは値の範囲もチェックし、範囲外の場合はNULLにします<br>
	 * booleanは文字列表現である't'/'f'ではなく、PHPのbool型で返します<br>
	 * is_scalar()がFALSEとなる値を渡された場合はNULLを返します<br>
	 * 
	 * @return string
	 */
	/*
	static function _filter_by_datatype($data_type,$value)
	{
		if( ! is_scalar($value) )
			return NULL;
		
		switch($data_type){
			case 'smallint':
			case 'int2':
				Model_ActionformValidator::
				$value = self::_filter_hankaku($value);
				if($value < -32767 || 32767 < $value)
					$value = NULL;
				break;
			case 'integer':
			case 'int4':
				$value = self::_filter_hankaku($value);
				if($value < -2147483647 || 2147483647 < $value)
					$value = NULL;
				break;
			case 'bigint':
			case 'int8':
				$value = self::_filter_hankaku($value);
				if($value < -9223372036854775807 || 9223372036854775807 < $value)
					$value = NULL;
				break;
			case 'decimal':
			case 'numeric':
			case 'real':
			case 'double precision':
			case 'float':
			case 'float4':
			case 'float8':
				$value = self::_filter_numeric($value);
				break;
			case 'bool':
			case 'boolean':
				if($value !== NULL){
					if(is_string($value)){
						//文字列表現だった場合は先頭1文字で判別
						$value = strtolower($value[0]) == 't' ? true : false;
					}
					else{
						//文字列以外だった場合は真偽判定を式で判別
						$value = (boolean)$value;
					}
				}
				break;
			case 'text':
			case 'date':
			case 'timestamp':
			case 'timestamptz':
			case 'varchar':
			case 'cidr':
			case 'inet':
				$value = strlen($value) ? $vale : NULL;
				break;
			default:
				throw new Exception("unknown data type $data_type");
		}
		
		return $value;
	}
	 */
}
