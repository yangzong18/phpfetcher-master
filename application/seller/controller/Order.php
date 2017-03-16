<?php
/**
 * create by: PhpStorm
 * desc:商家中心订单模块
 * author:yangmeng
 */

namespace app\seller\controller;
use app\common\model\Order as Orders;
use app\seller\logic\Order as OrderLogic;
use app\common\logic\RealOrderLogic;
use app\common\controller\Auth;
use Excel\Excel;
use think\Db;
use think\Validate;

class Order extends Auth
{
    protected $modelOrder;
    protected $search = ['order_sn' => '订单编号', 'order_id' => '订单ID',];
    protected $orderState;

    public function __construct(){
        parent::__construct();
        $this->modelOrder = new Orders();
        $this->logicOrder = new OrderLogic();
        $this->orderState = Orders::$order_state_arr;
		$this->orderState['refund'] = '售后订单';
        $this->orderState['']='全部订单';
    }

    /*
     * 获取订单列表
     * */
    private function _getOrder()
    {
        //搜索条件
        $param = $this->request->param();
        $searchType = $this->request->param('searchType');
        $searchValue = $this->request->param('searchValue', '', 'trim');
        $orderState  = $this->request->param('orderState');

        $condition = array();
        if( $searchType != '' && $searchValue != ''){
            if($searchType=='order_sn'){
                $searchType="CAST(order_sn as char)";
            }elseif($searchType=='order_id'){
				$searchType="CAST(order_id as char)";
			}
            $condition[$searchType] = array('like', '%'.$searchValue.'%');
        }
        if($orderState != '' && $orderState != 'refund'){
            $condition['order_state'] = $orderState;
            $condition['lock_state'] = 0;
        }
		if($orderState == 'refund'){
			$condition['refund_state'] = ['in',[1,2]];
		}

        //如果传入时间
        if(!empty($param['start'])){
            $condition['add_time'] = array('gt',strtotime($param['start']));
        }

        if(!empty($param['end'])){
            $condition['add_time'] = array('lt',strtotime($param['end']));
        }
        if(!empty($param['start']) && !empty($param['end'])){
            $condition['add_time'] = array(array('gt',strtotime($param['start'])),array('lt',strtotime($param['end'])),'and');
        }

        //通过商品名称检索
        if ( isset( $param['goods_name'] ) && trim( $param['goods_name'] ) != '' ) {
            $orderId = Db::name('order_goods')->where( array( 'goods_name' => array( 'like', '%'.$param['goods_name'].'%' ) ) )->column('order_id');
			if(count($orderId) > 1){
				$condition['order_id'] = array( 'in', $orderId );
			}else{
				$condition['order_id'] = 0;
			}
        }


        //分页
        $orderPage = Db::name('order')->where($condition)->paginate(10,'',['query' => $this->request->param()]);
        $order = Db::name('order')->where($condition)->order(array("order_id" => "desc"))->paginate(10,'',['query' => $this->request->param()])->column('order_id');
        if(count($order)>0){
            $condition['order_id'] = array('in',$order);
        }

        $page = $orderPage->render();
        $orderList = $this->modelOrder->getOrderList($condition, '*', 'order_id desc', '',array('order_goods','order_common'));

        //页面中显示那些操作
        foreach ($orderList as $key => $order_info) {
            //显示取消订单
            $order_info['if_cancel'] = $this->modelOrder->getOrderOperateState('store_cancel',$order_info);
            //显示调整费用
            $order_info['if_modify_price'] = $this->modelOrder->getOrderOperateState('modify_price',$order_info);
            //显示发货
            $order_info['if_send'] = $this->modelOrder->getOrderOperateState('send',$order_info);
            //显示退款后操作
            $order_info['refundAfter'] = $this->modelOrder->getOrderOperateState('refundAfter',$order_info);
            //显示锁定中
            $order_info['if_lock'] = $this->modelOrder->getOrderOperateState('lock',$order_info);
            //显示物流跟踪
            $order_info['if_deliver'] = $this->modelOrder->getOrderOperateState('deliver',$order_info);

			//2017-2-28 杨鹏  添加物流信息
			$order_info['edit_deliver'] = $this->modelOrder->getOrderOperateState('edit_deliver',$order_info);
            $order_info['goods_count'] = count($order_info['extend_order_goods']);

            $order_info['order_yhj']=number_format($order_info['goods_real_amount'] - $order_info['order_amount'],2 ,  '.' ,  '');

            $orderList[$key] = $order_info;
        }

        $this->assign('param',$param);
        $this->assign('pageTotal',$orderPage->total());
        $this->assign('search', $this->search);
        $this->assign('searchType', $searchType);
        $this->assign('searchValue', $searchValue);
        $this->assign('searchOrderState',$orderState);
        $this->assign('orderState',$this->orderState);
        $this->assign('list',$orderList);
        $this->assign('page',$page);
    }

    /*
     * 订单列表
     *
     * */
    public function lists()
    {
        $this->_getOrder();
        return $this->fetch();
    }

    /*
     * 导出订单
     *
     * */
    public function outOrder(){
        $this->_getOrder();
        $this->assign('url_request',$this->request->query());
        return $this->fetch();
    }

    /*
     * 导出订单到Excel
     * */
    public function exportOrder()
    {
        $params = $this->request->query();
        if(empty($params))$this->error('请传递查询参数',url('Seller/order/lists'));
        $params_arr = array();
        parse_str($params,$params_arr);
        if(array_key_exists('page',$params_arr)) unset($params_arr['page']);
        if(empty($params_arr)) $this->error('请传递查询参数',url('Seller/order/lists'));

        //搜索条件
        $searchType = $this->request->param('searchType');
        $searchValue = $this->request->param('searchValue', '', 'trim');
        $orderState  = $this->request->param('orderState');
        $condition = array();
        if( $searchType != '' && $searchValue != ''){
            if($searchType=='order_sn') $searchType="CAST(order_sn as char)";
            $condition[$searchType] = array('like', '%'.$searchValue.'%');
        }

        //如果传入时间
        if(!empty($params_arr['start'])){
            $condition['add_time'] = array('gt',strtotime($params_arr['start']));
        }

        if(!empty($params_arr['end'])){
            $condition['add_time'] = array('lt',strtotime($params_arr['end']));
        }
        if(!empty($params_arr['start']) && !empty($params_arr['end'])){
            $condition['add_time'] = array(array('gt',strtotime($params_arr['start'])),array('lt',strtotime($params_arr['end'])),'and');
        }

        $condition['delete_state'] = 0;
        if($orderState != '') $condition['order_state'] = $orderState;
        $orderList = $this->modelOrder->getOrderList($condition, '*', 'order_id desc', '',array('order_goods','order_common'));

        //页面中显示那些操作
        foreach ($orderList as $key => $order_info)
        {
            //显示取消订单
            $order_info['if_cancel'] = $this->modelOrder->getOrderOperateState('store_cancel',$order_info);
            //显示调整费用
            $order_info['if_modify_price'] = $this->modelOrder->getOrderOperateState('modify_price',$order_info);
            //显示发货
            $order_info['if_send'] = $this->modelOrder->getOrderOperateState('send',$order_info);
            //显示退款后操作
            $order_info['refundAfter'] = $this->modelOrder->getOrderOperateState('refundAfter',$order_info);
            //显示锁定中
            $order_info['if_lock'] = $this->modelOrder->getOrderOperateState('lock',$order_info);
            //显示物流跟踪
            $order_info['if_deliver'] = $this->modelOrder->getOrderOperateState('deliver',$order_info);
            $order_info['goods_count'] = count($order_info['extend_order_goods']);
            //update at 2017/02/13 by laijunliang, 追加推荐电话号码导出
            $order_info['recommond_phone'] = array();
            foreach ($order_info['extend_order_goods'] as $goods) {
                $remark = explode('{}', $goods['remark']);
                if ( isset( $remark[1] ) && trim( $remark[1] ) != '' ) {
                    array_push($order_info['recommond_phone'], $remark[1]);
                }
            }
            $order_info['recommond_phone'] = join( ',', $order_info['recommond_phone'] );

            $orderList[$key] = $order_info;
        }

        if(empty($orderList)) $this->error('没有可以被导出的订单数据',url('Seller/order/lists'));

        $this->createExcel($orderList);
    }

    /**
     * 生成excel
     *
     * @param array $data
     */
    private function createExcel($data = array())
    {
        $excel_obj = new Excel();
        $excel_data = array();
        //设置样式
        $excel_obj->setStyle(array('id'=>'s_title','Font'=>array('FontName'=>'宋体','Size'=>'12','Bold'=>'1')));
        //header
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'订单号');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'买家');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'下单时间');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'支付时间');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'订单总额');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'订单状态');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'物流单号');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'买家Email');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'推荐者电话');
        //data
        foreach ((array)$data as $k=>$v){
            $tmp = array();
            $tmp[] = array('data'=>$v['order_sn']);
            $tmp[] = array('data'=>$v['member_name']);
            $tmp[] = array('data'=>date('Y-m-d H:i:s',$v['add_time']));
            $payment_time = ($v['payment_time']<=0) ? '-' : date('Y-m-d H:i:s',$v['payment_time']);
            $tmp[] = array('data'=>$payment_time);
            $tmp[] = array('format'=>'Number','data'=>ncPriceFormat($v['order_amount']));
            $tmp[] = array('data'=>$this->orderState[$v['order_state']]);
            $tmp[] = array('data'=>$v['shipping_code']);
            $tmp[] = array('data'=>$v['member_email']);
            $tmp[] = array('data'=>$v['recommond_phone']);
            $excel_data[] = $tmp;
        }
        $excel_data = $excel_obj->charset($excel_data,CHARSET);
        $excel_obj->addArray($excel_data);
        $excel_obj->addWorksheet($excel_obj->charset('木筑生活馆订单',CHARSET));
        $excel_obj->generateXML($excel_obj->charset('木筑生活馆订单',CHARSET).'-'.date('Y-m-d-H',time()));
    }

    /*
     * 取消订单
     */
    public function orderCancel() {
		$this->view->engine->layout(false);
        //取消页面参数
        $orderId = $this->request->param('orderId');
        //取消页面提交表单
        $type = $this->request->param('type');
        if($type){
            $cancelReason = $this->request->param('cancelReason');
            $orderInfo = $this->modelOrder->getOrderInfo(array('order_id'=>$orderId));
            if(!$orderInfo['order_state']) $this->error('订单已取消','',1);
            //取消订单操作
            $ifAllow = $this->modelOrder->getOrderOperateState('store_cancel',$orderInfo);
            if (!$ifAllow) $this->error('取消失败','',1);

            $logicOrder = new RealOrderLogic();
            $result = $logicOrder->changeOrderStateCancel($orderInfo,'buyer',$this->user['member_name'],$cancelReason);
            if($result['state']){
                $this->success('取消成功','',1);
            }else{
                $this->error('取消失败','',1);
            }
        }
		$orderInfo = $this->modelOrder->getOrderInfo(array('order_sn'=>$orderId));
		$orderInfo['favourable_price'] = $orderInfo['goods_real_amount'] - $orderInfo['order_amount'];
        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }

    /**
     * 修改价格
     */
    public function orderModifyPrice(){

        //修改价格页面参数
        $orderId = $this->request->param('orderId');
        $orderInfo = $this->modelOrder->getOrderInfo(array('order_id'=>$orderId));

        //修改价格提交表单
        $type = $this->request->param('type');
        if($type){
            //2017/2/14 杨萌 前端取消订单，后台不刷新页面做判断
            if(!$orderInfo['order_state']) $this->error('订单已取消，请刷新页面','',1);

            $orderAmount = $this->request->param('order_amount');
            if($orderAmount<0.01) $this->error('优惠价格必须大于等于0.01','',1);

            if( !preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $orderAmount)){
                $this->error('请输入正确的优惠价格','',1);
            }

            if($orderAmount>=$orderInfo['order_amount']){
                $this->error('优惠价格不能大于等于订单价格','',1);
            }
            //修改价格操作
            $ifAllow = $this->modelOrder->getOrderOperateState('modify_price',$orderInfo);
            if (!$ifAllow) $this->error('操作失败','',1);

            $result = $this->logicOrder->orderModifyPrice($orderInfo,'seller','',$orderAmount);
            if( $result ) $this->success('操作成功','',1);
            else $this->error('操作失败','',1);
        }

        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }

    /**
     * 立即发货
     */
    public function orderSend() {
        //立即发货页面参数
        $orderId = $this->request->param('orderId');
        $orderInfo = $this->modelOrder->getOrderInfo(array('order_id'=>$orderId),array('order_common'));
        $orderInfo['favourable_price'] = $orderInfo['goods_amount'] - $orderInfo['order_amount'];
        //立即发货提交表单
        $type = $this->request->param('type');
        if($type){
            //2017/2/14 杨萌 前端申请退款，后台不刷新页面做判断
            if( $orderInfo['refund_state'] == 1 ) $this->error('订单已申请退款，请刷新页面','',1);
            //参数
            $param['shipping_code'] = $this->request->param('shipping_code');
            $param['reciver_name'] = $this->request->param('reciver_name');
            $param['phone'] = $this->request->param('phone');
            $param['address'] = $this->request->param('address');
			$param['shipping_mode'] = $this->request->param('shipping_mode');
			$rule = [
				'shipping_code'  	=> 'require|length:10,20',
				'reciver_name'		=> 'require|max:20',
				'phone'				=> ['regex'=>'/^1[34578]\d{9}$|^(\d{3,4}-?)?\d{7,9}$/i'],
				'address'		=> 'require|max:60'

			];
			$msg = [
				'shipping_code.require' 		=> '物流单号不能为空',
				'shipping_code.length'     		=> '物流单号单号长度是10到20位',
				'reciver_name.require'     		=> '收货人不能为空',
				'reciver_name.max'      		=> '收货人长度不能超过20',
				'phone.regex'					=> '联系方式不正确',
				'address.require'				=> '收货地址不能为空',
				'address.max'				  	=> '收货地址长度不能超过60',
			];
			$validate = new validate($rule,$msg);
			if (!$validate->check($param)) {
				$this->error($validate->getError());
			}
            //立即发货操作
            $ifAllow = $this->modelOrder->getOrderOperateState('send',$orderInfo);
            if (!$ifAllow) $this->error('操作失败','',1);
            $result = $this->logicOrder->orderSend($orderInfo,'seller','',$param);
            if( $result ) $this->success('操作成功','',1);
            else $this->error('操作失败','',1);
        }
        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }

    /*
     * 删除订单
     */
    public function orderDelete(){
        $orderId = $this->request->param('orderId','','intval');
        $orderInfo = $this->modelOrder->getOrderInfo(array('order_id'=>$orderId));

        $type = $this->request->param('type');
        if($type){
            if (!is_numeric($orderId)) $this->error('删除失败','',1);

            if ( $this->modelOrder->update( array('delete_state'=>1),array('order_id' => $orderId)) )
                $this->success('删除成功','',1);
            else $this->error('删除失败','',1);
        }

        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }


    /**
     * 退款审核操作
     */
    public function orderRefundVerify(){
        //退款页面参数
        $orderId = $this->request->param('orderId');
        $orderInfo = $this->modelOrder->getOrderInfo(array('order_id'=>$orderId));
        $refundInfo = $this->modelOrder->getRefundInfo(array('order_id'=>$orderId));
        //退款提交表单
        $type = $this->request->param('type');
        if($type == 1){
			if($orderInfo['refund_state'] != 1){
				$this->error('此订单已被重新处理');
			}
			$refundAmount = $this->request->param('refund_amount');
			if( !preg_match("/^([1-9][0-9]*|0)(\.\d{1,2})?$/",$refundAmount) || $refundAmount <= 0 || $refundAmount>$orderInfo['order_amount'])
				$this->error('请输入正确的退款价格','',1);
            $orderInfo['refund_state'] = 2;
            //退款操作
            $ifAllow = $this->modelOrder->getOrderOperateState('refund',$orderInfo);
            if (!$ifAllow) $this->error('操作失败','',1);
			$orderInfo['seller_amount'] = $refundAmount;
            $result = $this->logicOrder->orderRefund($orderInfo);
            if( $result ) $this->success('操作成功','',1);
            else $this->error('操作失败','',1);
        }else if($type == 2){//拒绝申请
			if($orderInfo['refund_state'] != 1){
				$this->error('此订单已被重新处理');
			}
			$refundImg = $this->request->param('refuse_img/a');
			if($refundImg){
				if(count($refundImg) > 5){
					$this->error('图片个数不能多于五个');
				}
				$data['seller_img'] = json_encode($refundImg);
			}else{
				$this->error('图片不能为空');
			}
			$sellerMessage = $this->request->param('seller_message','','trim');
			$imgStr = json_encode($refundImg);
			$data = ['seller_message'=>$sellerMessage,'seller_img'=>$imgStr];
			$rule = [
				'seller_message'  	=> 'require|max:200',
			];
			$msg = [
				'seller_message.require' 		=> '退款原因不能为空',
				'seller_message.max'     		=> '退款原因最多不能超过200个字符',
			];
			$validate = new validate($rule,$msg);
			if (!$validate->check($data)) {
				$this->error($validate->getError());
			}
			$orderInfo['refund_state'] = 2;
			//实际退款金额
			//退款操作
			$ifAllow = $this->modelOrder->getOrderOperateState('refund',$orderInfo);
			if (!$ifAllow) $this->error('操作失败','',1);
			$result = $this->logicOrder->orderRefundNo($orderInfo,$data);
			if( $result ) $this->success('操作成功','',1);
			else $this->error('操作失败','',1);
		}
		$refundInfo['img'] = json_decode($refundInfo['buyer_img']);
        $this->assign('order_info',$orderInfo);
        $this->assign('refund_info',$refundInfo);
        return $this->fetch();
    }

    /**
     * 退款同意页面
     */
    public function orderRefundAgree(){
        //退款页面参数
        $orderId = $this->request->param('orderId');
        $orderInfo = $this->modelOrder->getOrderInfo(array('order_id'=>$orderId));
		$refundAmount = $this->request->param('refund_amount','','trim');
		if( !preg_match("/^([1-9][0-9]*|0)(\.\d{1,2})?$/",$refundAmount) || $refundAmount <= 0 || $refundAmount>$orderInfo['order_amount'])
			$this->error('请输入正确的退款价格','',1);
		$paymentName = $this->modelOrder->orderPaymentName($orderInfo['payment_code']);
		$this->assign('payment_name',$paymentName);
		$this->assign('refund_amount',$refundAmount);
		$this->assign('order_info',$orderInfo);
		return $this->fetch();
    }
    /**
     * 退款拒绝页面
     */
    public function orderRefundRefuse(){
        //退款页面参数
		$this->view->engine->layout(false);
        $orderId = $this->request->param('orderId');
        $orderInfo = $this->modelOrder->getOrderInfo(array('order_id'=>$orderId));
        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }

	/**
	 * 订单详情页面
	 */
	public function orderDetail(){
		$orderId = $this->request->param('order_id','','intval');
		if(empty($orderId)) $this->error('参数错误');
		$orderInfo = $this->modelOrder->getOrderInfo(array('order_id'=>$orderId),array('order_goods','order_common','refund_return'));
        //显示系统自动取消订单日期
        if ($orderInfo['order_state'] == Orders::ORDER_STATE_NEW) {
            $orderInfo['order_cancel_day'] = $orderInfo['add_time'] + ORDER_AUTO_CANCEL_DAY * 24 * 3600;
        }
        //如果订单已取消，取得取消原因、时间，操作人
        if ($orderInfo['order_state'] == Orders::ORDER_STATE_CANCEL) {
            $orderInfo['close_info'] = $this->modelOrder->getOrderLogInfo(array('order_id'=>$orderInfo['order_id']),'log_id desc');
        }

        //显示系统自动取消订单日期
        if ($orderInfo['order_state'] == Orders::ORDER_STATE_SEND) {
            $orderInfo['order_confirm_day'] = $orderInfo['delay_time'] + ORDER_AUTO_RECEIVE_DAY * 24 * 3600;
        }

        //支付方式
        if($orderInfo['payment_code']!='offline' && $orderInfo['order_state']==Orders::ORDER_STATE_PAY){
            $orderInfo['orderPaymentName'] =  orderPaymentName($orderInfo['payment_code']);
        }
		$this->assign('order_info',$orderInfo);
        $this->assign('orderState',$this->orderState);
		return $this->fetch();
	}

	/**
	 * 订单详情页面
	 */
	public function orderAfter(){
		$orderId = $this->request->param('order_id','','intval');
		if(empty($orderId)){
			$this->error('参数错误');
		}
		$orderInfo = $this->modelOrder->getOrderInfo(array('order_id'=>$orderId),array('order_goods','order_common'));
		$refundInfo = $this->modelOrder->getRefundInfo(array('order_id'=>$orderId));
		$this->assign('order_info',$orderInfo);
		$this->assign('refund_info',$refundInfo);
		return $this->fetch();
	}

	/**
	 * @return mixed
	 *
	 * 添加物流信息
	 */

	public function editDeliver(){
		$type = $this->request->param('type','0','trim');
		$orderId = $this->request->param('orderId','','intval');
		if($type == 1){
			$time = strtotime($this->request->param('time',0,'trim'));
			$delivery = $this->request->param('delivery','','trim');
			$data = ['delivery'=>$delivery,'order_id'=>$orderId];
			$rule = [
				'delivery'   => 'require|max:500|desc'
			];
			$msg = [
				'delivery.require'     		=> '物流信息不能为空',
				'delivery.max'     			=> '物流信息不能超过500个字符',
				'delivery.desc'     		=> '物流信息含有非法字符',
			];
			$validate = new validate($rule,$msg);
			if (!$validate->check($data)) {
				$this->error($validate->getError());
			}
			$modelDelivery = new DeliveryInfo();
			$rs = $modelDelivery->insert($data);
			if($rs){
				$this->success('添加成功','seller/order/lists');
			}else{
				$this->error('添加失败');
			}
		}else{
			if(empty($orderId)){
				$this->error('参数错误');
			}
			$orderInfo = $this->modelOrder->getOrderInfo(array('order_id'=>$orderId),array('order_goods','order_common'));
			$this->assign('order_info',$orderInfo);
			return $this->fetch();
		}
	}

}
