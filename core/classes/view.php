<?
require_once('Smarty/libs/Smarty.class.php');

class View
{
	var $af;
	var $smarty;
	var $template_filename;
	protected $data;
			
	function __construct($template_filename = NULL,$data = array())
	{
		if( ! $template_filename ){
			$template_filename = implode('/',array_slice(explode('_',strtolower(get_called_class())),1));
		}
		$this->template_filename = $template_filename.'.'.Config::get('smarty.extension');
		
		$this->smarty = new Smarty();
		
		$this->smarty->template_dir = APPPATH.'views/';
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
	function render()
	{
		Session::load_flash();
		
		$r = $this->view();
		
		// view()がResponseオブジェクト(JSONを想定)を返した場合はそのまま呼び出し元(たぶんResopnse::send()へ返す
		if($r instanceof Response)
			return $r;
		
		if( ! $this->smarty->templateExists($this->template_filename) )
			throw new HttpNotFoundException();
		
		$this->smarty->assignByRef('data', $this->data);
		$this->smarty->assignByRef('af', $this->af);
		return $this->smarty->fetch($this->template_filename);
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
	function after() {}
}
