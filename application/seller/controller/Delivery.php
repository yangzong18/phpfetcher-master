<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/3/1
 * Time: 11:09
 */
namespace app\seller\controller;
use app\common\controller\Auth;
use app\common\model\DeliveryInfo;
use app\common\model\Order as Orders;
use app\common\model\CrowdfundingOrder as Order;
use think\Validate;

class Delivery extends Auth{
	/**
	 * @return mixed
	 *
	 * 添加物流信息
	 */
	public function editDeliver(){
		$type = $this->request->param('type','0','intval');
		$orderId = $this->request->param('orderId','');
		$s = $this->request->param('s','0','intval');
		if($s == 1){//普通订单
			$modelorder = new Orders();
			$orderInfo = $modelorder->getOrderInfo(['order_id'=>$orderId],array('order_common'));
		}else{//众筹订单
			$modelorder = new Order();
			$orderInfo = $modelorder->getCrowdfundingOrderInfo(['order_id'=>$orderId],$field = '*',$order = 'order_id desc',$limit='', $extend = array('order_common'));
		}
		if($type == 1){
			$delivery = $this->request->param('delivery','','trim');
			$data = ['delivery'=>$delivery,'order_id'=>$orderId];
			if($delivery == $orderInfo['delivery']){
				$this->error('物流信息未做出修改');
			}
			$rule = [
				'delivery' => 'max:512',
				'order_id' => 'require'
			];
			$msg = [
				'delivery.max'     			=> '物流信息不能超过512个汉字',
				'order_id.require'			=> '订单编号不能为空',
			];
			$validate = new validate($rule,$msg);
			if (!$validate->check($data)) {
				$this->error($validate->getError());
			}
			if($s == 1){//普通订单
				$modelOrder = new Orders();
				$rs = $modelOrder->editOrder(['delivery'=>$delivery],['order_id'=>$orderId,'order_state'=>30]);
			}else{//众筹订单
				$modelOrder = new Order();
				$rs = $modelOrder->editOrder(['delivery'=>$delivery],['order_id'=>$orderId,'order_state'=>30]);
			}
			if($rs){
				$this->success('更新成功','',1);
			}else{
				$this->success('更新失败','',1);
			}
		}else{
			if(empty($orderId)){
				$this->error('参数错误');
			}
			$this->view->engine->layout(false);
			$this->assign('s',$s);
			$this->assign('order_info',$orderInfo);
			return $this->fetch();
		}
	}
}

