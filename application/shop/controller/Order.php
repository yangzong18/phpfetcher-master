<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/19
 * Time: 10:47
 *
 * 木住生活馆订单列表
 */
namespace app\shop\controller;
use app\common\controller\Member;
use app\common\logic\RealOrderLogic;
use app\common\model\Order as OrderModel;
use app\common\model\RefundReturn;
use think\Db;
use think\Exception;
use think\Validate;

class Order extends Member {

	const DELETE_STATE0 = 0;//正常
	const DELETE_STATE1 = 1;//回收站
	const DELETE_STATE2 = 2;//永久删除

	//状态常量
	const REASON_1 		= 1;
	const REASON_2		= 2;
	const REASON_3		= 3;

	//订单状态描述
	public static  $reason_arr=array(
		self::REASON_1 =>'质量问题',
		self::REASON_2 =>'实际商品与本网站产品描述不符',
		self::REASON_3 =>'运输过程中造成严重损坏',
	);
	/**
	 * @return mixed   array
	 *
	 * 通过登录信息的id查找用户的订单信息
	 */
	public function orderList(){
		$orderModel = new OrderModel();
		$style = $this->request->param('style','all','trim');
		$goodsName = $this->request->param('goods_name','','trim');
		if($style == 'all'){

		}else if($style == 'new'){
			$where['order_state'] = $orderModel::ORDER_STATE_NEW;
			$where['refund_state'] = ['in',[0,3]];
		}else if($style == 'pay'){
			$where['order_state'] = $orderModel::ORDER_STATE_PAY;
			$where['refund_state'] = ['in',[0,3]];
		}else if($style == 'send'){
			$where['order_state'] = $orderModel::ORDER_STATE_SEND;
			$where['refund_state'] = ['in',[0,3]];
		}elseif($style == 'delivery'){
			$where['order_state'] = $orderModel::ORDER_STATE_SUCCESS;
			$where['refund_state'] = ['in',[0,3]];
		}else{
			$where['refund_state'] = ['in',[1,2]];

		}
		$where['member_id'] = $this->user['member_id'];
		$where['delete_state'] = self::DELETE_STATE0;
		if(!empty($goodsName)){
			$this->assign('goods_name',$goodsName);
			$condition['goods_name'] = array('like', '%'.$goodsName.'%');
			$countOne = Db::name('order_goods')->distinct(true)->field('order_id')->where($condition)->order('order_id desc')->select();
			if (!empty($countOne)) {
				$orderArray = array();
				foreach ($countOne as $value) {
					$orderArray[] = $value['order_id'];
				}
				$where['order_id'] = ['in',$orderArray];
			}else{
				$where['order_id'] = ['in',0];
			}
		}
		$count = Db::name('order')->distinct(true)->field('order_id')->where($where)->order('order_id desc')->select();
		$list = Db::name('order')->distinct(true)->field('*')->where($where)->order('order_id desc')->paginate(10,count($count),['query' =>$this->request->param(),]);
		$orderList = array();
		foreach ($list as $order){
			if (isset($order['order_state']))  $order['state_desc'] = $orderModel->orderState($order);
			if (isset($order['payment_code'])) {
				$order['payment_name'] = orderPaymentName($order['payment_code']);
			}
			$orderList[$order['order_id']] = $order;
		}
		$page = $this->dealPage($list->render());
		$orderGoodsList = array();
		if(!empty($orderList)) {//取商品列表
			$orderGoodsList = $orderModel->getOrderGoodsList(array('order_id' => array('in', array_keys($orderList))));
		}
		if (!empty($orderGoodsList)) {
			foreach ($orderGoodsList as $value) {
				$value['sku_name'] = json_decode($value['sku_name'],true);
				$orderList[$value['order_id']]['extend_order_goods'][] = $value;
			}
		} else {
			foreach ($orderGoodsList as $value) {
				$orderList[$value['order_id']]['extend_order_goods'] = array();
			}
		}
		//页面中显示那些操作
		foreach ($orderList as $key => $orderInfo) {
			//显示取消订单
			$orderInfo['if_cancel'] 	= $orderModel->getOrderOperateState('store_cancel',$orderInfo);
			//显示申请退款
			$orderInfo['if_refund'] 	= $orderModel->getOrderOperateState('refund_cancel',$orderInfo);
			//显示支付
			$orderInfo['if_payment'] 	= $orderModel->getOrderOperateState('payment',$orderInfo);
			//显示收货
			$orderInfo['if_receive'] 	= $orderModel->getOrderOperateState('receive',$orderInfo);
			//显示删除
			$orderInfo['if_delete'] 	= $orderModel->getOrderOperateState('delete',$orderInfo);
			//显示物流跟踪
			$orderInfo['if_deliver'] 	= $orderModel->getOrderOperateState('deliver',$orderInfo);
			$orderInfo['goods_count'] = count($orderInfo['extend_order_goods']);
			$orderList[$key] = $orderInfo;
		}
		//未付款订单
		$countOne 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$orderModel::ORDER_STATE_NEW,'refund_state'=>['in',[0,3]],'delete_state'=>self::DELETE_STATE0],'order_id');
		//待发货
		$countTwo 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$orderModel::ORDER_STATE_PAY,'refund_state'=>['in',[0,3]],'delete_state'=>self::DELETE_STATE0],'order_id');
		//待收货
		$countThree 	= $orderModel->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$orderModel::ORDER_STATE_SEND,'refund_state'=>['in',[0,3]],'delete_state'=>self::DELETE_STATE0],'order_id');
		//收货
		$countFive 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$orderModel::ORDER_STATE_SUCCESS,'refund_state'=>['in',[0,3]],'delete_state'=>self::DELETE_STATE0],'order_id');
		//发动退款
		$countFour 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'refund_state'=>['in',[1,2]],'delete_state'=>self::DELETE_STATE0],'order_id');
		$array = ['new'=>$countOne,'pay'=>$countTwo,'send'=>$countThree,'refund'=>$countFour,'delivery'=>$countFive];
		$this->assign('array',$array);
		$this->assign('list',$orderList);
		$this->assign('page',$page);
		$this->assign('style',$style);
		return $this->fetch();
	}
	/*
	 * 订单详情
	 * */
	public function orderDetail()
	{
		$orderModel = new OrderModel();
		$orderId = $this->request->param('order_id','','intval');
		if($orderId<=0) $this->error('参数错误');
		$condition['order_id'] = $orderId;
		$condition['member_id'] = $this->user['member_id'];
		$orderInfo = $orderModel->getOrderInfo($condition,array('order_goods','order_common'));
		if(empty($orderInfo)) $this->error('没有这样的订单');
		
		//显示系统自动取消订单日期
		if ($orderInfo['order_state'] == OrderModel::ORDER_STATE_NEW) {
			$orderInfo['order_cancel_day'] = $orderInfo['add_time'] + ORDER_AUTO_CANCEL_DAY * 24 * 3600;
		}

		//如果订单已取消，取得取消原因、时间，操作人
		if ($orderInfo['order_state'] == OrderModel::ORDER_STATE_CANCEL) {
			$orderInfo['close_info'] = $orderModel->getOrderLogInfo(array('order_id'=>$orderInfo['order_id']),'log_id desc');
		}

		//显示系统自动取消订单日期
		if ($orderInfo['order_state'] == OrderModel::ORDER_STATE_SEND) {
			$orderInfo['order_confirm_day'] = $orderInfo['delay_time'] + ORDER_AUTO_RECEIVE_DAY * 24 * 3600;
		}

		//支付方式
		if($orderInfo['payment_code']!='offline' && $orderInfo['order_state']==OrderModel::ORDER_STATE_PAY){
			$orderInfo['orderPaymentName'] =  orderPaymentName($orderInfo['payment_code']);
		}

		$this->assign('order_info',$orderInfo);
		$this->assign('orderState',OrderModel::$order_state_arr);
		return $this->fetch();
	}

	/*
	 * 取消订单
	 */
	public function orderCancel() {
		$orderModel = new OrderModel();
		//取消页面参数
		$orderId = $this->request->param('orderId');
		$condition['order_id'] = $orderId;
		$condition['member_id'] = $this->user['member_id'];
		$orderInfo = $orderModel->getOrderInfo($condition);
		if(empty($orderInfo)) $this->error('此订单不存在');
		//取消页面提交表单
		$type = $this->request->param('type');
		if($type){
            //2017.2.15 杨萌 后台取消，前端未刷新界面做判断
            if(!$orderInfo['order_state']) $this->error('订单已取消，请刷新页面','',1);
			//取消订单操作
			$ifAllow = $orderModel->getOrderOperateState('store_cancel',$orderInfo);
			if (!$ifAllow) $this->error('取消失败','',1);
			$logicOrder = new RealOrderLogic();
			$result = $logicOrder->changeOrderStateCancel($orderInfo,'buyer',$this->user['member_name'],'手动关闭订单');
			if($result['state']) {
				$this->success('取消成功','',1);
			}else{
			 $this->error($result['msg'],'',1);
			}

		}
		$this->view->engine->layout(false);
		$this->assign('order_info',$orderInfo);
		return $this->fetch();
	}

	/*
	 * 删除订单
	 */
	public function orderDelete(){
		die('暂不使用');
		$orderModel = new OrderModel();
		$orderId = $this->request->param('orderId','','intval');
		$condition['order_id'] = $orderId;
		$condition['member_id'] = $this->user['member_id'];
		$orderInfo = $orderModel->getOrderInfo($condition);
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
	}

	/**
	 * @return mixed
	 * 添加全部退款即取消订单  备份
	 */
	public function orderRefund(){
		$modelOrder = new OrderModel();
		$orderId = $this->request->param('order_id','','intval');
		if(empty($orderId))	$this->error('参数错误');
		$memberId = $this->user['member_id'];
		$orderInfo = $modelOrder->getOrderInfo(array('order_id'=>$orderId,'member_id'=>$memberId),array('order_goods'));
		if(empty($orderInfo)){
			$this->error('此订单不存在');
		}
		$refundInfo = $modelOrder->getRefundInfo(array('order_id'=>$orderId,'member_id'=>$memberId));
		if(!empty($refundInfo)) $this->error('此订单已经申请过退款');
		$type = $this->request->param('type','','trim');
		if($type){
			$data = array();
			$reasonId = $this->request->param('reason_id',0,'intval');
			if($reasonId > 0){
				$data['reason_info'] = self::$reason_arr[$reasonId];
			}else{
				$data['reason_info'] = $this->request->param('reason_info','','trim');
			}
			$data['refund_amount'] = $this->request->param('refund_amount','','trim');
			//默认传过来的是数组 没有值的话是null ---wu.li修改
			$buyerImg = $this->request->param('buyer_img/a');
			$buyerImg_arr = array();
			if(is_array($buyerImg)){
				$cimg=count($buyerImg);
                //2017.2.14 图片个数判断错误，最多为5
				if($cimg < 6) {
					if($cimg>0) $buyerImg_arr = $buyerImg;
				}else{
					$this->error('图片个数不能多于五个');
				}
			}

			$data['buyer_img'] = json_encode($buyerImg_arr);
			if($data['refund_amount'] <= 0) $this->error('退款金额必须大于零');
			$rule = [
				'reason_info'  	=> 'require|max:200',
				'refund_amount' => 'require',
				'refund_amount' => ['regex'=>'/^([1-9][0-9]*|0)(\.\d{1,2})?$/i'],
			];
			$msg = [
				'reason_info.require' 		=> '退款原因不能为空',
				'reason_info.max'     		=> '退款原因最多不能超过200个字符',
				'refund_amount.require'   	=> '退款金额不能为空',
				'refund_amount.regex'  		=> '退款金额填写不正确',
			];
			$validate = new validate($rule,$msg);
			if (!$validate->check($data)) {
				$this->error($validate->getError());
			}
			$modelRefund = new RefundReturn();
			$result = $modelRefund->addRefundReturn($data,$orderInfo);
			if($result){
				$this->success('申请成功',$this->request->domain().'/shop/order/orderlist');
			}else{
				$this->error('申请失败');
			}
		}else{
			$orderInfo['payment_name'] = $modelOrder->orderPaymentName($orderInfo['payment_code']);
			$this->assign('order_info',$orderInfo);
			$this->assign('refund_info',$refundInfo);
			return $this->fetch();
		}
	}

	/**
	 * 取消退款申请流程
	 */
	public function RefundCancel(){
		$orderId = $this->request->param('orderId','','intval');
		if($orderId <= 0) $this->error('参数错误');
		$type = $this->request->param('type','','trim');
		$modelOrder = new OrderModel();
		$orderInfo = $modelOrder->getOrderInfo(array('order_id'=>$orderId),array('order_goods','order_common'));
		$refundInfo = $modelOrder->getRefundInfo(array('order_id'=>$orderId));
		if($type)//退款申请提交
		{
			try{
				Db::startTrans();
				$data 		= ['refund_state'=>3,'lock_state'=>0];
				$where 		= ['order_id'=>$orderId,'refund_state'=>1];
				$rs=$modelOrder->editOrder($data,$where);
				if($rs){
					Db::commit();
					$this->success('操作成功',$this->request->domain().'/shop/order/orderlist');
				}else{
					throw new Exception('操作失败');
				}

			}catch (Exception $e){
				Db::rollback();
				$this->error('操作失败',$this->request->domain().'/shop/order/orderlist');
			}
		}
		$this->view->engine->layout(false);
		$this->assign('order_info',$orderInfo);
		$this->assign('refund_info',$refundInfo);
		return $this->fetch();
	}

	/**
	 * 售后详情页面
	 */
	public function orderAfter(){
		$modelOrder = new OrderModel();
		$orderId = $this->request->param('order_id','','intval');
		if(empty($orderId)){
			$this->error('参数错误');
		}
		$condition['order_id'] = $orderId;
		$condition['member_id'] = $this->user['member_id'];
		$orderInfo = $modelOrder->getOrderInfo($condition,array('order_goods','order_common'));
		$refundInfo = $modelOrder->getRefundInfo(array('order_id'=>$orderId));
		if(empty($refundInfo)){
			$this->error('此订单还没有申请退款');
		}
		$refundInfo['payment_name'] = $modelOrder->orderPaymentName($orderInfo['payment_code']);
		$refundInfo['buyer_img'] = json_decode($refundInfo['buyer_img']);
		$refundInfo['seller_img'] = json_decode($refundInfo['seller_img']);
		$this->assign('order_info',$orderInfo);
		$this->assign('refund_info',$refundInfo);
		return $this->fetch();
	}


	/*
	 * 确认收货
	 * */
	public function orderReceive()
	{
		$orderId = $this->request->param('orderId','','intval');
		if(empty($orderId))	$this->error('参数错误');
		$model_order = new \app\common\model\Order();
		$condition = array();
		$condition['order_id'] = $orderId;
		$condition['member_id'] = $this->user['member_id'];
		$order_info	= $model_order->getOrderInfo($condition);
		if(empty($order_info)) $this->error('订单信息不存在',url('/shop/order/orderlist'));

		$form_submit = $this->request->param('form_submit','');
		if(!empty($form_submit) && $form_submit=='ok')
		{
			$logic_order = new RealOrderLogic();
			$if_allow = $model_order->getOrderOperateState('receive',$order_info);
			if (!$if_allow)  $this->error('操作失败','');

			$result = $logic_order->changeOrderStateReceive($order_info,'buyer',$this->user['member_id']);
			if(!$result['state']){
				$this->error('确认收货失败');
			}else{
				$this->success('操作成功');
			}
		}
		$this->assign('order_info', $order_info);
		$this->view->engine->layout(false);
		return $this->fetch();
	}
}
