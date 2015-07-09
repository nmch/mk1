<?

class DbTest extends Testcase
{
	static function setUpBeforeClass()
	{
		DB::delete_all_tables();
		$q = "
			CREATE TABLE testtable (
				test_id SERIAL PRIMARY KEY,
				test_text TEXT,
				test_int1 INTEGER,
				test_int2 INTEGER,
				test_created_at TIMESTAMP
			);
		";
		DB::query($q)->execute();
		DB::clear_schema_cache();
	}

	function test空白のwhere_openでエラーにならない()
	{
		$r = DB::select()
		       ->from('testtable')
		       ->where_open()
		       ->where_close()
		       ->execute();
		$this->assertInstanceOf('Database_Resultset', $r);
	}
}
