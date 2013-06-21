<?php

class Task_J
{
	
	
	static function test_create()
	{
		\DB::start_transaction();
		$job = new \Model_Job;
		$job->job_method_group = 'job';
		$job->job_method_name = 'test_exec';
		$job->job_title = 'テスト';
		$job->job_success_notice = TRUE;
		$job->save();
		$job2 = new \Model_Job;
		$job2->job_method_group = 'job';
		$job2->job_method_name = 'test_exec';
		$job2->job_title = 'テスト2';
		$job2->job_success_notice = TRUE;
		$job2->job_wait_id = $job->job_id;
		$job2->save();
		
		$job3 = new \Model_Job;
		$job3->job_method_group = 'job';
		$job3->job_method_name = 'test_exec_fail';
		$job3->job_title = 'FAILテスト';
		$job3->save();
		
		$job4 = new \Model_Job;
		$job4->job_method_group = 'job';
		$job4->job_method_name = 'test_exec_error';
		$job4->job_title = 'errorテスト';
		$job4->save();
		
		\DB::commit_transaction();
	}
	
	static function test_exec($job)
	{
		$job->doing();
	}
	static function test_exec_fail($job)
	{
		$job->doing();
		throw new \Exception('test fail');
	}
	static function test_exec_error($job)
	{
		$job->doing();
		new COM('123');
	}
	
	static function test_fb()
	{
		$q = \Model_User::ormquery_get_valid_users()
			->where('user_facebook_accesstoken','is not',NULL)
			->and_where_open()
			->where('user_facebook_imported_at','is',NULL)
			->or_where('user_facebook_imported_at','<',new \Database_Expression("now() + '-1 hours'"))
			->and_where_close()
			->limit(15);
		$users_block = $q->get();
		
		$requests = array();
		foreach($users_block as $user)
			$requests[] = $user->user_facebook_id;
		
		$job = new \Model_Job;
		$job->job_method_group = 'facebook';
		$job->job_method_name = 'update_users';
		$job->job_title = 'User update from Facebook';
		$job->job_value_serialized = serialize($requests);
		$job->save();
	}
}
