<?php

class Aws_Sdk
{
	protected $credentials = [];
	
	function set_credentials(?array $credentials): Aws_Sdk
	{
		if( $credentials ){ // 上書きしたくない場合はnullを指定することができる
			$this->credentials = $credentials;
		}
		
		return $this;
	}
	
	function get_credentials()
	{
		$access_key_id     = null;
		$secret_access_key = null;
		
		if( $api_config = \Config::get('aws.api') ){
			if( ($id = ($api_config['access_key'] ?? null)) && ($secret = ($api_config['access_secret'] ?? null)) ){
				$access_key_id     = $id;
				$secret_access_key = $secret;
			}
			if( ($id = ($api_config['access_key_id'] ?? null)) && ($secret = ($api_config['secret_access_key'] ?? null)) ){
				$access_key_id     = $id;
				$secret_access_key = $secret;
			}
		}
		if( ($id = getenv("AWS_ACCESS_KEY_ID")) && ($secret = getenv("AWS_SECRET_ACCESS_KEY")) ){
			$access_key_id     = $id;
			$secret_access_key = $secret;
		}
		if( $this->credentials ){
			if( ($id = ($this->credentials[0] ?? null)) && ($secret = ($this->credentials[1] ?? null)) ){
				$access_key_id     = $id;
				$secret_access_key = $secret;
			}
			if( ($id = \Arr::get($this->credentials, 'access_key_id')) && ($secret = \Arr::get($this->credentials, 'secret_access_key')) ){
				$access_key_id     = $id;
				$secret_access_key = $secret;
			}
		}
		
		$credentials = null;
		if( $access_key_id && $secret_access_key ){
			$credentials = [
				'key'    => $access_key_id,
				'secret' => $secret_access_key,
			];
		}
		
		return $credentials;
	}
	
	function get_region()
	{
		$region = "ap-northeast-1";
		if( $value = getenv("AWS_DEFAULT_REGION") ){
			$region = $value;
		}
		
		return $region;
	}
	
	function get_aws_sdk_config(): array
	{
		$config = [
			'region'  => $this->get_region(),
			'version' => 'latest',
		];
		
		if( $credentials = $this->get_credentials() ){
			$config['credentials'] = $credentials;
		}
		
		if( $http_proxy = getenv('HTTP_PROXY') ){
			\Arr::set($config, 'http.proxy.http', $http_proxy);
		}
		
		return $config;
	}
	
	function get_sdk(?array $config = null): \Aws\Sdk
	{
		$sdk = new \Aws\Sdk($config ?? $this->get_aws_sdk_config());
		
		return $sdk;
	}
	
	function get_cfn_client(): \Aws\CloudFormation\CloudFormationClient
	{
		$sdk = $this->get_sdk();
		
		$client = $sdk->createCloudFormation();
		
		return $client;
	}
	
	function get_ecs_client(): \Aws\Ecs\EcsClient
	{
		$sdk = $this->get_sdk();
		
		$client = $sdk->createEcs();
		
		return $client;
	}
	
	function get_s3_client(?string $endpoint = null): \Aws\S3\S3Client
	{
		$config = $this->get_aws_sdk_config();
		
		if( $endpoint ){
			$config['endpoint']                = $endpoint;
			$config["use_path_style_endpoint"] = true;
		}
		
		$sdk = $this->get_sdk($config);
		
		$client = $sdk->createDynamoDb();
		
		return $client;
	}
	
	function get_dynamodb_client(string $endpoint = null): \Aws\DynamoDb\DynamoDbClient
	{
		$config = $this->get_aws_sdk_config();
		
		$config['endpoint'] = $endpoint;
		
		$sdk = $this->get_sdk($config);
		
		$client = $sdk->createDynamoDb();
		
		return $client;
	}
}