<?php
/**
 * 商城登录/注册管理
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷 at 2016/11/28
 */

namespace app\shop\controller;
use app\api\controller\SentTemplatesSMS;
use app\common\controller\Shop;
use app\shop\model\Cart;
use app\common\model\Member;
use app\common\logic\Auth;
use app\shop\model\Navigation;
use think\Cache;
use think\Db;
use think\Config;
use think\Session;
use think\Validate;
use Util\Tools;
use think\resquest;

class Login extends Shop{
    protected $model;

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Member();
    }

    /**
     * 登录页面
     */
    public function login() {
        $this->view->engine->layout(false);
        //新手帮助查询
        $navModel = new Navigation();
        $helpUrl  = $navModel->inquireInfo(6);
        $this->assign('helpUrl', $helpUrl);
        return $this->fetch();
    }

    /**
     * 登录处理
     */
    public function loginDo()
    {
        $account = $this->request->param('account', '', 'trim');
        $password  = $this->request->param('password', '', 'trim');
		$captcha	= $this->request->param('captcha', '', 'trim');
        $localCart  = $this->request->param('localCart', '', 'trim');
        $memberInfo = [ 'member_name' => $account, 'password' => $password ,'captcha'=>$captcha];
		$rule = array(
			'member_name' => 'require',
			'password'=> 'require',
			'captcha' =>'require'
		);
		$message = array(
			'account.require' => '账号不能为空',
			'password.require' => '密码不能为空',
			'captcha.require' => '验证码不能为空'
		);
        //参数空验证
        $validate = new Validate($rule, $message);
        if ( !$validate->check( $memberInfo ) )
            $this->error( $validate->getError() );
		//验证码验证  验证码验证完后会删除掉，所以最后验证
		if(!captcha_check($captcha)){
			//验证失败
			$this->error( '验证码错误');
		}
        //账户验证
        $selectInfo = $this->model->where('phone', $memberInfo['member_name'])->find();
        if( !$selectInfo )
            $this->error( '账号或密码错误' );
        if( $selectInfo->password != $memberInfo['password'] )
            $this->error( '账号或密码错误' );

        //登陆成功后，存储session
        Session::set('user', serialize( $selectInfo ));
        //登录成功后，若localStorage有购物车数据，将其加到数据库中
        if( $localCart !='' ) {
            ( new Cart() )->mergeCart($localCart, $selectInfo->member_id);
        }
		$captcha = new \think\captcha\Captcha();
		if(!$captcha->delete($captcha)){
			$this->error('验证码清除失败！');
		}
        //登录成功
        if( Session::has('returnUrl') && Session::get('returnUrl') != ''){
            $this->success('登录成功',Session::get('returnUrl'));
        } else {
            $this->success('登录成功','index/index');
        }

    }

    /**
     * 用户注册页面
     */
    public function register() {
        $this->view->engine->layout(false);
        //新手帮助查询
        $navModel = new Navigation();
        $helpUrl  = $navModel->inquireInfo(6);
        $this->assign('helpUrl', $helpUrl);
        return $this->fetch();
    }

    /**
     * 注册处理
     */
    public function registerDo() {
        $phone = $this->request->param('phone', '', 'trim');
        $authCode = $this->request->param('authCode', '', 'trim');
        $password  = $this->request->param('password', '', 'trim');
        $repeatPassword  = $this->request->param('repeat_password', '', 'trim');
        $email  = $this->request->param('email', '', 'trim');
        $memberInfo = [ 'phone' => $phone, 'password' => $password, 'repeatPassword' => $repeatPassword,
                        'authCode' => $authCode,'email' => $email ];
        //验证数据
        $result = $this->registerValidate( $memberInfo );
        if( !$result['code'] ) $this->error($result['msg']);
        //数据验证成功
        unset( $memberInfo['repeatPassword'] );
        unset( $memberInfo['authCode'] );
        $memberInfo['member_id'] = Tools::guid();
        $memberInfo['account'] = $phone;
        $memberInfo['password'] = md5( $memberInfo['password'] );

        if( !$this->model->save($memberInfo) ) {//注册会员
            $this->error('注册失败');
        }
        Session::delete('phone'.$phone.'.code');
        Session::delete('phone'.$phone.'.time');
        $this->success('注册成功', 'login');

    }

    /**
     * 获取手机验证码
     */
    public function getAuthCode(){
        $phone = $this->request->param('phone', '', 'trim');
        $ip = $this->request->ip();
        //要做IP限制，否则会被攻击
        $authLogic = new Auth();
//        $msg = $authLogic->codeLimit($ip, $phone);
//        if( !$msg['code'] ) exit(json_encode($msg));
        //假如没有超过限制
        $sms = new SentTemplatesSMS();
        $code = generateCode();
        $result = $sms->sent($phone, [$code], "phone_code");
       // $result = array('code'=>2, 'msg'=>'发送成功'.$code);
        if( $result['code'] == 2 ) {//短信发送成功
            Session::set('phone'.$phone.'.code',$code);
            Session::set('phone'.$phone.'.time',time());
        }
        echo json_encode($result);
    }

    /**
     * 手机验证码登录
     */
    public function verifyCode() {
        $phone = $this->request->param('phone', '', 'trim');//手机号
        $code  = $this->request->param('code', '', 'trim');//验证码
        $authLogic = new Auth();
        $result = $authLogic->authCode( $phone, $code );
        //如果验证码不通过
        if ( $result['code'] != 1 ) {
            $this->error( $result['msg'],'',$result['code']);
        }
        $this->success( $result['msg'] );
    }

    /**
     * 验证码登录
     * @param string $phone 手机号码
     * @param string $code 验证码
     */
    public function authCode() {
        $phone = $this->request->param('phone', '', 'trim');//手机号
        $code  = $this->request->param('code', '', 'trim');//验证码
        $authLogic = new Auth();
        $result = $authLogic->authCode( $phone, $code );
        //如果验证码通过
        if ( $result['code'] != 1 ) {
            $this->error( $result['msg'] );
        }
        //判断用户是否存在，如果存在的话则直接登录
        $user = $this->model->where('phone', $phone)->find();
        //如果用户存在，则直接登录
        if ( $user ) {
            $result = $authLogic->login( $user['phone'], $user['password'] );
            if ( $result['code'] == 1 ) {
               $this->success('成功');
            } else {
               $this->error( $result['msg'] );
            }
        } else {
            //否则进行注册，提交信息
            $name    = $this->request->param('name', '', 'trim');//手机号
            $gender  = $this->request->param('gender', 1, 'intval');//验证码
            $password = Tools::getRandChar(6);
            //进行注册
            $result = $authLogic->regist( $phone, md5($password), $name, $gender );
            if ( $result['code'] != 1 ) {
                $this->error( $result['msg'] );
            }
            //注册成功后，给用户发送密码到手机
            $result = ( new SentTemplatesSMS() )->sent($phone, [$phone,$phone,$password], "logs_new_account");
            //注册成功后进行登录
            $result = $authLogic->login( $phone, md5($password) );
            if ( $result['code'] != 1 ) {
                $this->error( $result['msg'] );
            }
            $this->success('成功');
        }
    }

    /**
     * 手机号码唯一性验证
     */
    public function checkPhone() {
        $phone = $this->request->param('phone', '', 'trim');//手机号
        $type = $this->request->param('type', '', 'intval');//判断是注册手机还是找回密码
        if( $type == 1 ) {//如果是找回密码，手机号码不存在返回false
            if( !$this->model->where('phone', $phone)->find() ) {
                echo 'false';
            } else {
                echo 'true';
            }

        } else {//假如是注册手机，如果已存在返回false
            if( !$this->model->where('phone', $phone)->find() ) {
                echo 'true';
            } else {
                echo 'false';
            }
        }
    }

    /**
     * 找回密码页面
     */
    public function forgetPassword() {
		if( $this->login == 1) {//假如是登录后修改密码
			$this->assign('phone', $this->user['phone']);
			$this->assign('is_login', 1);
			$this->view->engine->layout(false);
			return $this->fetch();
		} else {//假如是未登录找回密码
			$this->assign('is_login', 0);
			$this->view->engine->layout(false);
			return $this->fetch();
		}
    }

    /**
     * 重置密码界面
     */
    public function resetPassword() {
		$submit = $this->request->param('sub','','trim');
		if($submit != 'ok'){
			$this->redirect('shop/login/forgetpassword');
		}
        if( $this->login == 1) {//假如是登录后修改密码
            $this->assign('phone', $this->user['phone']);
            $this->assign('is_login', 1);
            return $this->fetch();
        } else {//假如是未登录找回密码
            $phone = $this->request->param('phone','','trim');
            Session::set('retrievePhone', $phone);
            $this->assign('phone', $phone);
            $this->assign('is_login', 0);
            $this->view->engine->layout(false);
            return $this->fetch('nologinreset');
        }
    }

    //修改密码方法
    public function resetDo() {
        $account = $this->request->param('phone', '', 'trim');
        $password  = $this->request->param('password', '', 'trim');
        $repeatPassword  = $this->request->param('repeat_password', '', 'trim');
        $isLogin = $this->request->param('is_login', 0, 'intval');
        $memberInfo = [ 'phone' => $account, 'password' => $password, 'repeatPassword'=>$repeatPassword];
		$token = $this->request->token('__token__', '', 'trim');
		if(!Validate::token('__token__','',['__token__'=>$token])){
			$this->error( 'token校验不正确');
		}
		$rule = array(
            'phone' => 'require|regex:/^1[34578]\d{9}$/',
            'password'=> 'require',
            'repeatPassword' => 'require|confirm:password'
        );
        $message = array(
            'phone.require' => '手机号不能为空',
            'phone.regex' => '手机号无效',
            'password.require' => '密码不能为空',
            'repeatPassword.require' => '确认密码不能为空',
            'repeatPassword.confirm'  => '两次输入密码不一致'
        );
        //参数空验证
        $validate = new Validate($rule, $message);
        if ( !$validate->check( $memberInfo ) )
            $this->error( $validate->getError() );

        //账户验证
        if( $isLogin == 0) {//假如未登录
            if(  $this->login == 1 || Session::get('retrievePhone') != $account )
                $this->error( '重置帐号错误' );
        } else {//假如已登录
            if( $this->login == 0 || $this->user['phone'] != $account )
                $this->error( '重置帐号错误' );
        }
        if( $this->model->where('phone', $account)->update(['password'=> $password]) === false) {
            $this->error( '重置密码失败','',var_dump($this->model->where('phone', $account)->update(['password'=> $password])) );
        } else {//重置成功
            Session::delete('retrievePhone');
            Session::delete('user');
            Session::delete('returnUrl');
            $this->success('重置密码成功，请登录','login');
        }


    }

    /**
     * 注册账户验证
     * @param $dataInfo 待验证数据
     * @return array
     */
    private function registerValidate( $dataInfo ) {
        $rule = array(
            'phone' => 'require|unique:member|regex:/^1([3-9]{1})([0-9]{1})([0-9]{8})$/',
            'password'=> 'require|regex:/^(\w){6,18}$/',
            'repeatPassword' => 'require|confirm:password',
            'authCode'=> 'require',
            'email' => 'require'
        );
        $message = array(
            'phone.require' => '手机号不能为空',
            'phone.unique' => '手机号已注册',
            'phone.regex' => '手机号无效',
            'password.require' => '密码不能为空',
            'password.regex' => '请输入6-18个字符的密码，字母数字或下划线',
            'repeatPassword.require' => '确认密码不能为空',
            'repeatPassword.confirm'  => '两次输入密码不一致',
            'authCode.require' => '验证码不能为空',
            'email.require' => '邮箱不能为空'
        );
        //参数空验证
        $validate = new Validate($rule, $message);
        if( !$validate->check( $dataInfo ) ){
            return ['code' => 0, 'msg' => $validate->getError()];
        }
        return ['code' => 1];
    }


    /**
     * 登出
     */
    public function signOut() {
        //销毁session, 跳转到登陆页面
        Session::delete('user');
        Session::delete('returnUrl');
        $this->redirect('shop/login/login');
    }
    /**
     * 阅读条款
     */
    public function read() {
        $this->view->engine->layout(false);
        return $this->fetch();
    }

	private function verifyPhone($phone){
		$message = ['code'=> 0, 'msg'=> '手机号码格式错误'];
		//手机号码验证
		if ( !preg_match( Config::get('other.phone'), $phone ) ) {
			return $message;
		}
		$message['msg'] = '验证码错误';
		if( !Session::has('phone'.$phone.'.code') )//假如手机号从未获取过验证码
			return $message;
		if( Session::get('phone'.$phone.'.time')+300 >= time() ) {//判断验证码是否过期
			if( $code !== Session::get('phone'.$phone.'.code') ) {
				return $message;
			}
			$message = ['code'=> 1, 'msg'=> '成功'];
			return $message;
		} else {//过期,删除session
//            Session::delete('phone'.$phone.'.code');
//            Session::delete('phone'.$phone.'.time');
			$message = ['code'=> 2, 'msg'=> '验证码已过期，请重新获取'];
			return $message;
		}
	}

}
