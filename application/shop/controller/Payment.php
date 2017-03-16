<?php
/**
 * 订单付款
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang at 2016/12/05
 */

namespace app\shop\controller;
use app\common\controller\Member;
use app\common\controller\Pay;
use app\common\logic\RealOrderLogic;
use app\common\logic\LogsOrderLogic;
use app\common\logic\CrowdOrderLogic;
use think\Log;
class Payment extends Member
{
    public static $_return_url=array(
        'real_order'=>'shop/buy/payok',
        'crowd_order'=>'shop/crowdfunding_order/payok',
        'logs_order'=>'shop/logs_order/payok',
    );

    /**
     * 构造器
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 实物商品订单
     */
    public function payOrder()
    {
        $url=url('shop/order/orderlist');
        $pay_sn = trim($_POST['pay_sn']);
        $payment_code = trim($_POST['payment_code']);
        if(empty($pay_sn) || empty($payment_code)) $this->error('参数错误');
        $result = new Pay($pay_sn,$payment_code);
        if(!is_object($result)) $this->error($result['msg']);
        $rs=$result->pay('real',$this->user['member_id']);
        if(!$rs['state']) $this->error($rs['msg'],$url);
    }

    /**
     * 整装订单
     */
    public function payLogsOrder()
    {
        $orderId = $this->request->param('orderId','','intval');
        $url=url('shop/logs_order/detail',array('orderId'=>$orderId));

        $pay_sn = trim($_POST['pay_sn']);
        $payment_code = trim($_POST['payment_code']);
        if(empty($pay_sn) || empty($payment_code)) $this->error('参数错误');
        $result = new Pay($pay_sn,$payment_code);
        if(!is_object($result)) $this->error($result['msg']);
        $rs=$result->pay('logs',$this->user['member_id']);
        if(!$rs['state']) $this->error($rs['msg'],$url);
    }


    /**
     * 众筹商品订单
     */
    public function payCrowdOrder()
    {
        $url=url('shop/crowdfunding_order/lists');
        $pay_sn = trim($_POST['pay_sn']);
        $payment_code = trim($_POST['payment_code']);
        if(empty($pay_sn) || empty($payment_code)) $this->error('参数错误');
        $result = new Pay($pay_sn,$payment_code);
        if(!is_object($result)) $this->error($result['msg']);
        $rs=$result->pay('crowd',$this->user['member_id']);
        if(!$rs['state']) $this->error($rs['msg'],$url);
    }





    /**
     * 支付接口返回同步通知
     * 其中： $order_type ：real_order=实物订单  crowd_order=众筹订单  logs_order=整装订单
     *       $out_trade_no : 支付单号pay_sn
     *       $trade_no : 支付接口的交易号
     */
    public function returnpay()
    {
        //支付接口编号
        $payment_code    = trim($this->request->param('payment_code'));
        $url=url('shop/order/orderlist');
        if(empty($payment_code))  $this->error('参数错误',$url);
        if($payment_code=='alipay'){//支付宝
            $order_type = $_GET['extra_common_param'];
            $out_trade_no = $_GET['out_trade_no'];
            $trade_no = $_GET['trade_no'];
        }else if($payment_code=='chinabank'){//网银
            $out_trade_no = $_POST['orderId'];
            $order_type = $_POST['reqReserved'];
            $trade_no = $_POST['queryId'];
        }else if($payment_code=='tenpay'){//财付通
            $out_trade_no = $_GET['sp_billno'];
            $order_type = $_GET['attach'];
            $trade_no = $_GET['transaction_id'];
        }

        if(empty($order_type)) $this->error('订单类型参数错误',$url);
        $result = new Pay($out_trade_no,$payment_code);
        if(!is_object($result)) $this->error($result['msg'],$url);
        $rs = $result->returnpay($order_type,$trade_no,'return_verify');
        if (!$rs['state']) $this->error('支付失败：'.$rs['msg'],$url);

        //支付金额
        $api_pay_amount = $rs['data']['api_pay_amount'];
        $this->redirect(self::$_return_url[$order_type],array('pay_sn'=>$out_trade_no,'pay_amount'=>ncPriceFormat($api_pay_amount)));
    }

}
