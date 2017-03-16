<?php
/**
 * 购买行为
 *
 * @copyright  Copyright (c) 2007-2014 CHShop Inc. (http://www.changhong.com.cn)
 * @license    http://www.changhong.com.cn
 * @link       http://www.changhong.com.cn
 * @since      File available since Release v1.1
 */
namespace app\common\logic;
use app\common\model\Cart;
use app\common\model\GoodsSku;
use think\db;

class Queue
{

    /**
     * 删除购物车
     * @param unknown $cart
     */
    public function delCart($cart) {
        if (!is_array($cart['cart_ids']) || empty($cart['member_id'])) return callback(true);
        $cart_obj = new Cart();
        $del = $cart_obj->deleteCart(array('member_id'=>$cart['member_id'],'cart_id'=>array('in',$cart['cart_ids'])));
        if (!$del) {
            return callback(false,'删除购物车数据失败');
        } else {
            return callback(true);
        }
    }

    /**
     * 取消订单变更库存销量 还原库存
     * @param unknown $goods_buy_quantity
     */
    public function cancelOrderUpdateStorage($goods_buy_quantity) {
        $model_goods = new GoodsSku();
        foreach ($goods_buy_quantity as $goods_sku => $quantity) {
            $data = array();
            $data['goods_storage'] = array('exp','goods_storage+'.$quantity);
            $result = $model_goods->editGoodsBySKU($data, array($goods_sku));
            if (!$result)  break;
        }
        if (!$result) {
            return callback(false,'变更商品库存与销量失败');
        } else {
            return callback(true);
        }
    }

    /**
     * 下单变更库存销量  减去库存
     * @param unknown $goods_buy_quantity
     */
    public function createOrderUpdateStorage($goods_buy_quantity)
    {
        $model_goods = new GoodsSku();
        foreach ($goods_buy_quantity as $goods_sku => $quantity) {
            $data = array();
            $data['goods_storage'] = array('exp','goods_storage-'.$quantity);
            $result = $model_goods->editGoodsBySKU($data, $goods_sku);
            if (!$result)  break;
        }
        if (!$result) {
            return callback(false,'变更商品库存与销量失败');
        } else {
            return callback(true);
        }
    }
}