<?php
/* *
 * 功能：手机控件支付类 专用于ios sdk
 * 版本：5.1.0
 * 修改日期：2017-12-18日
 * Author:wu.li
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 */
use com\unionpay\acp\sdk\LogUtil;
use com\unionpay\acp\sdk\AcpService;
use com\unionpay\acp\sdk\PhpLog;
define('PAYPATH',str_replace('\\','/',dirname(__FILE__)));
require_once PAYPATH.'/sdk/acp_service.php';
include_once PAYPATH.'/sdk/log.class.php';
include_once PAYPATH.'/sdk/common.php';
class chinabank
{
    //支付接口配置信息
    private $payment;

    //订单信息
    private $order;

    //发送至银联的参数
    private $parameter;

    //SDK配置
    private $sdkconfig=null;

	//额外参数
	private $extend_arr = array();

	//查询结果
	public $trade_status='';

	//网银订单号
	public $trade_no='';

    /*
     * 实例化支付类
     * */
    function __construct($payment_info = array(),$order_info = array(),$extend_arr=array())
    {
        if (!empty($payment_info)) {
            $this->payment = $payment_info;
            $this->order = $order_info;
			$this->extend_arr = $extend_arr;
        }
        $this->sdkconfig = com\unionpay\acp\sdk\SDKConfig::getSDKConfig($payment_info);
    }

    /**
     * 手机控件支付方法
     * alipay.trade.wap.pay
     * @param $builder 业务参数，使用buildmodel中的对象生成。
     * @param $return_url 同步跳转地址，公网可访问
     * @param $notify_url 异步通知地址，公网可以访问
     * @return $response 支付宝返回的信息
     */
    function aliPay()
    {
		$payment_time = date('YmdHis');
        $this->parameter = array(
            //以下信息非特殊情况不需要改动
            'version' => $this->sdkconfig->version,                 //版本号
            'encoding' => 'utf-8',				  //编码方式
            'txnType' => '01',				      //交易类型 固定填写
            'txnSubType' => '01',				  //交易子类 固定填写
            'bizType' => '000201',				  //业务类型 固定填写
            'frontUrl' =>  HTTP_SITE_HOST.$this->sdkconfig->frontUrl,  //前台通知地址
            'backUrl' => HTTP_SITE_HOST.$this->sdkconfig->backUrl,	  //后台通知地址
            'signMethod' => $this->sdkconfig->signMethod,	              //签名方式，证书方式固定01，请勿改动
            'channelType' => '08',	              //渠道类型，07-PC，08-手机
            'accessType' => '0',		          //接入类型
            'currencyCode' => '156',	          //交易币种，境内商户固定156

            //TODO 以下信息需要填写
            'merId' => $this->payment['payment_config']['chinabank_account'],		//商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
            'orderId' => $this->order['pay_sn'],	//商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
            'txnTime' => $payment_time,	//订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
            'txnAmt' => $this->order['api_pay_amount'] * 100,	//交易金额，单位分，此处默认取demo演示页面传递的参数

            // 请求方保留域，
            // 透传字段，查询、通知、对账文件中均会原样出现，如有需要请启用并修改自己希望透传的数据。
            // 出现部分特殊字符时可能影响解析，请按下面建议的方式填写：
            // 1. 如果能确定内容不会出现&={}[]"'等符号时，可以直接填写数据，建议的方法如下。
            //    'reqReserved' =>'透传信息1|透传信息2|透传信息3',
            // 2. 内容可能出现&={}[]"'符号时：
            // 1) 如果需要对账文件里能显示，可将字符替换成全角＆＝｛｝【】“‘字符（自己写代码，此处不演示）；
            // 2) 如果对账文件没有显示要求，可做一下base64（如下）。
            //    注意控制数据长度，实际传输的数据长度不能超过1024位。
            //    查询、通知等接口解析时使用base64_decode解base64后再对数据做后续解析。
            //    'reqReserved' => base64_encode('任意格式的信息都可以'),
            'reqReserved' => base64_encode(json_encode(array('pay_sn'=>$this->order['pay_sn'],'order_type'=>$this->order['order_type']))),

            //TODO 其他特殊用法请查看 pages/api_05_app/special_use_purchase.php
        );
        $sign_result=com\unionpay\acp\sdk\AcpService::sign ( $this->parameter); // 签名
        if(!$sign_result){
            throw new Exception('付款签名错误，请检测参数');
        }else{
            $logger = LogUtil::getLogger();
            $url = com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->appTransUrl;
            //后台交易 HttpClient通信
            $result_arr = com\unionpay\acp\sdk\AcpService::post ($this->parameter,$url);
            if(count($result_arr)<=0) { //没收到200应答的情况
                $logger->LogError($this->printResult ($url, $this->parameter, "" ));
                throw new Exception('没收到任何请求请重试');
            }else{
               // printResult ($url, $this->parameter, $result_arr ); //页面打印请求应答数据
                $response_result = com\unionpay\acp\sdk\AcpService::validate ($result_arr);
                if (!$response_result){
                    $logger->LogError($this->printResult ($url, $this->parameter, $result_arr ));
                    throw new Exception('应答报文验签失败请重试');
                }else{
                    if ($result_arr["respCode"] == "00"){
                        return array('payment_time'=>$payment_time,'tn'=>$result_arr["tn"]);
//                        echo "成功接收tn：" . $result_arr["tn"] . "<br>\n";
//                        echo "后续请将此tn传给手机开发，由他们用此tn调起控件后完成支付。<br>\n";
//                        echo "手机端demo默认从仿真获取tn，仿真只返回一个tn，如不想修改手机和后台间的通讯方式，【此页面请修改代码为只输出tn】。<br>\n";
                    } else {
                        //其他应答码做以失败处理
                        $logger->LogError($this->printResult ($url, $this->parameter, $result_arr ));
                        throw new Exception($result_arr["respMsg"]);
                    }
                }
            }
        }
    }


	/**
	 * 手机控件支付查询
	 *
	 */
	public function Query()
	{
		$this->parameter = array(
			//以下信息非特殊情况不需要改动
			'version' => $this->sdkconfig->version,		  //版本号
			'encoding' => 'utf-8',		  //编码方式
			'signMethod' => $this->sdkconfig->signMethod,		  //签名方法
			'txnType' => '00',		      //交易类型
			'txnSubType' => '00',		  //交易子类
			'bizType' => '000000',		  //业务类型
			'accessType' => '0',		  //接入类型
			'channelType' => '07',		  //渠道类型

			//TODO 以下信息需要填写
			'orderId' => $this->order['pay_sn'],	//请修改被查询的交易的订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数
			'merId' => $this->payment['payment_config']['chinabank_account'],	    //商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
			'txnTime' => $_POST["txnTime"],	//请修改被查询的交易的订单发送时间，格式为YYYYMMDDhhmmss，此处默认取demo演示页面传递的参数
		);

		$result=AcpService::sign ( $this->parameter ); // 签名
		$url = com\unionpay\acp\sdk\SDKConfig::getSDKConfig()->singleQueryUrl;
		$logger = LogUtil::getLogger();
		if($result)
		{
			$result_arr = com\unionpay\acp\sdk\AcpService::post ( $this->parameter, $url);
			if(count($result_arr)>0) //没收到200应答的情况
			{
				if ($result_arr["respCode"] == "00")
				{
					if ($result_arr["origRespCode"] == "00") {
						$this->trade_no = $this->extend_arr['trade_no'];
						$this->trade_status='TRADE_SUCCESS';
					} else if ($result_arr["origRespCode"] == "03" || $result_arr["origRespCode"] == "04" || $result_arr["origRespCode"] == "05"){
						$logger->LogError($this->printResult ($url, $this->parameter,'交易处理中， 请稍后查询'.$result_arr["respMsg"]. '<br>\n'));
					} else {
						//其他应答码做以失败处理
						$logger->LogError($this->printResult ($url, $this->parameter,'交易失败'.$result_arr["origRespMsg"].'<br>\n'));
					}
				} else if ($result_arr["respCode"] == "03" || $result_arr["respCode"] == "04" || $result_arr["respCode"] == "05" ){
					$logger->LogError($this->printResult ($url, $this->parameter,'处理超时，请稍后查询 '.$result_arr["respMsg"]. '<br>\n'));
				} else {
					$logger->LogError($this->printResult ($url, $this->parameter,'交易失败，'.$result_arr["respMsg"]. '<br>\n'));
				}
			}else{
				$logger->LogError($this->printResult ($url, $this->parameter,'没收到200应答的情况'.$result_arr["respMsg"]. '<br>\n'));
			}
		}else{
			$logger->LogError($this->printResult ($url, $this->parameter,'签名失败<br>\n'));
		}
		return $this;
	}


    /**
     * 打印请求应答
     *
     * @param
     *        	$url
     * @param
     *        	$req
     * @param
     *        	$resp
     */
    private function printResult($url, $req, $resp) {
        $return_log_str="=============<br>\n";
        $return_log_str.="地址：" . $url . "<br>\n";
        $return_log_str.= "请求：" . str_replace ( "\n", "\n<br>", htmlentities ( com\unionpay\acp\sdk\createLinkString ( $req, false, true ) ) ) . "<br>\n";
        $return_log_str.= "应答：" . str_replace ( "\n", "\n<br>", htmlentities ( com\unionpay\acp\sdk\createLinkString ( $resp , false, false )) ) . "<br>\n";
        $return_log_str.= "=============<br>\n";
		return $return_log_str;
    }
}
