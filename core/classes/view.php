<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class View
{
	const TEMPLATE_RESOURCE_DEFAULT = '';
	const TEMPLATE_RESOURCE_FILE    = 'file: ';
	const TEMPLATE_RESOURCE_STRING  = 'string: ';
	
	/** @var Actionform $af */
	protected $af;
	protected $smarty;
	protected $template_resource = \View::TEMPLATE_RESOURCE_DEFAULT;
	protected $template_filename;
	protected $template_string;
	protected $data;
	/** @var bool render()時にフラッシュメッセージをクリアしない */
	private $do_not_clear_flash = false;
	private $content_type       = null;
	
	function __construct($template_filename = null, $data = [], $do_not_clear_flash = false)
	{
		$this->af                 = Actionform::instance();
		$this->do_not_clear_flash = $do_not_clear_flash;
		
		// hide=trueに設定すると、as_array()では出てこないが名前を指定してget()すると取得できる
		// as_array()時にフォーム入力値以外を出さないようにするための措置
		$this->af->set('_view_class_name', strtolower(get_called_class()), false, true);
		
		if( ! $template_filename ){
			$template_filename = implode('/', array_slice(explode('_', $this->af->_view_class_name), 1));
		}
		$this->template_filename($template_filename);
		
		$this->smarty = new Smarty();
		
		// テンプレートディレクトリ設定
		$template_dir_array = [
			COREPATH . 'views/',
		];
		foreach(glob(PKGPATH . '*', GLOB_ONLYDIR) as $dir){
			$template_dir_array[] = $dir . '/' . 'views/';
		}
		$template_dir_array[] = PKGPATH;
		$template_dir_array[] = APPPATH . 'views/';
		$template_dir_array   = array_reverse($template_dir_array);
		//print_r($template_dir_array);
		//Log::debug2('$template_dir_array', $template_dir_array);
		//throw new Exception();
		$this->smarty->setTemplateDir($template_dir_array);
		//$this->smarty->template_dir = APPPATH.'views/';
		
		// プラグインディレクトリ設定
		$list = [SMARTY_PLUGINS_DIR, COREPATH . 'plugin/Smarty/'];
		foreach(glob(PKGPATH . '*', GLOB_ONLYDIR) as $dir){
			$list[] = $dir . '/' . 'plugin/Smarty/';
		}
		$list[]         = APPPATH . 'plugin/Smarty/';
		$config_plugins = Config::get('smarty.environment.plugins_dir');
		if( $config_plugins ){
			if( ! is_array($config_plugins) ){
				$config_plugins = [$config_plugins];
			}
			$list = array_merge($config_plugins, $list);
		}
		$this->smarty->setPluginsDir(array_reverse($list));
		
		// その他の設定
		$environments = Config::get('smarty.environment');
		if( is_array($environments) ){
			foreach($environments as $name => $environment){
				if( $name !== 'plugins_dir' ){    //プラグインディレクトリは先に設定済み
					$this->smarty->{$name} = $environment;
				}
			}
		}
		
		//Log::coredebug("compile_dir = ",$this->smarty->compile_dir);
		$this->data = $data;
		$this->set_view();
		$this->before();
	}
	
	public function set_actionform(Actionform $af)
	{
		$this->af = $af;
		
		return $this;
	}
	
	public function set_data(array $data)
	{
		$this->data = $data;
		
		return $this;
	}
	
	protected function set_view(){ }
	
	function before(){ }
	
	function before_view(){ }
	
	function nofilter()
	{
		$this->set_smarty_environment('default_modifiers', []);
		
		return $this;
	}
	
	function set_smarty_environment($name, $value)
	{
		$this->smarty->$name = $value;
		
		return $this;
	}
	
	function __toString()
	{
		try {
			return $this->render();
		} catch(Exception $e){
			Log::error("Smarty error : {$e->getMessage()} at {$e->getFile()} (line:{$e->getLine()})");
			
			return '';
		}
	}
	
	/**
	 * do_not_clear_flashフラグを変更する
	 *
	 * @param bool $value
	 */
	function do_not_clear_flash($value = true)
	{
		$this->do_not_clear_flash = $value;
	}
	
	/**
	 * @return null|string
	 */
	protected function change_template_filename(){ }
	
	/**
	 * テンプレートファイル名を変更する
	 *
	 * @param $filename
	 *
	 * @return $this
	 */
	public function template_filename($filename)
	{
		$this->template_resource = '';
		
		$this->template_filename = $filename . '.' . Config::get('smarty.extension');
		
		return $this;
	}
	
	public function template_string($template_string)
	{
		$this->template_resource = static::TEMPLATE_RESOURCE_STRING;
		$this->template_string   = $template_string;
		
		return $this;
	}
	
	function content_type($content_type = null)
	{
		if( $content_type ){
			$this->content_type = $content_type;
		}
		
		return $this->content_type;
	}
	
	/**
	 * view()の実行結果を得る
	 *
	 * @return Response|string
	 */
	function get_view()
	{
		// before_render_*
		foreach(get_class_methods($this) as $method_name){
			if( strpos($method_name, 'before_render_') === 0 ){
				$this->$method_name();
			}
		}
		
		$this->before_view();
		
		$r = $this->view();
		
		$this->after_view();
		
		return $r;
	}
	
	/**
	 * 表示内容を生成する
	 *
	 * @return string
	 * @throws HttpNotFoundException
	 * @throws MkException
	 */
	function render()
	{
		$return_value = null;
		$r            = $this->get_view();
		
		// view()でset_flashする可能性があるので、clear_flash()はview()のあとで。
		if( ! $this->do_not_clear_flash ){
			Session::clear_flash();
		}
		
		// view()がResponseオブジェクト(JSONを想定)を返した場合はそのまま呼び出し元(たぶんResopnse::send()へ返す
		if( $r instanceof Response ){
			$return_value = $r;
		}
		else{
			if( strval($this->template_resource) === static::TEMPLATE_RESOURCE_DEFAULT ){
				$template_filename = $this->change_template_filename() ?: $this->template_filename;
				if( ! $this->template_exists($template_filename) ){
					if( is_scalar($template_filename) ){
						Log::error("template not found {$template_filename}");
					}
					throw new HttpNotFoundException();
				}
			}
			elseif( strval($this->template_resource) === static::TEMPLATE_RESOURCE_STRING ){
				$template_filename = ($this->template_resource . $this->template_string);
			}
			else{
				throw new MkException("invalid template settings");
			}
			
			foreach(get_object_vars($this) as $name => $value){
				$this->smarty->assign($name, $value);
			}
			
			$return_value = $this->smarty->fetch($template_filename);
		}
		
		$this->after();
		
		return $return_value;
	}
	
	/**
	 * @returns Response|string
	 */
	public function view(){ }
	
	function after_view(){ }
	
	function template_exists($template_filename)
	{
		return $this->smarty->templateExists($template_filename);
	}
	
	function after(){ }
	
	/**
	 * 指定されたオブジェクトから自分にプロパティをコピーする
	 *
	 * @param $obj
	 *
	 * @return View
	 * @throws Exception
	 */
	function import_property($obj)
	{
		if( ! is_object($obj) ){
			throw new Exception('cannot import from non-object');
		}
		foreach(get_object_vars($obj) as $key => $value){
			if( property_exists($this, $key) ){
				$this->{$key} = $value;
			}
		}
		
		return $this;
	}
	
	function __get($name)
	{
		return $this->get($name);
	}
	
	function __set($name, $arg)
	{
		return $this->set($name, $arg);
	}
	
	function get($name)
	{
		return property_exists($this, $name) ? $this->{$name} : null;
	}
	
	function set($name, $value)
	{
		if( property_exists($this, $name) ){
			$this->{$name} = $value;
		}
		
		return $this;
	}
}
