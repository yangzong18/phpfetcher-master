<?php
/**
 * create by: PhpStorm
 * desc:订单逻辑
 * author:yangmeng
 * create time:2016/12/01
 */
namespace app\seller\logic;
use app\common\controller\Auth;
use app\common\logic\RealOrderLogic;
use app\common\payment\Payment;
use app\common\model\Order as Orders;
use think\Db;
use think\Config;
use think\image\Exception;
use think\Log;

class Order extends Auth
{
	    /**
     * 更改优惠价格
     * @param array $order_info
     * @param string $role 操作角色 buyer、seller、admin、system 分别代表买家、商家、管理员、系统
     * @param string $user 操作人
     * @param float $price 运费
     * @return array
     */
    public function orderModifyPrice($order_info, $role, $user = '', $order_amount) {
        $orderModel = new Orders();
        //try {

            $order_id = $order_info['order_id'];

            $update = $orderModel->editOrder(array('order_amount'=>$order_amount),array('order_id'=>$order_id));
//            if (!$update) {
//                throw new Exception('保存失败');
//            }
            return $update;
            //记录订单日志
//            $data = array();
//            $data['order_id'] = $order_id;
//            $data['log_role'] = $role;
//            $data['log_user'] = $user;
//            $data['log_msg'] = '修改了运费'.'( '.$price.' )';;
//            $data['log_orderstate'] = $order_info['payment_code'] == 'offline' ? ORDER_STATE_PAY : ORDER_STATE_NEW;
//            $model_order->addOrderLog($data);
//            return callback(true,'操作成功');
//        } catch (Exception $e) {
//            return callback(false,'操作失败');
//        }
    }

    public function orderSend($order_info, $role, $user = '', $param) {
        $orderModel = new Orders();
        $order_id = $order_info['order_id'];
		// 启动事务
		try{
			Db::startTrans();
			//修改order表
			$updateOrder = array();
			$updateOrder['shipping_mode'] 	= $param['shipping_mode'];
			$updateOrder['shipping_code'] 	= $param['shipping_code'];
			$updateOrder['order_state'] 	= Orders::ORDER_STATE_SEND;
			$updateOrder['shipping_time']   = time();
			$orderModel->editOrder($updateOrder,array('order_id'=>$order_id));
			$updateOrderCommon = array();
			$updateOrderCommon['reciver_name'] = $param['reciver_name'];
			$data = array();
			$data['phone'] = $param['phone'];
			$data['address'] = $param['address'];
			$data['phone'] = $param['phone'];
			$updateOrderCommon['reciver_info'] = serialize($data);
			$orderModel->editOrderCommon($updateOrderCommon,array('order_id'=>$order_id));
			// 提交事务
			Db::commit();
			return true;
		} catch (Exception $e) {
			// 回滚事务
			Db::rollback();
			return false;
		}
    }

	/**
	 *
	 * 退款同意处理
	 * @param $order_info
	 * @return bool
	 */
    public function orderRefund($order_info) {
        $orderModel = new Orders();
        $order_id = $order_info['order_id'];
		$tradeNo = Db::name('order_pay')->where( array( 'pay_sn' => $order_info['pay_sn'], 'api_pay_state' => 1 ) )->column('trade_no');
		//进行批量退款
		$time       = time();
		$refundNo   = date('YmdHis', $time). str_pad($order_info['order_id'], 8, '0', STR_PAD_LEFT);
		try{
			Db::startTrans();
			//修改order表
			$updateOrder = array();
			$updateOrder['refund_state'] = 2;
			$where = ['order_id'=> $order_info['order_id'],'seller_state'=>1];
			$refundInfo = array(
				'seller_amount' => $order_info['seller_amount'],
				'seller_state'  => 2,//同意
				'seller_time' 	=> time(),
				'refund_no'		=> $refundNo
			);

			$result=$orderModel->editOrder($updateOrder,array('order_id'=>$order_id,'refund_state'=>1));
			if($result){
				$rt=$orderModel->editRefund($refundInfo,$where);
				if($rt){
					$detail_data = array(array($tradeNo[0], $order_info['seller_amount'], '买家申请退款' ));
					$orderLogic = new RealOrderLogic();
					$orderLogic->backOrderKC($order_id);
					$refund =  Payment::getInstance($order_info['payment_code'])->refund( $refundNo,1, $detail_data, 'orderRefund' );
					if ( !isset( $refund['is_success'] ) || $refund['is_success'] !='T' ) {
						Db::rollback();
						Log::write('退款申请失败, data:'.json_encode( $refund ),'error');
						return false;
					}
				}else{
					Db::rollback();
					return false;
				}

			}else{
				Db::rollback();
				return false;
			}

			/**
			 *
			 * 调用退款接口
			 *
			 */
			//提交事务
			Db::commit();
			return true;
		}catch (Exception $e) {
			Db::rollback();
			return false;
		}
    }

	/**
	 *
	 * 退款不同意处理
	 * @param $order_info
	 * @return bool
	 */
	public function orderRefundNO($order_info,$param) {
		$orderModel = new Orders();
		$order_id = $order_info['order_id'];
		try{
			Db::startTrans();
			//修改order表
			$updateOrder = array();
			$updateOrder['refund_state'] = $order_info['refund_state'];
			$updateOrder['lock_state'] = 0;
			$where = ['order_id'=> $order_info['order_id'],'seller_state'=>1];
			$refundInfo = array(
				'seller_state'  => 3,//不同意
				'seller_time' 	=> time(),
				'seller_message'=> $param['seller_message'],
				'seller_img' 	=> $param['seller_img'],
			);

			$result=$orderModel->editOrder($updateOrder,array('order_id'=>$order_id,'refund_state'=>1));
			if($result){
				$res=$orderModel->editRefund($refundInfo,$where);
				if($res){
					//提交事务
					Db::commit();
					return true;
				}else{
					Db::rollback();
					return false;
				}
			}else{
				Db::rollback();
				return false;
			}

		}catch (Exception $e) {
			Db::rollback();
			return false;
		}
	}

}
