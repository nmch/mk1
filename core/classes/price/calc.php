<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

/**
 * 価格計算
 */
class Price_Calc
{
	protected $config = [];
	/** @var Price_Item[] */
	protected $items = [];
	
	public $partitioned_items    = [];
	public $existing_tax_rates   = [];
	public $existing_tax_classes = [];
	
	function __construct($config = [])
	{
		$this->config = $config;
	}
	
	function add_item(Price_Item $item)
	{
		$this->items[] = $item->calc();
		
		return $this;
	}
	
	function get_items(): array
	{
		return $this->items;
	}
	
	function get_items_count(): int
	{
		return count($this->items);
	}
	
	function calc(
		Price_Tax_Calc_Type $tax_calc_type
		, Price_Round $price_round
		, Price_Round $tax_round
	)
	{
		bcscale(0);
		$this->partitioned_items = [];
		
		/**
		 * 消費税率と税込区分について、それぞれの配列用キーと連番を計算する
		 * キーが連番になっていないとJSで処理する際にArrayとして認識してくれないので
		 * PHP側でも連想配列ではなく配列として格納できるようにしておく
		 */
		$tax_rate_key_map  = [];
		$tax_class_key_map = [];
		foreach($this->items as $item){
			$tax_rate_key  = $item->tax_rate->get_rate_for_index();
			$tax_class_key = $item->tax_class->get_tax_class();
			
			if( ! in_array($tax_rate_key, $tax_rate_key_map) ){
				$tax_rate_key_map[] = $tax_rate_key;
			}
			if( ! in_array($tax_class_key, $tax_class_key_map) ){
				$tax_class_key_map[] = $tax_class_key;
			}
		}
		$this->existing_tax_rates   = array_flip($tax_rate_key_map);
		$this->existing_tax_classes = array_flip($tax_class_key_map);
		
		foreach($this->items as $item){
			// 消費税率別・税込区分別に整理する
			$tax_rate_key  = $item->tax_rate->get_rate_for_index();
			$tax_class_key = $item->tax_class->get_tax_class();
			
			$tax_rate_index  = $this->existing_tax_rates[$tax_rate_key];
			$tax_class_index = $this->existing_tax_classes[$tax_class_key];
			
			$array_key = "per_tax_rate.{$tax_rate_index}.per_tax_class.{$tax_class_index}.list";
			if( ! Arr::get($this->partitioned_items, $array_key) ){
				Arr::set($this->partitioned_items, $array_key, []);
			}
			$this->partitioned_items['per_tax_rate'][$tax_rate_index]['per_tax_class'][$tax_class_index]['list'][] = $item;
		}
		
		$total = [
			'total_without_tax' => '0',
			'total_with_tax'    => '0',
			'total_tax'         => '0',
		];
		foreach($this->partitioned_items['per_tax_rate'] ?? [] as $tax_rate_index => $per_tax_rate){
			$per_tax_rate_total = [];
			
			foreach($per_tax_rate['per_tax_class'] ?? [] as $tax_class_index => $per_tax_class){
				$per_tax_class_total = [];
				
				/** @var Price_Item $item */
				foreach($per_tax_class['list'] ?? [] as $item){
					if( ! $per_tax_rate_total ){
						$per_tax_rate_total = [
							'tax_rate'          => $item->tax_rate,
							'total_without_tax' => '0',
							'total_with_tax'    => '0',
							'total_tax'         => '0',
						];
					}
					if( ! $per_tax_class_total ){
						$per_tax_class_total = [
							'tax_class'         => $item->tax_class,
							'total_input'       => '0',
							'total_without_tax' => '0',
							'total_with_tax'    => '0',
							'total_tax_adjust'  => '0',
							'total_tax'         => '0',
						];
					}
					
					$item_price             = $item->calced_price;
					$item_price_without_tax = $item->calced_price_without_tax;
					$item_price_with_tax    = $item->calced_price_with_tax;
					$item_tax_amount        = $item->calced_rounded_tax_amount;
					$item_tax_adjust        = $item->calced_tax_adjust;
					
					/**
					 * 伝票の消費税計算区分が0:無効の場合、明細の計算結果がどうであれ
					 * 伝票としての消費税はゼロにする
					 */
					if( $tax_calc_type->get_tax_calc_type() === 0 ){
						$item_price_with_tax = bcsub($item_price_with_tax, $item_tax_amount);
						$item_tax_amount     = '0';
					}
					
					$per_tax_class_total['total_input']       = bcadd($per_tax_class_total['total_input'], $item_price);
					$per_tax_class_total['total_without_tax'] = bcadd($per_tax_class_total['total_without_tax'], $item_price_without_tax);
					$per_tax_class_total['total_with_tax']    = bcadd($per_tax_class_total['total_with_tax'], $item_price_with_tax);
					$per_tax_class_total['total_tax']         = bcadd($per_tax_class_total['total_tax'], $item_tax_amount);
					$per_tax_class_total['total_tax_adjust']  = bcadd($per_tax_class_total['total_tax_adjust'], $item_tax_adjust);
				}
				
				/**
				 * 伝票毎の消費税計算モードの場合、合計金額から再計算する
				 */
				if( $tax_calc_type->is_tax_calc_per_slip() ){
					$price_item = new Price_Item(
						new Price_Amount($per_tax_class_total['total_input'], 1, $price_round)
						, new Price_Amount($per_tax_class_total['total_tax_adjust'], 1, $tax_round)
						, $per_tax_class_total['tax_class']
						, $per_tax_rate_total['tax_rate']
						, $price_round
						, $tax_round
					);
					$price_item->calc();
					
					$per_tax_class_total['tax_calced_per_slip'] = [
						'total_without_tax' => [
							'per_item' => $per_tax_class_total['total_without_tax'],
							'per_slip' => $price_item->calced_price_without_tax,
						],
						'total_with_tax'    => [
							'per_item' => $per_tax_class_total['total_with_tax'],
							'per_slip' => $price_item->calced_price_with_tax,
						],
						'total_tax'         => [
							'per_item' => $per_tax_class_total['total_tax'],
							'per_slip' => $price_item->calced_rounded_tax_amount,
						],
					];
					
					$per_tax_class_total['total_without_tax'] = $price_item->calced_price_without_tax;
					$per_tax_class_total['total_with_tax']    = $price_item->calced_price_with_tax;
					$per_tax_class_total['total_tax']         = $price_item->calced_rounded_tax_amount;
				}
				
				$this->partitioned_items['per_tax_rate'][$tax_rate_index]['per_tax_class'][$tax_class_index]['total'] = $per_tax_class_total;
				
				$per_tax_rate_total['total_without_tax'] = bcadd($per_tax_rate_total['total_without_tax'], $per_tax_class_total['total_without_tax']);
				$per_tax_rate_total['total_with_tax']    = bcadd($per_tax_rate_total['total_with_tax'], $per_tax_class_total['total_with_tax']);
				$per_tax_rate_total['total_tax']         = bcadd($per_tax_rate_total['total_tax'], $per_tax_class_total['total_tax']);
			}
			
			$this->partitioned_items['per_tax_rate'][$tax_rate_index]['total'] = $per_tax_rate_total;
			
			$total['total_without_tax'] = bcadd($total['total_without_tax'], $per_tax_rate_total['total_without_tax']);
			$total['total_with_tax']    = bcadd($total['total_with_tax'], $per_tax_rate_total['total_with_tax']);
			$total['total_tax']         = bcadd($total['total_tax'], $per_tax_rate_total['total_tax']);
		}
		
		$this->partitioned_items['total'] = $total;
	}
	
	function get_grand_total(): array
	{
		return $this->partitioned_items['total'] ?? [];
	}
}
