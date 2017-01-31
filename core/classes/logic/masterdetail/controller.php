<?php

/**
 * マスタデータ編集
 *
 * @property Actionform af
 *
 * @package    App
 * @subpackage Logic
 * @author     Hakonet Inc
 */
trait Logic_Masterdetail_Controller
{
	use Logic_Masterdetail_Common;

	/** @var bool detail保存時にindexではなくdetailへ戻るフラグ */
	protected $redirect_to_detail = false;

	function get_index()
	{
		if( method_exists($this, 'before_get_index') ){
			$this->before_get_index();
		}

		$view_class_name = 'View_' . $this->get_base_class_name() . '_Index';
		if( ! class_exists($view_class_name) ){
			throw new Exception("view class({$view_class_name}) not found");
		}

		return new $view_class_name;
	}

	function post_detail($id = null)
	{
		$options = [];

		$savepoint = DB::place_savepoint();
		try {
			if( method_exists($this, 'before_post_detail') ){
				$options = $this->before_post_detail();
				if( ! is_array($options) ){
					$options = [];
				}
			}
			$model_name       = $this->get_model_name();
			if( ! preg_match('/Model_(.+)$/', $model_name, $match) ){
				throw new Exception("unexpected model name {$model_name}");
			}
			$primary_key_name  = $model_name::primary_key();
			$primary_key_value = $this->af->get($primary_key_name);
			$af_preset_name    = strtolower($match[1]);

			$deleted = false;
			$action  = '';
			if( $this->af->delete && $primary_key_value ){
				/** @var Model $obj */
				$obj = $model_name::find($primary_key_value);
				$obj->delete();
				$this->af->set_message('success', "削除しました");
				$deleted = true;
				$action  = 'delete';
			}
			else{
				Log::info("プリセット [{$af_preset_name}] でバリデーションと自動保存を行います");
				Log::coredebug("保存前af", $this->af->as_array());

				if( method_exists($this, 'before_save_detail') ){
					$r = $this->before_save_detail();
					if( is_array($r) ){
						$options = array_merge($options, $r);
					}
				}

				/** @var Model $obj */
				$obj = $this->af->save($af_preset_name);

				$primary_key_diff = $obj->get_save_diff($obj->primary_key());
				$action           = ( ! $primary_key_diff[0] && $primary_key_diff[1]) ? 'create' : 'update';

				if( method_exists($this, 'after_save_detail') ){
					$r = $this->after_save_detail($obj);
					if( is_array($r) ){
						$options = array_merge($options, $r);
					}
				}

				Log::coredebug("保存後obj", $obj->as_array());
				$this->af->set_message('success', "保存しました");
			}
			$list_path = '/' . strtolower(str_replace('_', '/', $this->get_base_class_name()));

			if( method_exists($this, 'after_process_detail') ){
				$r = $this->after_process_detail($action, $obj);
				if( is_array($r) ){
					$options = array_merge($options, $r);
				}
			}

			if( (Arr::get($options, 'redirect_to_detail') || $this->redirect_to_detail) && ! $deleted ){
				$list_path .= '/detail/' . $obj->$primary_key_name;
			}

			DB::commit_savepoint($savepoint);

			$redirect_to = Arr::get($options, 'redirect_to', $list_path);

			return new Response_Redirect($redirect_to);
		} catch(AppException $e){
			DB::rollback_savepoint($savepoint);
			$this->af->set_message('error', $e->getMessage());

			return $this->get_detail($id);
		} catch(Exception $e){
			DB::rollback_savepoint($savepoint);
			throw $e;
		}
	}

	abstract protected function get_model_name();

	private function prepare_view($view_class_name, $id)
	{
		if( ! class_exists($view_class_name) ){
			throw new Exception("view class({$view_class_name}) not found");
		}

		$view = new $view_class_name();

		$model_name = $this->get_model_name();
		if( ! class_exists($model_name) ){
			throw new Exception("model class({$model_name}) not found");
		}

		if( $id ){
			/** @var Model_Query $query */
			$query = $model_name::find()->where($model_name::primary_key(), $id);
			if( method_exists($this, 'get_ignore_conditions') ){
				$query->ignore_conditions($this->get_ignore_conditions());
			}
			$view->item = $query->get_one(true);
		}
		else{
			$view->item = new $model_name();
		}

		return $view;
	}

	function get_view($id)
	{
		$view_class_name = 'View_' . $this->get_base_class_name() . '_View';

		return $this->prepare_view($view_class_name, $id);
	}

	function get_detail($id = null)
	{
		$view_class_name = 'View_' . $this->get_base_class_name() . '_Detail';

		return $this->prepare_view($view_class_name, $id);
	}
}
