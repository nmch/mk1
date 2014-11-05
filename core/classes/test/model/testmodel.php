<?
class test_model_testmodel extends Model
{
	protected static $_table_name = 'testmodel';
	protected static $_join = array(
		'left join testmodel_join on test_id=testjoin_test_id',
	);
	protected static $_add_field = 'test_int1 * test_int2 as test_int_power';
	protected static $_belongs_to = array(
		'belongsto' => array(
			'key_from' => 'test_id',
			'model_to' => 'test_model_testmodel_belongsto',
			'key_to' => 'testbelongsto_test_id',
		),
		'belongsto_autogen' => array(
			'key_from' => 'test_id',
			'model_to' => 'test_model_testmodel_belongsto',
			'key_to' => 'testbelongsto_test_id',
			'autogen' => true,
		),
	);
	public static $_conditions;
}
