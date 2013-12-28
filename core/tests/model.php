<?
class ModelTest extends Testcase
{
	static function setUpBeforeClass()
	{
		DB::delete_all_tables();
		$q = "
			create table testmodel (
				test_id serial primary key,
				test_text text,
				test_int1 integer,
				test_int2 integer,
				test_created_at timestamp
			);
			create table testmodel_belongsto (
				testbelongsto_id serial primary key,
				testbelongsto_test_id integer references testmodel(test_id) on update cascade on delete cascade,
				testbelongsto_text text,
				testbelongsto_created_at timestamp
			);
		";
		DB::query($q)->execute();
		DB::clear_schema_cache();
	}
	
	function testCRUD()
	{
		$obj = new test_model_testmodel;
		$obj->test_text = 'test';
		$obj->save();
		$this->assertInstanceOf('test_model_testmodel',$obj);
		$this->assertGreaterThanOrEqual(1, $obj->test_id);
		$id = $obj->test_id;
		$r = DB::query("select * from testmodel")->execute()->as_array();
		$this->assertEquals('test', $r[0]['test_text']);
		
		unset($obj);
		$obj = test_model_testmodel::find($id);
		$this->assertInstanceOf('test_model_testmodel',$obj);
		
		$obj->test_text = 'test2';
		$obj->save();
		$r = DB::query("select * from testmodel")->execute()->as_array();
		$this->assertEquals('test2', $r[0]['test_text']);
		
		$obj->delete();
		$this->assertNull($obj->test_id);
		$r = DB::query("select * from testmodel")->execute()->as_array();
		$this->assertEquals([], $r);
	}
	
	function testBelongsto()
	{
		$obj = new test_model_testmodel;
		$obj->test_text = 'test';
		$obj->save();
		$id = $obj->test_id;
		
		$obj2 = new test_model_testmodel_belongsto;
		$obj2->testbelongsto_test_id = $obj->test_id;
		$obj2->testbelongsto_text = 'test_belongsto';
		$obj2->save();
		
		unset($obj);
		
		$obj = test_model_testmodel::find($id);
		$this->assertEquals('test_belongsto', $obj->testbelongsto_text);
		
		$obj2->testbelongsto_text = 'test_belongsto_changed';
		$obj2->save();
		$obj->reload();
		$this->assertEquals('test_belongsto_changed', $obj->testbelongsto_text);
		
		$obj->delete();
		$r = DB::query("select * from testmodel")->execute()->as_array();
		$this->assertEquals([], $r);
	}
	
	function testAddfield()
	{
		$obj = new test_model_testmodel;
		$obj->test_int1 = 123;
		$obj->test_int2 = 100;
		$obj->save();
		$this->assertEquals( 123 * 100, $obj->test_int_power);
		
		$obj->test_int1 = 256;
		$obj->test_int2 = 789;
		$obj->save();
		$this->assertEquals( 256 * 789, $obj->test_int_power);
		
		$obj->delete();
		$r = DB::query("select * from testmodel")->execute()->as_array();
		$this->assertEquals([], $r);
	}
}
