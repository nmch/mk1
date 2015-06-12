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

		Log::coredebug("mail files = ",$this->get_config('file'));
		if( $this->get_config('file') ){
			$boundary            = '__BOUNDARY__' . md5(rand());
			$additional_header[] = "Content-Type: multipart/mixed;boundary=\"{$boundary}\"\n";
			$body                = "--{$boundary}\n";
			$body .= "Content-Type: text/plain; charset=\"ISO-2022-JP\"\n";
			$body .= "\n{$this->get_config('body')}\n";
			foreach($this->config['file'] as $filepath){
				if( ! file_exists($filepath) ){
					throw new MkException("file not found");
				}
				$attach_mime_type = "application/octet-stream";
				$filebase         = basename($filepath);
			            $body .= "\n--{$boundary}\n";
			            $body .= "Content-Type: {$attach_mime_type}; name=\"{$filebase}\"\n";
			            $body .= "Content-Disposition: attachment; filename=\"{$filebase}\"\n";
			            $body .= "Content-Transfer-Encoding: base64\n";
			            $body .= "\n";
			            $body .= chunk_split(base64_encode(file_get_contents($filepath))) . "\n";
			}
			$body .= "--{$boundary}--";
		}

		if( isset($this->config['from']) ){
			$from = $this->config['from'];
			if( isset($this->config['from_name']) ){
				$from = mb_encode_mimeheader($this->config['from_name']) . " <{$from}>";
			}
			$additional_header[] = "From: $from";
		}

		if( isset($this->config['cc']) && is_array($this->config['cc']) ){
			$additional_header[] = "Cc: " . implode(',', $this->config['cc']);
		}
		if( isset($this->config['bcc']) && is_array($this->config['bcc']) ){
			$additional_header[] = "Bcc: " . implode(',', $this->config['bcc']);
		}
		$additional_header = implode("\n", $additional_header);

		$to      = implode(',', $this->config['to']);
		$subject = isset($this->config['subject']) ? $this->config['subject'] : '';
		$r       = mb_send_mail($to, $subject, $body, $additional_header);
		if( $r !== true ){
			Log::error("メールの送信に失敗しました", $r, $to, $subject, $body, $additional_header);
			throw new EmailSendingFailedException();
		}
		Log::coredebug("sent email to $to [$subject]");
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
