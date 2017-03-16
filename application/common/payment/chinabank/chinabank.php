<?php
/**
 * 银联支付接口
 * @copyright  Copyright (c) 2017 Changehong Inc. (http://www.changhong.com)
 * @author laijunliang at 2017/02/17
 */
include_once dirname(__FILE__).'/sdk/acp_service.php';
class chinabank{
	
	/**
	 * 支付接口标识
	 *
	 * @var string
	 */
    private $code      = 'chinabank';
    /**
	 * 支付接口配置信息
	 *
	 * @var array
	 */
    private $payment;
     /**
	 * 订单信息
	 *
	 * @var array
	 */
    private $order;
    /**
	 * 发送至支付宝的参数
	 *
	 * @var array
	 */
    private $parameter;
    /**
     * 订单类型
     * @var unknown
     */
    private $order_type;

    public function __construct($payment_info = array(),$order_info = array()){
    	if(!empty($payment_info) ){
    		$this->payment	= $payment_info;
    		$this->order	= $order_info;
    	}
    }

    /**
     * 获取支付接口的请求地址
     *
     * @return string
     */
    public function get_payurl(){
    	$this->parameter = array(
            //以下信息非特殊情况不需要改动
			'version' => '5.0.0',                 //版本号
			'encoding' => 'utf-8',				  //编码方式
			'txnType' => '01',				      //交易类型
			'txnSubType' => '01',				  //交易子类
			'bizType' => '000201',				  //业务类型
			'frontUrl' =>  HTTP_SITE_HOST."/shop/payment/returnpay/payment_code/chinabank",  //前台通知地址
			'backUrl'   => HTTP_SITE_HOST."/shop/payment_notify/notify/payment_code/chinabank",
			'signMethod' => '01',	              //签名方法
			'channelType' => '07',	              //渠道类型，07-PC，08-手机
			'accessType' => '0',		          //接入类型
			'currencyCode' => '156',	          //交易币种，境内商户固定156
			
			//TODO 以下信息需要填写
			'merId'   => $this->payment['payment_config']['chinabank_account'],		//商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
			'orderId' => $this->order['pay_sn'],	//商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
			'txnTime' => $this->order['payment_time'],	//订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
			'txnAmt'  => round($this->order['api_pay_amount']*100),	//交易金额，单位分，此处默认取demo演示页面传递的参数
            'reqReserved' => $this->order['order_type'],
			//TODO 其他特殊用法请查看 special_use_purchase.php
        );
        com\unionpay\acp\sdk\AcpService::sign ( $this->parameter );
		$uri = com\unionpay\acp\sdk\SDK_FRONT_TRANS_URL;
		return com\unionpay\acp\sdk\AcpService::createAutoFormHtml( $this->parameter, $uri );
    }


    /**
	 * 通知地址验证
	 *
	 * @return bool
	 */
	public function notify_verify() {
		if (isset( $_POST ['signature'] ) && com\unionpay\acp\sdk\AcpService::validate ( $_POST ) ) {
			return true;
		}
		return false;
	}

	/**
	 * 返回地址验证
	 *
	 * @return bool
	 */
	public function return_verify() {
		return $this->notify_verify();
	}


	/**
	 * 
	 * 取得订单支付状态，成功或失败
	 * @param array $param
	 * @return array
	 */
	public function getPayResult($param){
		if ( isset( $param['respCode'] ) && ($param['respCode'] == '00' || $param['respCode'] == 'A6') ) {
			return true;
		}
		return false;
	}

	

	/**
	 * 退款申请发起
	 * @param string $refund_batch_no 批次号，必填，格式：当天日期[8位]+序列号[3至24位]，如：201603081000001
	 * @param string $batch_num 退款笔数，必填，参数detail_data的值中，“#”字符出现的数量加1，最大支持1000笔（即“#”字符出现的数量999个）
	 * @param string $detail_data 退款详细数据，必填，格式 array( array( 支付宝交易号, 退款金额, 备注 ), array( 支付宝交易号, 退款金额, 备注 ) )
	 * @param string $way  通过way参数来区分是那种业务, 例如众筹crowd
	 * @return $result {"is_success":"T(成功)/F(失败)/P(处理中)","error":"BATCH_NO_FORMAT_ERROR"}  
	 */
	public function refund( $refund_batch_no, $batch_num, $detail_data, $way ) {
		$time = date('YmdHis', time());
		$params = array(
			//以下信息非特殊情况不需要改动
			'version' => '5.0.0',		      //版本号
			'encoding' => 'utf-8',		      //编码方式
			'signMethod' => '01',		      //签名方法
			'txnType' => '04',		          //交易类型
			'txnSubType' => '00',		      //交易子类
			'bizType' => '000201',		      //业务类型
			'accessType' => '0',		      //接入类型
			'channelType' => '07',		      //渠道类型
			'backUrl' => HTTP_SITE_HOST."/shop/refund/notify/payment_code/chinabank/payment_way/".$way, //后台通知地址
			//TODO 以下信息需要填写
			'orderId' => $refund_batch_no,	    //商户订单号，8-32位数字字母，不能含“-”或“_”，可以自行定制规则，重新产生，不同于原消费，此处默认取demo演示页面传递的参数
			'merId'   => $this->payment['payment_config']['chinabank_account'],        //商户代码，请改成自己的测试商户号，此处默认取demo演示页面传递的参数
			'origQryId' => '', //原消费的queryId，可以从查询接口或者通知接口中获取，此处默认取demo演示页面传递的参数
			'txnTime' => $time,	    //订单发送时间，格式为YYYYMMDDhhmmss，重新产生，不同于原消费，此处默认取demo演示页面传递的参数
			'txnAmt' => 0,       //交易金额，退货总金额需要小于等于原消费
		);
		$url = com\unionpay\acp\sdk\SDK_BACK_TRANS_URL;
		$fail= array();
		$failMessage = array();
		for ($i=0; $i < $batch_num; $i++) { 
			$params['origQryId'] = $detail_data[$i][0];
			$params['txnAmt'] = intval($detail_data[$i][1]*100);
			//进行退款操作
			com\unionpay\acp\sdk\AcpService::sign ( $params );
            $result_arr = com\unionpay\acp\sdk\AcpService::post ( $params, $url);
            if ( count($result_arr) > 0 && com\unionpay\acp\sdk\AcpService::validate ($result_arr) && $result_arr["respCode"] == "00" ) {
            	//成功的退款申请
            } else {
            	//失败的退款申请
            	array_push($fail, $detail_data[$i]);
            	array_push($failMessage, $result_arr);
            }
		}
		//如果有一个失败，则标记失败，前端进行处理
		$result = array( 'is_success' => 'F', 'error' => '失败' );
		if ( count( $fail ) > 0 ) {
			$result['fail'] = $fail;
			$result['fail_message'] = $failMessage;
		} else {
			$result['is_success'] = 'T';
		}
		return $result;
	}

    

    /**
     * 支付宝退款申请异步通知验证
     * @param $result  {"status": true/false , 'refund_batch_no' => '退款流水',  'batch_no' => '交易流水', 'amount' => '退款金额', 'result' => false/true退款结果  }
     */
    public function refundNotifyVerify() {
    	$message = array( 
    		'status' => false, 
    		'refund_batch_no' => '', 
    		'batch_no' => '', 
    		'amount' => '' , 
    	    'result' => '' 
    	);
    	if ( $this->notify_verify() && $this->getPayResult($_POST) ) {
    		$message['status'] = true;
        	$message['refund_batch_no'] = $_POST['orderId'];
			$message['batch_no'] = $_POST['queryId'];
			$message['amount'] = 1;
			$message['result'] = true;
    	}
    	return $message;
    }

}