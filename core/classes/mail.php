<?

class AttachmentNotFoundException extends MkException {}
class InvalidAttachmentsException extends MkException {}
class InvalidEmailStringEncoding extends MkException {}
class EmailSendingFailedException extends MkException {}
class EmailValidationFailedException extends MkException {}

class Mail
{
	protected $config = array();
	
	function send()
	{
		if(empty($this->config['to']))
			throw new Exception('empty to');
		
		$additional_header = array();
		if(isset($this->config['from'])){
			$from = $this->config['from'];
			if(isset($this->config['from_name'])){
				$from = mb_encode_mimeheader($this->config['from_name'])." <{$from}>";
			}
			$additional_header[] = "From: $from";
		}
		
		if(isset($this->config['cc']) && is_array($this->config['cc']))
			$additional_header[] = "Cc: ".implode(',',$this->config['cc']);
		if(isset($this->config['bcc']) && is_array($this->config['bcc']))
			$additional_header[] = "Bcc: ".implode(',',$this->config['bcc']);
		$additional_header = implode("\n",$additional_header);
		
		$to = implode(',',$this->config['to']);
		$subject = isset($this->config['subject']) ? $this->config['subject'] : '';
		$body = isset($this->config['body']) ? $this->config['body'] : '';
		$r = mb_send_mail($to, $subject, $body, $additional_header);
		if($r !== true){
			Log::error("メールの送信に失敗しました",$r,$to, $subject, $body, $additional_header);
			throw new EmailSendingFailedException();
		}
		Log::coredebug("sent email to $to [$subject]");
	}
	
	function __construct()
	{
		$setup_name = Config::get('mail.setup','default');
		$this->config = Arr::merge(
			Config::get('mail.default',array()),
			Config::get('mail.'.$setup_name,array())
		);
	}
	public static function instance()
	{
		return new self;
	}
	function from($address,$name = '')
	{
		$this->config['from'] = $address;
		$this->config['from_name'] = $name;
		
		return $this;
	}
	function template($view,$data = array())
	{
		if(is_scalar($view))
			$view = new View("mail/".$view,$data,true);	//第三引数はフラッシュメッセージをクリアさせないフラグ
		$body = $view->set_smarty_environment('default_modifiers',array())->render();
		list($subject,$body) = explode("\n",$body,2);
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
		if( ! is_array($address) )
			$address = array($address);
		$this->config['to'] = $address;
		return $this;
	}
	function cc($address)
	{
		if( ! is_array($address) )
			$address = array($address);
		$this->config['cc'] = $address;
		return $this;
	}
	function bcc($address)
	{
		if( ! is_array($address) )
			$address = array($address);
		$this->config['bcc'] = $address;
		return $this;
	}
}
