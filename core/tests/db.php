<?
class DbTest extends Testcase
{
	static function setUpBeforeClass()
	{
		DB::delete_all_tables();
		$q = "
			create table testtable (
				test_id serial primary key,
				test_text text,
				test_int1 integer,
				test_int2 integer,
				test_created_at timestamp
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
		$this->assertInstanceOf('Database_Resultset',$r);
	}
}
