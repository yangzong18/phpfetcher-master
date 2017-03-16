<?php
/**
 * 前端用户登录注册逻辑
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 
 */
namespace app\common\logic;
use think\Cache;
use think\Session;
use think\Config;
use app\common\model\Member;
use Util\Tools;

class Auth {
	/**
	 * 用户验证码验证
	 * @param string $phone 用户手机号码
	 * @param string $code 用户发送的验证码
	 * @return $result
	 */
	public function authCode( $phone, $code ) {
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


	/**
	 * 用户登录
	 * @param string $phone 用户手机号码
	 * @param string $password 密码
	 * @return $result
	 */
	public function login( $phone, $password ) {
		$message = ['code'=> 0, 'msg'=> '手机号码格式错误'];
		//手机号码验证
		if ( !preg_match( Config::get('other.phone'), $phone ) ) {
			return $message;
		}
		$memberModel = new Member();
		$where= array( 'phone' => $phone, 'password' => $password );
		$user = $memberModel->where( $where )->find();
        if( !$user ) {
            $message['msg'] = '错误的账号和密码组合';
            return $message;
        }
        //登陆成功后，存储session
        Session::set('user', serialize( $user ));
        return ['code'=> 1, 'msg'=> '登录成功'];
	}

	/**
	 * 用户注册
	 * @param string $phone 用户手机号码
	 * @param string $password 密码
	 * @param string $name 用户姓名
	 * @param string $gender 性别
	 * @param string $email 邮箱
	 * @return $result
	 */
	public function regist( $phone, $password, $name='', $gender=1, $email='' ) {
		$message = ['code'=> 0, 'msg'=> '手机号码格式错误'];
		//手机号码验证
		if ( !preg_match( Config::get('other.phone'), $phone ) ) {
			return $message;
		}
		$param = array( 
			'phone'  => $phone,
            'account'  => $phone,
            'password' => $password,
			'member_name'   => $name,
			'member_id' => Tools::guid(),
			'email'  => $email 
		);
		$memberModel = new Member();
		$user = $memberModel->data( $param )->isUpdate(false)->save();
        if( !$user ) {
            $message['msg'] = '注册失败';
            return $message;
        }
        return ['code'=> 1, 'msg'=> '注册成功'];
	}

    /**
     * 获取手机验证码对ip和手机号码做限制
     * @param $ip
     * @param $phone
     * @return array
     */
    public function codeLimit($ip, $phone) {
        if( Cache::has($phone.'number') && ( Cache::get($phone.'time')+3600)>= time() ) { //手机号限制
            if( Cache::get($phone.'number') < 3) Cache::inc($phone.'number');
            else return ['code'=>0, 'msg'=> '同一手机1小时内只能获取3次验证码'];
        } else {
            Cache::set($phone.'number', 1);
            Cache::set($phone.'time',time());
        }

        if( Cache::has($ip.'number') && ( Cache::get($ip.'time')+3600)>= time() ) {//ip限制
            if( Cache::get($ip.'number') < 3) Cache::inc($ip.'number');
            else return ['code'=>0, 'msg'=> '同一IP1小时内只能获取3次验证码'];
        } else {
            Cache::set($ip.'number', 1);
            Cache::set($ip.'time',time());
        }

        return ['code'=>1];
    }
}
