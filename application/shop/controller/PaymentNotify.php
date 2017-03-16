<?php
/**
 * 订单付款
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang at 2016/12/05
 */

namespace app\shop\controller;
use think\Controller;
use app\common\controller\Pay;
use app\common\logic\RealOrderLogic;
use app\common\logic\LogsOrderLogic;
use app\common\logic\CrowdOrderLogic;
use think\Log;
class PaymentNotify extends Controller
{
    /**
     * 构造器
     */
    public function __construct()
    {
        parent::__construct();
    }

    //  111.9.116.134:8083/shop/payment_notify/test/payment_code/alipay
    public function test()
    {
        exit('测试使用，，，暂不开放');
        $json = '{"discount":"0.00","extra_common_param":"real_order","payment_type":"1","subject":"\u5b9e\u7269\u8ba2\u5355_620536428703759d42","trade_no":"2016123021001004320219554980","buyer_email":"1965760098@qq.com","gmt_create":"2016-12-30 15:59:09","notify_type":"trade_status_sync","quantity":"1","out_trade_no":"620536428703759d42","seller_id":"2088521264390952","notify_time":"2016-12-30 15:59:20","body":"620536428703759d42","trade_status":"TRADE_SUCCESS","is_total_fee_adjust":"N","total_fee":"0.01","gmt_payment":"2016-12-30 15:59:19","seller_email":"muzhuzhineng@163.com","price":"0.01","buyer_id":"2088302351210326","notify_id":"98283348f356273f5d2613714d0e9c7igy","use_coupon":"N","sign_type":"MD5","sign":"27547bb846a093e58737c876b772b34a"}';
        $json_arr = json_decode($json,true);
        $_POST = $json_arr;
        $payment_code    = trim($this->request->param('payment_code'));
        if(empty($payment_code))  exit('fail');
        if($payment_code=='alipay' ){//支付宝
            $success = 'success';
            $fail = 'fail';
            $order_type = $_POST['extra_common_param'];
            $out_trade_no = $_POST['out_trade_no'];
            $trade_no = $_POST['trade_no'];
        }else if($payment_code=='chinabank'){//网银
            $success = 'ok';
            $fail = 'error';
            $out_trade_no = $_POST['v_oid'];
            $order_type = $_POST['remark1'];
            $trade_no = $_POST['v_idx'];

        }else if($payment_code=='tenpay') {//财付通
            $success = 'success';
            $fail = 'fail';
            $out_trade_no = $_POST['sp_billno'];
            $order_type = $_POST['attach'];
            $trade_no = $_POST['transaction_id'];
            exit();
        }

        if(empty($order_type)) exit($fail);

        $result = new Pay($out_trade_no,$payment_code);
        if(!is_object($result)) {
            Log::write('POST 支付接口返回异步通知2: '.json_encode($result).'\r\n');
            exit($fail);
        }
        $rs = $result->returnpay($order_type,$trade_no,'notify_verify');
        print_r($rs);
        echo '<hr>';
        if(!$rs['state']){
            Log::write('POST 支付接口返回异步通知3: '.json_encode($rs).'\r\n');
        }
        exit($rs['state'] ? $success : $fail);


    }

    /**
     * 支付接口返回异步通知(支付宝异步通知和网银在线自动对账)
     * 其中： $order_type ：real_order=实物订单  crowd_order=众筹订单  logs_order=整装订单
     *       $out_trade_no : 支付单号pay_sn
     *       $trade_no : 支付接口的交易号
     */
    public function notify()
    {
        Log::write('POST 支付接口返回异步通知1: '.json_encode($_POST).'\r\n');
        $payment_code    = trim($this->request->param('payment_code'));
        if(empty($payment_code))  exit('fail');
        if($payment_code=='alipay' ){//支付宝
            $success = 'success';
            $fail = 'fail';
            $order_type = $_POST['extra_common_param'];
            $out_trade_no = $_POST['out_trade_no'];
            $trade_no = $_POST['trade_no'];
        }else if($payment_code=='chinabank'){//网银
            $success = 'ok';
            $fail = 'error';
            $out_trade_no = $_POST['orderId'];
            $order_type = $_POST['reqReserved'];
            $trade_no = $_POST['queryId'];

        }else if($payment_code=='tenpay') {//财付通
            $success = 'success';
            $fail = 'fail';
            $out_trade_no = $_POST['sp_billno'];
            $order_type = $_POST['attach'];
            $trade_no = $_POST['transaction_id'];
            exit();
        }

        if(empty($order_type)) exit($fail);

        $result = new Pay($out_trade_no,$payment_code);
        if(!is_object($result)) {
            Log::write('POST 支付接口返回异步通知2: '.json_encode($result).'\r\n');
            exit($fail);
        }
        $rs = $result->returnpay($order_type,$trade_no,'notify_verify');
        if(!$rs['state']){
            Log::write('POST 支付接口返回异步通知3: '.json_encode($rs).'\r\n');
        }
        exit($rs['state'] ? $success : $fail);
    }
}