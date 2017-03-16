<?php
	/**
	 * 实物订单结算页面
	 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
	 * Author: laijunliang at 2016/12/05
	 */

namespace app\shop\controller;
use app\common\controller\Member;
use app\common\logic\Buy as BuyLogic;
use app\common\model\LogsDecorationOrder;
use app\common\model\Order;
use app\common\model\Payment;
use app\shop\model\DeliveryAddress;
use think\Session;
use think\Config;
use think\Cache;



class Buy extends Member{

	/**
	 * 构造器
	 */
	public function __construct() {
		parent::__construct();
	}

    /**
     * 订单结算页面
     */
    public function index()
	{
		$ifCart = $this->request->param('ifcart', '', 'intval');
		$cartId = $this->request->param('cart_id/a');
		$memberId = $this->user['member_id'];
		if ($ifCart == '') {
			$this->redirect('shop/index/index');
		} else {
			//得到购买数据
			$logicBuy = new BuyLogic();
			$result = $logicBuy->buyStep1($cartId, $ifCart, $memberId);
			if (!$result['state']) {
				$this->error($result['msg'], '');
			} else {
				$this->assign('result', $result['data']['store_cart_list']);
				$this->assign('member_id', $memberId);
				$this->assign('ifcart', $ifCart);
                if( $this->request->has('is_exchange') ) {//假如是兑换商品
                    $logsOrderId = $this->request->param('logs_order_id',0,'intval');
                    $result = ( new LogsDecorationOrder() )->checkExchange( ['id'=>$logsOrderId,'member_id'=>$this->user->member_id] );
                    if( $result['code'] == 0) {
                        $this->assign('msg', $result['msg']);
                        $this->assign('logs_order_id', $logsOrderId);
                        return $this->fetch('exchangemessage');
                    }
                    $this->assign('is_exchange', $this->request->param('is_exchange',0,'intval'));
                    $this->assign('logs_order_id', $logsOrderId);
                }
				$this->view->engine->layout(true);
               // 获取服务站点信息
				$servicePoint = $dataList = Cache::get('service_point_ctrl');
				$servicePoint = empty($servicePoint) ? array() : $servicePoint;
				$this->assign('servicePoint', $servicePoint);
				return $this->fetch();
			}

		}
	}

	/**
	 * 生成订单
	 *
	 */
	public function stepTwo()
	{
		if(!array_key_exists('cart_id',$this->request->post())){
			$this->error('没有可结算的商品', url("shop/carts/index"));
		}
		$logic_buy = new BuyLogic();
		$result = $logic_buy->buyStep2($this->request->param(), $this->user['member_id'], $this->user['phone'], $this->user['email']);
		if(!$result['state']) {
			$this->error($result['msg'], url("shop/carts/index"));
		}
        if( $result['data']['is_exchange'] == 1 ) {//假如是兑换商品成功
            $this->assign('msg', '兑换成功');
            $this->assign('logs_order_id', $result['data']['logs_order_id']);
            return $this->fetch('exchangemessage');
        }
		//转向到商城支付页面
		$this->redirect('shop/buy/pay',array('pay_sn'=>$result['data']['pay_sn']));
	}

	/**
	 * 下单时支付页面
	 */
	public function pay()
	{
		$gotoURL=url('shop/order/orderlist');
		$pay_sn = $this->request->param('pay_sn');
		if(strlen($pay_sn)<=0 || strlen($pay_sn)>18) $this->error('该订单不存在',$gotoURL);

		//查询支付单信息
		$model_order= new Order();
		$pay_info = $model_order->getOrderPayInfo(array('pay_sn'=>$pay_sn,'member_id'=>$this->user['member_id']),'master');
		if(empty($pay_info)) $this->error('该订单不存在',$gotoURL);
		$this->assign('pay_info',$pay_info);


		//取子订单列表
		$condition = array();
		$condition['pay_sn'] = $pay_sn;
		$condition['order_state'] = array('in',array(Order::ORDER_STATE_NEW,Order::ORDER_STATE_PAY));
		$order_list = $model_order->getOrderList($condition,'order_id,order_state,payment_code,order_amount,order_sn','','',array(),true);
		if (empty($order_list)) $this->error('未找到需要支付的订单',$gotoURL);

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
		$this->assign('order_list',$order_list);

		//如果线上线下支付金额都为0，转到支付成功页
		if ($pay_amount_online<=0) $this->redirect('shop/buy/payOk',array('pay_sn'=>$pay_sn));


		//输出订单描述
		$this->assign('order_remind','请您及时付款，以便订单尽快处理！');
		$this->assign('pay_amount_online',ncPriceFormat($pay_amount_online));

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
			if (empty($payment_list))  $this->error('暂未找到合适的支付方式',$gotoURL);
		}

		$this->assign('payment_list',$payment_list);

		return $this->fetch();
	}

	/*
	 * 支付成功
	 * */
	function payok()
	{
		$pay_sn = $this->request->param('pay_sn');
		if (empty($pay_sn)) $this->error('该订单不存在',url('shop/order/orderlist'));

		//查询支付单信息
		$model_order= new Order();
		$order_info = $model_order->getOrderInfo(array('pay_sn'=>$pay_sn,'member_id'=>$this->user['member_id']));
		if(empty($order_info))$this->error('该订单不存在',url('shop/order/orderlist'));
		$this->assign('pay_info',$order_info);
		$this->assign('pay_amount',$order_info['order_amount']);
		return $this->fetch();
	}

	/**
	 *添加用户地址
	 */
	public function addAddress() {
		$memberId	= $this->user['member_id'];
		$this->assign('member_id',$memberId);
		$this->view->engine->layout(false);
		// 获取服务站点信息
		$servicePoint = $dataList = Cache::get('service_point_ctrl');
		$servicePoint = empty($servicePoint) ? array() : $servicePoint;
		$this->assign('servicePoint', $servicePoint);
		return $this->fetch();
	}
	/**
	 *修改用户地址
	 */
	public function editAddress() {
		$addressId = $this->request->param('address_id','','intval');
		$modelAddress = new DeliveryAddress();
		$addressOne = $modelAddress->where(['address_id'=>$addressId])->order('is_default','DESC')->find();
		$this->view->engine->layout(false);
		$this->assign('address_one',$addressOne);
		// 获取服务站点信息
		$servicePoint = $dataList = Cache::get('service_point_ctrl');
		$servicePoint = empty($servicePoint) ? array() : $servicePoint;
		$this->assign('servicePoint', $servicePoint);
		return $this->fetch();
	}

}
