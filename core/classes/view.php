<?
require_once('Smarty/libs/Smarty.class.php');

class View
{
	var $af;
	var $smarty;
	var $template_filename;
	private $do_not_clear_flash = false;
	protected $data;
	
	function __construct($template_filename = NULL,$data = array(),$do_not_clear_flash = false)
	{
		$this->do_not_clear_flash = $do_not_clear_flash;
		
		if( ! $template_filename ){
			$template_filename = implode('/',array_slice(explode('_',strtolower(get_called_class())),1));
		}
		$this->template_filename = $template_filename.'.'.Config::get('smarty.extension');
		
		$this->smarty = new Smarty();
		
		$list = [COREPATH.'views/'];
		foreach(glob(PKGPATH.'*',GLOB_ONLYDIR) as $dir){
			$list[] = $dir.'/'.'views/';
		}
		$list[] = APPPATH.'views/';
		$this->smarty->template_dir = array_reverse($list);
		//$this->smarty->template_dir = APPPATH.'views/';
		$environments = Config::get('smarty.environment');
		if(is_array($environments)){
			foreach($environments as $name => $environment){
				if($name == 'plugins_dir')
					$environment = array_merge(array(SMARTY_PLUGINS_DIR),$environment);
				$this->smarty->$name = $environment;
			}
		}
		
		$this->af = Actionform::instance();
		$this->data = $data;
		$this->set_view();
		$this->before();
	}
	protected function set_view() {}
	public function view() {}
	
	function set_smarty_environment($name,$value)
	{
		$this->smarty->$name = $value;
		return $this;
	}
	function nofilter()
	{
		$this->set_smarty_environment('default_modifiers',array());
		return $this;
	}
	function render()
	{
		$r = $this->view();
		
		$this->after_view();
		
		// view()でset_flashする可能性があるので、clear_flash()はview()のあとで。
		if( ! $this->do_not_clear_flash ){
			Session::clear_flash();
		}
		
		// view()がResponseオブジェクト(JSONを想定)を返した場合はそのまま呼び出し元(たぶんResopnse::send()へ返す
		if($r instanceof Response)
			return $r;
		
		if( ! $this->template_exists($this->template_filename) ){
			if(is_scalar($this->template_filename))
				Log::error("template not found {$this->template_filename}");
			throw new HttpNotFoundException();
		}
		//echo "<PRE>"; print_r($this->smarty); echo "</PRE>";
		
		$this->smarty->assignByRef('data', $this->data);
		$this->smarty->assignByRef('af', $this->af);
		return $this->smarty->fetch($this->template_filename);
	}
	function template_exists($template_filename)
	{
		return $this->smarty->templateExists($template_filename);
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
	function __destruct()
	{
		$this->after();
	}
	function before() {}
	function after_view() {}
	function after() {}
}
