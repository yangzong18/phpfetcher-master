<?php
/**
 * 整装订单购买逻辑
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author:
 */
namespace app\common\logic;
use app\common\model\LogsDecorationOrder as Order;
use think\Db;
use think\Exception;
class LogsOrderLogic
{


    /*
     * 获取实例化对象 请勿删除和修改
     * */
    public  static function create(){
        return new self;
    }

    /**
     * 取得整装订单所需支付金额等信息
     * @param int $pay_sn
     * @param int $member_id
     * @return array
     */
    public function getOrderInfo($pay_sn, $member_id = null)
    {

        //验证订单信息
        $model_order = new Order();
        $condition = array();
        $condition['pay_sn'] = $pay_sn;
        if (!empty($member_id))  $condition['member_id'] = $member_id;
        $order_pay_info = $model_order->getOrderPayInfo($condition);
        if(empty($order_pay_info)) return callback(false,'该支付单不存在');

        $order_pay_info['subject'] = '整装订单_'.$order_pay_info['pay_sn'];
        $order_pay_info['order_type'] = 'logs_order';//整装订单标识

        $condition = array();
        $condition['pay_sn'] = $pay_sn;
        $condition['order_status'] = array('in',array(Order::LOGS_ORDER_NEW,Order::LOGS_ORDER_PAY));
        $orderInfo = $model_order->getOrderInfo($condition);

        //计算本次需要在线支付的订单总金额
        $pay_amount = $orderInfo['deposit'];

        $order_pay_info['api_pay_amount'] = $pay_amount;
        $order_pay_info['order_list'] = $orderInfo;

        return callback(true,'',$order_pay_info);

    }

    /**
     * 支付成功后修改实物订单状态
     */
    public function updateOrder($out_trade_no, $payment_code, $order_info, $trade_no) {
        $post['payment_code'] = $payment_code;
        $post['trade_no'] = $trade_no;
        return $this->changeOrderReceivePay($order_info, 'system', '系统', $post);
    }

    /**
     * 收到货款
     * @param array $order_info
     * @param string $role 操作角色 buyer、seller、admin、system 分别代表买家、商家、管理员、系统
     * @param string $user 操作人
     * @return array
     */
    private function changeOrderReceivePay($order_info, $role, $user = '', $post = array()){
        try {
            // 启动事务
            Db::startTrans();

            $model_order = new Order();

            $order_id = $order_info['id'];
            $payment_time=(array_key_exists('payment_time',$post) ? strtotime($post['payment_time']) : time());
            $payment_time = ($payment_time<=0) ? time() : $payment_time;
            //更新订单状态
            $update_order = array();
            $update_order['order_status'] = Order::LOGS_ORDER_PAY;
            $update_order['speed_status'] = Order::LOGS_SPEED_DESIGN;
            $update_order['payment_time'] = $payment_time;
            $update_order['payment_code'] = $post['payment_code'];

            $update = $model_order->save( $update_order , array('id'=>$order_id) );

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

            //添加订单日志
        $data = array();
        $data['order_id'] = $order_id;
        $data['log_role'] = $role;
        $data['log_user'] = $user;
        $data['log_msg'] = '收到了货款 ( 支付平台交易号 : '.$post['trade_no'].' )';
        $data['log_orderstate'] = Order::LOGS_ORDER_PAY;
        $data['order_type'] = 1;
        $model_order->addOrderLog($data);


        return callback(true);
    }
}