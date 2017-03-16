<?php
namespace app\common\controller;

use think\Controller;
use app\common\model\Payment;
use app\common\logic\RealOrderLogic;
use app\common\logic\LogsOrderLogic;
use app\common\logic\CrowdOrderLogic;
set_time_limit(0);
class Pay extends Common
{
    /*
     * 支付方式信息
     * */
    private $_payment_info=array();

    /*
     * 支付接口代码
     * */
    private $_payment_code='';

    /*
     * 支付单号
     * */
    private $_pay_sn='';

    public function __construct($pay_sn,$payment_code)
    {
        if(!is_string($payment_code) || empty($payment_code)) {
            return callback(false,'支付接口参数错误');
        }
        if(!is_string($pay_sn) || empty($pay_sn)) {
            return callback(false,'支付单号参数错误');
        }
        $result=$this->_getPaymentInfo($payment_code);
        if(!$result['state'])  return $result;
        $this->_pay_sn = $pay_sn;
        parent::__construct();
    }

    /*
     * 付款
     * @param string $tp 支付的业务类型  实物(普通)订单=real  | 众筹订单=crowd  | 整装订单logs
     * */
    public function pay($tp='real',$member_id = null)
    {
        $order_logic_class = ucfirst($tp).'OrderLogic';
        if(!class_exists('app\\common\\logic\\'.$order_logic_class)) {
            return callback(false,'订单逻辑类不存在');
        }

        $orderLogic = call_user_func(array('app\\common\\logic\\'.$order_logic_class,'create'));
        $result = $orderLogic->getOrderInfo($this->_pay_sn, $member_id);
        if(!$result['state']) return $result;
        if ($result['data']['api_pay_state'] || empty($result['data']['api_pay_amount'])) {
            return callback(false,'该订单不需要支付');
        }
        //转到第三方API支付
        $this->_api_pay($result['data']);
    }


    /**
     * 第三方在线支付接口
     *
     */
    private function _api_pay($order_info)
    {
        $order_info['payment_time'] = date('YmdHis', time());
		$order_info['member_name'] = $order_info['order_list'][0]['member_name'];
        $payment_api = $this->_get_pay_api($order_info);
        if($this->_payment_info['payment_code'] == 'chinabank') {
            echo $payment_api->get_payurl();
        } else {

            @header("Location: ".$payment_api->get_payurl());
        }
        exit();
    }

    /*
     * 获取第三方支付接口对象
     *
     * */
    private function _get_pay_api($order_info)
    {
        //-------开始实例化支付接口类-------
        $inc_file = APP_PATH.'common'.DS.'payment'.DS.$this->_payment_code.DS.$this->_payment_code.'.php';
        if(!file_exists($inc_file)) return callback(false,'系统不支持选定的支付方式');

        //开始实例化支付接口类
        require_once($inc_file);
        $payment_api = new $this->_payment_code($this->_payment_info,$order_info);
        return $payment_api;
    }


    /*
     * 支付接口返回通知(同步或异步)
     * 其中： $order_type ：real_order=实物订单  crowd_order=众筹订单  logs_order=整装订单
     *       $trade_no : 支付接口的交易号
     *       $log_return 支付接口校验方式 ： return_verify同步方法交易  notify_verify异步方法交易
     * */
    public function returnpay($order_type,$trade_no=null,$log_return='return_verify',$member_id = null)
    {
        //获取订单逻辑
        $order_type_arr = explode('_',$order_type);
        $order_logic_class = ucfirst($order_type_arr[0]).'OrderLogic';
        if(!class_exists('app\\common\\logic\\'.$order_logic_class)) {
            return callback(false,$order_logic_class.'订单逻辑类不存在');
        }

        $orderLogic = call_user_func(array('app\\common\\logic\\'.$order_logic_class,'create'));
        $payment_state = null;//支付状态
        $result = $orderLogic->getOrderInfo($this->_pay_sn);
        if(!$result['state'])  $this->error($result['msg']);
        if ($result['data']['api_pay_state'])  $payment_state = 'success';

        $order_list = $result['data']['order_list'];//订单数据
        $order_pay_info = $result['data'];//订单支付数据
        $api_pay_amount = $result['data']['api_pay_amount'];//订单支付金额

        //修改支付数据
        if ($payment_state != 'success')
        {
            //创建支付接口对象
            $payment_api	= $this->_get_pay_api($order_pay_info);

            //返回参数判断
            if($log_return=='return_verify'){
                $parr = $_GET;
                $verify = $payment_api->return_verify();
            }elseif($log_return=='notify_verify'){
                $parr = $_POST;
                $verify = $payment_api->notify_verify();
            }

            if(!$verify) return callback(false,'支付数据验证失败');

            //取得支付结果
            $pay_result	= $payment_api->getPayResult($parr);
            if (!$pay_result)  return callback(false,'非常抱歉，您的订单支付没有成功，请您稍后尝试');

            //修改改订单支付状态
            $rt = $orderLogic->updateOrder($this->_pay_sn, $this->_payment_info['payment_code'], $order_list, $trade_no);
            if (!$rt['state']) return callback(false,'支付状态更新失败：'.$rt['msg']);
        }
        return callback(true,'支付状态更新成功',array('api_pay_amount'=>$api_pay_amount));
    }

    /**
     * 取得所使用支付方式信息
     * @param unknown $payment_code
     */
    private function _getPaymentInfo($payment_code)
    {
        if (in_array($payment_code,array('offline','predeposit')) || empty($payment_code)) {
            return callback(false,'系统不支持选定的支付方式');
        }

        $model_payment = new Payment();
        $condition = array();
        $condition['payment_code'] = $payment_code;
        $payment_info = $model_payment->getPaymentOpenInfo($condition);
        if(empty($payment_info)) return callback(false,'系统不支持选定的支付方式');;
        $payment_info['payment_config'] = unserialize($payment_info['payment_config']);
        $this->_payment_code = $payment_info['payment_code'];
        $this->_payment_info = $payment_info;
        if(empty($this->_payment_code))  return callback(false,'支付接口代码错误');
        return callback(true,'',$payment_info);
    }

}
