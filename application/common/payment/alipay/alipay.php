<?php
/**
 * 支付宝接口类
 *
 * 
 * @copyright  Copyright (c) 2007-2013 ShopNC Inc. (http://www.shopnc.net)
 * @license    http://www.shopnc.net
 * @link       http://www.shopnc.net
 * @since      File available since Release v1.1
 */

class alipay{
	/**
	 *支付宝网关地址（新）
	 */
	private $alipay_gateway_new = 'https://mapi.alipay.com/gateway.do?';
	/**
	 * 消息验证地址
	 *
	 * @var string
	 */
	private $alipay_verify_url = 'https://mapi.alipay.com/gateway.do?service=notify_verify&';
	/**
	 * 支付接口标识
	 *
	 * @var string
	 */
    private $code      = 'alipay';
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
    	if (!extension_loaded('openssl')) $this->alipay_verify_url = 'http://notify.alipay.com/trade/notify_query.do?';
    	if(!empty($payment_info) ){
    		$this->payment	= $payment_info;
    		$this->payment['payment_config']['sign_type'] = 'MD5';
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
            'service'		    => 'create_direct_pay_by_user',	//服务名
            'partner'		    => $this->payment['payment_config']['alipay_partner'],	//合作伙伴ID
            'key'               => $this->payment['payment_config']['alipay_key'],
            '_input_charset'	=> CHARSET,					//网站编码
			'return_url'	    => HTTP_SITE_HOST."/shop/payment/returnpay/payment_code/alipay",	//同步通知URL
            'notify_url'	    => HTTP_SITE_HOST."/shop/payment_notify/notify/payment_code/alipay",	//异步通知URL
            'sign_type'		    => 'MD5',				//签名方式
            'extra_common_param'=> $this->order['order_type'],
            'subject'		    => $this->order['subject'],	//商品名称
            'body'			    => $this->order['pay_sn'],	//商品描述
            'out_trade_no'	    => $this->order['pay_sn'],		//外部交易编号
            'payment_type'	    => 1,							//支付类型
            'logistics_type'    => 'EXPRESS',					//物流配送方式：POST(平邮)、EMS(EMS)、EXPRESS(其他快递)
            'logistics_payment'	=> 'BUYER_PAY',				     //物流费用付款方式：SELLER_PAY(卖家支付)、BUYER_PAY(买家支付)、BUYER_PAY_AFTER_RECEIVE(货到付款)
            'receive_name'		=> $this->order['member_name'],//收货人姓名
            'receive_address'	=> 'N',	//收货人地址
            'receive_zip'		=> 'N',	//收货人邮编
            'receive_phone'		=> 'N',//收货人电话
            'receive_mobile'	=> 'N',//收货人手机
            'seller_email'		=> $this->payment['payment_config']['alipay_account'],	//卖家邮箱
            'price'             => $this->order['api_pay_amount'],//订单总价
            'quantity'          => 1,//商品数量
            'total_fee'         => 0,//物流配送费用
            'extend_param'      => "isv^sh32",
        );
        $this->parameter['sign']	= $this->sign($this->parameter);
        return $this->create_url();
    }

	/**
	 * 通知地址验证
	 *
	 * @return bool
	 */
	public function notify_verify() {
		$param	= $_POST;
		$param['key']	= $this->payment['payment_config']['alipay_key'];
		$veryfy_url = $this->alipay_verify_url. "partner=" .$this->payment['payment_config']['alipay_partner']. "&notify_id=".$param["notify_id"];
		$veryfy_result  = $this->getHttpResponse($veryfy_url);
		$mysign = $this->sign($param);
		if (preg_match("/true$/i",$veryfy_result) && $mysign == $param["sign"])  {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 返回地址验证
	 *
	 * @return bool
	 */
	public function return_verify() {
		$param	= $_GET;
		//将系统的控制参数置空，防止因为加密验证出错
		$param['act']	= '';
		$param['op']	= '';
		$param['payment_code'] = '';
		$param['key']	= $this->payment['payment_config']['alipay_key'];
		$veryfy_url = $this->alipay_verify_url. "partner=" .$this->payment['payment_config']['alipay_partner']. "&notify_id=".$param["notify_id"];
		$veryfy_result  = $this->getHttpResponse($veryfy_url);
		$mysign = $this->sign($param);
		if (preg_match("/true$/i",$veryfy_result) && $mysign == $param["sign"])  {
            return true;
		} else {
			return false;
		}
	}

	/**
	 * 
	 * 取得订单支付状态，成功或失败
	 * @param array $param
	 * @return array
	 */
	public function getPayResult($param){
		return $param['trade_status'] == 'TRADE_SUCCESS';
	}

	/**
	 * 
	 *
	 * @param string $name
	 * @return 
	 */
	public function __get($name){
	    return $this->$name;
	}

	/**
	 * 远程获取数据
	 * $url 指定URL完整路径地址
	 * @param $time_out 超时时间。默认值：60
	 * return 远程输出的数据
	 */
	private function getHttpResponse($url,$time_out = "60") {
		$urlarr     = parse_url($url);
		$errno      = "";
		$errstr     = "";
		$transports = "";
		$responseText = "";
		if($urlarr["scheme"] == "https") {
			$transports = "ssl://";
			$urlarr["port"] = "443";
		} else {
			$transports = "tcp://";
			$urlarr["port"] = "80";
		}
		$fp=@fsockopen($transports . $urlarr['host'],$urlarr['port'],$errno,$errstr,$time_out);
		if(!$fp) {
			die("ERROR: $errno - $errstr<br />\n");
		} else {
			if (trim(CHARSET) == '') {
				fputs($fp, "POST ".$urlarr["path"]." HTTP/1.1\r\n");
			} else {
				fputs($fp, "POST ".$urlarr["path"].'?_input_charset='.CHARSET." HTTP/1.1\r\n");
			}
			fputs($fp, "Host: ".$urlarr["host"]."\r\n");
			fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
			fputs($fp, "Content-length: ".strlen($urlarr["query"])."\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			fputs($fp, $urlarr["query"] . "\r\n\r\n");
			while(!feof($fp)) {
				$responseText .= @fgets($fp, 1024);
			}
			$responseText = trim(stristr($responseText,"\r\n\r\n"),"\r\n");
			return $responseText;
		}
	}

    /**
     * 制作支付接口的请求地址
     *
     * @return string
     */
    private function create_url() {
		$url        = $this->alipay_gateway_new;
		$filtered_array	= $this->para_filter($this->parameter);
		$sort_array = $this->arg_sort($filtered_array);
		$arg        = "";
		while (list ($key, $val) = each ($sort_array)) {
			$arg.=$key."=".urlencode($val)."&";
		}
		$url.= $arg."sign=" .$this->parameter['sign'] ."&sign_type=".$this->parameter['sign_type'];
		return $url;
	}

	/**
	 * 取得支付宝签名
	 *
	 * @return string
	 */
	private function sign($parameter) {
		$mysign = "";
		
		$filtered_array	= $this->para_filter($parameter);
		$sort_array = $this->arg_sort($filtered_array);
		$arg = "";
        while (list ($key, $val) = each ($sort_array)) {
			$arg	.= $key."=".$this->charset_encode($val,(empty($parameter['_input_charset'])?"UTF-8":$parameter['_input_charset']),(empty($parameter['_input_charset'])?"UTF-8":$parameter['_input_charset']))."&";
		}
		$prestr = substr($arg,0,-1);  //去掉最后一个&号
		$prestr	.= $parameter['key'];
        if($parameter['sign_type'] == 'MD5') {
			$mysign = md5($prestr);
		}elseif($parameter['sign_type'] =='DSA') {
			//DSA 签名方法待后续开发
			die("DSA 签名方法待后续开发，请先使用MD5签名方式");
		}else {
			die("支付宝暂不支持".$parameter['sign_type']."类型的签名方式");
		}
		return $mysign;

	}

	/**
	 * 除去数组中的空值和签名模式
	 *
	 * @param array $parameter
	 * @return array
	 */
	private function para_filter($parameter) {
		$para = array();
		while (list ($key, $val) = each ($parameter)) {
			if($key == "sign" || $key == "sign_type" || $key == "key" || $val == "")continue;
			else	$para[$key] = $parameter[$key];
		}
		return $para;
	}

	/**
	 * 重新排序参数数组
	 *
	 * @param array $array
	 * @return array
	 */
	private function arg_sort($array) {
		ksort($array);
		reset($array);
		return $array;

	}

	/**
	 * 实现多种字符编码方式
	 */
	private function charset_encode($input,$_output_charset,$_input_charset="UTF-8") {
		$output = "";
		if(!isset($_output_charset))$_output_charset  = $this->parameter['_input_charset'];
		if($_input_charset == $_output_charset || $input == null) {
			$output = $input;
		} elseif (function_exists("mb_convert_encoding")){
			$output = mb_convert_encoding($input,$_output_charset,$_input_charset);
		} elseif(function_exists("iconv")) {
			$output = iconv($_input_charset,$_output_charset,$input);
		} else die("sorry, you have no libs support for charset change.");
		return $output;
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
		$detailData = array();
		foreach ($detail_data as $detail) {
			array_push($detailData, join('^', $detail));
		}
		//参数构造
        $parameter = array(
			"service" => 'refund_fastpay_by_platform_nopwd',
			"partner" => $this->payment['payment_config']['alipay_partner'],
			"notify_url"	=> HTTP_SITE_HOST."/shop/refund/notify/payment_code/alipay/payment_way/".$way,
			"seller_user_id"	=> $this->payment['payment_config']['alipay_partner'],
			"refund_date"	=> date("Y-m-d H:i:s",time()),
			"batch_no"	=> $refund_batch_no,
			"batch_num"	=> $batch_num,
			"detail_data"	=> join('#', $detailData),
			"_input_charset"	=> 'utf-8',	
		);
		$parameter = $this->refundSign( $parameter );
		$arg        = "";
		foreach ($parameter as $key => $val) {
			$arg.=$key."=".urlencode($val)."&";
		}
		$url = $this->alipay_gateway_new;
		$url.= $arg."sign=" .$parameter['sign'] ."&sign_type=".$parameter['sign_type'];
		$result = json_decode(json_encode( simplexml_load_string( $this->getHttpResponseGET( $url ) ) ), true );
        return $result;
	}

	/**
	 * 退款生成签名
	 */
	public function refundSign( $param ) {
		//除去待签名参数数组中的空值和签名参数
		$filtered_array	= $this->para_filter( $param );
		//对待签名参数数组排序
		$sort_array = $this->arg_sort($filtered_array);
		//生成签名结果
		$arg  = "";
		while (list ($key, $val) = each ($sort_array)) {
			$arg.=$key."=".$val."&";
		}
		//去掉最后一个&字符
		$arg = substr($arg,0,count($arg)-2);
		//如果存在转义字符，那么去掉转义
		if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}
        $prestr = $arg;
		$mysign = "";
		switch (strtoupper(trim($this->payment['payment_config']['sign_type']))) {
			case "MD5" :
			    $prestr = $prestr . $this->payment['payment_config']['alipay_key'];
				$mysign = md5($prestr);
                break;
			default :
				$mysign = "";
		}
		$sort_array['sign'] = $mysign;
		$sort_array['sign_type'] = strtoupper(trim($this->payment['payment_config']['sign_type']));
        return $sort_array;
	}

	/**
	 * 远程获取数据，GET模式
	 * 注意：
	 * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
	 * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
	 * @param $url 指定URL完整路径地址
	 * @param $cacert_url 指定当前工作目录绝对路径
	 * return 远程输出的数据
	 */
	public function getHttpResponseGET($url, $cacert_url = '') {
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
		curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);//严格认证
		//curl_setopt($curl, CURLOPT_CAINFO,$cacert_url);//证书地址
		$responseText = curl_exec($curl);
		//var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
		curl_close($curl);
		return $responseText;
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
    	//如果异步验证通过
        if ( $this->verifyRefundNotify() ) {
        	$message['status'] = true;
        	$message['refund_batch_no'] = $_POST['batch_no'];
			//批量退款数据中的详细信息
			$result_details = explode('^', $_POST['result_details'] );
			$message['batch_no'] = $result_details[0];
			$message['amount'] = $result_details[1];
			$message['result'] = ($result_details[2] == 'SUCCESS' ? true : false);
        }
        return $message;
    }

    /**
     * 针对notify_url验证消息是否是支付宝发出的合法消息
     * @return 验证结果
     */
	public function verifyRefundNotify(){
		if(empty($_POST)) {//判断POST来的数组是否为空
			return false;
		} else {
			$para_temp = $_POST;
			//除去待签名参数数组中的空值和签名参数
		    $filtered_array	= $this->para_filter( $para_temp );
		    //对待签名参数数组排序
		    $sort_array = $this->arg_sort($filtered_array);
			
			//把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
			$arg  = "";
			while (list ($key, $val) = each ($sort_array)) {
				$arg.=$key."=".$val."&";
			}
			//去掉最后一个&字符
			$arg = substr($arg,0,count($arg)-2);
			//如果存在转义字符，那么去掉转义
			if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}
			$prestr = $arg;
			$isSign = false;
			switch (strtoupper(trim($this->payment['payment_config']['sign_type']))) {
				case "MD5" :
				    $prestr = $prestr . $this->payment['payment_config']['alipay_key'];
	                $mysgin = md5($prestr);
					$isSign = $mysgin == $para_temp['sign'] ? true :false;
					break;
				default :
					$isSign = false;
			}
			//获取支付宝远程服务器ATN结果（验证是否是支付宝发来的消息）
			$responseTxt = 'false';
			if ( isset( $para_temp["notify_id"] ) ) {
		        $veryfy_url = $this->alipay_verify_url;
		        $veryfy_url = $veryfy_url."partner=" . $this->payment['payment_config']['alipay_partner'] . "&notify_id=" . $para_temp["notify_id"];
				$responseTxt = $this->getHttpResponseGET( $veryfy_url );
			}
			//验证
			//$responsetTxt的结果不是true，与服务器设置问题、合作身份者ID、notify_id一分钟失效有关
			//isSign的结果不是true，与安全校验码、请求时的参数格式（如：带自定义参数等）、编码格式有关
			if (preg_match("/true$/i",$responseTxt) && $isSign) {
				return true;
			} else {
				return false;
			}
		}
	}

}