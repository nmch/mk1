<?
class Dataobject implements ArrayAccess
{
	private $data;
	
	function __get($name)
	{
		return $this->offsetGet($name);
	}
	function __set($name,$value)
	{
		$this->offsetSet($name,$value);
	}
	
	function __construct($data = array())
	{
		$this->data = $data;
	}
	
    public function offsetSet($offset, $value) {
		$this->data[$offset] = $value;
    }
    public function offsetExists($offset) {
		return array_key_exists($offset,$this->data);
    }
    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }
    public function offsetGet($offset) {
        return $this->offsetExists($offset) ? $this->data[$offset] : NULL;
    }
}
