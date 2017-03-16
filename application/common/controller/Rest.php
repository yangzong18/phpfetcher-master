<?php
/*
 * 短信发送类
 *  Copyright (c) 2014 The CCP project authors. All Rights Reserved.
 *
 *  Use of this source code is governed by a Beijing Speedtong Information Technology Co.,Ltd license
 *  that can be found in the LICENSE file in the root of the web site.
 *
 *   http://www.yuntongxun.com
 *
 *  An additional intellectual property rights grant can be found
 *  in the file PATENTS.  All contributing project authors may
 *  be found in the AUTHORS file in the root of the source tree.
 */
namespace app\common\controller;

class Rest {
	private $accountSid;
	private $accountToken;
	private $appId;
	private $subAccountSid;
	private $subAccountToken;
	private $serverIP;
	private $serverPort;
	private $softVersion;
	private $batch;  //时间sh
	private $bodyType = "xml";//包体格式，可填值：json 、xml
	private $enabeLog = true; //日志开关。可填值：true、
	//private $Filename="../log.txt"; //日志文件
	//private $Handle;

    /**
     * 构造方法
     * @param $serverIP 请求地址
     * @param $serverPort 端口
     * @param $softVersion 版本号
     */
	function __construct($serverIP, $serverPort, $softVersion) {
		$this->batch = date("YmdHis");
		$this->serverIP = $serverIP;
		$this->serverPort = $serverPort;
		$this->softVersion = $softVersion;
        //$this->Handle = fopen($this->Filename, 'a');
	}

   /**
    * 设置主帐号
    * 
    * @param accountSid 主帐号
    * @param accountToken 主帐号Token
    */
    function setAccount($accountSid, $accountToken) {
        $this->accountSid = $accountSid;
        $this->accountToken = $accountToken;
    }
    
   /**
    * 设置子帐号
    * 
    * @param subAccountSid 子帐号
    * @param subAccountToken 子帐号Token
    */    
    function setSubAccount($subAccountSid, $subAccountToken) {
        $this->subAccountSid = $subAccountSid;
        $this->subAccountToken = $subAccountToken;
    }
    
   /**
    * 设置应用ID
    * 
    * @param appId 应用ID
    */
    function setAppId($appId) {
       $this->appId = $appId;
    }
    
   /**
    * 打印日志
    * 
    * @param log 日志内容
    */
    function showlog($log) {
        if($this->enabeLog) {
        //TODO 存入日志操作
       }
    }
    
    /**
     * 发起HTTPS请求
     */
     function curl_post($url, $data, $header, $post=1) {
        //初始化curl
         $ch = curl_init();
         //参数设置
         $res= curl_setopt ($ch, CURLOPT_URL,$url);
         curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
         curl_setopt ($ch, CURLOPT_HEADER, 0);
         curl_setopt($ch, CURLOPT_POST, $post);
         if( $post )
             curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
         curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
         curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
         $result = curl_exec ($ch);
         //连接失败
         if($result == FALSE) {
          if($this->bodyType=='json') {
             $result = "{\"statusCode\":\"172001\",\"statusMsg\":\"网络错误\"}";
          } else {
             $result = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Response><statusCode>172001</statusCode><statusMsg>网络错误</statusMsg></Response>"; 
          }    
       }

       curl_close($ch);
       return $result;
     } 

    /**
    * 创建子帐号
    * @param friendlyName 子帐号名称
    */
	  function createSubAccount($friendlyName) {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 拼接请求包体
        if($this->bodyType=="json"){
           $body= "{'appId':'$this->appId','friendlyName':'$friendlyName'}";
        }else{
           $body="<SubAccount>
                    <appId>$this->appId</appId>
                    <friendlyName>$friendlyName</friendlyName>
                  </SubAccount>";
        }
        $this->showlog("request body = ".$body);
        // 大写的sig参数  
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/SubAccounts?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐号Id + 英文冒号 + 时间戳
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头 
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas;
	  }
        
    /**
    * 获取子帐号
    * @param startNo 开始的序号，默认从0开始
    * @param offset 一次查询的最大条数，最小是1条，最大是100条
    */
    function getSubAccounts($startNo, $offset) {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 拼接请求包体
        $body="
            <SubAccount>
              <appId>$this->appId</appId>
              <startNo>$startNo</startNo>  
              <offset>$offset</offset>
            </SubAccount>";
        if($this->bodyType=="json"){
           $body= "{'appId':'$this->appId','startNo':'$startNo','offset':'$offset'}";
        }else{
        	 $body="
            <SubAccount>
              <appId>$this->appId</appId>
              <startNo>$startNo</startNo>  
              <offset>$offset</offset>
            </SubAccount>";
        }
        $this->showlog("request body = ".$body);
        // 大写的sig参数  
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/GetSubAccounts?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头 
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas;
    }
        
   /**
    * 子帐号信息查询
    * @param friendlyName 子帐号名称
    */
    function querySubAccount($friendlyName) {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 拼接请求包体
        
        if($this->bodyType=="json"){
           $body= "{'appId':'$this->appId','friendlyName':'$friendlyName'}";
        }else{
        	 $body="
            <SubAccount>
              <appId>$this->appId</appId>
              <friendlyName>$friendlyName</friendlyName>
            </SubAccount>";
        }
        $this->showlog("request body = ".$body);
        // 大写的sig参数  
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/QuerySubAccountByName?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头 
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas; 
    }
    
   /**
    * 发送模板短信
    * @param to 短信接收彿手机号码集合,用英文逗号分开
    * @param datas 内容数据
    * @param $tempId 模板Id
    */       
    function sendTemplateSMS($to, $datas, $tempId) {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 拼接请求包体
        if($this->bodyType=="json"){
           $data="";
           for($i=0;$i<count($datas);$i++){
              $data = $data. "'".$datas[$i]."',"; 
           }
           $body= "{'to':'$to','templateId':'$tempId','appId':'$this->appId','datas':[".$data."]}";
        }else{
           $data="";
           for($i=0;$i<count($datas);$i++){
              $data = $data. "<data>".$datas[$i]."</data>"; 
           }
           $body="<TemplateSMS>
                    <to>$to</to> 
                    <appId>$this->appId</appId>
                    <templateId>$tempId</templateId>
                    <datas>".$data."</datas>
                  </TemplateSMS>";
        }
        $this->showlog("request body = ".$body);
        // 大写的sig参数 
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL        
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/SMS/TemplateSMS?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头  
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        //重新装填数据
        if($datas->statusCode==0){
         if($this->bodyType=="json"){
            $datas->TemplateSMS =$datas->templateSMS;
            unset($datas->templateSMS);   
          }
        }
 
        return $datas; 
    } 

    
    /**
    * 外呼通知
    * @param to 被叫号码
    * @param mediaName 语音文件名称，格式 wav。与mediaTxt不能同时为空。当不为空时mediaTxt属性失效。
    * @param mediaTxt 文本内容
    * @param displayNum 显示的主叫号码
    * @param playTimes 循环播放次数，1－3次，默认播放1次。
    * @param respUrl 外呼通知状态通知回调地址，云通讯平台将向该Url地址发送呼叫结果通知。
    * @param userData 用户私有数据
    * @param maxCallTime 最大通话时长
    * @param speed 发音速度
    * @param volume 音量
    * @param pitch 音调
    * @param bgsound 背景音编号
    */
    function landingCall($to,$mediaName,$mediaTxt,$displayNum,$playTimes,$respUrl,$userData,$maxCallTime,$speed,$volume,$pitch,$bgsound) {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        } 
        // 拼接请求包体
        if($this->bodyType=="json"){
           $body= "{'playTimes':'$playTimes','mediaTxt':'$mediaTxt','mediaName':'$mediaName','to':'$to','appId':'$this->appId','displayNum':'$displayNum','respUrl':'$respUrl',
           'userData':'$userData','maxCallTime':'$maxCallTime','speed':'$speed','volume':'$volume','pitch':'$pitch','bgsound':'$bgsound'}";
        }else{
           $body="<LandingCall>
                    <to>$to</to>
                    <mediaName>$mediaName</mediaName>
                    <mediaTxt>$mediaTxt</mediaTxt> 
                    <appId>$this->appId</appId>
                    <displayNum>$displayNum</displayNum>
                    <playTimes>$playTimes</playTimes>
                    <respUrl>$respUrl</respUrl>
                    <userData>$userData</userData>
                    <maxCallTime>$maxCallTime</maxCallTime>
                    <speed>$speed</speed>
                    <volume>$volume</volume>
                    <pitch>$pitch</pitch>
                    <bgsound>$bgsound</bgsound>
                  </LandingCall>";
        }
        $this->showlog("request body = ".$body);
        // 大写的sig参数
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL  
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/Calls/LandingCalls?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头  
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas;
    }
    
    /**
    * 语音验证码
    * @param verifyCode 验证码内容，为数字和英文字母，不区分大小写，长度4-8位
    * @param playTimes 播放次数，1－3次
    * @param to 接收号码
    * @param displayNum 显示的主叫号码
    * @param respUrl 语音验证码状态通知回调地址，云通讯平台将向该Url地址发送呼叫结果通知
    * @param lang 语言类型
    * @param userData 第三方私有数据
    */
    function voiceVerify($verifyCode,$playTimes,$to,$displayNum,$respUrl,$lang,$userData) {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 拼接请求包体
        if($this->bodyType=="json"){
           $body= "{'appId':'$this->appId','verifyCode':'$verifyCode','playTimes':'$playTimes','to':'$to','respUrl':'$respUrl','displayNum':'$displayNum',
           'lang':'$lang','userData':'$userData'}";
        }else{
           $body="<VoiceVerify>
                    <appId>$this->appId</appId>
                    <verifyCode>$verifyCode</verifyCode>
                    <playTimes>$playTimes</playTimes>
                    <to>$to</to>
                    <respUrl>$respUrl</respUrl>
                    <displayNum>$displayNum</displayNum>
                    <lang>$lang</lang>
                    <userData>$userData</userData>
                  </VoiceVerify>";
        }
        $this->showlog("request body = ".$body);
        // 大写的sig参数
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL  
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/Calls/VoiceVerify?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头  
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas;
    }
      
   /**
    * IVR外呼
    * @param number   待呼叫号码，为Dial节点的属性
    * @param userdata 用户数据，在<startservice>通知中返回，只允许填写数字字符，为Dial节点的属性
    * @param record   是否录音，可填项为true和false，默认值为false不录音，为Dial节点的属性
    */
    function ivrDial($number, $userdata, $record) {
       //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        } 
       // 拼接请求包体
        $body=" <Request>
                  <Appid>$this->appId</Appid>
                  <Dial number='$number'  userdata='$userdata' record='$record'></Dial>
                </Request>";
        $this->showlog("request body = ".$body);
        // 大写的sig参数
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL  
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/ivr/dial?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头  
        $header = array("Accept:application/xml","Content-Type:application/xml;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        $datas = simplexml_load_string(trim($result," \t\n\r"));
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas;
    }
    
   /**
    * 话单下载
    * @param date     day 代表前一天的数据（从00:00 – 23:59）
    * @param keywords   客户的查询条件，由客户自行定义并提供给云通讯平台。默认不填忽略此参数
    */
    function billRecords($date,$keywords)
    {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 拼接请求包体
        if($this->bodyType=="json"){
           $body= "{'appId':'$this->appId','date':'$date','keywords':'$keywords'}";
        }else{
           $body="<BillRecords>
                    <appId>$this->appId</appId>
                    <date>$date</date>
                    <keywords>$keywords</keywords>
                  </BillRecords>";
        }
        $this->showlog("request body = ".$body);
        // 大写的sig参数
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL  
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/BillRecords?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头  
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas; 
   } 
   
  /**
    * 主帐号信息查询
    */
   function queryAccountInfo()
   {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 大写的sig参数
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL  
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/AccountInfo?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头  
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,"",$header,0);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas;  
   }
   
    /**
    * 短信模板查询
    * @param date     templateId 模板ID
    */
    function QuerySMSTemplate($templateId)
    {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 拼接请求包体
        if($this->bodyType=="json"){
           $body= "{'appId':'$this->appId','templateId':'$templateId'}";
        }else{
           $body="<Request>
                    <appId>$this->appId</appId>
                    <templateId>$templateId</templateId>  
                  </Request>";
        }
        $this->showlog("request body = ".$body);
        // 大写的sig参数
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL  
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/SMS/QuerySMSTemplate?sig=$sig";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头  
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header); 
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式 
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas; 
   }

   
   /**
    * 呼叫状态查询
    * @param callid     呼叫Id 
    * @param action   查询结果通知的回调url地址 
    */
    function QueryCallState($callid,$action)
    {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 拼接请求包体
        if($this->bodyType=="json"){
           $body= "{'Appid':'$this->appId','QueryCallState':{'callid':'$callid','action':'$action'}}";
        }else{
           $body="<Request>
                    <Appid>$this->appId</Appid>
                    <QueryCallState callid ='$callid' action='$action'/>
                  </Request>";
        }
        $this->showlog("request body = ".$body);
        // 大写的sig参数
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL  
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/ivr/call?sig=$sig&callid=$callid";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头  
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas; 
   } 
   
   /**
    * 呼叫结果查询
    * @param callSid     呼叫Id
    */
   function CallResult($callSid)
   {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 大写的sig参数
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL  
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/CallResult?sig=$sig&callsid=$callSid";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头  
        $header = array("Accept:application/$this->bodyType","Content-Type:application/$this->bodyType;charset=utf-8","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,"",$header,0);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas;  
   }
   
      /**
    * 语音文件上传
    * @param filename     文件名
    * @param body   二进制串
    */
    function MediaFileUpload($filename,$body)
    {                      
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth=$this->accAuth();
        if($auth!=""){
            return $auth;
        }
        // 拼接请求包体

        $this->showlog("request body = ".$body);
        // 大写的sig参数
        $sig =  strtoupper(md5($this->accountSid . $this->accountToken . $this->batch));
        // 生成请求URL  
        $url="https://$this->serverIP:$this->serverPort/$this->softVersion/Accounts/$this->accountSid/Calls/MediaFileUpload?sig=$sig&appid=$this->appId&filename=$filename";
        $this->showlog("request url = ".$url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->accountSid . ":" . $this->batch);
        // 生成包头  
        $header = array("Accept:application/$this->bodyType","Content-Type:application/octet-stream","Authorization:$authen");
        // 发送请求
        $result = $this->curl_post($url,$body,$header);
        $this->showlog("response body = ".$result);
        if($this->bodyType=="json"){//JSON格式
           $datas=json_decode($result); 
        }else{ //xml格式
           $datas = simplexml_load_string(trim($result," \t\n\r"));
        }
      //  if($datas == FALSE){
//            $datas = new stdClass();
//            $datas->statusCode = '172003';
//            $datas->statusMsg = '返回包体错误'; 
//        }
        return $datas; 
   } 
   
  /**
    * 子帐号鉴权
    */   
   function subAuth()
   {
       if($this->serverIP==""){
            $data = new stdClass();
            $data->statusCode = '172004';
            $data->statusMsg = 'serverIP为空';
          return $data;
        }
        if($this->serverPort<=0){
            $data = new stdClass();
            $data->statusCode = '172005';
            $data->statusMsg = '端口错误（小于等于0）';
          return $data;
        }
        if($this->softVersion==""){
            $data = new stdClass();
            $data->statusCode = '172013';
            $data->statusMsg = '版本号为空';
          return $data;
        } 
        if($this->subAccountSid==""){
            $data = new stdClass();
            $data->statusCode = '172008';
            $data->statusMsg = '子帐号为空';
          return $data;
        }
        if($this->subAccountToken==""){
            $data = new stdClass();
            $data->statusCode = '172009';
            $data->statusMsg = '子帐号令牌为空';
          return $data;
        }
        if($this->appId==""){
            $data = new stdClass();
            $data->statusCode = '172012';
            $data->statusMsg = '应用ID为空';
          return $data;
        }  
   }
   
  /**
    * 主帐号鉴权
    */   
   function accAuth() {
       if($this->serverIP==""){
            $data = new stdClass();
            $data->statusCode = '172004';
            $data->statusMsg = 'serverIP为空';
          return $data;
        }
        if($this->serverPort<=0){
            $data = new stdClass();
            $data->statusCode = '172005';
            $data->statusMsg = '端口错误（小于等于0）';
          return $data;
        }
        if($this->softVersion==""){
            $data = new stdClass();
            $data->statusCode = '172013';
            $data->statusMsg = '版本号为空';
          return $data;
        } 
        if($this->accountSid==""){
            $data = new stdClass();
            $data->statusCode = '172006';
            $data->statusMsg = '主帐号为空';
          return $data;
        }
        if($this->accountToken==""){
            $data = new stdClass();
            $data->statusCode = '172007';
            $data->statusMsg = '主帐号令牌为空';
          return $data;
        }
        if($this->appId==""){
            $data = new stdClass();
            $data->statusCode = '172012';
            $data->statusMsg = '应用ID为空';
          return $data;
        }
   }
}