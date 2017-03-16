<?php
/**
 * 支付抽象类
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 
 */
namespace app\common\payment;
use app\common\model\Payment as Pay;

class Payment {
     //静态变量保存全局实例
    private static $_instance = null;
    private function __construct() {}
    private function __clone() {}
    public static function getInstance( $code = 'alipay' ) {
        if (is_null ( self::$_instance ) || isset ( self::$_instance )) {
            $model_payment = new Pay();
            $condition = array( 'payment_code' => $code );
            $payment_info = $model_payment->getPaymentOpenInfo($condition);
            if ( !$payment_info ) {
                return NULL;
            }
            $payment_info['payment_config'] = unserialize($payment_info['payment_config']);
            $path = APP_PATH.DS.'common'.DS.'payment'.DS.$code.DS.$code.'.php';
            if(!file_exists($path)) {
                return NULL;
            }
            //开始实例化支付接口类
            require($path);
            return new $code($payment_info);
        }
        return self::$_instance;
    }
}
