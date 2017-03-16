<?php
/**
 * 不需要登录类
 */

namespace app\mobile\controller;
use app\common\model\Member;
use app\common\model\MobileMemberToken;
use think\Db;

class MobileHome extends Mobile{
    public function __construct() {
        parent::__construct();

        $key = $this->request->post('key', '', 'trim');
        if( !empty($key) ){
            $tokenModel = new MobileMemberToken();
            $mobileUserTokenInfo =  $tokenModel->getMobileUserTokenInfo(['token'=>$key]);
            if( !empty($mobileUserTokenInfo) ){
                $memberModel = new Member();
                $this->user = $memberModel->getMemberInfo( ['member_id' => $mobileUserTokenInfo['member_id']] );
            }
        }
    }
}