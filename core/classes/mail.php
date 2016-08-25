<?php

class AttachmentNotFoundException extends MkException
{
}

class InvalidAttachmentsException extends MkException
{
}

class InvalidEmailStringEncoding extends MkException
{
}

class EmailSendingFailedException extends MkException
{
}

class EmailValidationFailedException extends MkException
{
}

class Mail
{
	protected $config = [];
	
	function __construct()
	{
		$setup_name   = Config::get('mail.setup', 'default');
		$this->config = Arr::merge(
			Config::get('mail.default', []),
			Config::get('mail.' . $setup_name, [])
		);
	}
	
	public static function instance()
	{
		return new self;
	}
	
	function get_config($name)
	{
		return Arr::get($this->config, $name);
	}
	
	function set_config($name, $value)
	{
		Arr::set($this->config, $name, $value);
		
		return $this;
	}
	
	function send()
	{
		if( empty($this->config['to']) ){
			throw new Exception('empty to');
		}
		
		$additional_header = [];
		
		$body = $this->get_config('body');
		
		Log::coredebug("mail files = ", $this->get_config('file'));
		if( $this->get_config('file') ){
			$boundary            = '__BOUNDARY__' . md5(uniqid(rand(), true));
			$additional_header[] = "MIME-Version: 1.0";
			$additional_header[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
			//$additional_header[] = "Content-Transfer-Encoding: 7bit";
			$body_array   = [];
			$body_array[] = "--{$boundary}";
			$body_array[] .= 'Content-Type: text/plain; charset="UTF-8"';
			$body_array[] = '';
			$body_array[] .= $this->get_config('body');
			foreach($this->config['file'] as $filepath){
				if( is_array($filepath) ){
					$fileinfo = $filepath;
					$filepath = Arr::get($fileinfo, 'filepath');
					$filename = Arr::get($fileinfo, 'filename') ?: basename($filepath);
					$filemime = Arr::get($fileinfo, 'mime') ?: "application/octet-stream";
				}
				else{
					$filename = basename($filepath);
					$filemime = "application/octet-stream";
				}
				
				if( ! file_exists($filepath) ){
					throw new MkException("file not found");
				}
				$body_array[] = '';
				$body_array[] .= "--{$boundary}";
				$body_array[] .= "Content-Type: {$filemime}; name=\"{$filename}\"";
				$body_array[] .= "Content-Disposition: attachment; filename=\"{$filename}\"";
				$body_array[] .= "Content-Transfer-Encoding: base64";
				$body_array[] .= "";
				$body_array = array_merge($body_array, str_split(base64_encode(file_get_contents($filepath)), 76));
				//$body_array[] .= chunk_split(base64_encode(file_get_contents($filepath)), 76, "\n") . "\n";
			}
			$body_array[] = "--{$boundary}--";
			$body         = implode("\r\n", $body_array);
		}
		else{
			$additional_header[] = 'Content-Type: text/plain; charset="UTF-8"';
		}
		
		$from_address = null;
		$from         = null;
		if( isset($this->config['from']) ){
			$from_address = $this->config['from'];
			$from         = $from_address;
			
			if( isset($this->config['from_name']) ){
				$from_name         = $this->config['from_name'];
				$encoded_from_name = mb_encode_mimeheader($from_name);
				$from              = "{$encoded_from_name} <{$from}>";
			}
			$additional_header[] = "From: {$from}";
		}
		
		if( isset($this->config['cc']) && is_array($this->config['cc']) ){
			$additional_header[] = "Cc: " . implode(',', $this->config['cc']);
		}
		if( isset($this->config['bcc']) && is_array($this->config['bcc']) ){
			$additional_header[] = "Bcc: " . implode(',', $this->config['bcc']);
		}
		$imploded_additional_header = implode("\r\n", $additional_header);
		
		$additional_parameter = null;
		if( isset($this->config['envelope_from']) ){
			$additional_parameter = "-f{$this->config['envelope_from']}";
		}
		
		$to              = implode(',', $this->config['to']);
		$subject         = $this->get_config('subject');
		$encoded_subject = mb_encode_mimeheader($subject);
		$r               = mail($to, $encoded_subject, $body, $imploded_additional_header, $additional_parameter);
		
		if( $r !== true ){
			Log::error("メールの送信に失敗しました", $r, $to, $subject, $body, $additional_header);
			throw new EmailSendingFailedException();
		}
		Log::coredebug("sent email to $to [$subject]");
	}
	
	function envelope_from($address)
	{
		$this->config['envelope_from'] = $address;
	}
	
	function from($address, $name = '')
	{
		if( is_array($address) ){
			$name    = Arr::get($address, 1);
			$address = Arr::get($address, 0);
		}
		$this->config['from']      = $address;
		$this->config['from_name'] = $name;
		
		return $this;
	}
	
	function template($view, $data = [])
	{
		if( is_scalar($view) ){
			// 第三引数はフラッシュメッセージをクリアさせないフラグ
			$view = new View("mail/" . $view, $data, true);
		}
		if( ! $view instanceof View ){
			throw new MkException("invalid object");
		}
		$body = $view->set_smarty_environment('default_modifiers', [])->render();
		list($subject, $body) = explode("\n", $body, 2);
		$this->subject($subject);
		$this->body($body);
		
		return $this;
	}
	
	function subject($subject)
	{
		$this->config['subject'] = $subject;
		
		return $this;
	}
	
	function body($body)
	{
		$this->config['body'] = $body;
		
		return $this;
	}
	
	function to($address)
	{
		if( ! is_array($address) ){
			$address = [$address];
		}
		$this->config['to'] = $address;
		
		return $this;
	}
	
	function cc($address)
	{
		if( ! is_array($address) ){
			$address = [$address];
		}
		$this->config['cc'] = $address;
		
		return $this;
	}
	
	function bcc($address)
	{
		if( ! is_array($address) ){
			$address = [$address];
		}
		$this->config['bcc'] = $address;
		
		return $this;
	}
	
	function file($filepath)
	{
		if( ! is_array($filepath) ){
			$filepath = [$filepath];
		}
		$this->config['file'] = $filepath;
		
		return $this;
	}
}
