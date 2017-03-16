<?php
/**
 * 登出模块
 * 罗婷
 */

namespace app\mobile\controller;
use app\common\model\MobileMemberToken;

class LoginOut extends MobileMember{

    /**
     * 构造方法
     */
    public function __construct(){
        parent::__construct();
    }

    /**
     * 注销
     */
    public function loginOut(){

        if( !$this->request->has('account') )
            $this->returnJson('', 1,   '参数错误');
        $phone = $this->request->post('account', '', 'trim');
        $tokenModel = new MobileMemberToken();

        if($this->tokenInfo['account'] == $phone ) {
            $condition = [];
            $condition['member_id'] = $this->user['member_id'];
            $tokenModel->delMobileMemberToken($condition);
            $this->returnJson('', 0, '退出成功');
        } else {
            $this->returnJson('', 1, '帐号错误');
        }
    }
}