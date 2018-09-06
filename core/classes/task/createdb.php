<?php

/**
 * DB作成
 */
class Task_Createdb extends Task
{
	function run($name = null)
	{
		$config                     = \Database_Connection::get_config($name);
		$original_connection_config = \Database_Connection::get_connection_config($config);
		$target_database_name       = null;
		
		// DB接続設定のデータベース名をtemplate1に書き換える
		if( preg_match("/dbname=([^ ]+)/", $original_connection_config, $match) ){
			$target_database_name = $match[1];
			$connection_config    = str_replace("dbname={$target_database_name}", "dbname=template1", $original_connection_config);
		}
		else{
			throw new MkException("DB接続設定からデータベース名が識別できませんでした");
		}
		
		// 設定情報を利用し、template1に接続する
		$conn = new \Database_Connection(
			[
				'connection' => $connection_config,
			] + $config
		);
		
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
