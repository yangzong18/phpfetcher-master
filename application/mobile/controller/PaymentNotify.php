<?php
/**
 * 订单付款
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang at 2016/12/05
 */

namespace app\mobile\controller;
use app\common\controller\PayMobile;
use app\common\logic\RealOrderLogic;
use app\common\logic\LogsOrderLogic;
use app\common\logic\CrowdOrderLogic;
use think\Log;
class PaymentNotify extends Mobile
{
    /**
     * 构造器
     */
    public function __construct()
    {
        parent::__construct();
    }

    //  111.9.116.134:8084/mobile/payment_notify/test/payment_code/chinabank
    public function test()
    {
        exit('测试使用，，，暂不开放');
        $json = '{"accessType":"0","bizType":"000201","currencyCode":"156","encoding":"utf-8","merId":"777290058143591","orderId":"8405409067726348b2","queryId":"201702201311592118098","reqReserved":"eyJwYXlfc24iOiI4NDA1NDA5MDY3NzI2MzQ4YjIiLCJvcmRlcl90eXBlIjoyfQ==","respCode":"00","respMsg":"Success","settleAmt":"400","settleCurrencyCode":"156","settleDate":"0220","signMethod":"01","signPubKeyCert":"-----BEGIN CERTIFICATE-----\r\nMIIEOjCCAyKgAwIBAgIFEAJkAUkwDQYJKoZIhvcNAQEFBQAwWDELMAkGA1UEBhMC\r\nQ04xMDAuBgNVBAoTJ0NoaW5hIEZpbmFuY2lhbCBDZXJ0aWZpY2F0aW9uIEF1dGhv\r\ncml0eTEXMBUGA1UEAxMOQ0ZDQSBURVNUIE9DQTEwHhcNMTUxMjA0MDMyNTIxWhcN\r\nMTcxMjA0MDMyNTIxWjB5MQswCQYDVQQGEwJjbjEXMBUGA1UEChMOQ0ZDQSBURVNU\r\nIE9DQTExEjAQBgNVBAsTCUNGQ0EgVEVTVDEUMBIGA1UECxMLRW50ZXJwcmlzZXMx\r\nJzAlBgNVBAMUHjA0MUBaMTJAMDAwNDAwMDA6U0lHTkAwMDAwMDA2MjCCASIwDQYJ\r\nKoZIhvcNAQEBBQADggEPADCCAQoCggEBAMUDYYCLYvv3c911zhRDrSWCedAYDJQe\r\nfJUjZKI2avFtB2\/bbSmKQd0NVvh+zXtehCYLxKOltO6DDTRHwH9xfhRY3CBMmcOv\r\nd2xQQvMJcV9XwoqtCKqhzguoDxJfYeGuit7DpuRsDGI0+yKgc1RY28v1VtuXG845\r\nfTP7PRtJrareQYlQXghMgHFAZ\/vRdqlLpVoNma5C56cJk5bfr2ngDlXbUqPXLi1j\r\niXAFb\/y4b8eGEIl1LmKp3aPMDPK7eshc7fLONEp1oQ5Jd1nE\/GZj+lC345aNWmLs\r\nl\/09uAvo4Lu+pQsmGyfLbUGR51KbmHajF4Mrr6uSqiU21Ctr1uQGkccCAwEAAaOB\r\n6TCB5jAfBgNVHSMEGDAWgBTPcJ1h6518Lrj3ywJA9wmd\/jN0gDBIBgNVHSAEQTA\/\r\nMD0GCGCBHIbvKgEBMDEwLwYIKwYBBQUHAgEWI2h0dHA6Ly93d3cuY2ZjYS5jb20u\r\nY24vdXMvdXMtMTQuaHRtMDgGA1UdHwQxMC8wLaAroCmGJ2h0dHA6Ly91Y3JsLmNm\r\nY2EuY29tLmNuL1JTQS9jcmw0NDkxLmNybDALBgNVHQ8EBAMCA+gwHQYDVR0OBBYE\r\nFAFmIOdt15XLqqz13uPbGQwtj4PAMBMGA1UdJQQMMAoGCCsGAQUFBwMCMA0GCSqG\r\nSIb3DQEBBQUAA4IBAQB8YuMQWDH\/Ze+e+2pr\/914cBt94FQpYqZOmrBIQ8kq7vVm\r\nTTy94q9UL0pMMHDuFJV6Wxng4Me\/cfVvWmjgLg\/t7bdz0n6UNj4StJP17pkg68WG\r\nzMlcjuI7\/baxtDrD+O8dKpHoHezqhx7dfh1QWq8jnqd3DFzfkhEpuIt6QEaUqoWn\r\nt5FxSUiykTfjnaNEEGcn3\/n2LpwrQ+upes12\/B778MQETOsVv4WX8oE1Qsv1XLRW\r\ni0DQetTU2RXTrynv+l4kMy0h9b\/Hdlbuh2s0QZqlUMXx2biy0GvpF2pR8f+OaLuT\r\nAtaKdU4T2+jO44+vWNNN2VoAaw0xY6IZ3\/A1GL0x\r\n-----END CERTIFICATE-----","traceNo":"211809","traceTime":"0220131159","txnAmt":"400","txnSubType":"01","txnTime":"20170220131159","txnType":"01","version":"5.1.0","signature":"OGiSlLqvvLZ8QoqJ3amcjezLzz0tcFhKk0tvtWunDBk0vuDIZiLQz5nO08Mm8aZcqNjq5UeW3512tdu53HgH0EAL4G5q2JC6Oytp41WP13wc8dxJK1s7UAH9beVRSOEmjAseBfITuTb1fVj4tvBGlcxTNzZ2VFhBcVopbzsizsifCQTXn0+COw6hydAlAA9HrUUF+Kqg6m8JQ25XXYuApqHZ1wajlHyxojshmUJjryaTjZ3\/sNx0a6gzImOERZMVpIL4jTAsI8mvut+zdrGIA98zi7E39DDBBXguEwBB+pj9gjml\/m46u3bAM7Ru9JnDRxT9h4Jq9aA0FXTzfCUkEQ=="}';
        $json_arr = json_decode($json,true);
        $_POST = $json_arr;

		$payment_code    = trim($this->request->param('payment_code'));
		if(empty($payment_code))  exit('fail');
		if($payment_code=='alipay' ){//支付宝
			Log::write('POST 手机端宝支付接口返回异步通知: '.json_encode($_POST).'\r\n');
			$success = 'success';
			$fail = 'fail';
			$passback_params = parse_str(urldecode($_POST['passback_params']));
			$order_type = 'real_order';
			$out_trade_no = $_POST['out_trade_no'];
			$trade_no = $_POST['trade_no'];
		}else if($payment_code=='chinabank'){//网银
			//log::write('POST 手机端网银支付接口返回异步通知: '.json_encode($_POST).'\r\n');
			$success = 'success';
			$fail = 'fail';
			$out_trade_no = $_POST['queryId'];
			//base64_encode(json_encode(array('pay_sn'=>$this->order['pay_sn'],'order_type'=>2)))
			$reqReserved_arr = json_decode(base64_decode($_POST['reqReserved']),true);
			if(empty($reqReserved_arr)) exit($fail);
			$order_type = 'real_order';
			$trade_no = $_POST['queryId'];
			$out_trade_no = $reqReserved_arr['pay_sn'];
		}else{
			exit('fail');
		}

		if(empty($order_type)) exit($fail);
		$result = new PayMobile($out_trade_no,$payment_code);
		if(!is_object($result))  exit($fail);
		$rs = $result->returnpay($order_type,$trade_no,'notify_verify');
		exit($rs['state'] ? $success : $fail);
    }

    /**
     * 支付接口返回同步通知
     * 其中： $order_type ：real_order=实物订单  crowd_order=众筹订单  logs_order=整装订单
     *       $out_trade_no : 支付单号pay_sn
     *       $trade_no : 支付接口的交易号
     */
    public function returnpay()
    {
        //支付接口编号
        $payment_code    = trim($this->request->param('payment_code'));

        if(empty($payment_code)) {
            $this->returnJson('',1,'参数错误');
        }else{
			if($payment_code=='alipay'){//如果是支付宝
				Log::write('POST 手机端支付宝支付接口返回同步通知: '.json_encode($this->request->param()).'\r\n');
				$order_type = 'real_order';
				$out_trade_no = $this->request->post('out_trade_no');
				$trade_no = $this->request->post('trade_no');
			}elseif($payment_code=='chinabank'){//如果是网银支付
				Log::write('POST 手机端网银支付接口返回同步通知: '.json_encode($this->request->param()).'\r\n');
				//base64_encode(json_encode(array('pay_sn'=>$this->order['pay_sn'],'order_type'=>2)))
				$reqReserved_arr = json_decode(base64_decode($_POST['reqReserved']),true);
				if(empty($reqReserved_arr)) $this->returnJson('',1,'无效的返回参数');;
				$order_type = 'real_order';
				$trade_no = $_POST['queryId'];
				$out_trade_no = $reqReserved_arr['pay_sn'];
			}else{
				$this->returnJson('',1,'无效的支付方式');
			}

			$result = new PayMobile($out_trade_no,$payment_code);
			if(!is_object($result)) $this->error($result['msg']);
			$rs = $result->returnpay($order_type,$trade_no,'return_verify');
			if (!$rs['state']){
				$this->returnJson('',1,'支付失败：'.$rs['msg']);
			} else{
				$data=array();
				$data['pay_amount'] = $rs['data']['api_pay_amount'];//支付金额
				$data['order_list'] = $rs['data']['order_list'];
				$this->returnJson($data);
			}
        }
    }

    /**
     * 支付接口返回异步通知(支付宝异步通知和网银在线自动对账)
     * 其中： $order_type ：real_order=实物订单  crowd_order=众筹订单  logs_order=整装订单
     *       $out_trade_no : 支付单号pay_sn
     *       $trade_no : 支付接口的交易号
     */
    public function notify()
    {
		$payment_code    = trim($this->request->param('payment_code'));
		if(empty($payment_code))  exit('fail');
		if($payment_code=='alipay' ){//支付宝
			Log::write('POST 手机端支付宝支付接口返回异步通知: '.json_encode($_POST).'\r\n');
			$success = 'success';
			$fail = 'fail';
			$passback_params = parse_str(urldecode($_POST['passback_params']));
			$order_type = 'real_order';
			$out_trade_no = $_POST['out_trade_no'];
			$trade_no = $_POST['trade_no'];
		}else if($payment_code=='chinabank'){//网银
			Log::write('POST 手机端网银支付接口返回异步步通知: '.json_encode($_POST).'\r\n');
			$success = 'success';
			$fail = 'fail';
			//base64_encode(json_encode(array('pay_sn'=>$this->order['pay_sn'],'order_type'=>2)))
			$reqReserved_arr = json_decode(base64_decode($_POST['reqReserved']),true);
			if(empty($reqReserved_arr)) exit($fail);
			$order_type = 'real_order';
			$trade_no = $_POST['queryId'];
			$out_trade_no = $reqReserved_arr['pay_sn'];
		}else{
			exit('fail');
		}

		if(empty($order_type)) exit($fail);
		$result = new PayMobile($out_trade_no,$payment_code);
		if(!is_object($result))  exit($fail);
		$rs = $result->returnpay($order_type,$trade_no,'notify_verify');
		exit($rs['state'] ? $success : $fail);
    }
}
