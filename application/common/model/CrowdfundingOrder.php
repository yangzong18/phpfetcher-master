<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/16  15:25
 */
namespace app\common\model;

use think\Db;
use think\Model;

class CrowdfundingOrder extends Model
{

    //状态常量
    const ORDER_STATE_CANCEL = 0;
    const ORDER_STATE_NEW = 10;
    const ORDER_STATE_PAY = 20;
    const ORDER_STATE_SEND = 30;
    const ORDER_STATE_SUCCESS = 40;
    const ORDER_STATE_REFUND = 50;

    //订单状态描述
    public static  $order_state_arr=array(
        self::ORDER_STATE_CANCEL =>'已取消',
        self::ORDER_STATE_NEW =>'待付款',
        self::ORDER_STATE_PAY =>'待发货',
        self::ORDER_STATE_SEND =>'已发货',
        self::ORDER_STATE_SUCCESS =>'交易成功',
        //self::ORDER_STATE_REFUND =>'退款',
        ''=>'所有订单'
    );

    /**
     * 取得订单状态文字输出形式
     *
     * @param array $order_info 订单数组
     * @return string $order_state 描述输出
     */
    function crowdfundingorderState($order_info){
        return self::$order_state_arr[$order_info['order_state']];
    }


    /**
     * 插入订单支付表信息  众筹商品也使用普通商品的支付单
     * @param array $data
     * @return int 返回 insert_id
     */
    public function addCrowdfundingOrderPay($data) {
        return Db::name('order_pay')->insertGetId($data);
    }


    /**
     * 插入订单表信息
     * @param array $data
     * @return int 返回 insert_id
     */
    public function addCrowdfundingOrder($data) {
        return Db::name('crowdfunding_order')->insertGetId($data);
    }


    /**
     * 插入订单扩展表信息
     * @param array $data
     * @return int 返回 insert_id
     */
    public function addCrowdfundingOrderCommon($data) {
        return Db::name('crowdfunding_order_common')->insertGetId($data);
    }

    /**
     * 插入订单扩展表信息
     * @param array $data
     * @return int 返回 insert_id
     */
    public function addCrowdfundingOrderGoods($data) {
        return Db::name('crowdfunding_order_goods')->insertGetId($data);
    }

    /**
     * 查询订单信息  众筹商品和普通商品一个使用一个支付单
     * @param $condition array
     * @return array
     */
    public function getOrderPayInfo($condition = array()) {
        return Db::name('order_pay')->where($condition)->find();
    }

    /**
     * 取得订单扩展表列表
     * @param array $condition
     * @param string $fields
     * @param string $limit
     * @return array
     */
    public function getOrderCommonList($condition = array(), $fields = '*', $order = '', $limit = null) {
        return Db::name('crowdfunding_order_common')->field($fields)->where($condition)->order($order)->limit($limit)->select();
    }

	/**
	 * 返回order_common表信息
	 *
	 * 单条
	 */
	public function getOrderCommonInfo($condition = array(), $field = '*') {
		return Db::name('crowdfunding_order_common')->field($field)->where($condition)->find();
	}


    /**
     * 取得订单商品表列表
     * @param array $condition
     * @param string $fields
     * @param string $limit
     * @param string $order
     * @return array
     */
    public function getOrderGoodsList($condition = array(), $fields = '*', $limit = null, $order = 'order_goods_id desc') {
        return Db::name('crowdfunding_order_goods')->field($fields)->where($condition)->limit($limit)->order($order)->select();
    }

    /**
     * 取得订单列表(未被删除)
     * @param unknown $condition
     * @param string $field
     * @param string $order
     * @param string $limit
     * @param array $extend 追加返回那些表的信息,如array('order_common','order_goods','store')
     * @return Ambigous <multitype:boolean Ambigous <string, mixed> , unknown>
     */
    public function getNormalOrderList($condition, $field = '*', $order = 'order_id desc', $limit = '', $extend = array()){
        $condition['delete_state'] = 0;
        return $this->getCrowdfundingOrderList($condition, $field, $order, $limit, $extend);
    }


    /**
     * 更改订单信息
     *
     * @param unknown_type $data
     * @param unknown_type $condition
     * @param $limit string
     * @return array
     */
    public function editOrder($data,$condition,$limit = '') {
        return $this->where($condition)->limit($limit)->update($data);
    }


    /**
     * 根据订单ID取得一条订单信息
     * @param $where array
     * @return array
     */
    public function getCrowdfundingOrder($where){
        return $this->where($where)->find();
    }


    /**
     * 更改订单支付信息
     *
     * @param unknown_type $data
     * @param unknown_type $condition
     * @return array
     */
    public function editOrderPay($data,$condition) {
        return DB::name('order_pay')->where($condition)->update($data);
    }


    /**
     * 添加订单日志
     * @param $data array
     * @return int
     */
    public function addOrderLog($data) {
        $data['log_role'] = str_replace(array('buyer','seller','system','admin'),array('买家','商家','系统','管理员'), $data['log_role']);
        $data['log_time'] = time();
        return Db::name('order_log')->insert($data);
    }



    /**
     * 取得订单列表(所有)
     * @param unknown $condition
     * @param string $field
     * @param string $order
     * @param string $limit
     * @param array  $extend 追加返回那些表的信息,如array('order_common','order_goods','store')
     * @return  bool Ambigous <multitype:boolean Ambigous <string, mixed> , unknown>
     */
    public function getCrowdfundingOrderList($condition,  $field = '*', $order = 'order_id desc',$limit='', $extend = array(), $master = false){
        $list = DB::name('crowdfunding_order')->field($field)->where($condition)->limit($limit)->order($order)->master($master)->select();
        if (empty($list)) return array();
        $order_list = array();
        foreach ($list as $order)
        {
            if (isset($order['order_state']))  $order['state_desc'] = self::$order_state_arr[$order['order_state']];
            if (isset($order['payment_code'])) {
                $order['payment_name'] = orderPaymentName($order['payment_code']);
            }
            if (!empty($extend)) $order_list[$order['order_id']] = $order;
        }
        if (empty($order_list)) $order_list = $list;
        //追加返回订单扩展表信息
        if (in_array('order_common',$extend)) {
            $order_common_list = $this->getOrderCommonList(array('order_id'=>array('in',array_keys($order_list))));
            foreach ($order_common_list as $value) {
                $order_list[$value['order_id']]['extend_order_common'] = $value;
                $order_list[$value['order_id']]['extend_order_common']['reciver_info'] = @unserialize($value['reciver_info']);
            }
        }
        //追加返回店铺信息
        if (in_array('store',$extend)) {
            $store_id_array = array();
            foreach ($order_list as $value) {
                if (!in_array($value['store_id'],$store_id_array)) $store_id_array[] = $value['store_id'];
            }
            $store_list = Model('store')->getStoreList(array('store_id'=>array('in',$store_id_array)));
            $store_new_list = array();
            foreach ($store_list as $store) {
                $store_new_list[$store['store_id']] = $store;
            }
            foreach ($order_list as $order_id => $order) {
                $order_list[$order_id]['extend_store'] = $store_new_list[$order['store_id']];
            }
        }

        //追加返回买家信息
        if (in_array('member',$extend)) {
            foreach ($order_list as $order_id => $order) {
                $order_list[$order_id]['extend_member'] = Model('member')->getMemberInfoByID($order['buyer_id']);
            }
        }

        //追加返回商品信息
        if (in_array('order_goods',$extend)) {
            //取商品列表
            $order_goods_list = $this->getOrderGoodsList(array('order_id'=>array('in',array_keys($order_list))));
            if (!empty($order_goods_list)) {
                foreach ($order_goods_list as $value) {
                    $value['sku_name'] = json_decode($value['sku_name'],true);
                    $order_list[$value['order_id']]['extend_order_goods'][] = $value;
                }
            } else {
                foreach ($order_goods_list as $value) {
                    $order_list[$value['order_id']]['extend_order_goods'] = array();
                }
            }
        }
        return $order_list;
    }

	/**
	 * 取得订单列表(单条)
	 * @param unknown $condition
	 * @param string $field
	 * @param string $order
	 * @param string $limit
	 * @param array  $extend 追加返回那些表的信息,如array('order_common','order_goods','store')
	 * @return  bool Ambigous <multitype:boolean Ambigous <string, mixed> , unknown>
	 */
	public function getCrowdfundingOrderInfo($condition,  $field = '*', $order = 'order_id desc',$limit='', $extend = array(), $master = false){
		$order = DB::name('crowdfunding_order')->field($field)->where($condition)->limit($limit)->order($order)->master($master)->find();
		if (empty($order)) return array();
		if (isset($order['order_state']))  $order['state_desc'] = self::$order_state_arr[$order['order_state']];
		if (isset($order['payment_code'])) {
			$order['payment_name'] = orderPaymentName($order['payment_code']);
		}
		//追加返回订单扩展表信息
		if (in_array('order_common',$extend)) {
			$order['extend_order_common'] = $this->getOrderCommonInfo(array('order_id'=>$order['order_id']));
			$order['extend_order_common']['reciver_info'] = unserialize($order['extend_order_common']['receiver_info']);
			$order['extend_order_common']['invoice_info'] = unserialize($order['extend_order_common']['invoice_info']);
		}
		//追加返回买家信息
		if (in_array('member',$extend)) {
			$order['extend_member'] = Model('member')->getMemberInfoByID($order['buyer_id']);
		}
		return $order;
	}


    /**
     * 返回是否允许某些操作
     * @param unknown $operate
     * @param unknown $order_info
     * @return s
     */
    public function getOrderOperateState($operate,$order_info){
        if (!is_array($order_info) || empty($order_info)) return false;
        switch ($operate) {
            //买家取消订单
            case 'buyer_cancel':
                $state = ($order_info['order_state'] == self::ORDER_STATE_NEW) || $order_info['order_state'] == self::ORDER_STATE_PAY;
                break;
            //申请退款
            case 'refund_cancel':
                $state = ($order_info['order_state'] != self::ORDER_STATE_CANCEL && $order_info['order_state'] != self::ORDER_STATE_NEW) && !intval($order_info['lock_state']);
                break;
            //商家取消订单
            case 'store_cancel':
                $state = ($order_info['order_state'] == self::ORDER_STATE_NEW );
                break;
            //买家投诉
            case 'complain':
                $state = in_array($order_info['order_state'],array( Order::ORDER_STATE_PAY , Order::ORDER_STATE_SEND )) ||
                    intval($order_info['finnshed_time']) > (time() - C('complain_time_limit'));
                break;
            //支付
            case 'payment':
                $state = $order_info['order_state'] == Order::ORDER_STATE_NEW;
                break;
            //调整运费
            case 'modify_price':
                $state = ($order_info['order_state'] == Order::ORDER_STATE_NEW);
                break;
            //发货
            case 'send':
                $state = $order_info['order_state'] == Order::ORDER_STATE_PAY;
                break;
            //收货
            case 'receive':
                $state = $order_info['order_state'] == Order::ORDER_STATE_SEND;
                break;
            //评价
            case 'evaluation':
                $state = !$order_info['evaluation_state'] && $order_info['order_state'] == Order::ORDER_STATE_SUCCESS;
                break;
            //锁定 款申请锁定订单
            case 'lock':
                $state = intval($order_info['lock_state']) ? true : false;
                break;
            //快递跟踪
            case 'deliver':
                $state = !empty($order_info['shipping_code']) && in_array($order_info['order_state'],array(Order::ORDER_STATE_SEND , Order::ORDER_STATE_SUCCESS));
                break;
            //放入回收站
            case 'delete':
                $state = in_array($order_info['order_state'], array(Order::ORDER_STATE_CANCEL,Order::ORDER_STATE_SUCCESS)) && $order_info['delete_state'] == 0;
                break;

            //永久删除、从回收站还原
            case 'drop':
            case 'restore':
                $state = in_array($order_info['order_state'], array(Order::ORDER_STATE_CANCEL, Order::ORDER_STATE_SUCCESS)) && $order_info['delete_state'] == 1;
                break;

            //分享
            case 'share':
                $state = true;
                break;
            //退款
            case 'refund':
                $state = in_array( $order_info['order_state'] ,array(Order::ORDER_STATE_PAY,Order::ORDER_STATE_SEND,ORDER::ORDER_STATE_SUCCESS)) && intval($order_info['lock_state']);
                break;
            //申请退款后
            case 'refundAfter':
                $state = in_array( $order_info['order_state'] ,array(Order::ORDER_STATE_PAY,Order::ORDER_STATE_SEND,ORDER::ORDER_STATE_SUCCESS)) && intval($order_info['lock_state']) && intval($order_info['refund_state']) == 1;
                break;
        }
        return $state;

    }


    /**
     * @param $where
     * @param string $fields
     * @return int 统计订单数量
     */

    public function getCount($where,$fields='*'){
        return $this->where($where)->count($fields);
    }



}
