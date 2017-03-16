<?php
/**
 * 订单模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-12-02 10:08
 */

namespace app\common\model;
use think\Model;
use think\Db;
use think\Config;
class Order extends Model
{
    //状态常量
    const ORDER_STATE_CANCEL = 0;
    const ORDER_STATE_NEW = 10;
    const ORDER_STATE_PAY = 20;
    const ORDER_STATE_SEND = 30;
    const ORDER_STATE_SUCCESS = 40;

    //订单状态描述
    public static  $order_state_arr=array(
        self::ORDER_STATE_CANCEL =>'已取消',
        self::ORDER_STATE_NEW =>'待付款',
        self::ORDER_STATE_PAY =>'待发货',
        self::ORDER_STATE_SEND =>'已发货',
        self::ORDER_STATE_SUCCESS =>'交易成功'
    );
    /**
     * 取得订单状态文字输出形式
     *
     * @param array $order_info 订单数组
     * @return string $order_state 描述输出
     */
    function orderState($order_info){
        return self::$order_state_arr[$order_info['order_state']];
    }


    /**
     * 插入订单支付表信息
     * @param array $data
     * @return int 返回 insert_id
     */
    public function addOrderPay($data) {
        return Db::name('order_pay')->insertGetId($data);
    }

    /*
     * 查询订单支付信息
     * */
    public function getOrderPayInfo($condition = array()) {
        return Db::name('order_pay')->where($condition)->find();
    }

    /**
     * 插入订单表信息
     * @param array $data
     * @return int 返回 insert_id
     */
    public function addOrder($data) {
        return Db::name('order')->insertGetId($data);
    }

    /**
     * 取得单条订单操作记录
     * @param unknown $condition
     * @param string $order
     */
    public function getOrderLogInfo($condition = array(), $order = '') {
        return Db::name('order_log')->where($condition)->order($order)->find();
    }

    /**
     * 插入订单扩展表信息
     * @param array $data
     * @return int 返回 insert_id
     */
    public function addOrderCommon($data) {
        return Db::name('order_common')->insertGetId($data);
    }

    /**
     * 插入订单扩展表信息
     * @param array $data
     * @return int 返回 insert_id
     */
    public function addOrderGoods($data) {
        return Db::name('order_goods')->insertAll($data);
    }

    /**
     * 取得订单列表(未被删除)
     * @param unknown $condition
     * @param string $pagesize
     * @param string $field
     * @param string $order
     * @param string $limit
     * @param unknown $extend 追加返回那些表的信息,如array('order_common','order_goods','store')
     * @return Ambigous <multitype:boolean Ambigous <string, mixed> , unknown>
     */
    public function getNormalOrderList($condition, $field = '*', $order = 'order_id desc', $limit = '', $extend = array()){
        $condition['delete_state'] = 0;
        return $this->getOrderList($condition, $field, $order, $limit, $extend);
    }


    /**
     * 取得订单列表(所有)
     * @param unknown $condition
     * @param string $pagesize
     * @param string $field
     * @param string $order
     * @param string $limit
     * @param unknown $extend 追加返回那些表的信息,如array('order_common','order_goods','store')
     * @return Ambigous <multitype:boolean Ambigous <string, mixed> , unknown>
     */
    public function getOrderList($condition,  $field = '*', $order = 'order_id desc',$limit='', $extend = array(), $master = false){
        $list = DB::name('order')->field($field)->where($condition)->limit($limit)->order($order)->master($master)->select();
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
            $store_model = new Store();
            $store_list = $store_model->getStoreList(array('store_id'=>array('in',$store_id_array)));
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
            $member_model = new Member();
            foreach ($order_list as $order_id => $order) {
                $order_list[$order_id]['extend_member'] = $member_model->getMemberInfoByID($order['member_id']);
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
     * 取单条订单信息
     *
     * @param array $condition
     * @param array $extend 追加返回那些表的信息,如array('order_common','order_goods','store','refund_return')
     * @return $order_info
     */
    public function getOrderInfo($condition = array(), $extend = array(), $fields = '*') {
        $orderInfo = $this->where($condition)->field($fields)->where($condition)->find();
        if (!is_object($orderInfo))  return array();
        $order_info = $orderInfo->toArray();
        if (isset($order_info['order_state'])) {
            $order_info['state_desc'] = $this->orderState($order_info);
        }
        if (isset($order_info['payment_code'])) {
            $order_info['payment_name'] = $this->orderPaymentName($order_info['payment_code']);
        }

        //追加返回订单扩展表信息
        if (in_array('order_common',$extend)) {
            $order_info['extend_order_common'] = $this->getOrderCommonInfo(array('order_id'=>$order_info['order_id']));
            $order_info['extend_order_common']['reciver_info'] = unserialize($order_info['extend_order_common']['reciver_info']);
            $order_info['extend_order_common']['invoice_info'] = unserialize($order_info['extend_order_common']['invoice_info']);
        }

        //追加返回店铺信息
        if (in_array('store',$extend)) {
            $store_model = new Store();
            $order_info['extend_store'] = $store_model->getStoreInfo(array('store_id'=>$order_info['store_id']));
        }

        //返回买家信息
        if (in_array('member',$extend)) {
            $member_model = new Member();
            $order_info['extend_member'] = $member_model->getMemberInfoByID($order_info['member_id']);
        }

        //追加返回商品信息
        if (in_array('order_goods',$extend)) {
            //取商品列表
            $order_goods_list = $this->getOrderGoodsList(array('order_id'=>$order_info['order_id']));
            foreach($order_goods_list as $key => $goodsinfo) {

                if(!empty($goodsinfo['sku_name'])){
                    $goodsinfo['sku_name'] = json_decode($goodsinfo['sku_name'],true);
                }else{
                    $goodsinfo['sku_name'] = '';
                }

                $goodsinfo['bz_remark']='';
                $goodsinfo['phone_remark']='';
                if(!empty($goodsinfo['remark']) && $goodsinfo['remark']!='-{}-'){
                    $goods_remark_arr = explode('{}',$goodsinfo['remark']);
                    if(count($goods_remark_arr)==2){
                        $goodsinfo['bz_remark'] = ($goods_remark_arr[0]=='-') ? '' : $goods_remark_arr[0];
                        $goodsinfo['phone_remark'] = ($goods_remark_arr[1]=='-') ? '' : $goods_remark_arr[1];
                    }
                }
                $order_goods_list[$key] = $goodsinfo;
            }
            $order_info['extend_order_goods'] = $order_goods_list;
        }

        //退款信息
        if(in_array('refund_return',$extend)){
            $extend_refund_arr = array();
            if($orderInfo['refund_state']>0)
            {
               $rufund_model = new RefundReturn();
                $arr = $rufund_model->getRefundReturnList(array('order_sn'=>$order_info['order_sn']));
                if(!empty($arr)) {
                    $extend_refund_arr = $arr[0];
                    $extend_refund_arr['buyer_img'] = json_decode($extend_refund_arr['buyer_img'],true);
					$extend_refund_arr['seller_img'] = json_decode($extend_refund_arr['seller_img'],true);
					if(empty($extend_refund_arr['seller_img'])) $extend_refund_arr['seller_img']=array();
                    if(empty($extend_refund_arr['buyer_img'])) $extend_refund_arr['buyer_img']=array();
                }
            }
            $order_info['extend_refund_arr'] = $extend_refund_arr;
        }

        return $order_info;
    }

    /**
     * 返回order_common表信息
     */
    public function getOrderCommonInfo($condition = array(), $field = '*') {
        return Db::name('order_common')->field($field)->where($condition)->find();
    }

    /**
     * 返回refund_return表信息
     */
    public function getRefundInfo($condition = array(), $field = '*') {
        return Db::name('refund_return')->field($field)->where($condition)->find();
    }
    /**
     * 取得订单扩展表列表
     * @param array $condition
     * @param string $fields
     * @param string $limit
     */
    public function getOrderCommonList($condition = array(), $fields = '*', $order = '', $limit = null) {
        return Db::name('order_common')->field($fields)->where($condition)->order($order)->limit($limit)->select();
    }

    /**
     * 取得订单商品表列表
     * @param array $condition
     * @param string $fields
     * @param string $limit
     * @param string $order
     */
    public function getOrderGoodsList($condition = array(), $fields = '*', $limit = null, $order = 'order_goods_id desc') {
        return Db::name('order_goods')->field($fields)->where($condition)->limit($limit)->order($order)->select();
    }

    /**
     * 更改订单信息
     *
     * @param unknown_type $data
     * @param unknown_type $condition
     */
    public function editOrder($data,$condition,$limit = '') {
        return $this->where($condition)->limit($limit)->update($data);
    }

    /**
     * 更改订单支付信息
     *
     * @param unknown_type $data
     * @param unknown_type $condition
     */
    public function editOrderPay($data,$condition) {
        return DB::name('order_pay')->where($condition)->update($data);
    }

    /**
     * 更改订单扩展信息
     *
     * @param unknown_type $data
     * @param unknown_type $condition
     */
    public function editOrderCommon($data,$condition,$limit = '') {
        $update = Db::name('order_common')->where($condition)->limit($limit)->update($data);
        return $update;
    }


	/**
	 * 新增退款申请
	 * @param $data array
	 * @return int|string
	 */
	public function addRefund($data) {
		return Db::name('refund_return')->insert($data);
	}

	/**
	 * 编辑退款信息
	 *
	 * 2016-12-16
	 * @param $data array
	 * @param $where array
	 * @return int|string
	 */
	public function editRefund($data,$where) {
		return Db::name('refund_return')->where($where)->update($data);
	}

    /**
     * 添加订单日志
     */
    public function addOrderLog($data) {
        $data['log_role'] = str_replace(array('buyer','seller','system','admin'),array('买家','商家','系统','管理员'), $data['log_role']);
        $data['log_time'] = time();
        return Db::name('order_log')->insert($data);
    }


    /**
     * 返回是否允许某些操作
     * @param unknown $operate
     * @param unknown $order_info
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
                $state = ($order_info['order_state'] != self::ORDER_STATE_CANCEL && $order_info['order_state'] != self::ORDER_STATE_NEW) && !intval($order_info['lock_state']) && !intval($order_info['refund_state']);
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
                $state = ($order_info['order_state'] == Order::ORDER_STATE_PAY && !intval($order_info['lock_state']));
                break;
            //收货 ,2017/2/13 杨萌，退款成功的订单不显示收货按钮
            case 'receive':
                $state = ($order_info['order_state'] == Order::ORDER_STATE_SEND && $order_info['refund_state'] != 2 && $order_info['refund_state'] != 1 ) || ($order_info['order_state'] == Order::ORDER_STATE_SEND && $order_info['refund_state'] == 2 && $order_info['lock_state'] == 0) ;
                break;
            //评价
            case 'evaluation':
                $state = !$order_info['evaluation_state'] && $order_info['order_state'] == Order::ORDER_STATE_SUCCESS;
                break;
            //锁定 款申请锁定订单
            case 'lock':
                $state = intval($order_info['lock_state']) ? true : false;
                break;
			case 'edit_deliver':
				$state = !empty($order_info['shipping_code']) && $order_info['order_state'] == Order::ORDER_STATE_SEND && (($order_info['lock_state'] != 1 && $order_info['refund_state'] == 2) || $order_info['refund_state'] != 2);
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


    /**
     * 取得订单支付类型文字输出形式
     *
     * @param array $payment_code
     * @return string
     */
    function orderPaymentName($payment_code) {
        return str_replace(
            array('offline','online','alipay','tenpay','chinabank','predeposit'),
            array('货到付款','在线付款','支付宝','财付通','网银在线','站内余额支付'),
            $payment_code);
    }
}
