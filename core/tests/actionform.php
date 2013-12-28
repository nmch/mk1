<?
class test_Actionform extends Testcase
{
	protected $af;
	
	function setUp()
	{
		$_POST = array_merge($_POST,[
			'var_str' => 'string',
			'var_int' => 12345,
			'var_boolean' => true,
		]);
		$this->af = Actionform::instance();
	}
	
	function test外部からの変数()
	{
		$this->assertEquals('string',$this->af->var_str);
		$this->assertEquals(12345,$this->af->var_int);
		$this->assertEquals(true,$this->af->var_boolean);
	}
}
