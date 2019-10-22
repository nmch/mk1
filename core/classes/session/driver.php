<?php

abstract class Session_Driver implements SessionHandlerInterface
{
	protected $config;
	
	function __construct($config = [])
	{
		$this->config = $config;
	}
	
	static function hash($value): string
	{
		return md5($value);
	}
	
	function encode_data($data)
	{
		$encoded_data = base64_encode($data); // session.serialize_handlerが'php'の場合、バイナリが渡されることがあるのでbase64でエンコードしたものを保存する
		$hash         = static::hash($encoded_data);
		
		$original_data = null;
		switch($this->config['serialize_handler']){
			case 'php_serialize':
				try {
					$original_data = unserialize($data);
				} catch(Exception $e){
					$original_data = null;
				}
				break;
		}
		
		return [$encoded_data, $hash, $original_data];
	}
	
	function decode_data($encoded_data, $hash)
	{
		$decoded_data = base64_decode($encoded_data, true);
		if( $decoded_data === false ){
			// もともとシリアライズされたデータがエンコードされているはずなので、デコードしてfalseになることはないはず
			Log::warning("セッションデータのデコードに失敗しました", $encoded_data);
			$decoded_data = null;
		}
		elseif( $hash ){
			$encoded_data_hash = static::hash($encoded_data);
			if( $encoded_data_hash !== $hash ){
				Log::warning("セッションデータのハッシュ値検査に失敗しました", $encoded_data, $encoded_data_hash, $hash);
				$decoded_data = null;
			}
		}
		
		return $decoded_data;
	}
}
