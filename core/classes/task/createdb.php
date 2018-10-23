<?php

/**
 * DB作成
 */
class Task_Createdb extends Task
{
	function run($name = null)
	{
		$target_database_name = null;
		
		$conn = Database_Connection::get_template1_connection([], $name);
		
		// 対象のデータベースが存在しているか確認
		$r = DB::select()
		       ->from('pg_database')
		       ->where('datname', $target_database_name)
		       ->execute($conn)
		;
		
		// 対象のデータベースが存在していなければ作成する
		if( ! $r->count() ){
			Log::info("[db create] データベース {$target_database_name} を作成します");
			DB::query("create database {$target_database_name}")->execute($conn);
		}
	}
}
