<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Response
{
	/** @var string|View $body */
	protected $body;
	protected $status;
	protected $headers;
	protected $before_send_functions = [];
	/** @var bool send()実行時にコンテンツをechoしない */
	protected $do_not_display = false;
	
	public function __construct($body = null, $status = 200, array $headers = [])
	{
		Log::coredebug("[response] status=$status");
		$this->set_body($body);
		$this->set_status($status);
		
		foreach($headers as $key => $value){
			$this->set_header($key, $value);
		}
		foreach(Config::get('response.default_headers', []) as $key => $value){
			$this->set_header($key, $value);
		}
	}
	
	/**
	 * 即座にリダイレクトする
	 *
	 * Response_Redirect()を推奨
	 *
	 * @param string $url
	 * @param string $method
	 * @param int    $redirect_code
	 */
	public static function redirect($url = '', $method = 'location', $redirect_code = 302)
	{
		$response = new static;
		
		$response->set_status($redirect_code);
		
		//		if (strpos($url, '://') === false)
		//		{
		//			$url = $url !== '' ? \Uri::create($url) : \Uri::base();
		//		}
		//
		//		strpos($url, '*') !== false and $url = \Uri::segment_replace($url);
		
		if( $method == 'location' ){
			$response->set_header('Location', $url);
		}
		elseif( $method == 'refresh' ){
			$response->set_header('Refresh', '0;url=' . $url);
		}
		else{
			return;
		}
		
		$response->send(true);
		exit;
	}
	
	public function set_body($body)
	{
		$this->body = $body;
		
		return $this;
	}
	
	public function get_body_as_json()
	{
		return json_decode($this->body, true);
	}
	
	public function set_body_as_json($data)
	{
		$this->set_body(($data === null) ? null : Mk::json_encode($data));
		
		$this->set_header('Content-Type', Config::get('response_json.content-type', 'application/json; charset=utf-8'));
		
		return $this;
	}
	
	/**
	 * レスポンスコードを設定する
	 *
	 * @param int $status
	 */
	public function set_status($status)
	{
		$this->status = $status;
	}
	
	/**
	 * @param null $status
	 *
	 * @return int レスポンスコードを設定・取得する
	 */
	public function status($status = null): int
	{
		if( $status !== null ){
			$this->status = $status;
		}
		
		return $this->status;
	}
	
	/**
	 * HTTPヘッダを設定する
	 *
	 * @param string $name
	 * @param string $value
	 */
	public function set_header($name, $value)
	{
		$this->headers[$name] = $value;
	}
	
	/**
	 * 送出前実行関数を設定する
	 *
	 * @param string   $name
	 * @param callable $func
	 */
	public function set_before_send_functions($name, $func)
	{
		$this->before_send_functions[$name] = $func;
	}
	
	/**
	 * コンテンツ送出前処理
	 *
	 * @return bool not trueを返すとコンテンツが送出されない
	 */
	protected function before()
	{
		return true;
	}
	
	function send_header()
	{
		if( ! Mk::is_cli() ){
			if( headers_sent() ){
				Log::warning("HTTPヘッダがすでに送出されているためResponse処理を中断します");
				
				return false;
			}
			http_response_code($this->status);
			foreach($this->headers as $key => $header){
				if( $header === null ){
					continue;
				}
				if( is_bool($header) ){
					$header = $header ? 'true' : 'false';
				}
				//Log::debug2($key, var_export($header, true));
				header($key . ':' . $header);
			}
		}
	}
	
	/**
	 * コンテンツ送出後処理
	 *
	 * 送出が行われなかった場合は実行されない
	 */
	protected function after(){ }
	
	/**
	 * @return Response
	 * @throws HttpNotFoundException
	 * @throws MkException
	 */
	public function send()
	{
		//		Log::coredebug(__CLASS__,__METHOD__,__FILE__,__LINE__);
		if( $this->before() ){
			if( is_array($this->before_send_functions) ){
				foreach($this->before_send_functions as $func){
					if( is_callable($func) ){
						call_user_func_array($func, [$this]);
					}
				}
			}
			
			if( $this->body instanceof View ){
				$body = $this->body->render();
				
				// bodyがResponseオブジェクトだった場合(View::view()がResponseインスタンスを返した場合)、そこから先はそのインスタンスのsend()に任せる
				if( $body instanceof Response ){
					return $body->send();
				}
				
				if( $content_type = $this->body->content_type() ){
					$this->set_header("Content-Type", $content_type);
				}
			}
			else{
				$body = $this->body;
			}
			
			if( ! $this->do_not_display ){
				$this->set_header("Content-Length", strlen($body));
				$this->send_header();
				echo $body;
			}
			
			$this->after();
		}
		else{
			Log::coredebug("Response::before()がfalseを返したため実行を中断しました");
		}
		
		return $this;
	}
	
	/**
	 * @param bool $flag send()実行時にコンテンツをechoしないフラグ
	 */
	function do_not_display($flag)
	{
		$this->do_not_display = boolval($flag);
	}
}
