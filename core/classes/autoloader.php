<?
class Autoloader
{
	public static function register()
	{
		spl_autoload_register('Autoloader::load', true, true);
	}
	
	public static function load($class)
	{
		if(class_exists($class, false))
			return true;
		// deal with funny is_callable('static::classname') side-effect
		if (strpos($class, 'static::') === 0)
		{
			// is called from within the class, so it's already loaded
			return true;
		}
		//echo "try load $class<BR>\n";
		
		$filepath_candidates = [];
		$class_exploded = explode('_',ltrim(preg_replace_callback('/(_?[A-Z]+[a-z]*)/',function($splitted){
			$str = $splitted[0];
			if($str[0] != '_')
				$str = '_'.$str;
			return strtolower($str);
		},$class),'_'));
		foreach($class_exploded as $key => $value){
			$path = $key > 0 ? implode('/',array_slice($class_exploded,0,$key,true)) : '';
			$basename = implode('_',array_slice($class_exploded,$key,count($class_exploded),true));
			$filepath_candidates[] = [$path,$basename];
		}
		//echo "filepath_candidates = ".print_r($filepath_candidates,true);
		
		$include_path_list = [
			APPPATH,
			COREPATH,
		];
		
		
		// View::__construct()でも同じロジックを使っているので注意。いつか共通化する。
		$list = [COREPATH];
		foreach(glob(PKGPATH.'*',GLOB_ONLYDIR) as $dir){
			$list[] = $dir.'/';
		}
		$list[] = APPPATH;
		$include_path_list = array_reverse($list);
		//echo "<PRE>"; print_r($include_path_list);
		
		foreach($include_path_list as $include_path){
			$include_path .= 'classes/';
			
			foreach($filepath_candidates as $filepath_candidate){
				$path = $filepath_candidate[0];
				$basename = $filepath_candidate[1];
				
				if($path)
					$path .= '/';
				
				$filepath = $include_path.$path.$basename.'.php';
				//echo "check $filepath <br>\n";
				if(is_readable($filepath)){
					//echo "found! $filepath<BR>";
					include($filepath);
					if(class_exists($class) && method_exists($class, '_init') && is_callable($class.'::_init'))
						call_user_func($class.'::_init');
					if(class_exists($class) || interface_exists($class))
						return true;
				}
			}
		}
		return false;
	}
}
