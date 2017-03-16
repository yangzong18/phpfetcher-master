<?php
/**
 * 商家中心登陆
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-10 11:08
 */
namespace app\seller\controller;
use think\Controller;
use app\common\model\Member;
use app\admin\model\Seller;
use app\seller\logic\Authority;
use think\Validate;
use think\Db;
use think\Session;

class Login extends Controller {
   
    /**
     * 登陆页面
     */
    public function index(){
    	$this->view->engine->layout(false);
        return $this->fetch();
    }

    /**
     * 登陆方法
     */
    public function loginDo(){
    	$rule = array(
    		'account' => 'require',
    		'password'=> 'require',
            'captcha' =>'require'
    	);
    	$message = array(
    		'account.require' => '账号不能为空',
    		'password.require' => '密码不能为空',
            'captcha.require' => '验证码不能为空'
    	);

    	//参数空验证
        $param = $this->request->param();
    	$validate = new Validate($rule, $message);
        if ( !$validate->check( $param ) ) {
        	$this->error( $validate->getError() );
        }
		//验证码验证  验证码验证完后会删除掉，所以最后验证
		if(!captcha_check($param['captcha'])){
			//验证失败
			$this->error( '验证码错误');
		}
        //进行登陆
    	$authority  = new Authority();
    	$result  = $authority->login( $param['account'], $param['password'] );
        switch ($result) {
        	case -1:
        		$this->error( '错误的账号和密码组合' );
        		break;
        	case -2:
        		$this->error( '非管理员账号' );
        		break;
        	default:
				$captcha = new \think\captcha\Captcha();
				if($captcha->delete($param['captcha'])){
					$this->success('登陆成功');
				}
        }
    	
    }

    /**
     * 用户退出
     */
    public function logout() {
        // 清除缓存
        session(null);
        $this->redirect('index');
    }


	/**
	 * 忘记密码
	 */
	public function editPassword() {
		//如果session不存在或者为空，则跳转到登陆页面
		if ( !Session::has('seller') )  $this->redirect($this->request->domain().'/seller/login');
		$user = unserialize( Session::get('seller') );
		$managerRoleRelation = Db::name('manager_role_relation')
			->where( array('member_id' => $user->member_id) )
			->find();
		if($managerRoleRelation['role_id'] == 1) $this->error('超级管理员不能进行修改');
		$type	= $this->request->param('type', 0, 'intval');
		$memberModel = new Member();
		if($type){
			$memberId = $user->member_id;
			$memberPassword = $this->request->param('password', '', 'trim');
			$memberRPassword = $this->request->param('repassword', '' ,'trim');
			if( $memberPassword !== $memberRPassword)
				$this->error('两次密码不一致');
			$updateInfo = array(
				'password' => $memberPassword,
			);
			$result = $memberModel->where(['member_id'=>$memberId])->update($updateInfo);
			if($result){
				$this->success('密码修改成功');
			}else{
				$this->error('密码修改失败');
			}
		}
		$data  = $memberModel->where('member_id', $user->member_id)->find();
		$this->view->engine->layout(false);
		$this->assign('data', $data);
		return $this->fetch();
	}
}
