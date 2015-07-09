<?php

class Facebook_Signedrequest
{
	use Singleton;

	const SIGNED_REQUEST_ALGORITHM = 'HMAC-SHA256';
	public    $algorithm;
	public    $expires;
	public    $issued_at;
	public    $oauth_token;
	public    $user_id;
	public    $user = [];
	public    $access_token;
	protected $signed_request;

	function __construct($data = null, $auto_extend = true)
	{
		if( $data === null ){
			if( isset($_REQUEST['signed_request']) ){
				$data = $this->parseSignedRequest($_REQUEST['signed_request']);
			}
		}
		if( $data ){
			$this->signed_request = $data;
			if( is_array($data) ){
				foreach($data as $key => $value){
					$this->$key = $value;
				}
			}
			if( $this->oauth_token ){
				$this->access_token = new Facebook_Accesstoken($this);
				if( $auto_extend ){
					$this->access_token->auto_extend();
				}
			}
		}
	}

	protected function parseSignedRequest($signed_request)
	{
		list($encoded_sig, $payload) = explode('.', $signed_request, 2);

		// decode the data
		$sig  = Facebook::base64UrlDecode($encoded_sig);
		$data = json_decode(Facebook::base64UrlDecode($payload), true);
		//Log::coredebug("[fb] signed_request",$data);

		if( strtoupper($data['algorithm']) !== self::SIGNED_REQUEST_ALGORITHM ){
			throw new FacebookException('Unknown algorithm. Expected ' . self::SIGNED_REQUEST_ALGORITHM);
		}

		// check sig
		$expected_sig = hash_hmac('sha256', $payload, Facebook_Config::getAppSecret(), $raw = true);
		if( $sig !== $expected_sig ){
			throw new FacebookException('Bad Signed JSON signature!');
		}

		return $data;
	}

	public function get_access_token()
	{
		return $this->access_token;
	}

	public function set_access_token($token)
	{
		if( empty($token) ){
			throw new FacebookException('empty token');
		}
		$this->oauth_token = $token;

		return $this->access_token = new Facebook_Accesstoken($token);
	}
}
