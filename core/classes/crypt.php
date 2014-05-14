<?php
require_once('phpseclib/Crypt/AES.php');

/**
 * 暗号化
 */
class Crypt
{
	function __construct()
	{
		$this->cipher = new Crypt_AES(CRYPT_AES_MODE_ECB);
		$this->cipher->setKeyLength(256);
		$this->cipher->setKey(Config::get('crypt.crypto_key'));
	}
	
	function encode($value, $key = NULL)
	{
		if($key){
			$this->cipher->setKey($key);
		}
		return $this->cipher->encrypt($value);
	}
	function decode($value, $key = NULL)
	{
		if($key){
			$this->cipher->setKey($key);
		}
		return $this->cipher->decrypt($value);
	}
}
