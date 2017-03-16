<?php
/**
 * 实物订单购买逻辑
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author:
 */
namespace app\common\logic;
use app\common\model\Goods;
use app\common\model\Order;
use think\Db;
use think\Exception;
use think\Cache;
class RealOrderLogic
{

    /*
     * 获取实例化对象 请勿删除和修改
     * */
    public  static function create(){
        return new self;
    }

        /**
     * 取得实物订单所需支付金额等信息
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

        $order_pay_info['subject'] = '实物订单_'.$order_pay_info['pay_sn'];
        $order_pay_info['order_type'] = 'real_order';//实物订单标识

        $condition = array();
        $condition['pay_sn'] = $pay_sn;
        $condition['order_state'] = array('in',array(Order::ORDER_STATE_NEW,Order::ORDER_STATE_PAY));
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
     * $out_trade_no 支付单号
     * $payment_code 支付编码
     * $order_list 订单信息
     * $trade_no 支付接口的交易号
     */
    public function updateOrder($out_trade_no, $payment_code, $order_list, $trade_no) {
        $post['payment_code'] = $payment_code;
        $post['trade_no'] = $trade_no;
        return $this->changeOrderReceivePay($order_list, 'system', '系统', $post);
    }

    /**
     * 收到货款
     * @param array $order_info
     * @param string $role 操作角色 buyer、seller、admin、system 分别代表买家、商家、管理员、系统
     * @param string $user 操作人
     * @return array
     */
    private function changeOrderReceivePay($order_list, $role, $user = '', $post = array())
    {
        try {
            // 启动事务
            Db::startTrans();

            $model_order = new Order();
            foreach($order_list as $key=> $order_info) {
                if ($order_info['order_state'] != ORDER::ORDER_STATE_NEW) continue;
            }

            $payment_time=(array_key_exists('payment_time',$post) ? strtotime($post['payment_time']) : time());
            $payment_time = ($payment_time<=0) ? time() : $payment_time;
            //更新订单状态
            $update_order = array();
            $update_order['order_state'] = Order::ORDER_STATE_PAY;
            $update_order['payment_time'] = $payment_time;
            $update_order['payment_code'] = $post['payment_code'];
            $dt = array('pay_sn'=>$order_info['pay_sn'],'order_state'=>Order::ORDER_STATE_NEW);
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
            $data['log_msg'] = '收到了货款 ( 支付平台交易号 : '.$post['trade_no'].' )';
            $data['log_orderstate'] = Order::ORDER_STATE_PAY;
            $model_order->addOrderLog($data);
        }

        return callback(true);
    }


    /*
     * 还原订单库存
     * */
    public function backOrderKC($order_id)
    {
        $model_order = new Order();
        $goods_model = new Goods();

        //库存销量变更
        $goods_list = $model_order->getOrderGoodsList(array('order_id'=>$order_id));
        $data = array();
        $goods_id_arr = array();//总库存
        $sale_id_arr = array();//销量
        foreach ($goods_list as $goods) {
            $data[$goods['goods_sku']] = $goods['goods_num'];
            if(!array_key_exists($goods['goods_id'],$goods_id_arr)){
                $goods_id_arr[$goods['goods_id']]=0;
            }
            $goods_sku_cache_arr = $goods_model->getSkuCache($goods['goods_id']);
            if(!is_array($goods_sku_cache_arr)) $goods_sku_cache_arr=array();
            if(in_array($goods['goods_sku'],$goods_sku_cache_arr)) $goods_id_arr[$goods['goods_id']]+=$goods['goods_num'];

            if(!array_key_exists($goods['goods_id'],$sale_id_arr)){
                $sale_id_arr[$goods['goods_id']]=0;
            }
            $sale_id_arr[$goods['goods_id']]+=$goods['goods_num'];

        }

        //为保证数据准确，不使用队列
        $queue = new Queue();
        $queue->cancelOrderUpdateStorage($data);

        //还原总库存
        $goods_model->changeAllKCNumber($goods_id_arr,0,$sale_id_arr);

        return true;
    }


    /**
     * 取消订单
     * @param array $order_info
     * @param string $role 操作角色 buyer、seller、admin、system 分别代表买家、商家、管理员、系统
     * @param string $user 操作人
     * @param string $msg 操作备注
     * @param boolean $if_queue 是否使用队列
     * @return array
     */
    public function changeOrderStateCancel($order_info, $role, $user = '', $msg = '')
    {
        try {
            $order_id = $order_info['order_id'];

            // 启动事务
            Db::startTrans();
            $model_order = new Order();

            $this->backOrderKC($order_id);

            //更新订单信息
            $update_order = array('order_state' => Order::ORDER_STATE_CANCEL);
            $update = $model_order->editOrder($update_order,array('order_id'=>$order_id));
            if (!$update)  throw new Exception('保存失败');

            // 提交事务
            Db::commit();

            //添加订单日志
            $data = array();
            $data['order_id'] = $order_id;
            $data['log_role'] = $role;
            $data['log_msg'] = '取消了订单';
            $data['log_user'] = $user;
            if ($msg)  $data['log_msg'] .= ' ( '.$msg.' )';
            $data['log_orderstate'] = Order::ORDER_STATE_CANCEL;
            $model_order->addOrderLog($data);

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
    public function changeOrderStateReceive($order_info, $role, $user = '', $msg = '')
    {
        try {

            // 启动事务
            Db::startTrans();

            $order_id = $order_info['order_id'];
            $model_order = new Order();

            //更新订单状态
            $update_order = array();
            $update_order['finnshed_time'] = time();
            $update_order['receive_time'] = time();
            $update_order['order_state'] = Order::ORDER_STATE_SUCCESS;
            $update = $model_order->editOrder($update_order,array('order_id'=>$order_id));
            if (!$update)  throw new Exception('保存失败');

            //添加订单日志
            $data = array();
            $data['order_id'] = $order_id;
            $data['log_role'] = 'buyer';
            $data['log_msg'] = '签收了货物';
            $data['log_user'] = $user;
            if ($msg)  $data['log_msg'] .= ' ( '.$msg.' )';
            $data['log_orderstate'] = Order::ORDER_STATE_SUCCESS;
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
