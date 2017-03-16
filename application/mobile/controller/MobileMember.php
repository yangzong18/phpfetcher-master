<?php
/**
 * 需要登录类
 */

namespace app\mobile\controller;
use app\common\model\Member;
use app\common\model\MobileMemberToken;
use think\Db;

class MobileMember extends Mobile{
    protected $whiteApi = ['mobile.address.arealist'];
    public $tokenInfo = [];
    public function __construct() {
        parent::__construct();
        $app        = $this->request->module();
        $controller = $this->request->controller();
        $action     = $this->request->action();
        $url  = strtolower($app.'.'.$controller.'.'.$action);
        if( !in_array($url, $this->whiteApi) ) {
            $key = $this->request->post('key', '', 'trim');
            if( empty($key) )  $this->returnJson('', 2, '请登录');
            $tokenModel = new MobileMemberToken();
            $mobileUserTokenInfo =  $tokenModel->getMobileUserTokenInfo(['token'=>$key]);
            if( empty($mobileUserTokenInfo) )  $this->returnJson('', 2, '请登录');
            $this->tokenInfo = $mobileUserTokenInfo;
            $memberModel = new Member();
            $this->user = $memberModel->getMemberInfo( ['member_id' => $mobileUserTokenInfo['member_id']] );
            if( empty($this->user) )  $this->returnJson('', 2, '请登录');
        }
    }
}