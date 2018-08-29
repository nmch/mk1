<?php

class test_Mk extends Testcase
{
	function testブートストラップ()
	{
		global $mk;
		$this->assertInstanceOf('Mk', $mk);

		$this->assertFileExists(FWPATH . 'mk.php');
		$this->assertFileExists(COREPATH . 'classes/mk.php');
	}
}
