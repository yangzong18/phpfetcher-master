<?php
/**
 * Created by 长虹.
 * User: 李武
 * Date: 2017/1/5
 * Time: 10:56
 * Desc: 手机端订单管理接口
 */
namespace app\mobile\controller;
use app\common\logic\RealOrderLogic;
use app\common\model\Order as OrderModel;
use app\common\model\RefundReturn;
use think\Exception;
use think\Validate;
use think\Db;

class Order extends MobileMember
{
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
     * 订单列表接口
     */
    public function orderList()
    {
        $orderModel = new OrderModel();
        $style = $this->request->post('style','all','trim');
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
        $count = Db::name('order')->distinct(true)->field('order_id')->where($where)->order('order_id desc')->select();
        $list = Db::name('order')->distinct(true)->field('*')->where($where)->order('order_id desc')->paginate(10,count($count),['query' => array('style'=>$style),]);
        $page = array('currentPage'=>$list->currentPage(),'lastPage'=>$list->lastPage(),'total'=>$list->total());
        $orderList = array();
        foreach ($list as $order){
            if (isset($order['order_state']))  $order['state_desc'] = $orderModel->orderState($order);
            if (isset($order['payment_code'])) {
                $order['payment_name'] = orderPaymentName($order['payment_code']);
            }
            $orderList[$order['order_id']] = $order;
        }

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

        $data = array('list'=>array_values($orderList),'style'=>$style,'page'=>$page);
        $this->returnJson($data);
    }

    /*
	 * 订单详情接口
	 * */
    public function orderDetail()
    {
        $orderModel = new OrderModel();
        $orderSn = $this->request->post('order_sn', '' ,'trim');
        if( empty( $orderSn) ){
            $this->returnJson('',1,'参数错误');
        }else{
            $condition['order_sn'] = $orderSn;
            $condition['member_id'] = $this->user['member_id'];
            $orderInfo = $orderModel->getOrderInfo($condition,array('order_goods','order_common'));
            if(empty($orderInfo)){
                $this->returnJson('',1,'没有这样的订单');
            }else{
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

                $data = array('order_info'=>$orderInfo,'orderState'=>OrderModel::$order_state_arr);
                $this->returnJson($data);
            }
        }
    }


    /*
	 * 订单确认收货接口
	 * */
    public function orderReceive()
    {
        $orderSn = $this->request->post('order_sn','','trim');
        if(empty($orderSn)){
            $this->returnJson('',1,'参数错误');
        }else{
            $model_order = new \app\common\model\Order();
            $condition = array();
            $condition['order_sn'] = $orderSn;
            $condition['member_id'] = $this->user['member_id'];
            $order_info	= $model_order->getOrderInfo($condition);
            if(empty($order_info)){
                $this->returnJson('',1,'不存在这样的订单');
            }else{
                $logic_order = new RealOrderLogic();
                $if_allow = $model_order->getOrderOperateState('receive',$order_info);
                if (!$if_allow) {
                    $this->returnJson('',1,'操作失败');
                }else{
                    $result = $logic_order->changeOrderStateReceive($order_info,'buyer',$this->user['member_id']);
                    if(!$result['state']){
                        $this->returnJson('',1,'确认收货失败');
                    }else{
                        $this->returnJson();
                    }
                }
            }
        }
    }


    /*
	 * 未付款取消订单接口
	 */
    public function orderCancel()
    {
        $orderModel = new OrderModel();
        $orderSn = $this->request->post('order_sn','','trim');
        if(empty($orderSn)){
            $this->returnJson('',1,'参数错误');
        }else{
            $condition['order_sn'] = $orderSn;
            $condition['member_id'] = $this->user['member_id'];
            $orderInfo = $orderModel->getOrderInfo($condition);
            if(empty($orderInfo)){
                $this->returnJson('',1,'没有这样的订单');
            }else{
                //取消订单操作
                $ifAllow = $orderModel->getOrderOperateState('store_cancel',$orderInfo);
                if (!$ifAllow){
                    $this->returnJson('',1,'取消失败');
                }else{
                    $logicOrder = new RealOrderLogic();
                    $result = $logicOrder->changeOrderStateCancel($orderInfo,'buyer',$this->user['member_name'],'手动关闭订单');
                    if($result){
                        $this->returnJson();
                    }else{
                        $this->returnJson('',1,'取消失败');
                    }
                }
            }
        }
    }


    /*
     * 退款表单信息接口
     * */
    public function orderRefund()
    {
        $orderModel = new OrderModel();
        $orderSn = $this->request->post('order_sn','','trim');
        if(empty($orderSn)){
            $this->returnJson('',1,'参数错误');
        }else{
            $memberId = $this->user['member_id'];
            $orderInfo = $orderModel->getOrderInfo(array('order_sn'=>$orderSn,'member_id'=>$memberId),array('order_goods'));
            if( empty($orderInfo) ) $this->returnJson('',1,'没有这样的订单');
            //退款表单操作判断
            $ifAllow = $orderModel->getOrderOperateState('refund_cancel',$orderInfo);
            if (!$ifAllow){
                $this->returnJson('',1,'操作失败');
            } else{
                $refundInfo = $orderModel->getRefundInfo(array('order_sn'=>$orderSn,'member_id'=>$memberId));
                if(!empty($refundInfo)){
                    $this->returnJson('',1,'此订单已经申请过退款');
                }else{
                    $orderInfo['payment_name'] = $orderModel->orderPaymentName($orderInfo['payment_code']);
                    $data = array();
                    $data['order_info'] = $orderInfo;
                    $data['refund_info'] = $refundInfo;
                    $this->returnJson($data);
                }
            }
        }
    }

    /*
     * 退款处理接口
     * */
    public function orderRefundCl()
    {
        $orderModel = new OrderModel();
        $orderSn = $this->request->post('order_sn','','trim');
        if( empty($orderSn) ) {
            $this->returnJson('',1,'参数错误');
        } else {
            $memberId = $this->user['member_id'];
            $orderInfo = $orderModel->getOrderInfo(array('order_sn'=>$orderSn,'member_id'=>$memberId),array('order_goods'));
            if( empty($orderInfo) ) $this->returnJson('',1,'没有这样的订单');
            //退款表单操作判断
            $ifAllow = $orderModel->getOrderOperateState('refund_cancel',$orderInfo);
            if (!$ifAllow){
                $this->returnJson('',1,'操作失败');
            }else{
                $refundInfo = $orderModel->getRefundInfo(array('order_sn'=>$orderSn,'member_id'=>$memberId));
                if(!empty($refundInfo)){
                    $this->returnJson('',1,'此订单已经申请过退款');
                }else{
                    $data = array();
                    $reasonId = $this->request->param('reason_id',0,'intval');
                    if($reasonId > 0){
                        $data['reason_info'] = self::$reason_arr[$reasonId];
                    }else{
                        $data['reason_info'] = $this->request->param('reason_info','','trim');
                    }

                    $data['refund_amount'] = $this->request->param('refund_amount','','trim');
                    if($data['refund_amount'] <= 0){
                        $this->returnJson('',1,'退款金额必须大于零');
                    }else{
                        $buyerImg = $this->request->param('buyer_img/s','','trim');
                        $buyerImg_arr = array();
                        if(!empty($buyerImg)) {
                            $buyerImg_arr = json_decode($buyerImg,true);
                            if(is_array($buyerImg_arr)){
                                $cimg = count($buyerImg_arr);
                                //罗婷修改
                                if($cimg > 5)  $this->returnJson('',1,'图片个数不能多于五个');
                            }
                        }
                        $data['buyer_img'] = json_encode($buyerImg_arr);

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
                            $this->returnJson('',1,$validate->getError());
                        }else{
                            $modelRefund = new RefundReturn();
                            $result = $modelRefund->addRefundReturn($data,$orderInfo);
                            if($result){
                                $this->returnJson();
                            }else{
                                $this->returnJson('',1,'申请失败');
                            }
                        }
                    }
                }
            }
        }

    }


    /**
     * 取消退款申请流程
     */
    public function RefundCancel()
    {
        $orderSn = $this->request->post('order_sn','','trim');
        if(empty($orderSn)){
            $this->returnJson('',1,'参数错误');
        }else{
            $modelOrder = new OrderModel();
            $orderInfo = $modelOrder->getOrderInfo(array('order_sn'=>$orderSn),array('order_goods','order_common'));
            $refundInfo = $modelOrder->getRefundInfo(array('order_sn'=>$orderSn));
            if($orderInfo['refund_state'] != 1){
                $this->returnJson('',1,'此订单已被处理');
            }else{
                if(empty($refundInfo)){
                    $this->returnJson('',1,'此订单还没有申请退款');
                }else{

					try{
						Db::startTrans();
						$data 		= ['refund_state'=>3,'lock_state'=>0];
						$where 		= ['order_sn'=>$orderSn,'refund_state'=>1];
						$rs = $modelOrder->editOrder($data,$where);
						if($rs){
							Db::commit();
							$this->returnJson();
						}else{
							throw new Exception('操作失败');
						}

					}catch (Exception $e){
						Db::rollback();
						$this->returnJson('',1,'处理失败');
					}
                }
            }
        }
    }

    /**
     * 退款退货售后详情接口
     */
    public function orderAfter()
    {
        $orderModel = new OrderModel();
        $orderSn = $this->request->post('order_sn','','trim');
        if(empty($orderSn)){
            $this->returnJson('',1,'参数错误');
        }else{
            $condition['order_sn'] = $orderSn;
            $condition['member_id'] = $this->user['member_id'];
            $orderInfo = $orderModel->getOrderInfo($condition,array('order_goods','order_common'));
            if(empty($orderInfo)){
                $this->returnJson('',1,'没有这样的订单');
            }else{
                $refundInfo = $orderModel->getRefundInfo(array('order_sn'=>$orderSn));
                if(empty($refundInfo)){
                    $this->returnJson('',1,'此订单还没有申请退款');
                }else{
                    $refundInfo['payment_name'] = $orderModel->orderPaymentName($orderInfo['payment_code']);
                    $refundInfo['buyer_img'] = json_decode($refundInfo['buyer_img']);
                    $refundInfo['seller_img'] = json_decode($refundInfo['seller_img']);
                    $data = array('order_info'=>$orderInfo,'refund_info'=>$refundInfo);
                    $this->returnJson($data);
                }
            }
        }
    }


}
