<?php
/**
 * 延时任务样例
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: Laijunliang at 2016-12-21
 */
namespace app\crontab\controller;

use app\common\logic\CrowdOrderLogic;
use app\common\model\CrowdfundingOrder;
use app\shop\model\CrowdfundingGoods;
use app\common\model\CrowdfundingGoods as CrowdfundingCommon;
use app\common\payment\Payment;
use think\Controller;
use think\Db;
use think\Log;

class Task extends Controller{



    /**
     * 加入购物车
     */
    public function demo() {
        $param = $this->request->param();
        $where = array( 'id' => $param['id'] );
        //Db::name('crowdfunding_goods')->where( $where )->update( array( 'state' => $param['state'] ) );
        return $this->result('', 1, '成功', 'json');
    }

    /**
     * 处理众筹商品状态(众筹结束时间延时15分钟执行)
     */
    public function dealCrowdfundingGoods(){
        $param = $this->request->param();
        $id    = $param['id'];
        $where = array('id' => $id);
        $goods = new CrowdfundingGoods();
        $orderModel = new CrowdfundingOrder();
        $res   = $goods->getCrowdfundingGoods($id);
        if ( !$res ) {
            Log::write('众筹退款记录生成失败, data:'.json_encode( $refundData ),'error');
            return $this->result('', 0, '未查到该众筹商品', 'json');
        }
        $data = array('state'=>$goods::GOODS_STATE_FAIL);
        if ( $res['quotient'] == $res['sale_number'] ) {
            $data['state'] = $goods::GOODS_STATE_SUCCESS;
            if ( !$goods->saveCrowdfundingGoods( $data, $where) ) {
                return $this->result('', 1, '众筹成功', 'json');
            }
        } else {
            //如果购买份额不够，众筹没有成功
            Db::startTrans();
            $result = $goods->saveCrowdfundingGoods($data,$where);
            //进行退款操作
            $orderIdList = Db::name('crowdfunding_order_goods')->where( array( 'goods_id' => $id ) )->column('order_id');
            if ( count( $orderIdList ) > 0 ) {
                $where     = array( 'order_id' => array( 'in', $orderIdList ), 'order_state' => CrowdfundingOrder::ORDER_STATE_PAY );
                $orderList = $orderModel->field('order_id, pay_sn, member_id, order_amount, payment_code')->where( $where )->select();
                if ( count( $orderList ) > 0 ) {
                    //查询退款单号
                    $paySnList = array();
                    $orderIdList = array();
                    foreach ($orderList as $order) {
                        array_push($paySnList, $order['pay_sn']);
                        array_push($orderIdList, $order['order_id']);
                    }
                    $tradeNo = Db::name('order_pay')->where( array( 'pay_sn' => array( 'in', $paySnList ), 'api_pay_state' => 1 ) )->column('pay_sn, trade_no');

                    //进行批量退款
                    $detail     = array();
                    $time       = time();
                    $refundData = array();
                    $refundNo   = date('YmdHis', $time). str_pad($id, 8, '0', STR_PAD_LEFT);
                    foreach ($orderList as $key => $order) {
                        $tag  = $order['pay_sn'];
                        $code = $order['payment_code'];
                        array_push( $refundData , array(
                            'refund_sn' => $refundNo,
                            'payment_code' => $code,
                            'trade_no'  => $tradeNo[$tag],
                            'order_id'  => $order['order_id'],
                            'member_id' => $order['member_id'],
                            'refund_amount' => $order['order_amount'],
                            'refund_state' => 1,
                            'refund_type' => 1,
                            'created_at' => $time
                        ));
                        if ( !array_key_exists($code, $detail) ) {
                            $detail[ $code ] = array();
                        }
                        $detail[ $code ][$key] = array( $tradeNo[$tag], $order['order_amount'], '众筹失败' );
                    }
                    //进行退款申请，将失败的申请再次记录下来，5分钟后再次发起申请
                    $failRefund = array();
                    foreach ($detail as $code => $unit) {
                        $refund =  Payment::getInstance( $code )->refund( $refundNo, count( $unit ), array_values($unit), 'crowdRefund' );
                        if ( !isset( $refund['is_success'] ) || $refund['is_success'] !='T' ) {
                            Log::write('退款申请失败, data:'.json_encode( $unit ),'error');
                            //退款请求失败，记录
                            //update by laijunliang at 2017/02/20,判定是否有失败的记录,针对银联支付
                            if ( isset( $refund['fail'] ) && is_array( $refund['fail'] ) ) {
                                foreach ($refund['fail'] as $fail) {
                                    foreach ($refundData as $key => $temp) {
                                        if ( $temp['trade_no'] == $fail[0] ) {
                                            $refundData[$key]['refund_state'] = 2;
                                            break 1;
                                        }
                                    }
                                }
                            } else {
                                foreach ($unit as $key => $data) {
                                    $refundData[$key]['refund_state'] = 2;
                                    array_push($failRefund, $refundData[$key]['order_id']);
                                }
                            }
                        }
                    }
                    //讲众筹的结果插入数据库
                    $insert = Db::name('crowdfunding_refund')->insertAll( $refundData );
                    //修改订单退款状态为全部退款
                    $where  = array( 'order_id' => array( 'in', $orderIdList ) );
                    $update = $orderModel->where( $where )->update( array( 'refund_state' => 2, 'order_state' => 0 ) );

                    if ( !$result || !$insert || !$update ) {
                        Db::rollback();
                        Log::write('众筹退款记录生成失败, data:'.json_encode( $refundData ),'error');
                        return $this->result('', 0, '众筹退款记录生成失败', 'json');
                    }

                }
            }
            if ( !$result ) {
                Db::rollback();
                Log::write('众筹退款记录生成失败, data:'.json_encode( $param ),'error');
                return $this->result('', 0, '众筹退款记录生成失败', 'json');
            }
            Db::commit(); 
        }
        return $this->result('', 1, '众筹结果记录成功', 'json');
    }
    


    /**
     * 处理众筹商品状态(众筹开始时执行)
     */
    public function dealCrowdfundingGoodsStart(){
        $param = $this->request->param();
        $id    = $param['id'];
        $where = array('id' => $id);
        $goods = new CrowdfundingGoods();
        $res   = $goods->getCrowdfundingGoods($id);
        if($res){
            if($res['verify'] != 1){  //到了众筹开始时间如果还没审核则改为众筹失败
                $data = array('state'=>$goods::GOODS_STATE_PROJECT_FAIL,'verify'=>0);
                $result = $goods->saveCrowdfundingGoods($data,$where);
            }else{
                $data = array('state' => $goods::GOODS_STATE_SEND);  //如果已审核通过则为众筹中
                $result = $goods->saveCrowdfundingGoods($data,$where);
            }
            if($result){
                return $this->result('', 1, '状态更新成功' ,'json');
            }else{
                Log::write('处理众筹商品状态(众筹开始时执行)状态更新失败, data:'.json_encode( $data ),'error');
                return $this->result('', 0, '状态更新失败', 'json');
            }
        }
    }


    /**
     * 处理众筹订单状态(下单15分钟后判断支付状态，如果未付款则取消该订单并减掉该订单所占份额)
     */
    public function dealCrowdfundingOrderStart(){
        $param = $this->request->param();
        $order_id    = $param['order_id'];
        $goods_id = $param['goods_id'];
        $goods_number = $param['goods_number'];
        $where = array('order_id' => $order_id);
        $order = new CrowdfundingOrder();
        $goods = new CrowdfundingCommon();
        $res   = $order->getCrowdfundingOrder($where);
        if($res){
            if($res['order_state'] == 10){  //15分钟后如果未支付
                $data = array('order_state'=>$order::ORDER_STATE_CANCEL);
                Db::startTrans();
                try{

                    $end = $order->editOrder($data,$where);
                    //取消订单后修改已购买份额
                    if(!$end){
                        Db::rollback();
                        Log::write('修改订单状态失败, data:'.json_encode( $data ),'error');
                        return $this->result('', 0, '修改订单状态失败', 'json');
                    }

                    $result = $goods->cancelOrderUpdateStorage($goods_id,$goods_number);
                    if(!$result){
                        Db::rollback();
                        Log::write('库存更新失败, data:'.json_encode( $data ),'error');
                        return $this->result('', 0, '库存更新失败', 'json');
                    }

                    Db::commit();

                }catch (\Exception $e){
                    Db::rollback();
                    Log::write('修改失败, data:'.json_encode( $data ),'error');
                    return $this->result('', 0, '修改失败', 'json');
                }
                return $this->result('', 1, '成功', 'json');
            }else{
                return $this->result('',1,'不是未付款状态','json');
            }
        }else{
            Log::write('未找到订单信息, data:'.json_encode( $where ),'error');
            return $this->result('', 0, '未找到订单信息', 'json');
        }


    }

}