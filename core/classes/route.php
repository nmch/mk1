<?
class Route
{
	static function get_controller($uri)
	{
		$request_method = empty($_SERVER['REQUEST_METHOD']) ? '' : strtolower($_SERVER['REQUEST_METHOD']);
		//Log::coredebug("[route] request_method=$request_method");
		
		$controller_name = '';
		$controller_method_name = '';
		$controller_method_options = array();
		
		if( ! is_array($uri) )
			$uri = static::get_route($uri);
		
		if( is_array($uri) ){
			list($controller_name,$controller_method_name) = $uri;
			$controller_name = 'Controller_'.ucfirst($controller_name);
			$controller_method_name = static::find_method($controller_name,$controller_method_name);
		}
		else{
			$uri_exploded = explode('/',$uri);
			
			$controller_name = '';
			$controller_method_name = '';
			for($c = 0;$c < count($uri_exploded);$c++){
				$tmp_uri_exploded = $uri_exploded;
				
				$controller_name_candidate = implode('_',array_map(function($str){ return ucfirst($str); },array_splice($tmp_uri_exploded,0,count($tmp_uri_exploded) - $c)));
				$controller_name_candidate = 'Controller_'.$controller_name_candidate;
				
				$controller_method_name_candidate = count($tmp_uri_exploded) ? strtolower(array_shift($tmp_uri_exploded)) : 'index';
				//Log::coredebug("[route] controller_name_candidate=$controller_name_candidate");
				//Log::coredebug("[route] controller_method_name_candidate=$controller_method_name_candidate");
				
				
				if(class_exists($controller_name_candidate)){
//					Log::coredebug("[route] Found controller class $controller_name_candidate");
					$controller_method_name = static::find_method($controller_name_candidate,$controller_method_name_candidate,$request_method);
//					Log::coredebug("[route] Found controller method $controller_method_name");
					
					if( $controller_method_name ){
						$controller_name = $controller_name_candidate;
						$controller_method_options = $tmp_uri_exploded;
						break;
					}
				}
			}
			
			// 最後にクラス名 + ***_index()メソッドに渡せるかどうかチェック
			if( ! class_exists($controller_name) || ! method_exists($controller_name,$controller_method_name) ){
				if(count($uri_exploded) > 1){
					$controller_method_name = static::find_method('Controller_'.$uri_exploded[0],'index',$request_method);
					if( $controller_method_name ){
						$controller_name = $controller_name_candidate;
						$controller_method_options = $tmp_uri_exploded;
					}
				}
			}
		}
		//Log::coredebug("[route] controller_name=$controller_name");
		//Log::coredebug("[route] controller_method_name=$controller_method_name");
		if( ! class_exists($controller_name) || ! method_exists($controller_name,$controller_method_name) ){
			//Log::coredebug("Not Found $controller_name / $controller_method_name");
			throw new HttpNotFoundException();
		}
//		$controller = new $controller_name;
		
		return array(
			$controller_name,
			$controller_method_name,
			$controller_method_options,
		);
	}
	static function find_method($controller_name,$controller_method_name_candidate,$request_method = NULL)
	{
		//Log::coredebug("find_method $controller_name $request_method $controller_method_name_candidate");
		if( ! class_exists($controller_name) )
			return false;
		
		$controller_method_name = false;
		
		// 実行するメソッドをコントローラー自身が判断できるfind_method()メソッドを実装している場合は処理をそちらに任せる
		if( method_exists($controller_name,'find_method') ){
			//Log::coredebug("[route] delegate to $controller_name::find_method()");
			return $controller_name::find_method($controller_method_name_candidate,$request_method);
		}
		
		if( $request_method && method_exists($controller_name,$request_method.'_'.$controller_method_name_candidate )){
			// リクエストメソッドを冠したget_XXX()やpost_XXX()が定義されている場合はそれを採用
			$controller_method_name = $request_method.'_'.$controller_method_name_candidate;
		}
		else if( method_exists($controller_name,'action_'.$controller_method_name_candidate) ){
			// 上記が無い場合はaction_XXXを採用
			$controller_method_name = 'action_'.$controller_method_name_candidate;
		}
		
		return $controller_method_name;
	}
	
	static function get_route($uri)
	{
		$uri = trim($uri,'/');
		
		if( ! $uri )
			$uri = explode('/',Config::get('routes._root_','default/index'));
		
		$routes = Config::get('routes');
		foreach($routes as $search => $route){
			if($search[0] == '_')
				continue;
			
			$search = str_replace(array(
				':any',
				':alnum',
				':num',
				':alpha',
				':segment',
			), array(
				'.+',
				'[[:alnum:]]+',
				'[[:digit:]]+',
				'[[:alpha:]]+',
				'[^/]*',
			), $search);
			$_uri = preg_replace("#^$search$#",$route,$uri);
			if($uri != $_uri){
				Log::coredebug("[route] Route ReWrited : [$uri] to [$_uri]");
				$uri = $_uri;
			}
		}
		
		return $uri;
	}
}
