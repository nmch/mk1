<?php
require_once('Smarty/libs/Smarty.class.php');

class View
{
	/** @var Actionform $af */
	protected $af;
	protected $smarty;
	protected $template_filename;
	protected $data;
	/** @var bool render()時にフラッシュメッセージをクリアしない */
	private $do_not_clear_flash = false;

	function __construct($template_filename = null, $data = [], $do_not_clear_flash = false)
	{
		$this->af                 = Actionform::instance();
		$this->do_not_clear_flash = $do_not_clear_flash;

		$this->af->_view_class_name = strtolower(get_called_class());

		if( ! $template_filename ){
			$template_filename = implode('/', array_slice(explode('_', $this->af->_view_class_name), 1));
		}
		$this->template_filename = $template_filename . '.' . Config::get('smarty.extension');

		$this->smarty = new Smarty();

		// テンプレートディレクトリ設定
		$list = [COREPATH . 'views/'];
		foreach(glob(PKGPATH . '*', GLOB_ONLYDIR) as $dir){
			$list[] = $dir . '/' . 'views/';
		}
		$list[]                     = APPPATH . 'views/';
		$this->smarty->template_dir = array_reverse($list);
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
		$this->smarty->plugins_dir = array_reverse($list);

		// その他の設定
		$environments = Config::get('smarty.environment');
		if( is_array($environments) ){
			foreach($environments as $name => $environment){
				if( $name !== 'plugins_dir' ){    //プラグインディレクトリは先に設定済み
					$this->smarty->$name = $environment;
				}
			}
		}

		//Log::debug("compile_dir = ",$this->smarty->compile_dir);
		$this->data = $data;
		$this->set_view();
		$this->before();
	}

	protected function set_view()
	{
	}

	function before()
	{
	}

	function before_view()
	{
	}

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
			$template_filename = $this->change_template_filename() ?: $this->template_filename;
			if( ! $this->template_exists($template_filename) ){
				if( is_scalar($template_filename) ){
					Log::error("template not found {$template_filename}");
				}
				throw new HttpNotFoundException();
			}
			//echo "<PRE>"; print_r($this->smarty); echo "</PRE>";

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
				//				Log::debug(__CLASS__.'::'.__METHOD__.' '.$key);
				$this->$key = $value;
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
		return property_exists($this, $name) ? $this->$name : null;
	}

	function set($name, $value)
	{
		if( property_exists($this, $name) ){
			$this->$name = $value;
		}

		return $this;
	}
}
