<?

class Dataobject implements ArrayAccess
{
	private $data;

	function __construct($data = [])
	{
		$this->data = $data;
	}

	function __get($name)
	{
		return $this->offsetGet($name);
	}

	function __set($name, $value)
	{
		$this->offsetSet($name, $value);
	}

	public function offsetGet($offset)
	{
		return $this->offsetExists($offset) ? $this->data[$offset] : null;
	}

	public function offsetExists($offset)
	{
		return array_key_exists($offset, $this->data);
	}

	public function offsetSet($offset, $value)
	{
		$this->data[$offset] = $value;
	}

	public function offsetUnset($offset)
	{
		unset($this->data[$offset]);
	}
}
