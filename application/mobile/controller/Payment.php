<?php
/**
 * Created by 长虹.
 * User: 李武
 * Date: 2017/1/5
 * Time: 10:56
 * Desc: 手机端订单支付接口调用
 */
namespace app\mobile\controller;
use app\common\controller\PayMobile;
use app\common\logic\RealOrderLogic;
use app\common\logic\LogsOrderLogic;
use app\common\logic\CrowdOrderLogic;
use think\log;
class Payment extends MobileMember
{
    /**
     * 实物商品订单
     */
    public function payOrder()
    {
        $pay_sn = $this->request->post('pay_sn', '', 'trim');
        $payment_code = $this->request->post('payment_code', '', 'trim');
        if(empty($pay_sn) || empty($payment_code)){
            $this->returnJson('',1,'参数错误');
        }else{
            $result = new PayMobile($pay_sn,$payment_code);
            if(!is_object($result)) {
                $this->returnJson('',1,$result['msg']);
            }else{
                $res=$result->pay('real',$this->user['member_id']);
                if(!$res['state']){
                    $this->returnJson('',1,$res['msg']);
                }else{
                    $this->returnJson($res['data']);
                }
            }
        }
    }
}
