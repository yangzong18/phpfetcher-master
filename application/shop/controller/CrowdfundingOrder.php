<?php
/**
 * 众筹商品订单
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/15  10:43
 */
namespace app\shop\controller;

use app\common\logic\CrowdfundingBuy;
use app\common\controller\Member;
use app\common\logic\CrowdOrderLogic;
use app\common\model\Payment;
use app\shop\model\CrowdfundingGoods;
use think\controller;
use app\common\model\CrowdfundingOrder as CrowdfundingOrders;
use app\common\model\CrowdfundingGoods as CrowdfundingGoodsCommon;
use think\Db;
use think\Cache;

class CrowdfundingOrder extends Member
{

    const DELETE_STATE0 = 0;//正常
    const DELETE_STATE1 = 1;//回收站
    const DELETE_STATE2 = 2;//永久删除

    /**
     * 开启状态标识
     * @var unknown
     */
    const STATE_OPEN = 1;

    /**
     * 众筹商品订单确认页
     */
    public function index(){

        $goodsId = $this->request->param('goods_id');
        $memberId = $this->user['member_id'];
        if ($goodsId == '') {
            $this->redirect('shop/index/index');
        } else {
            //得到购买数据
            $logicBuy = new CrowdfundingBuy();
            $result = $logicBuy->buyStep1($goodsId, $memberId);
            if (!$result['state']) {
                $this->error($result['msg'], '');
            } else {
                $result['progress'] = round($result['sale_number']/$result['quotient'],3)*100;
                $this->assign('result', $result);
                $this->assign('member_id', $memberId);
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
     *生成订单
     */
    public function add(){
        $param = $this->request->param();

        $member_id      = $this->user['member_id'];
        $member_name    = $this->user['member_name'];
        $member_email   = $this->user['email'];
        $logicBuy = new CrowdfundingBuy();
        $result = $logicBuy->buyStep2($param,$member_id,$member_name,$member_email);
        if(!$result['state']) {
            $this->error($result['msg'], url("shop/carts/index"));
        }
        //转向到商城支付页面
        $this->redirect('shop/crowdfunding_order/pay',array('pay_sn'=>$result['data']['pay_sn']));

    }


    /**
     * 下单时支付页面
     */
    public function pay()
    {
        $gotoURL=url('shop/crowdfunding_order/lists');
        $pay_sn = $this->request->param('pay_sn');
        if(strlen($pay_sn)<=0 || strlen($pay_sn)>18) $this->error('该订单不存在',$gotoURL);

        //查询支付单信息
        $model_order= new CrowdfundingOrders;
        $pay_info = $model_order->getOrderPayInfo(array('pay_sn'=>$pay_sn,'member_id'=>$this->user['member_id']),'master');
        if(empty($pay_info)) $this->error('该订单不存在',$gotoURL);
        $this->assign('pay_info',$pay_info);


        //取子订单列表
        $condition = array();
        $condition['pay_sn'] = $pay_sn;
        $condition['order_state'] = array('in',array(CrowdfundingOrders::ORDER_STATE_NEW,CrowdfundingOrders::ORDER_STATE_PAY));
        $order_list = $model_order->getCrowdfundingOrderList($condition,'order_id,order_state,payment_code,order_amount,order_sn','','',array(),true);
        if (empty($order_list)) $this->error('未找到需要支付的订单',$gotoURL);

        //重新计算在线支付金额
        $pay_amount_online = 0;
        //订单总支付金额(不包含货到付款)
        $pay_amount = 0;
        foreach ($order_list as $key => $order_info)
        {
            $payed_amount = 0;
            if ($order_info['order_state'] == CrowdfundingOrders::ORDER_STATE_NEW) {
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


    /**
     * 众筹商品订单列表
     */
    public function lists(){

        $crowdfundingOrder = new CrowdfundingOrders();
        $where = array();
        $state = $this->request->param('state','all','trim');
        $name = $this->request->param('name','','trim');

        if($state == 'all'){
            $where['lock_state']  = 0;
        }else if($state == 'new'){
            $where['order_state'] = $crowdfundingOrder::ORDER_STATE_NEW;
            $where['lock_state']  = 0;
        }else if($state == 'pay'){
            $where['order_state'] = $crowdfundingOrder::ORDER_STATE_PAY;
            $where['lock_state']  = 0;
        }else if($state == 'send'){
            $where['order_state'] = $crowdfundingOrder::ORDER_STATE_SEND;
            $where['lock_state']  = 0;
        }else if($state == 'refund'){
            $where['order_state'] = $crowdfundingOrder::ORDER_STATE_SUCCESS;
            $where['lock_state']  = 0;
        }else{
            $where['lock_state'] = 1;
        }

        if($name){
            $where['goods_name'] = array('like','%'.$name.'%');
            $this->assign('search_name',$name);
        }

        $where['member_id'] = $this->user['member_id'];
        $where['delete_state'] = self::DELETE_STATE0;

        $count = Db::name('crowdfunding_order')->alias('a')->distinct(true)->field('a.order_id')->join('crowdfunding_order_goods  b ',' a.order_id = b.order_id ')->where($where)->order('a.order_id desc')->select();
        $list = Db::name('crowdfunding_order')->alias('a')->distinct(true)->field('*')->join('crowdfunding_order_goods b ','a.order_id = b.order_id')->where($where)->order('a.order_id desc')->paginate(3,count($count),['query' => array('state'=>$state,'name'=>$name),]);
        $orderList = array();
        foreach ($list as $order){
            if (isset($order['order_state']))  $order['state_desc'] = $crowdfundingOrder->crowdfundingorderState($order);
            if (isset($order['payment_code'])) {
                $order['payment_name'] = orderPaymentName($order['payment_code']);
            }
            $orderList[$order['order_id']] = $order;
        }
        $page = $list->render();
        $orderGoodsList = array();
        $goods_ids = array(); //存储商品ID
        if(!empty(array_keys($orderList))){
            //取商品列表
            $orderGoodsList = $crowdfundingOrder->getOrderGoodsList(array('order_id'=>array('in',array_keys($orderList))));
        }

        if (!empty($orderGoodsList)) {
            foreach ($orderGoodsList as $value) {
                $orderList[$value['order_id']]['extend_order_goods'][] = $value;
                $goods_ids[] = $value['goods_id'];
                $orderList[$value['order_id']]['goods_state'] = $value['goods_id'];  //该订单的商品状态
            }
        } else {
            foreach ($orderGoodsList as $value) {
                $orderList[$value['order_id']]['extend_order_goods'] = array();
            }
        }
        $states = array();
        if($goods_ids){
            $crowdfundingGoods = new CrowdfundingGoods();
            $goods = $crowdfundingGoods->getAllCrowdfundingGoods(array('id'=>array('IN',$goods_ids)));


            if($goods){
                $status = $crowdfundingGoods::$goodsStatus;
                foreach($goods as $k=>$v){
                    $states[$v['id']] = $status[$v['state']];
                }
            }
        }

        //页面中显示那些操作
        foreach ($orderList as $key => $orderInfo) {
            //商品状态
            $orderInfo['goods_state'] = isset($states[$orderInfo['goods_state']])?$states[$orderInfo['goods_state']]:'';
            //显示取消订单
            $orderInfo['if_cancel'] 	= $crowdfundingOrder->getOrderOperateState('store_cancel',$orderInfo);
            //显示申请退款
            $orderInfo['if_refund'] 	= $crowdfundingOrder->getOrderOperateState('refund_cancel',$orderInfo);
            //显示支付
            $orderInfo['if_payment'] 	= $crowdfundingOrder->getOrderOperateState('payment',$orderInfo);
            //显示收货
            $orderInfo['if_receive'] 	= $crowdfundingOrder->getOrderOperateState('receive',$orderInfo);
            //显示删除
            $orderInfo['if_delete'] 	= $crowdfundingOrder->getOrderOperateState('delete',$orderInfo);
            //显示物流跟踪
            $orderInfo['if_deliver'] 	= $crowdfundingOrder->getOrderOperateState('deliver',$orderInfo);
            $orderInfo['goods_count'] = count($orderInfo['extend_order_goods']);
            $orderList[$key] = $orderInfo;
        }
        //未付款订单
        $countOne 		= $crowdfundingOrder->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$crowdfundingOrder::ORDER_STATE_NEW,'lock_state'=>0,'delete_state'=>self::DELETE_STATE0],'order_id');
        //待发货
        $countTwo 		= $crowdfundingOrder->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$crowdfundingOrder::ORDER_STATE_PAY,'lock_state'=>0,'delete_state'=>self::DELETE_STATE0],'order_id');
        //待收货
        $countThree 	= $crowdfundingOrder->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$crowdfundingOrder::ORDER_STATE_SEND,'lock_state'=>0,'delete_state'=>self::DELETE_STATE0],'order_id');
        //发动退款
        $countFour 		= $crowdfundingOrder->getCount(['member_id'=>$this->user['member_id'],'lock_state'=>1,'delete_state'=>self::DELETE_STATE0],'order_id');
        $countFive 		= $crowdfundingOrder->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$crowdfundingOrder::ORDER_STATE_SUCCESS,'lock_state'=>0,'delete_state'=>self::DELETE_STATE0],'order_id');
        $array = ['new'=>$countOne,'pay'=>$countTwo,'send'=>$countThree,'refund'=>$countFour+$countFive];

        $this->assign('array',$array);
        $this->assign('list',$orderList);
        $page = $this->dealPage($page);
        $this->assign('page',$page);
        $this->assign('state',$state);
        return $this->fetch();

    }


    /**
     *
     * 订单详情
     */
    public function detail()
    {
        $orderId = $this->request->param('order_id','','intval');
        $order = new CrowdOrderLogic();
        $result = $order->inquire($orderId);

        $this->assign('order_info',$result);

        return $this->fetch();
    }


    /**
     * 支付成功
     */
    function payok()
    {
        $pay_sn = $this->request->param('pay_sn');
        if (empty($pay_sn)) $this->error('该订单不存在',url('shop/crowdfunding_order/lists'));

        //查询支付单信息
        $model_order= new CrowdfundingOrders();
        $pay_info = $model_order->getCrowdfundingOrder(array('pay_sn'=>$pay_sn,'member_id'=>$this->user['member_id']));
        if(empty($pay_info))$this->error('该订单不存在',url('shop/crowdfunding_order/lists'));
        $this->assign('pay_info',$pay_info);
        $this->assign('pay_amount',$pay_info['order_amount']);
        return $this->fetch();
    }


    /**
     * 取消订单
     */
    public function cancel() {
        $orderModel = new CrowdfundingOrders();
        //取消页面参数
        $orderId = $this->request->param('orderId');
        $orderInfo = $orderModel->where(array('order_id'=>$orderId))->find();
        if(is_object($orderInfo)){
            $orderInfo = $orderInfo->toArray();
        }

        //取消页面提交表单
        $type = $this->request->param('type');
        if($type){
//            $orderInfo = $orderModel->where(array('order_id'=>$orderId))->find();
            //取消订单操作
            $ifAllow = $orderModel->getOrderOperateState('store_cancel',$orderInfo);
            if (!$ifAllow) $this->error('取消失败','',1);
            $logicOrder = new CrowdOrderLogic();
            $result = $logicOrder->orderCancel($orderInfo,'');
            if($result) $this->success('取消成功','',1);
            else $this->error('取消失败','',1);
        }
        $this->view->engine->layout(false);
        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }


    /**
     * 确认收货
     */
    public function receive()
    {
        $orderId = $this->request->param('orderId','','intval');
        if(empty($orderId))	$this->error('参数错误');
        $model_order = new CrowdfundingOrders();
        $condition = array();
        $condition['order_id'] = $orderId;
        $condition['member_id'] = $this->user['member_id'];
        $order_info	= $model_order->getCrowdfundingOrder($condition);
        if(is_object($order_info)){
            $order_info = $order_info->toArray();
        }
        if(empty($order_info)) $this->error('订单信息不存在',url('shop/crowdfunding_order/lists'));

        $form_submit = $this->request->param('form_submit','','string');

        if(!empty($form_submit) && $form_submit=='ok')
        {

            $logic_order = new CrowdOrderLogic();
            $if_allow = $model_order->getOrderOperateState('receive',$order_info);
            if (!$if_allow)  $this->error('操作失败','');

            $result = $logic_order->orderReceive($order_info,'buyer',$this->user['member_id']);
            if(!$result['state']){
                $this->error('确认收货失败',url('shop/crowdfunding_order/lists'));
            }else{
                $this->success('操作成功',url('shop/crowdfunding_order/lists'), 1);
            }
            exit;
        }
        $this->assign('order_info', $order_info);
        $this->view->engine->layout(false);
        return $this->fetch();
    }


    /*
 * 删除订单
 */
/*    public function delete(){
        $orderModel = new CrowdfundingOrders();
        $orderId = $this->request->param('orderId','','intval');
        $condition['order_id'] = $orderId;
        $condition['member_id'] = $this->user['member_id'];
        $orderInfo = $orderModel->getCrowdfundingOrder($condition);
        $states = $orderModel::$order_state_arr;
        $orderInfo['state_desc'] = isset($states[$orderInfo['order_state']])?$states[$orderInfo['order_state']]:'';
        $type = $this->request->param('type');
        if($type){
            if (!is_numeric($orderId)) $this->error('删除失败','',1);
            if ( $orderModel->update( array('delete_state'=>1),array('order_id' => $orderId)) )
                $this->success('删除成功','',1);
            else $this->error('删除失败','',1);
        }
        $this->view->engine->layout(false);
        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }*/




}
