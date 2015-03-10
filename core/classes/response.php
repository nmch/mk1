<?php

class Response
{
	/** @var string|View $body */
	protected $body;
	protected $status;
	protected $headers;
	protected $before_send_functions = [];

	protected function __construct($body = null, $status = 200, array $headers = [])
	{
		Log::coredebug("[response] status=$status");
		$this->body    = $body;
		$this->status  = $status;
		$this->headers = $headers;
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
		if( headers_sent() ){
			return false;
		}
		http_response_code($this->status);
		foreach($this->headers as $key => $header){
			header($key . ':' . $header);
		}

		return true;
	}

	/**
	 * コンテンツ送出後処理
	 *
	 * 送出が行われなかった場合は実行されない
	 */
	protected function after() { }

	public function send()
	{
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

				//bodyがResponseオブジェクトだった場合(View::view()がResponseインスタンスを返した場合)、そこから先はそのインスタンスのsend()に任せる
				if( $body instanceof Response ){
					return $body->send();
				}
			}
			else{
				$body = $this->body;
			}
			echo $body;

			$this->after();
		}
	}
}
