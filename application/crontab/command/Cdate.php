<?php
/**
 * 定时任务
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: Laijunliang at 2016-12-13
 */
namespace app\crontab\command;
use app\common\logic\RealOrderLogic;
use app\common\model\Order;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Util\Redis;
use think\Db;
use think\Config;
use think\Log;
use app\common\payment\Payment;
use app\common\model\CrowdfundingOrder;
use app\common\model\CrowdfundingGoods;

ini_set('default_socket_timeout', -1);

class Cdate extends Command{

    /**
     * 该文件中所有任务执行频率，默认1天，单位：秒
     * @var int
     */
    const EXE_TIMES = 86400;

    /*
     * 此方法不可更改
     * */
    protected function configure() {
        $this->setName('cdate')->setDescription('Here is the crontab task');
    }
    /**
     * 定时任务
     */
    public function execute(Input $input, Output $output)
    {
        //未付款订单超期自动关闭
        $this->_order_timeout_cancel();

        //已付款订单并发货后一个月内的订单到期自动完成
        $this->_order_auto_complete();

        //众筹已付款订单并发货后一个月内的订单到期自动完成
        $this->_crowd_order_auto_complete();
        
        //众筹商品验证是否成功
        $this->_crowd_goods_auto_complete();

        //将众筹项目中未申请退款成功的数据再次申请退款
        $this->refund();

        //Log::write('定时任务执行成功', 'info',true);
        exit("success at ".date('Y-m-d H:i:s',time())."\n");
    }


    /**
     * 未付款订单超期自动关闭
     */
    private function _order_timeout_cancel()
    {
        $time = time();
        $model_order = new Order();

        $condition = array();
        $condition['order_state'] = Order::ORDER_STATE_NEW;
        $condition['add_time'] = array('lt',$time - ORDER_AUTO_CANCEL_DAY * self::EXE_TIMES);
        $_break = false;
        $logic_order = new RealOrderLogic();
        //分批，每批处理100个订单，最多处理5W个订单
        for ($i = 0; $i < 500; $i++)
        {
            if ($_break)  break;
            $order_list = $model_order->getOrderList($condition, '*', 'add_time asc', 100);
            if (empty($order_list)) break;
            foreach ($order_list as $order_info)
            {
                $result = $logic_order->changeOrderStateCancel($order_info,'system','系统','超期未支付系统自动关闭订单');

                if (!$result['state']) {
                    Log::write('实物订单超期未支付关闭失败SN：'.$order_info['order_sn'].'\r\n'.$result['msg'], 'error');
                    //$_break = true;
                    //break;
                }
            }
        }
    }


    /**
     * 已付款订单并发货后一个月内的订单到期自动完成
     */
    private function _order_auto_complete()
    {
        $time = time();
        $model_order = new Order();
        $condition = array();
        $condition['order_state'] = Order::ORDER_STATE_SEND;
        $condition['lock_state'] = 0;
        $condition['shipping_time'] = array('lt',$time - ORDER_AUTO_RECEIVE_DAY * self::EXE_TIMES);
        $_break = false;
        $logic_order = new RealOrderLogic();
        //分批，每批处理100个订单，最多处理5W个订单
        for ($i = 0; $i < 500; $i++)
        {
            if ($_break)  break;
            $order_list = $model_order->getOrderList($condition, '*', 'shipping_time asc', 100);
            if (empty($order_list)) break;
            foreach ($order_list as $order_info)
            {
                $result = $logic_order->changeOrderStateReceive($order_info,'system','系统','超期未收货系统自动完成订单');
                if (!$result['state']) {
                    Log::write('实物订单超期未收货自动完成订单失败SN：'.$order_info['order_sn'].'\r\n'.$result['msg'], 'error');
                    $_break = true;
                    break;
                }
            }
        }
    }

    /**
     * 众筹已付款订单并发货后一个月内的订单到期自动完成
     */
    private function _crowd_order_auto_complete()
    {
        $time = time();
        $orderModel = new CrowdfundingOrder();
        $condition = array(
            'order_state' => CrowdfundingOrder::ORDER_STATE_SEND,
            'lock_state'  => 0,
            'shipping_time' => array('lt',$time - ORDER_AUTO_RECEIVE_DAY * self::EXE_TIMES)
        );
        //将这些订单修改为自动收货
        $orderIdList = $orderModel::where( $condition )->column( 'order_id' );
        if ( is_array( $orderIdList ) && count( $orderIdList ) > 0 ) {
            $where       = array( 'order_id' => array( 'in', $orderIdList ) );
            $param       = array( 'order_state' => CrowdfundingOrder::ORDER_STATE_SUCCESS );
            if ( $orderModel->where( $where )->update( $param ) ) {
                Log::write('众筹订单自动确认收货成功:'.json_encode( $orderIdList ), 'info');
            } else {
                Log::write('众筹订单自动确认收货失败:'.json_encode( $orderIdList ), 'error');
            }
        }
    }


    /**
     * 众筹商品验证是否成功
     */
    private function _crowd_goods_auto_complete()
    {
        $goodsModel = new CrowdfundingGoods();
        $orderModel = new CrowdfundingOrder();
        //查询回报中和筹款失败的订单
        $condition = array( 'state' => array( 'in', array( CrowdfundingGoods::GOODS_STATE_RETURN, CrowdfundingGoods::GOODS_STATE_FAIL ) )  );
        $goodsList = $goodsModel->field('id, sale_number, state')->where( $condition )->select();
        //查询订单的完成状态
        foreach ($goodsList as $goods) {
            $orderIdList = Db::name('crowdfunding_order_goods')->where( array('goods_id'=>$goods['id']) )->column( 'order_id' );
            //众筹成功的
            if ( $goods['state'] == CrowdfundingGoods::GOODS_STATE_RETURN ) {
                $where = array( 
                    'order_state' => CrowdfundingOrder::ORDER_STATE_SUCCESS,
                    'delete_state'=> 0,
                    'order_id'    => array( 'in', $orderIdList )
                );
                $orderIdList = $orderModel->where( $where )->column('order_id');
                if ( count( $orderIdList ) > 0 ) {
                    //查询有效的数量
                    $number = Db::name('crowdfunding_order_goods')->where( array('order_id'    => array( 'in', $orderIdList ) ) )->sum( 'goods_num' );

                    //如果所有人都收货成功了，则修改商品状态为众筹成功
                    if ( $number == $goods['sale_number'] ) {
                        $where = array( 'id' => $goods['id'] );
                        if ( !$goodsModel->where( $where )->update( array( 'state' => CrowdfundingGoods::GOODS_STATE_PROJECT_SUCCESS  ) ) ) {
                            Log::write('众筹商品成功状态修改失败:'.json_encode( $goods ), 'error');
                        } else {
                            Log::write('众筹商品成功状态修改成功:'.json_encode( $goods ), 'info');
                        }
                    }
                }
            } else {
                //众筹失败的
                $where = array( 
                    'order_state' => CrowdfundingOrder::ORDER_STATE_PAY,
                    'refund_state'=> 2,
                    'delete_state'=> 0,
                    'order_id'    => array( 'in', $orderIdList )
                );
                $number = $orderModel->where( $where )->count();
                //如果所有人都收货成功了，则修改商品状态为众筹成功
                if ( $number == $goods['sale_number'] ) {
                    $where = array( 'id' => $goods['id'] );
                    if ( !$goodsModel->where( $where )->update( array( 'state' => CrowdfundingGoods::GOODS_STATE_PROJECT_FAIL  ) ) ) {
                        Log::write('众筹失败状态修改失败:'.json_encode( $goods ), 'error');
                    } else {
                        Log::write('众筹失败状态修改成功:'.json_encode( $goods ), 'info');
                    }
                }
            }
        }
    }

    /**
     * 众筹退款失败后再次发起退款
     */
    private function refund() {
        $where = array( 'refund_state' => 2 );
        $refundList = Db::name('crowdfunding_refund')->where( $where )->select();
        $detail = array();
        foreach ($refundList as $refund) {
            $key  = $refund['refund_id'];
            $code = $detail['payment_code'];
            if ( !array_key_exists($code, $detail) ) {
                $detail[ $code ] = array();
            }
            $detail[ $code ][$key] = array( $refund['trade_no'], $refund['refund_amount'], '众筹失败' );
        }
        //进行批量申请退款
        $successRefundData = array();
        foreach ($detail as $code => $unit) {
            $refund =  Payment::getInstance( $code )->refund( $refundNo, count( $unit ), array_values($unit), 'crowdRefund' );
            if ( !isset( $refund['is_success'] ) || $refund['is_success'] !='T' ) {
                Log::write('补退款申请失败, data:'.json_encode( $unit ),'error');
                //update by laijunliang at 2017/02/20, 判定是否有失败的记录,针对银联支付
                if ( isset( $refund['fail'] ) && is_array( $refund['fail'] ) ) {
                    foreach ($refund['fail'] as $fail) {
                        foreach ($unit as $key => $temp) {
                            if ( $temp['trade_no'] == $fail[0] ) {
                                unset( $unit[$key] );
                                break 1;
                            }
                        }
                    }
                    //标记成功的
                    foreach ($unit as $key => $data) {
                        array_push($successRefundData, $key);
                    }   
                }
            } else {
                //退款请求失败，记录
                foreach ($unit as $key => $data) {
                    array_push($successRefundData, $key);
                }
            }
        }
        //如果有申请成功，则修改状态
        if ( count( $successRefundData ) > 0 ) {
            $where = array( 'refund_id' => array( 'in', $successRefundData ) );
            if ( !Db::name('crowdfunding_refund')->where( $where )->update( array( 'refund_state' => 1 ) ) ) {
                Log::write('补退款申请状态修改失败, 退款号:'.json_encode( $successRefundData ), 'error');
            }
        }

    }
}