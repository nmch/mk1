<?
class CryptTest extends Testcase
{
	function test暗号化()
	{
		$str = 'test';
		$key = 'test';
		
		$encoded = (new Crypt)->encode($str,$key);
		
		$obj2 = new Crypt;
		$this->assertEquals($str, $obj2->decode($encoded,$key));
		$this->assertNotEquals($str, $obj2->decode($encoded,$key.'2'));
	}
}
