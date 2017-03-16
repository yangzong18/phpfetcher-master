<?php
namespace app\seller\controller;
use app\admin\model\ManagerRole;
use app\common\controller\Auth;
use app\common\model\CrowdfundingGoods;
use app\common\model\CrowdfundingOrder;
use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\Order;
use app\common\model\OrderGoods;
use app\common\model\LogsDecorationOrder as LogsOrder;
use app\common\model\LogsDecorationGoods as LogGoods;

class Index extends Auth {
    
    public function index()
    {
		$modelMember = new Member();
		$userInfo = $modelMember->getMemberInfoByID($this->user['member_id']);
		$modelRole = new ManagerRole();
		$where['role_id'] = ['in',$this->user['role']];
		$role = $modelRole->getOneInfo($where);
		$userInfo['role_name'] = $role['name'];
		$this->assign('user',$this->user);
		$this->assign('user_info',$userInfo);

		//订单统计
		$orderModel = new Order();
		$orderOne 	= $orderModel->getCount(['order_state'=>$orderModel::ORDER_STATE_NEW,'lock_state'=>0]);
		$orderTwo 	= $orderModel->getCount(['order_state'=>$orderModel::ORDER_STATE_PAY,'lock_state'=>0]);
		$orderThree	= $orderModel->getCount(['refund_state'=>['in',[1,2]]]);

		$crowModel = new CrowdfundingOrder();
		$crowOne 	= $crowModel->getCount(['order_state'=>$crowModel::ORDER_STATE_NEW,'lock_state'=>0]);
		$crowTwo 	= $crowModel->getCount(['order_state'=>$crowModel::ORDER_STATE_PAY,'lock_state'=>0]);
		$crowThree	= $crowModel->getCount(['order_state'=>$crowModel::ORDER_STATE_SUCCESS]);
		$crowOrder = ['new'=>$crowOne,'pay'=>$crowTwo,'refund'=>$crowThree];
		$this->assign('crowOrder',$crowOrder);

		$logsModel 	= new LogsOrder();
		$logsOne 	= $logsModel->getCount(['speed_status'=>$logsModel::LOGS_SPEED_PAY,'delete_status'=>0]);
		$logsTwo 	= $logsModel->getCount(['speed_status'=>$logsModel::LOGS_SPEED_DESIGNED,'delete_status'=>0]);
		$logsThree	= $logsModel->getCount(['speed_status'=>$logsModel::LOGS_SPEED_ACCEPTED]);

		$logsOrder = ['new'=>$logsOne,'pay'=>$logsTwo,'refund'=>$logsThree];
		$this->assign('logsOrder',$logsOrder);


		//商品统计
		$goodsModel = new Goods();
		$goodsOne 	= $goodsModel->getCount(['goods_verify'=>$goodsModel::GOODS_STATE_ONE,'is_delete'=>0]);
		$goodsTwo 	= $goodsModel->getCount(['goods_verify'=>$goodsModel::GOODS_STATE_TWO,'is_delete'=>0]);
		$goodsThree = $goodsModel->getCount(['goods_verify'=>$goodsModel::GOODS_STATE_THREE,'is_delete'=>0]);
		$goods = ['verify_is'=>$goodsTwo,'verify_no'=>$goodsOne,'verify_now'=>$goodsThree];
		$this->assign('goods',$goods);

		$crowGoods = new CrowdfundingGoods();
		$crowGoodsOne 	= $crowGoods->getCount(['verify'=>$crowGoods::VERIFY1,'is_delete'=>0]);
		$crowGoodsTwo 	= $crowGoods->getCount(['verify'=>$crowGoods::VERIFY0,'is_delete'=>0]);
		$crowGoodsThree = $crowGoods->getCount(['verify'=>$crowGoods::VERIFY10,'is_delete'=>0]);
		$crowGoods = ['verify_is'=>$crowGoodsOne,'verify_no'=>$crowGoodsTwo,'verify_now'=>$crowGoodsThree];
		$this->assign('crowGoods',$crowGoods);

		$logsGoods 	= new LogGoods();
		$logsGoodsOne 	= $logsGoods->getCount(['goods_verify'=>$logsGoods::GOODS_STATE_TWO,'is_delete'=>0]);
		$logsGoodsTwo 	= $logsGoods->getCount(['goods_verify'=>$logsGoods::GOODS_STATE_ONE,'is_delete'=>0]);
		$logsGoodsThree	= $logsGoods->getCount(['goods_verify'=>$logsGoods::GOODS_STATE_THREE,'is_delete'=>0]);
		$logsGoods = ['verify_is'=>$logsGoodsOne,'verify_no'=>$logsGoodsTwo,'verify_now'=>$logsGoodsThree];
		$this->assign('logsGoods',$logsGoods);

		$yesTime = [strtotime(date("Y-m-d",strtotime("-1 day"))),strtotime(date('Y-m-d'))];
		$monTime = [strtotime(date("Y-m-d",strtotime("last month"))),strtotime(date('Y-m-d'))];
		$whereYes = [
			'order_state'	=> ['>=',20],
			'lock_state'	=> 0,
			'order_type'	=> 1,
			'add_time'		=> ['between',$yesTime]
		];
		$whereMon = [
			'order_state'	=> ['>=',20],
			'lock_state'	=> 0,
			'order_type'	=> 1,
			'add_time'		=> ['between',$monTime]
		];
		$orderAmountOne = $orderModel->where($whereYes)->sum('order_amount');
		$orderNumOne = $orderModel->where($whereYes)->count('order_id');
		$orderAmountTwo = $orderModel->where($whereMon)->sum('order_amount');
		$orderNumTwo = $orderModel->where($whereMon)->count('order_id');
		$order = ['new'=>$orderOne,'pay'=>$orderTwo,'refund'=>$orderThree,'yes_amount'=>$orderAmountOne,'yes_num'=>$orderNumOne,'mon_amount'=>$orderAmountTwo,'mon_num'=>$orderNumTwo];
		$orderId = $orderModel->getOrderList($whereMon,'order_id');
		if(count($orderId) > 0){
			$orderArray = array();
			foreach ($orderId as $orderOne){
				$orderArray[$orderOne['order_id']] = $orderOne;
			}
			$orderGoods = new OrderGoods();
			$orderGoodsList = $orderGoods->where(['order_id' =>['in',array_keys($orderArray)]])->field('sum(goods_num) tp_sum,goods_id,goods_name,sku_name')->order('tp_sum desc,order_id')->group('goods_sku')->limit('10')->select();
		}else{
			$orderGoodsList = [];
		}
		$this->assign('orderGoodsList',$orderGoodsList);
		$this->assign('order',$order);
        return $this->fetch();
    }
}
