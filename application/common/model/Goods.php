<?php
/**
 * 商品模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-11-29 9:54
 */

namespace app\common\model;
use think\Cache;
use think\Db;
use think\Model;

class Goods extends Model{
	//状态常量
	const GOODS_STATE_ONE 	= 0;
	const GOODS_STATE_TWO 	= 1;
	const GOODS_STATE_THREE = 3;


    /**
     * 获取SKU缓存
     * @param $goods_id string 商品ID
     * @return array|mixed
     */
    public function getSkuCache($goods_id){
        if(!$goods_id) return array();
        $gid_key = "sku_".$goods_id;
        $data = Cache::get($gid_key);
        if($data){
           return $data; 
        }else{
            $goods_sku_arr = array();
            $goodsSku = Db::name('goods_sku')->where(['goods_id'=>$goods_id])->column('goods_sku');
            foreach($goodsSku as $k=>$v){
                $goods_sku_arr[] = $v;
            }
            Cache::set($gid_key,$goods_sku_arr);
            return $goods_sku_arr;
        }

    }

	/**
	 * 商品分页条件查询
	 * @param string $field 所要查询的字段
	 * @param array $where 查询条件
	 * @param int $page 当前页数, 从0开始
	 * @param int $number 每页显示数量
	 * @return array $result 商品信息
	 */
	public function inquire( $field = '*', $where, $page = 0, $number = 10 ) {
		$where['is_delete'] = 0;
		$total     = $this->where( $where )->count();
        $goodsList = $this->field( $field )->where( $where )->limit( $page*$number.','.$number )->select();
        return array( 'data' => $goodsList, 'total' => $total, 'page' => $page, 'total_page' => ceil( $total/$number ) );
	}


	/**
	 * 商品分页条件查询
	 * @param string $field 所要查询的字段
	 * @param array $where 查询条件
	 * @param int $page 当前页数, 从0开始
	 * @param int $number 每页显示数量
	 * @return array $result 商品信息
	 */
	public function getCount($where) {
		return $this->where($where)->field('goods_id')->count('goods_id');
	}


    /**
     * 下单扣除总库存和增加总销量
     * @param $goods_id string  商品的ID
     * @param $num int  商品的购买数量
     * @return bool/int  false/number
     */
    private function _changeSaleNumber($goods_id,$num){
        $data = array();
        $data['goods_sale_number'] = array('exp','goods_sale_number+'.$num);
        $data['goods_storage'] = array('exp','goods_storage-'.$num);
        return $this->where(array('goods_id'=>$goods_id))->update($data);
    }

    /*
     * 更新总库存数量
     * */
    public function changeAllKCNumber($goods_id_arr=array(),$boolg=1,$sale_id_arr=array())
    {
        if(count($goods_id_arr)<=0) return false;
        $bool=true;
        if($boolg){//下单扣除总库存和增加总销量
            foreach($goods_id_arr as $goods_id => $quality){
                $rtb=$this->_changeSaleNumber($goods_id,$quality);
                if(!$rtb){
                    $bool=false;
                    break;
                }
            }
        }else{//退款退货和取消订单更正总库存和总销量

            foreach($goods_id_arr as $goods_id=>$goods_sale_number) {
                if($goods_sale_number>0){
                    $data = array();
                    $data['goods_storage'] = array('exp','goods_storage+'.$goods_sale_number);
                    $this->where(array('goods_id'=>$goods_id))->update($data);
                }
            }

            foreach($sale_id_arr as $goods_id2=>$goods_sale_number2) {
                if($goods_sale_number2>0){
                    $data = array();
                    $data['goods_sale_number'] = array('exp','goods_sale_number-'.$goods_sale_number2);
                    $this->where(array('goods_id'=>$goods_id2))->update($data);
                }
            }
        }
        return $bool;
    }
}
