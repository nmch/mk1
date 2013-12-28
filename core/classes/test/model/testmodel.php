<?
class test_model_testmodel extends Model
{
	protected static $_table_name = 'testmodel';
	protected static $_join = array(
		'left join testmodel_belongsto on test_id=testbelongsto_test_id',
	);
	protected static $_add_field = 'test_int1 * test_int2 as test_int_power';
}
