<?php
/* *
 * 功能：支付宝手机网站alipay.trade.close (统一收单交易关闭接口)业务参数封装
 * 版本：2.0
 * 修改日期：2016-11-01
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 */
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'wappay'.DIRECTORY_SEPARATOR.'buildermodel'.DIRECTORY_SEPARATOR.'AlipayTradeQueryContentBuilder.php';
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'wappay'.DIRECTORY_SEPARATOR.'buildermodel'.DIRECTORY_SEPARATOR.'AlipayTradeWapPayContentBuilder.php';
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'aop'.DIRECTORY_SEPARATOR.'request'.DIRECTORY_SEPARATOR.'AlipayTradePayRequest.php';
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'aop'.DIRECTORY_SEPARATOR.'request'.DIRECTORY_SEPARATOR.'AlipayTradeQueryRequest.php';
require_once dirname ( __FILE__ ).DIRECTORY_SEPARATOR.'aop'.DIRECTORY_SEPARATOR.'AopClient.php';

class alipay {

	//支付宝网关地址
	public $gateway_url = "https://openapi.alipay.com/gateway.do";

	//应用id
	public $appid='2016122904703781';

	//编码格式
	public $charset = "utf-8";

	public $token = NULL;
	
	//返回数据格式
	public $format = "json";

	//签名方式
	public $signtype = "RSA";

    //支付接口配置信息
    private $payment;

    //订单信息
    private $order;

    //发送至支付宝的参数
    private $parameter;

    //支付宝公钥
    public $alipay_public_key;

    //商户私钥
    public $private_key;

    //AlipayTradeWapPayContentBuilder对象
    private  $payRequestBuilder = null;

    //AlipayTradePayRequest对象
    private $AlipayTradePayRequest=null;

	//AlipayTradeQueryContentBuilder对象
	private $AlipayTradeQueryContentBuilder=null;

	//额外参数
	private $extend_arr = array();

    /*
     * 实例化支付类
     * */
	function __construct($payment_info = array(),$order_info = array(),$extend_arr=array())
    {
        if(!empty($payment_info)){
            $this->payment	= $payment_info;
            $this->payment['payment_config']['sign_type'] = $this->signtype;
            $this->order	= $order_info;
        }

        //获取公钥和私钥
        $this->_getprivatekey();
        $this->_getpublickey();

        //AlipayTradePayRequest对象
        $this->AlipayTradePayRequest = new AlipayTradePayRequest();

        $this->parameter = array(
            //'app_id'		    => $this->appid,	//合作伙伴ID
            'seller_id' => $this->payment['payment_config']['alipay_partner'],	//合作伙伴ID
            //'private_key'=>$this->private_key,//私钥
            //'alipay_public_key'=>$this->alipay_public_key,//公钥
            //'charset'	=> $this->charset,					//网站编码
            //'sign_type'=>$this->signtype,//签名方式
            //'return_url'	    => HTTP_SITE_HOST."/mobile/payment_notify/returnpay/payment_code/alipay",	//同步通知URL
            //'notify_url'	    => HTTP_SITE_HOST."/mobile/payment_notify/notify/payment_code/alipay",	//异步通知URL
            'order_type'=> $this->order['order_type'],
            'subject'		    => $this->order['subject'],	//商品名称
            'body'			    => $this->order['pay_sn'],	//商品描述
            'out_trade_no'	    => $this->order['pay_sn'],		//外部交易编号
            'receive_name'		=> $this->order['order_list'][0]['member_name'],//收货人姓名
            'seller_email'		=> $this->payment['payment_config']['alipay_account'],	//卖家邮箱
            'total_amount'             => "{$this->order['api_pay_amount']}",//订单总价
            'timeout_express' =>'1c',//超时时间
        );
		$this->extend_arr = $extend_arr;

		if(empty($this->appid)||trim($this->appid)=="") throw new Exception("应用ID不能为空!");
		if(empty($this->private_key)||trim($this->private_key)=="") throw new Exception("商户私钥不能为空!");
		if(empty($this->alipay_public_key)||trim($this->alipay_public_key)=="") throw new Exception("支付宝公钥不能为空!");
		if(empty($this->charset)||trim($this->charset)=="") throw new Exception("字符编码不能为空!");
		if(empty($this->gateway_url)||trim($this->gateway_url)=="") throw new Exception("支付宝网关地址不能为空");


	}

    /**
     * 手机网站支付接口的方法
     * alipay.trade.wap.pay
     * @param $builder 业务参数，使用buildmodel中的对象生成。
     * @param $return_url 同步跳转地址，公网可访问
     * @param $notify_url 异步通知地址，公网可以访问
     * @return $response 支付宝返回的信息
     */
    function aliPay()
    {
		//AlipayTradeWapPayContentBuilder对象
		$this->payRequestBuilder = new AlipayTradeWapPayContentBuilder();

		$this->payRequestBuilder->setTimeExpress($this->parameter['timeout_express']);
		$this->payRequestBuilder->setSellerId($this->parameter['seller_id']);
		$this->payRequestBuilder->setTotalAmount($this->parameter['total_amount']);
		$this->payRequestBuilder->setSubject($this->parameter['subject']);
		$this->payRequestBuilder->setBody($this->parameter['body']);
		$this->payRequestBuilder->setOutTradeNo($this->parameter['out_trade_no']);
		$this->payRequestBuilder->setPassback_Params(urlencode('order_type='.$this->parameter['order_type']));

        $biz_content=$this->payRequestBuilder->getBizContent();

        //打印业务参数
        $this->writeLog($biz_content);
		$return_url = HTTP_SITE_HOST."/mobile/payment_notify/returnpay/payment_code/alipay";	//同步通知URL
		$notify_url = HTTP_SITE_HOST."/mobile/payment_notify/notify/payment_code/alipay";	//异步通知URL
        $this->AlipayTradePayRequest->setNotifyUrl($return_url);
        $this->AlipayTradePayRequest->setReturnUrl($notify_url);
        $this->AlipayTradePayRequest->setBizContent ( $biz_content );

        // 首先调用支付api
        $response = $this->aopclientRequestExecute($this->AlipayTradePayRequest,true);
        return $response;
    }

    /*
      * SDK请求方法
      * */
    function aopclientRequestExecute($request,$ispage=false)
    {
        $aop = new AopClient ();
        $aop->gatewayUrl = $this->gateway_url;
        $aop->appId = $this->appid;
        $aop->rsaPrivateKey =  $this->private_key;
        $aop->alipayrsaPublicKey = $this->alipay_public_key;
        $aop->apiVersion ="1.0";
        $aop->postCharset = $this->charset;
        $aop->format= $this->format;
        $aop->signType=$this->signtype;
        // 开启页面信息输出
        $aop->debugInfo=true;
        if($ispage) {
            $result = $aop->pageExecute($request,"GET");
        } else {
            $result = $aop->Execute($request);
        }

        //打开后，将报文写入log文件
        $this->writeLog("response: ".var_export($result,true));
        return $result;
    }

    /*
     * 获取支付宝公钥
     * */
    private function _getpublickey(){
			$this->alipay_public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDDI6d306Q8fIfCOaTXyiUeJHkrIvYISRcc73s3vF1ZT7XN8RNPwJxo8pWaJMmvyTn9N4HQ632qJBVHf8sxHi/fEsraprwCtzvzQETrNRwVxLO5jVmRGi60j8Ue1efIlzPXV9je9mkjzOmdssymZkh2QhUrCmZYI/FCEa3/cNMW0QIDAQAB';
    }

    /*
     * 获取商户私钥
     * */
    private function _getprivatekey(){
			$this->private_key = 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQChr6XgLxemm0+oztUdlmEy+ptX44cxqwHzQ7Idu012KFYqvdsQ8vJSFiJGbfRah5t4g9d2Faegzam/ZKzsLKrIQeYBCyi/5bKu4OXfmugBBkb9CcY9oowgpOfp1kRvHjDemrAPAmD1V7Bd5ArXCBYjjPaHzBU2iMNpZKEr9T9AR4b+b8Z3zZsLqVpqDtx/RyHHZYAtl/a85ni9lk/ztMxS8Of8+pfhMsXeY+tGCoqfkEbAuGcvYiaJq7+MfadCOk/mTMIPUJHieuU7HzsPEcBo/60ncDF+iWPxu3xe/BW5QopFsN3FQ5iEl0tW8PlRbctTQSBF3bBioZBo89KCLmJ5AgMBAAECggEAA74aG9dbN8sOi/zFqBqsA08Tu3QT1A1+TRA7Fj8YquyCGhks8fZ9r3K9tl2jM1lCOwbqPNyBddJs5HZDHnBmP55u4YVNmyuI+E1SZNukFSn9CKxqP1D852CJ0brY+K19Ohngi2hlsCgod/PoYFPal1YS+s+5ifqec1kV9fuGTEmSJihCN6ZIO+dbXGxK+FDWZfozKhNCpVjAIA+8pwjwK1EqJU6g3lKoiDMUh/w/lufJyqVxc5gBCmbeBADbU5D3DvlGHUujzAR4bMehw3qsP2qRJS3fp4C8ae1Mn/aEOk46EQxOFLCM38gLM/5ebPjL2Op54oaILcoBKFBqAhL6AQKBgQDMV8NMFuq+Y7gvybW7KyXu0ydEJ403k7cTBsi2gR59HL6c6FbxnwC9yKsoqfjDgBtiqVptq/0WUp3fdouMVTFPn8PPNcvB/sCyaLOTy/lXTPSvS2T1XqDQPYuL1MKgloWq1Gjv+x1ig/GQvWAuTVPYrDKNuVGk6fG1cHH1GCwauQKBgQDKj1EebEbz6agNIawXRPlr2v9vHBO83Vj//LDKbL/1Dc7RqrMlK35VOr6LFBc/7MRBrS5elzsa8mMGlWBqK0GY1u6xRum2IMVuLmHGIBbwGQlgytPpXyAcc5pUkR6X7ddN7hin7STECZruZqyRE2unxjjLaztuI29megOVGt2lwQKBgFrbmwa2GeJVzIvTEG8MnG04jfkL7QqNL5XSKmSbvMa8hTSXSjFdFaNNGm2WRfoHeCXCT2b8Vigay/+UYjAfoTFaRGJZ9SNo9p1dWJua0l8y5Ikc6OMBFFgDRY0DKRbmVsDCeXZbHZG0QTCuQ5nS4DkzN7c0c0z0iHi4arMj8FhhAoGBAJlaBz/RGaZvrxrB81dqkKpnEhs8VnUV3ttuoymdS3ZrDbkOUrJBS1ObNcZ1X2S2C57tTb2vIMA14WKKlIPMW80qa2srFKUeClpwIvWsNbFwQvlUlTqJGfZwTtbXRyIennIRX/lCQCNqYjE66kqrOUW7fCQE+ulXSm960FuCC2wBAoGABkrkG/CPd2hKJFEw6eRvkSE3j5NmJ8cRUHISpXGrAZ9+kp2ZFijHktNXi4VjTbNNckhO3QKnNJgkAWTpxkusJ8zKcELwCPIyKpTsrTO9G8ZZZtRj0n1bAIg92Gmkso3Rpiev55CNC7WvNHBLdow7z7a7hZ46TzGxA3XyZBz+IYM=';
    }


	/**
	 * alipay.trade.query (统一收单线下交易查询)
     * 手机网站查询接口
	 * @param $builder 业务参数，使用buildmodel中的对象生成。
	 * @return $response 支付宝返回的信息
 	*/
	function Query()
	{
		//AlipayTradeQueryContentBuilder对象
		$this->AlipayTradeQueryContentBuilder = new AlipayTradeQueryContentBuilder();
		$this->AlipayTradeQueryContentBuilder->setTradeNo($this->extend_arr['trade_no']);
		$this->AlipayTradeQueryContentBuilder->setOutTradeNo($this->parameter['out_trade_no']);
		$biz_content=$this->AlipayTradeQueryContentBuilder->getBizContent();

		//打印业务参数
		$this->writeLog($biz_content);
		$request = new AlipayTradeQueryRequest();
		$request->setBizContent ( $biz_content );

		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_trade_query_response;
		return $response;
	}

	/**
	 *
	 * 取得订单支付状态，成功或失败
	 * @param array $param
	 * @return array
	 */
	public function getPayResult($param){
		return $param['resultStatus'] == '9000';
	}
	
	/**
	 * alipay.trade.refund (统一收单交易退款接口)
     * 手机网站退款接口
	 * @param $builder 业务参数，使用buildmodel中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	function Refund($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);
		$request = new AlipayTradeRefundRequest();
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_trade_refund_response;
		var_dump($response);
		return $response;
	}

	/**
	 * alipay.trade.close (统一收单交易关闭接口)
     * 手机网站关闭接口
	 * @param $builder 业务参数，使用buildmodel中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	function Close($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);
		$request = new AlipayTradeCloseRequest();
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_trade_close_response;
		var_dump($response);
		return $response;
	}
	
	/**
	 * 退款查询   alipay.trade.fastpay.refund.query (统一收单交易退款查询)
     * 手机网站退款查询接口
	 * @param $builder 业务参数，使用buildmodel中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	function refundQuery($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);
		$request = new AlipayTradeFastpayRefundQueryRequest();
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request);
		var_dump($response);
		return $response;
	}
	/**
     * 手机网站账单下载接口
	 * alipay.data.dataservice.bill.downloadurl.query (查询对账单下载地址)
	 * @param $builder 业务参数，使用buildmodel中的对象生成。
	 * @return $response 支付宝返回的信息
	 */
	function downloadurlQuery($builder){
		$biz_content=$builder->getBizContent();
		//打印业务参数
		$this->writeLog($biz_content);
		$request = new alipaydatadataservicebilldownloadurlqueryRequest();
		$request->setBizContent ( $biz_content );
	
		// 首先调用支付api
		$response = $this->aopclientRequestExecute ($request);
		$response = $response->alipay_data_dataservice_bill_downloadurl_query_response;
		var_dump($response);
		return $response;
	}

	/**
	 * 验签方法
     * 支付宝返回的信息验签
	 * @param $arr 验签支付宝返回的信息，使用支付宝公钥。
	 * @return boolean
	 */
	function check($arr){
		$aop = new AopClient();
        $aop->gatewayUrl = $this->gateway_url;
        $aop->appId = $this->appid;
        $aop->rsaPrivateKey =  $this->private_key;
        $aop->alipayrsaPublicKey = $this->alipay_public_key;
        $aop->apiVersion ="1.0";
        $aop->postCharset = $this->charset;
        $aop->format= $this->format;
        $aop->signType=$this->signtype;
		$result = $aop->rsaCheckV1($arr, $this->alipay_public_key, $this->signtype);
		return $result;
	}
	
	//请确保项目文件有可写权限，不然打印不了日志。
    //打印日志
	function writeLog($text) {
		file_put_contents ( dirname ( __FILE__ ).DIRECTORY_SEPARATOR."log.txt", date ( "Y-m-d H:i:s" ) . "  " . $text . "\r\n", FILE_APPEND );
	}
	

	/** *利用google api生成二维码图片
	 * $content：二维码内容参数
	 * $size：生成二维码的尺寸，宽度和高度的值
	 * $lev：可选参数，纠错等级
	 * $margin：生成的二维码离边框的距离
	 */
	function create_erweima($content, $size = '200', $lev = 'L', $margin= '0') {
		$content = urlencode($content);
		$image = '<img src="http://chart.apis.google.com/chart?chs='.$size.'x'.$size.'&amp;cht=qr&chld='.$lev.'|'.$margin.'&amp;chl='.$content.'"  widht="'.$size.'" height="'.$size.'" />';
		return $image;
	}
}

?>
