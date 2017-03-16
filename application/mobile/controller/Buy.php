<?php
/**
 * Created by 长虹.
 * User: 李武
 * Date: 2017/1/4
 * Time: 10:56
 * Desc: 手机端购物车接口
 */
namespace app\mobile\controller;
use app\common\logic\Buy as BuyLogic;
use app\common\model\Order;
use app\common\model\Payment;
use think\Db;

class Buy extends MobileMember
{

    /**
     * 常规商品购买接口---购买第一步 订单确认页面
     */
    public function index()
    {
        $ifCart = $this->request->post('ifcart', '', 'intval');
        $cartId = $this->request->post('cart_id','','trim');
        $memberId = $this->user['member_id'];

        if ($ifCart == '') {
            $this->returnJson('',1,'参数错误');
        } else {
            //得到购买数据
            $cartId = json_decode($cartId,true);
            if(!is_array($cartId) || count($cartId)<=0){
                $this->returnJson('',1,'参数错误');
            }else{
                $logicBuy = new BuyLogic();
                $result = $logicBuy->buyStep1($cartId, $ifCart, $memberId);
                if (!$result['state']) {
                    $this->returnJson('',1,$result['msg']);
                } else {
                    $return_arr = array();
                    $return_arr['member_id']=$this->user['member_id'];
                    $goodsTotal = 0;
                    foreach($result['data']['store_cart_list'][1] as $key => $val) {
                        $goodsTotal += $val['goods_total'];
                    }
                    $return_arr['result']=$result['data']['store_cart_list'][1];
                    $return_arr['goods_total']= ncPriceFormat($goodsTotal);
                    $return_arr['ifcart']=$ifCart;
                    $return_arr['cart_id']=$cartId;
                    $this->returnJson($return_arr);
                }
            }
        }
    }

    /**
     * 常规商品购买保存订单接口---购买第二步  生成订单
     *
     */
    public function stepTwo()
    {
        $ifCart = $this->request->post('ifcart', '', 'intval');
        $cartId = $this->request->post('cart_id','','trim');
        $addressId = $this->request->post('address_id','','trim');
        if ($ifCart == '' || $cartId == '' || $addressId == '') {
            $this->returnJson('',1,'参数错误');
        } else {
            //得到购买数据
            $cartId = json_decode($cartId,true);
            if(!is_array($cartId) || count($cartId)<=0){
                $this->returnJson('',1,'参数错误');
            }else{
                $this->request->post(array('cart_id'=>$cartId));
                $this->request->post(array('order_from'=>2));
                $logic_buy = new BuyLogic();
                $result = $logic_buy->buyStep2($this->request->param(), $this->user['member_id'], $this->user['phone'], $this->user['email']);
                if(!$result['state']) {
                    $this->returnJson('',1,$result['msg']);
                }else{
                    //下单成功返回付款单号，手机端转移到付款页面选择支付方式付款
                    $this->returnJson(array('pay_sn'=>$result['data']['pay_sn']));
                }
            }
        }

    }

    /**
     * 常规商品订单支付页面接口--支付确认选择支付方式
     */
    public function pay()
    {
        $pay_sn = $this->request->post('pay_sn');
        if(strlen($pay_sn)<=0 || strlen($pay_sn)>18) {
            $this->returnJson('',1,'参数错误');
        }else{
            //查询支付单信息
            $model_order= new Order();
            $pay_info = $model_order->getOrderPayInfo(array('pay_sn'=>$pay_sn,'member_id'=>$this->user['member_id']),'master');
            if(empty($pay_info)) {
                $this->returnJson('',1,'该订单不存在');
            }else{
                //取子订单列表
                $condition = array();
                $condition['pay_sn'] = $pay_sn;
                $condition['order_state'] = array('in',array(Order::ORDER_STATE_NEW,Order::ORDER_STATE_PAY));
                $order_list = $model_order->getOrderList($condition,'order_id,order_state,payment_code,order_amount,order_sn','','',array(),true);
                if (empty($order_list)){
                    $this->returnJson('',1,'未找到需要支付的订单');
                }else{
                    //重新计算在线支付金额
                    $pay_amount_online = 0;
                    //订单总支付金额(不包含货到付款)
                    $pay_amount = 0;
                    foreach ($order_list as $key => $order_info)
                    {
                        $payed_amount = 0;
                        if ($order_info['order_state'] == Order::ORDER_STATE_NEW) {
                            $pay_amount_online += ncPriceFormat(floatval($order_info['order_amount'])-$payed_amount);
                        }
                        $pay_amount += floatval($order_info['order_amount']);

                        //显示支付方式与支付结果
                        $order_list[$key]['payment_state'] = '在线支付';
                    }
                    //手机端没有assign 罗婷
                    //$this->assign('order_list',$order_list);

                    //如果线上线下支付金额都为0，转到支付成功页 ，手机端返回修改罗婷
                    if ($pay_amount_online<=0) $this->returnJson('',1,'订单状态已发生变化');;

                     //显示支付接口列表
                    $payment_list = array();
                    if ($pay_amount_online > 0)
                    {
                        $model_payment = new Payment();
                        $condition = array();
                        $payment_list = $model_payment->getPaymentOpenList($condition);
                        if (!empty($payment_list)) {
                            unset($payment_list['predeposit']);
                            unset($payment_list['offline']);
                        }
                        if (empty($payment_list)){
                            $this->returnJson('',1,'暂未找到合适的支付方式');
                        }else{
                            //输出订单描述
                            $data=array();
                            $data['pay_amount_online']=ncPriceFormat($pay_amount_online);
                            $data['pay_info']=$pay_info;
                            $data['payment_list']=$payment_list;
                            $this->returnJson($data);
                        }
                    }else{
                        $this->returnJson('',1,'订单不需要支付');
                    }
                }
            }
        }
    }

    /*
	 * 常规商品订单支付成功页面
	 * */
    function payOk()
    {
        $paySn = $this->request->post('pay_sn', '', 'trim');
        $payAmount = $this->request->post('pay_amount', '', 'trim');
        if ( empty($paySn) ) $this->returnJson('',1,'该订单不存在');
        //查询支付单信息
        $orderModel= new Order();
        $condition = ['pay_sn'=>$paySn, 'member_id'=>$this->user['member_id']];
        $payInfo = $orderModel->getOrderPayInfo( $condition );
        if( empty($payInfo) ) $this->returnJson('',1,'该订单不存在');
        //查询订单信息
        $orderInfo = $orderModel->getOrderInfo($condition,array('order_goods','order_common'));
        if( empty($orderInfo) ) $this->returnJson('',1,'该订单不存在');
        //显示系统自动取消订单日期
        if ($orderInfo['order_state'] == Order::ORDER_STATE_NEW) {
            $orderInfo['order_cancel_day'] = $orderInfo['add_time'] + ORDER_AUTO_CANCEL_DAY * 24 * 3600;
        }
        //支付方式
        if($orderInfo['payment_code'] != 'offline' && $orderInfo['order_state'] == Order::ORDER_STATE_PAY){
            $orderInfo['orderPaymentName'] =  orderPaymentName($orderInfo['payment_code']);
        }
        $data = array();
        $data['pay_info'] = $payInfo;
        $data['pay_amount'] = $payAmount;
        $data['order_info'] = $orderInfo;
        $data['orderState'] = Order::$order_state_arr;
        $this->returnJson($data);
    }
}
