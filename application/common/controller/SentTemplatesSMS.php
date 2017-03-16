<?php
/**
 * 短信接口
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: Luo Ting at 2016-11-24
 */
namespace app\common\controller;
use app\common\controller\Rest;

class SentTemplatesSMS {
    //主帐号
    private $accountSid = '8a216da8586ab13d01586bc685ea01be';
    //主帐号Token
    private $accountToken = '78267f2bf90b43038f3ccec52237086d';
    //应用Id
    private $appId = '8a216da8586ab13d01586bc6863901c3';
   //请求地址，格式如下，不需要写https://
    private $serverIP = 'app.cloopen.com';
    //请求端口
    private $serverPort = '8883';
   //REST版本号
    private $softVersion = '2013-12-26';

    private $smsType = ['logs_check'=>'145917',
                          'logs_designed'=>'145919',
                          'logs_new_account'=>'145921',
                          'phone_code'=>'145927',
                          'reset_account'=> '146557'];

    /**
     * 发送模板短信
     * @param $to 手机号码集合,用英文逗号分开
     * @param $data 内容数据 格式为数组 例如：array('Marry','Alon')，如不需替换请填 null
     * @param $templateId 模板Id
     * @return array
     */
    function sendTemplateSMS($to, $data, $templateId) {
       // 初始化REST SDK
       $rest = new Rest($this->serverIP, $this->serverPort, $this->softVersion);
       $rest->setAccount($this->accountSid, $this->accountToken);
       $rest->setAppId($this->appId);

       // 发送模板短信
       $result = $rest->sendTemplateSMS($to, $data, $templateId);
       if($result == NULL ) {
           return ['code'=>0, 'msg'=> '发送失败'];
       }
       if($result->statusCode!=0) {
           $errorCode = (array)$result->statusCode;
           $statusMsg = (array)$result->statusMsg;
           return ['code'=>1, 'errorCode' =>$errorCode[0], 'msg'=>$statusMsg[0] ];
           //TODO 添加错误处理逻辑
       } else {
           return ['code'=>2, 'msg'=> '发送成功'];
           //TODO 添加成功处理逻辑
       }
    }

    /**
     * 测试短信验证
     */
    public function test() {
        //Demo调用,参数填入正确后，放开注释可以调用
        var_dump($this->sendTemplateSMS("15129183019",['9999','5'],"1"));
    }

    /**
     * 信息分类发送处理
     * @param $to 手机号码集合,用英文逗号分开
     * @param $data 内容数据 格式为数组 例如：array('Marry','Alon')，如不需替换请填 null
     * @param $templateType 模板类型
     * @return array
     */
    public function sent($to, $data, $templateType) {
        return $this->sendTemplateSMS($to, $data, $this->smsType[$templateType]);
    }


}