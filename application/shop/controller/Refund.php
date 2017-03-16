<?php
/**
 * 退款异步通知
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang at 2016/12/19
 */
namespace app\shop\controller;
use think\Controller;
use app\common\payment\Payment;
use think\Db;
use think\log;

class Refund extends Controller{
    /**
     * 退款异步通知结果
     */
    public function notify(){
        $way  = $this->request->param('payment_way');
        call_user_func(array($this,$way));
    }


    /**
     * 众筹退款异步通知
     */
    private function crowdRefund() {
        $paymentCode = $this->request->param('payment_code');
        $pay         = Payment::getInstance( $paymentCode );
        $result      = $pay->refundNotifyVerify();
		Log::write('众筹退款, data:'.json_encode( $this->request->param() ),'info');
        //如果最终退款成功
        $param = array( 'refund_state' => 4 );
        if ( $result['result'] == true ) {
            $param['refund_state'] = 3;
        }
        $where = array( 'refund_sn' => $result['refund_batch_no'], 'trade_no' => $result['batch_no'] );
        if ( !Db::name('crowdfunding_refund')->where( $where )->update( $param ) ) {
            Log::write('众筹退款状态修改失败, data:'.$result['refund_state'],'error');
            exit('fail');
        }
        if ( $result['status'] ) {
            echo 'success';
        } else {
            echo 'fail';
        }
        exit;
    }

    private function work() {
        Log::write('接收到的参数, data:'.json_encode( $this->request->param() ),'info');
    }

	/**
	 * 标准订单退款异步通知
	 */
	private function orderRefund() {
		$paymentCode = $this->request->param('payment_code');
		$pay         = Payment::getInstance( $paymentCode );
		$result      = $pay->refundNotifyVerify();
		Log::write('标准订单退款, data:'.json_encode( $this->request->param() ),'info');
		//如果最终退款成功
		$param = array( 'refund_state' => 4 );
		if ( $result['result'] == true ) {
			$param['refund_state'] = 3;
		}
		$where = array( 'refund_no' => $result['refund_batch_no']);
		if ( !Db::name('refund_return')->where( $where )->update( $param ) ) {
			Log::write('标准订单退款状态修改失败, data:'.$result['refund_state'],'error');
			exit('fail');
		}
		if ( $result['status'] ) {
			echo 'success';
		} else {
			echo 'fail';
		}
		exit;
	}



   
}

