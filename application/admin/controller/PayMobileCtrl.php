<?php
/**
 * 移动端支付方式
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 李武 <lecheng406@sina.com> at 2017-1-6
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\common\model\PaymentMobile as Payment;
use think\Validate;
use think\Cache;

class PayMobileCtrl extends Auth
{
    private $key = 'pay_mobile_ctrl';

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * 支付方式列表展示
     */
    public function index()
    {
        $model_payment = new Payment();
        $payment_list = $model_payment->getPaymentList();
        $this->assign("searchData", $payment_list);
        return $this->fetch();
    }


    /**
     *  编辑支付方式
     */
    public function edit()
    {
        $model_payment = new Payment();
        $payment_id = intval($_GET["payment_id"]);
        $payment = $model_payment->getPaymentInfo(array('payment_id'=>$payment_id));
        $config_array =array();
        if ($payment['payment_config'] != '') $config_array = unserialize($payment['payment_config']);
        $this->assign('config_array',$config_array);
        $this->assign('payment',$payment);
        return $this->fetch();
    }

    /**
     * 保存支付方式
     */
    public function editPost() {

        if($_POST['form_submit']!='ok'){
            $this->error('非法提交');
        }else{

            $payment_id = intval($_POST["payment_id"]);
            if($payment_id<=0) $this->error('参数错误');

            $data = array();
            $data['payment_state'] = intval($_POST["payment_state"]);
            $payment_config	= '';
            $config_array = explode(',',$_POST["config_name"]);//配置参数
            if(is_array($config_array) && !empty($config_array)) {
                $config_info = array();
                foreach ($config_array as $k) {
                    $config_info[$k] = trim($_POST[$k]);
                }
                $payment_config	= serialize($config_info);
            }
            $data['payment_config'] = $payment_config;//支付接口配置信息

            $model_payment = new Payment();
            $result = $model_payment->editPayment($data,array('payment_id'=>$payment_id));
            if ($result>=0) {
                $this->success('编辑成功', url('index'));
            } else {
                $this->error('编辑失败');
            }
        }
    }
}