<?php
/**
 * 商家管理员角色和角色权限逻辑
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-10 15:10
 */
namespace app\seller\logic;

use think\Db;
use think\Config;
use think\Session;
use app\common\model\Member;
use app\seller\model\Seller;

class Authority 
{

	/**
	 * 登陆方法
	 *@param string account 用户账号，手机号码或者登陆信息
	 *@param string password 用户密码
	 *@return int 登陆成功或者失败的状态,1错误的账号和密码, 2非管理员账号
	 */
    public function login( $account, $password ) {
        //检查用户是否存在
        $memberModel = new Member();
        $where       = array('phone|account' => $account, 'password'=>$password);
        $member      = $memberModel->where( $where )->find();
        //会员查询失败
        if (!$member) {
        	return -1;
        }
        //进行商家管理员查询
        $sellerModel = new Seller();
        $where = array( 'status'=> 1,'is_delete'=>0,'member_id'=>$member['member_id'] );
        $seller= $sellerModel->where( $where )->find();
        if ( !$seller ) {
        	return -2;
        }
        $res = Db::name('store')->where(array('store_id'=>$seller['store_id']))->find();
        $seller['store_name'] = $res['store_name'];

        //登陆成功后，1.进行用户权限以及角色查询
        list($seller['role'], $seller['auth_list']) = $this->getAuthList( $member['member_id'] );
        //登陆成功后，2.存储session
        Session::set('seller', serialize( $seller ));
        //登录成功 3.更新用户的最近登录时间
        $memberModel->where( $where )->update(['last_login_at' => time(),'session_id'=> session_id()]);
        return 1;
    }

	/**
     * @param string memberId 用户ID
     * @return array authList 权限的菜单列表
     */
    public function getAuthList( $memberId ) {
		$where = ['status'=>1,'is_delete'=>0];
		$role = Db::table(Config::get('database.prefix').'manager_role')->where($where)->column('role_id');
        //角色查询
        $roleList = Db::table(Config::get('database.prefix').'manager_role_relation')->where('member_id', $memberId)->column('role_id');
		if(in_array($roleList[0],$role)){
			//角色权限查询
			$authList = array();
			if ( !empty( $roleList ) ) {
				$authList = Db::table(Config::get('database.prefix').'manager_role_auth')->where('role_id', 'in', $roleList)->column('menu_id');
			}
			return array($roleList, $authList);
		}else{
			return array($roleList, [0=>1]);
		}

    }
}
