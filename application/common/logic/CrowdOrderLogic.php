<?php
/**
 * 众筹订单购买逻辑
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author:
 */
namespace app\common\logic;
use app\common\model\CrowdfundingOrder;
use app\common\model\CrowdfundingGoods;
use app\common\model\Order;
use think\Db;
use think\Exception;
class CrowdOrderLogic
{

    /*
     * 获取实例化对象 请勿删除和修改
     * */
    public  static function create(){
        return new self;
    }

    /**
     * 取得众筹订单所需支付金额等信息
     * @param int $pay_sn
     * @param int $member_id
     * @return array
     */
    public function getOrderInfo($pay_sn, $member_id = null)
    {
        //验证订单信息
        $model_order = new CrowdfundingOrder();
        $condition = array();
        $condition['pay_sn'] = $pay_sn;
        if (!empty($member_id))  $condition['member_id'] = $member_id;
        $order_pay_info = $model_order->getOrderPayInfo($condition);
        if(empty($order_pay_info)) return callback(false,'该支付单不存在');

        $order_pay_info['subject'] = '众筹订单_'.$order_pay_info['pay_sn'];
        $order_pay_info['order_type'] = 'crowd_order';//众筹订单标识

        $condition = array();
        $condition['pay_sn'] = $pay_sn;
        $condition['order_state'] = array('in',array(CrowdfundingOrder::ORDER_STATE_NEW,CrowdfundingOrder::ORDER_STATE_PAY));
        $order_list = $model_order->getNormalOrderList($condition);

        //计算本次需要在线支付的订单总金额
        $pay_amount = 0;
        if (!empty($order_list)) {
            foreach ($order_list as $order_info) {
                $pay_amount += ncPriceFormat(floatval($order_info['order_amount']));
            }
        }

        $order_pay_info['api_pay_amount'] = $pay_amount;
        $order_pay_info['order_list'] = $order_list;

        return callback(true,'',$order_pay_info);
        
    }

    /**
     * 支付成功后修改实物订单状态
     */
    public function updateOrder($out_trade_no, $payment_code, $order_list, $trade_no) {
        $post['payment_code'] = $payment_code;
        $post['trade_no'] = $trade_no;
        return $this->changeOrderReceivePay($order_list, 'system', '系统', $post);
    }

    /**
     * 收到货款
     * @param array $order_list
     * @param string $role 操作角色 buyer、seller、admin、system 分别代表买家、商家、管理员、系统
     * @param string $user 操作人
     * @return array
     */
    private function changeOrderReceivePay($order_list, $role, $user = '', $post = array()){
        try {
            // 启动事务
            Db::startTrans();

            $model_order = new CrowdfundingOrder();
            foreach($order_list as $key=> $order_info) {
                $order_id = $order_info['order_id'];
                if ($order_info['order_state'] != CrowdfundingOrder::ORDER_STATE_NEW) continue;
            }

            $payment_time=(array_key_exists('payment_time',$post) ? strtotime($post['payment_time']) : time());
            $payment_time = ($payment_time<=0) ? time() : $payment_time;
            //更新订单状态
            $update_order = array();
            $update_order['order_state'] = CrowdfundingOrder::ORDER_STATE_PAY;
            $update_order['payment_time'] = $payment_time;
            $update_order['payment_code'] = $post['payment_code'];
            $dt = array('pay_sn'=>$order_info['pay_sn'],'order_state'=>CrowdfundingOrder::ORDER_STATE_NEW);
            $update = $model_order->editOrder($update_order,$dt);
            if (!$update)  throw new Exception('操作失败');

            //更新支付单状态
            $data = array();
            $data['api_pay_state'] = 1;
            if(array_key_exists('trade_no',$post) && !empty($post['trade_no'])){
                $data['trade_no'] = $post['trade_no'];
            }
            $update = $model_order->editOrderPay($data,array('pay_sn'=>$order_info['pay_sn']));
            if (!$update)   throw new Exception('更新支付单状态失败');

            // 提交事务
            Db::commit();
        } catch (Exception $e) {
            // 回滚事务
            Db::rollback();
            return callback(false,$e->getMessage());
        }

        foreach($order_list as $order_info) {
            $order_id = $order_info['order_id'];
            //添加订单日志
            $data = array();
            $data['order_id'] = $order_id;
            $data['log_role'] = $role;
            $data['log_user'] = $user;
            $data['order_type'] = 2;
            $data['log_msg'] = '收到了货款 ( 支付平台交易号 : '.$post['trade_no'].' )';
            $data['log_orderstate'] = CrowdfundingOrder::ORDER_STATE_PAY;
            $model_order->addOrderLog($data);
        }

        return callback(true);
    }

    /**
     * 通过订单ID信息获取
     * @param int $orderId 订单ID
     * @param array $order 订单信息
     */
    public function inquire( $orderId ) {
        $where = array( 'order_id' => $orderId );
        //主订单查询
        $orderModel = new CrowdfundingOrder();
        $order      = $orderModel->where( $where )->find();
        if ( !$order ) {
            return $order;
        }
        $order = $order->toArray();
        $order['payment_time'] = isset( $order['payment_time'] ) ? $order['payment_time'] : $order['add_time'] ;
        $order['payment_name'] = (new Order())->orderPaymentName($order['payment_code']);
        $order['order_confirm_day'] = $order['delay_time'] + ORDER_AUTO_RECEIVE_DAY * 24 * 3600;
        $order['order_cancel_day'] = $order['add_time'] + ORDER_AUTO_CANCEL_DAY * 24 * 3600;
        //商品信息查询
        $goodsModel = new CrowdfundingGoods();
        $goods = Db::name('crowdfunding_order_goods')->where( $where )->find();
        $order['goods'] = $goods;
        //获取订单状态
        $order['order_state_name'] = CrowdfundingOrder::$order_state_arr[ $order['order_state'] ];
        //查询order_common信息
        $orderCommon = Db::name('crowdfunding_order_common')->where( $where )->find();
        $orderCommon['receiver_info'] = unserialize($orderCommon['receiver_info']);
        $order['extend_order_common'] = $orderCommon;
        return $order;
    }


    /**
     * 取消众筹订单
     */
    public function orderCancel($order_info, $cancel_reason, $role = '', $user = '', $msg = '', $if_update_account = true, $if_quque = true) {
        $orderModel = new CrowdfundingOrder();
        try {
            // 启动事务
            Db::startTrans();
            $order_id = $order_info['order_id'];

            //库存销量变更
            $goods_list = $orderModel->getOrderGoodsList(array('order_id'=>$order_id));
            if(!$goods_list){
                throw new Exception('未找到订单商品');
            }

            $crowdfunding_goods = new CrowdfundingGoods();
            foreach($goods_list as $k=>$v){
                $result = $crowdfunding_goods->cancelOrderUpdateStorage($v['goods_id'],$v['goods_num']);
                if (!$result['state']) {
                    throw new Exception('还原库存失败');
                }
            }


            //更新订单信息
            $update_order = array();
            $update_order['order_state'] = CrowdfundingOrder::ORDER_STATE_CANCEL;
            $update_order['cancel_reason'] = $cancel_reason;
            $update = $orderModel->editOrder($update_order,array('order_id'=>$order_id));
            if(!$update){
                throw new Exception('订单状态更改失败');
            }


            //添加订单日志
            $data = array();
            $data['order_id'] = $order_id;
            $data['log_role'] = $role;
            $data['log_msg'] = '取消了订单';
            $data['log_user'] = $user;
            if ($msg)  $data['log_msg'] .= ' ( '.$msg.' )';
            $data['log_orderstate'] = CrowdfundingOrder::ORDER_STATE_CANCEL;
            $orderModel->addOrderLog($data);

            // 提交事务
            Db::commit();

            return callback(true,'操作成功');

        } catch (Exception $e) {
            // 回滚事务
            Db::rollback();
            return callback(false,'操作失败：'.$e->getMessage());
        }
    }



    /**
     * 收货
     * @param array $order_info
     * @param string $role 操作角色 buyer、seller、admin、system 分别代表买家、商家、管理员、系统
     * @param string $user 操作人
     * @param string $msg 操作备注
     * @return array
     */
    public function orderReceive($order_info, $role, $user = '', $msg = '')
    {
        try {

            // 启动事务
            Db::startTrans();

            $order_id = $order_info['order_id'];
            $model_order = new CrowdfundingOrder();

            //更新订单状态
            $update_order = array();
            $update_order['finnshed_time'] = time();
            $update_order['receive_time'] = time();
            $update_order['order_state'] = CrowdfundingOrder::ORDER_STATE_SUCCESS;
            $update = $model_order->editOrder($update_order,array('order_id'=>$order_id));
            if (!$update)  throw new Exception('保存失败');

            //添加订单日志
            $data = array();
            $data['order_id'] = $order_id;
            $data['log_role'] = 'buyer';
            $data['log_msg'] = '签收了货物';
            $data['log_user'] = $user;
            if ($msg)  $data['log_msg'] .= ' ( '.$msg.' )';
            $data['log_orderstate'] = CrowdfundingOrder::ORDER_STATE_SUCCESS;
            $model_order->addOrderLog($data);

            // 提交事务
            Db::commit();

            return callback(true,'操作成功');
        } catch (Exception $e) {
            // 回滚事务
            Db::rollback();
            return callback(false,'操作失败');
        }
    }

}
