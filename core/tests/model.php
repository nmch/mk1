<?

class ModelTest extends Testcase
{
	static function setUpBeforeClass()
	{
		DB::delete_all_tables();
		$q = "
			CREATE TABLE testmodel (
				test_id SERIAL PRIMARY KEY,
				test_text TEXT,
				test_int1 INTEGER,
				test_int2 INTEGER,
				test_json JSON,
				test_created_at TIMESTAMP
			);
			CREATE TABLE testmodel_join (
				testjoin_id SERIAL PRIMARY KEY,
				testjoin_test_id INTEGER REFERENCES testmodel(test_id) ON UPDATE CASCADE ON DELETE CASCADE,
				testjoin_text TEXT,
				testjoin_created_at TIMESTAMP
			);
			CREATE TABLE testmodel_belongsto (
				testbelongsto_id SERIAL PRIMARY KEY,
				testbelongsto_test_id INTEGER REFERENCES testmodel(test_id) ON UPDATE CASCADE ON DELETE CASCADE,
				testbelongsto_text TEXT,
				testbelongsto_created_at TIMESTAMP
			);
		";
		DB::query($q)->execute();
		DB::clear_schema_cache();
	}

	function testCRUD()
	{
		$obj            = new test_model_testmodel;
		$obj->test_text = 'test';
		$obj->save();
		$this->assertInstanceOf('test_model_testmodel', $obj);
		$this->assertGreaterThanOrEqual(1, $obj->test_id);
		$id = $obj->test_id;
		$r  = DB::query("SELECT * FROM testmodel")->execute()->as_array();
		$this->assertEquals('test', $r[0]['test_text']);

		unset($obj);
		$obj = test_model_testmodel::find($id);
		$this->assertInstanceOf('test_model_testmodel', $obj);

		$obj->test_text = 'test2';
		$obj->save();
		$r = DB::query("SELECT * FROM testmodel")->execute()->as_array();
		$this->assertEquals('test2', $r[0]['test_text']);

		$obj->delete();
		$this->assertNull($obj->test_id);
		$r = DB::query("SELECT * FROM testmodel")->execute()->as_array();
		$this->assertEquals([], $r);
	}

	function testJson()
	{
		// ************** 配列
		$testarray      = ['key' => 'value'];
		$obj            = new test_model_testmodel;
		$obj->test_json = $testarray;
		$obj->save();
		// セーブしても配列のまま。
		$this->assertEquals($testarray, $obj->test_json);
		$id = $obj->test_id;

		unset($obj);
		$obj = test_model_testmodel::find($id);
		// ロードしても同じ配列。
		$this->assertEquals($testarray, $obj->test_json);

		// 生データをみるとJSON
		$r = DB::select()->from('testmodel')->where('test_id', $id)->execute()->as_array();
		$this->assertEquals(json_encode($testarray), $r[0]['test_json']);

		// ************** NULL
		$obj->test_json = null;
		$obj->save();
		$this->assertEquals(null, $obj->test_json);
		$id = $obj->test_id;
		unset($obj);
		$obj = test_model_testmodel::find($id);
		$this->assertEquals(null, $obj->test_json);
		$r = DB::select()->from('testmodel')->where('test_id', $id)->execute()->as_array();
		$this->assertEquals(null, $r[0]['test_json']);

		// ************** 空配列
		$obj->test_json = [];
		$obj->save();
		$this->assertEquals([], $obj->test_json);
		$id = $obj->test_id;
		unset($obj);
		$obj = test_model_testmodel::find($id);
		$this->assertEquals([], $obj->test_json);
		$r = DB::select()->from('testmodel')->where('test_id', $id)->execute()->as_array();
		$this->assertEquals('[]', $r[0]['test_json']);

		// ************** 空文字列
		$obj->test_json = '';
		$obj->save();
		$this->assertEquals('', $obj->test_json);
		$id = $obj->test_id;
		unset($obj);
		$obj = test_model_testmodel::find($id);
		$this->assertEquals('', $obj->test_json);
		$r = DB::select()->from('testmodel')->where('test_id', $id)->execute()->as_array();
		$this->assertEquals('""', $r[0]['test_json']);

		// ************** 後始末
		$obj->delete();
		$r = DB::query("SELECT * FROM testmodel")->execute()->as_array();
		$this->assertEquals([], $r);
	}

	function testJoin()
	{
		$obj            = new test_model_testmodel;
		$obj->test_text = 'test';
		$obj->save();
		$id = $obj->test_id;

		$obj2                   = new test_model_testmodel_join;
		$obj2->testjoin_test_id = $obj->test_id;
		$obj2->testjoin_text    = 'test_join';
		$obj2->save();

		unset($obj);

		$obj = test_model_testmodel::find($id);
		$this->assertEquals('test_join', $obj->testjoin_text);

		$obj2->testjoin_text = 'test_join_changed';
		$obj2->save();
		$obj->reload();
		$this->assertEquals('test_join_changed', $obj->testjoin_text);

		$obj->delete();
		$r = DB::query("SELECT * FROM testmodel")->execute()->as_array();
		$this->assertEquals([], $r);
	}

	function testBelongsto()
	{
		$obj            = new test_model_testmodel;
		$obj->test_text = 'test';
		$obj->save();
		$id = $obj->test_id;
		$this->assertNull($obj->belongsto);

		$obj2                        = new test_model_testmodel_belongsto;
		$obj2->testbelongsto_test_id = $obj->test_id;
		$obj2->testbelongsto_text    = 'test_belongsto';
		$obj2->save();
		$id2 = $obj2->testbelongsto_id;

		$this->assertInstanceof('test_model_testmodel_belongsto', $obj->belongsto);
		$this->assertEquals('test_belongsto', $obj->belongsto->testbelongsto_text);

		$obj2->testbelongsto_text = 'test_belongsto_changed';
		$obj2->save();

		$this->assertEquals('test_belongsto_changed', $obj->belongsto->testbelongsto_text);

		$obj->delete();
		$r = DB::query("SELECT * FROM testmodel")->execute()->as_array();
		$this->assertEquals([], $r);
	}

	function testBelongstoAutogen()
	{
		$obj = new test_model_testmodel;

		$this->assertInstanceof('test_model_testmodel_belongsto', $obj->belongsto_autogen);
		$this->assertNull($obj->belongsto_autogen->testbelongsto_test_id);
		$this->assertNull($obj->belongsto_autogen->testbelongsto_id);

		$obj->test_text = 'test';
		$obj->save();
		$id = $obj->test_id;

		$this->assertInstanceof('test_model_testmodel_belongsto', $obj->belongsto_autogen);
		$this->assertEquals($id, $obj->belongsto_autogen->testbelongsto_test_id);
		$this->assertNull($obj->belongsto_autogen->testbelongsto_id);

		$obj->delete();
		$r = DB::query("SELECT * FROM testmodel")->execute()->as_array();
		$this->assertEquals([], $r);
	}

	function testAddfield()
	{
		$obj            = new test_model_testmodel;
		$obj->test_int1 = 123;
		$obj->test_int2 = 100;
		$obj->save();
		$this->assertEquals(123 * 100, $obj->test_int_power);

		$obj->test_int1 = 256;
		$obj->test_int2 = 789;
		$obj->save();
		$this->assertEquals(256 * 789, $obj->test_int_power);

		$obj->delete();
		$r = DB::query("SELECT * FROM testmodel")->execute()->as_array();
		$this->assertEquals([], $r);
	}

	function testConditionsOrderby()
	{
		test_model_testmodel::$_conditions = [
			'order_by' => 'test_int1',
		];
		$sql                               = test_model_testmodel::find()->get_query()->get_sql();
		$this->assertContains(strtolower('ORDER BY test_int1 asc'), strtolower($sql));

		test_model_testmodel::$_conditions = [
			'order_by' => [
				'test_int1' => 'asc',
				'test_int2' => 'desc',
			],
		];
		$sql                               = test_model_testmodel::find()->get_query()->get_sql();
		$this->assertContains(strtolower('ORDER BY test_int1 asc,test_int2 desc'), strtolower($sql));

		test_model_testmodel::$_conditions = [
			'order_by' => [
				['test_int1', 'asc'],
				['test_int2', 'desc'],
			],
		];
		$sql                               = test_model_testmodel::find()->get_query()->get_sql();
		$this->assertContains(strtolower('ORDER BY test_int1 asc,test_int2 desc'), strtolower($sql));

		test_model_testmodel::$_conditions = [
			[
				'label'   => 'test_int1',
				'name'    => 'order_by',
				'options' => ['test_int1', 'asc'],
			],
			[
				'label'   => 'test_int2',
				'name'    => 'order_by',
				'options' => ['test_int2', 'desc'],
			],
		];
		$sql                               = test_model_testmodel::find()->get_query()->get_sql();
		$this->assertContains(strtolower('ORDER BY test_int1 asc,test_int2 desc'), strtolower($sql));
	}
}
