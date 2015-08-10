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

	function get_index()
	{
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
			$model_name = $this->get_model_name();
			if( ! preg_match('/^Model_(.+)$/', $model_name, $match) ){
				throw new Exception("unexpected model name {$model_name}");
			}
			$primary_key_name  = $model_name::primary_key();
			$primary_key_value = $this->af->get($primary_key_name);
			$af_preset_name    = strtolower($match[1]);

			if( $this->af->delete && $primary_key_value ){
				/** @var Model $obj */
				$obj = $model_name::find($primary_key_value);
				$obj->delete();
				$this->af->set_message('success', "削除しました");
			}
			else{
				Log::info("プリセット [{$af_preset_name}] でバリデーションと自動保存を行います");
				Log::debug("保存前af", $this->af->as_array());

				/** @var Model $obj */
				$obj = $this->af->save($af_preset_name);

				if( method_exists($this, 'after_save_detail') ){
					$this->after_save_detail($obj);
				}

				Log::debug("保存後obj", $obj->as_array());

				$this->af->set_message('success', "保存しました");
			}
			/*
			if( ! $id && $obj->get($obj->primary_key()) ){
				// 新規保存でIDが発行された (エラー無し) の場合はリストへリダイレクト
				$list_path = '/'.strtolower(str_replace('_', '/', $this->get_base_class_name()));

				return new Response_Redirect($list_path);
			}

			return $this->get_detail($obj->get($obj->primary_key()));
			*/
			$list_path = '/' . strtolower(str_replace('_', '/', $this->get_base_class_name()));

			if( Arr::get($options, 'redirect_to_detail') ){
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
