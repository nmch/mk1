<?php

/**
 * Class Curl
 */
class Curl
{
	const OP_RETURN_AS_JSON         = 'return_as_json';
	const OP_CONVERT_ENCODING       = 'convert_encoding';
	const OP_EXCEPTION_WHEN_NOT_200 = 'exception_when_not_200';
	const OP_CAMOUFLAGE_UA          = 'camouflage_ua';
	const OP_CURL_SETTINGS          = 'curl_settings';
	const OP_BASE_URL               = 'base_url';
	const OP_REQUEST_HEADERS        = 'request_headers';
	const OP_REQUEST_DATA           = 'request_data';
	const OP_USERPWD                = 'userpwd';

	const METHOD_GET    = 'GET';
	const METHOD_POST   = 'POST';
	const METHOD_PUT    = 'PUT';
	const METHOD_DELETE = 'DELETE';

	/** @var array 実行時オプション */
	private $options = [];
	/** @var array curl初期化時オプション */
	private $curl_options = [];
	/** @var string メソッド */
	private $method = '';
	/** @var array リクエスト時のデータ */
	private $request_data = [];
	/** @var array */
	private $response_header = [];

	/** @var  Resource curl handle */
	private $curl;
	/** @var  array */
	private $curl_version;
	/** @var  string curl実行結果 */
	private $curl_result;
	/** @var  array エラー詳細 */
	private $curl_error;
	/** @var  array curl_getinfo()の結果 */
	private $curl_info;

	/** @var  Resource エラーを出力するファイルポインタ */
	private $error_output_file;
	/** @var  Resource 転送ヘッダを出力するファイルポインタ */
	private $transfer_header_file;
	/** @var  string Cookie保存用ファイルのパス */
	private $cookie_path;

	function __construct(array $options = [], array $curl_options = [])
	{
		$this->options = $options + [
				static::OP_RETURN_AS_JSON         => true,
				static::OP_CONVERT_ENCODING       => false,
				static::OP_EXCEPTION_WHEN_NOT_200 => false,
				static::OP_CAMOUFLAGE_UA          => true,
				static::OP_BASE_URL               => '',
				static::OP_REQUEST_DATA           => [],
				static::OP_REQUEST_HEADERS        => [],
				'default_ua'                      => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30; .NET CLR 3.0.04506.648)',
			];
		$this->setup_curl($curl_options);
	}

	/**
	 * 直前のリクエストのレスポンスヘッダを取得する
	 *
	 * @param null $key
	 *
	 * @return array|mixed
	 */
	public function response_header($key = null)
	{
		if( $key === null ){
			return $this->response_header;
		}
		else{
			return Arr::get($this->response_header, $key);
		}
	}

	/**
	 * 直前のリクエストの情報を取得する
	 *
	 * @param null $key
	 *
	 * @return array|mixed
	 */
	public function response_info($key = null)
	{
		if( $key === null ){
			return $this->curl_info;
		}
		else{
			return Arr::get($this->curl_info, $key);
		}
	}

	/**
	 * オプションを設定する
	 *
	 * @param $key
	 * @param $name
	 *
	 * @return $this
	 */
	public function set_option($key, $name)
	{
		Arr::set($this->options, $key, $name);

		return $this;
	}

	/**
	 * オプションを取得する
	 *
	 * @param $name
	 *
	 * @return mixed
	 */
	public function get_option($name)
	{
		return Arr::get($this->options, $name);
	}

	/**
	 * GET APIを実行
	 *
	 * @param string $url
	 * @param array  $data
	 *
	 * @return mixed|string
	 * @throws MkException
	 */
	public function get($url, array $data = [])
	{
		$this->method       = static::METHOD_GET;
		$this->request_data = $data;

		return $this->retrieve($url);
	}

	/**
	 * DELETE APIを実行
	 *
	 * @param string $url
	 * @param array  $data
	 *
	 * @return mixed|string
	 * @throws MkException
	 */
	public function delete($url, array $data = [])
	{
		$this->method       = static::METHOD_DELETE;
		$this->request_data = $data;

		return $this->retrieve($url);
	}

	/**
	 * POST APIを実行
	 *
	 * @param string $url
	 * @param array  $data
	 *
	 * @return mixed|string
	 * @throws MkException
	 */
	public function post($url, array $data = [])
	{
		$this->method       = static::METHOD_POST;
		$this->request_data = $data;

		return $this->retrieve($url);
	}

	/**
	 * 設定されているメソッドでcURLを実行
	 *
	 * @param $path
	 *
	 * @return mixed|string
	 * @throws MkException
	 */
	private function retrieve($path)
	{
		$curl_options = [];

		// メソッドごとのcURLの設定
		switch($this->method){
			case static::METHOD_GET:
				$curl_options[CURLOPT_HTTPGET]       = true;
				$curl_options[CURLOPT_CUSTOMREQUEST] = null;
				break;
			case static::METHOD_DELETE:
				$curl_options[CURLOPT_POST]          = 0;
				$curl_options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
				break;
			case static::METHOD_POST:
				$curl_options[CURLOPT_POST]          = true;
				$curl_options[CURLOPT_CUSTOMREQUEST] = null;
				break;
			default:
				throw new MkException("unknown method");
		}

		// ベースURLを使ったURLの設定
		if( $base_url = $this->get_option(static::OP_BASE_URL) ){
			$url = rtrim($base_url, '/') . '/' . ltrim($path, '/');
		}
		else{
			$url = $path;
		}

		// 送信するデータの処理
		$request_data = array_merge(Arr::get($this->options, static::OP_REQUEST_DATA, []), $this->request_data);
		if( $request_data ){
			if( $this->method === static::METHOD_POST ){
				$curl_options[CURLOPT_POSTFIELDS] = is_array($request_data) ? http_build_query($request_data) : $request_data;
			}
			else{
				$url .= '?' . http_build_query($request_data);
			}
		}

		// ユーザ名・パスワード
		if( $userpwd = Arr::get($this->options, static::OP_USERPWD) ){
			$curl_options[CURLOPT_USERPWD] = $userpwd[0] . ':' . $userpwd[1];
		}

		$curl_options[CURLOPT_URL] = $url;

		/**
		 * リクエストヘッダは[key => value]の形になっているので、['key: value']の形に整形する
		 */
		if( $request_headers = Arr::get($this->options, static::OP_REQUEST_HEADERS) ){
			$curl_headers = [];
			foreach($request_headers as $key => $value){
				$curl_headers[] = "{$key}: {$value}";
			}
			$curl_options[CURLOPT_HTTPHEADER] = $curl_headers;
		}

		Log::coredebug("cURL リクエストオプション", $curl_options);
		curl_setopt_array($this->curl, $curl_options);

		Log::coredebug("cURLの実行準備が整いました: method={$this->method} / url={$url}");

		$this->execute_curl();
		$http_code = intval($this->response_info('http_code'));

		if( Arr::get($this->options, static::OP_EXCEPTION_WHEN_NOT_200) && $http_code !== 200 ){
			throw new MkException('Bad Http Response', $http_code);
		}
		if( $to_encoding = Arr::get($this->options, static::OP_CONVERT_ENCODING) ){
			$this->curl_result = mb_convert_encoding($this->curl_result, $to_encoding, 'SJIS-win');
		}
		if( Arr::get($this->options, static::OP_RETURN_AS_JSON) ){
			$result = json_decode($this->curl_result, true);
			if( $result === null ){
				Log::error("cURL: JSONのデコードに失敗しました", $this->curl_result);
				throw new MkException('JSONのデコードに失敗しました');
			}
		}
		else{
			$result = $this->curl_result;
		}

		return $result;
	}

	/**
	 * cURLを実行
	 *
	 * @throws MkException
	 */
	private function execute_curl()
	{
		Log::coredebug("cURLを実行します");
		$this->curl_result = curl_exec($this->curl);
		$this->curl_info   = curl_getinfo($this->curl);
		$errno             = curl_errno($this->curl);
		Log::coredebug("cURLの実行が完了しました errno={$errno}");
		Log::coredebug("curl_info = " . print_r($this->curl_info, true));

		rewind($this->transfer_header_file);
		$response_header_str = stream_get_contents($this->transfer_header_file);
		Log::coredebug("header = {$response_header_str}");
		$response_header = [];
		foreach(explode("\n", $response_header_str) as $line){
			$line                  = explode(':', $line, 2);
			$key                   = trim(Arr::get($line, 0));
			$value                 = trim(Arr::get($line, 1));
			$response_header[$key] = $value;
		}
		$this->response_header = $response_header;

		rewind($this->error_output_file);
		$error_output = stream_get_contents($this->error_output_file);
		Log::coredebug("error = " . $error_output);

		$this->curl_error = [];
		if( $errno !== 0 ){
			$this->curl_error = [
				'errno'    => $errno,
				'strerror' => curl_strerror($errno),
				'error'    => curl_error($this->curl),
				'stderr'   => $error_output,
			];
			Log::debug("curl error", $this->curl_error);
		}

		if( $this->curl_result === false ){
			throw new MkException("curl execution failed");
		}
	}

	/**
	 * cURLを初期化
	 *
	 * @param array $curl_options
	 */
	private function setup_curl(array $curl_options)
	{
		$this->curl_version = curl_version();
		$this->curl         = curl_init();

		$this->error_output_file    = tmpfile();
		$this->transfer_header_file = tmpfile();

		$this->cookie_path = tempnam(null, 'CURL');

		$this->curl_options = $curl_options + [
				CURLOPT_VERBOSE        => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HEADER         => false,
				CURLOPT_AUTOREFERER    => true,
				CURLOPT_COOKIESESSION  => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_COOKIEFILE     => $this->cookie_path,
				CURLOPT_COOKIEJAR      => $this->cookie_path,
				//			    CURLOPT_FILE => 'step1.txt',
				CURLOPT_STDERR         => $this->error_output_file,
				CURLOPT_WRITEHEADER    => $this->transfer_header_file,
			];

		if( $camouflage_ua = Arr::get($this->options, static::OP_CAMOUFLAGE_UA) ){
			$this->curl_options[CURLOPT_USERAGENT] = is_string($camouflage_ua) ? $camouflage_ua : Arr::get($this->options, 'default_ua');
		}

		curl_setopt_array($this->curl, $this->curl_options);

		Log::coredebug("CURLを初期化しました", $this->curl_version);
	}

	function __destruct()
	{
		curl_close($this->curl);

		fclose($this->error_output_file);
		fclose($this->transfer_header_file);
	}
}
