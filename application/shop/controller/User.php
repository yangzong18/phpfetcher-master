<?php
/**
 * 用户模块
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang at 2016/12/19
 */
namespace app\shop\controller;
use app\common\controller\Member;
use app\shop\model\DeliveryAddress;
use think\Validate;

class User extends Member {
	public function __construct() {
		parent::__construct();
	}
    
    /**
     * 用户中心首页
     */
	public function index(){
		return $this->fetch();
	}

	/**
	 * 用户帐号信息
	 */
	public function info(){
		$where['member_id'] = $this->user['member_id'];
		$model_address = new DeliveryAddress();
		$addressList = $model_address->where($where)->order('address_id', 'ASC')->select();
		$modelMember = new \app\common\model\Member();
		$memberInfo  = $modelMember->getMemberInfoByID($where['member_id']);
		$this->assign('member_info',$memberInfo);
		$this->assign('address_list',$addressList);
		return $this->fetch();
	}

	/**
	 * 用户帐号信息
	 */
	public function editemail(){
		$where['member_id'] = $this->user['member_id'];
		$modelMember = new \app\common\model\Member();
		$memberInfo  = $modelMember->getMemberInfoByID($where['member_id'],'email');
		$type = $this->request->param('type','','intval');
		if($type){
			$email = $this->request->param('email','','trim');
			if(empty($email)){
				$this->error('请输入必要参数');
			}
			if($email == $memberInfo['email']){
				$this->error('邮箱没有做出更改');
			}
			$data = ['email'=>$email];
			$rule = [
				'email'  	=> 'require|email',
			];
			$msg = [
				'email.require' 		=> '邮箱不能为空',
				'email.email'     		=> '邮箱的格式不正确',
			];
			$validate = new validate($rule,$msg);
			if (!$validate->check($data)) {
				$this->error($validate->getError());
			}
			$result = $modelMember->editMemberInfo($data,$where);
			if($result){
				$this->success('操作成功','',$email);
			}else{
				$this->error('操作失败');
			}
		}
		$this->view->engine->layout(false);
		$this->assign('member_info',$memberInfo);
		return $this->fetch();

	}

	/**
	 * @return bool
	 *
	 * 检查数据库的数据是否进行了更改
	 */
	public function checkEmail(){
		$email = $this->request->param('email','','trim');
		$memberId = $this->user['member_id'];
		$modelMember = new \app\common\model\Member();
		$info = $modelMember->getMemberInfoByID($memberId);
		if($info['email'] == $email){
			return false;
		}else{
			return true;
		}
	}

	/**
	 * @return bool
	 *
	 * 检查数据库的数据是否进行了更改
	 */
	public function setDefault(){
		$type = $this->request->param('type','','trim');
		$addressId = $this->request->param('address_id','','trim');
		if($type){
			$memberId = $this->user['member_id'];
			$where = ['address_id'=>$addressId,'member_id'=>$memberId];
			$data = ['is_default'=>1];
			$modelAddress = new DeliveryAddress();
			$result = $modelAddress->updateData($data,$where);
			if($result){
				$this->success('设置成功',url('shop/user/info'),$addressId);
			}else{
				$this->success('设置失败');
			}
		}
		$this->view->engine->layout(false);
		$this->assign('address_id',$addressId);
		return $this->fetch();
	}


	/**
	 *删除用户地址
	 */
	public function deladdress() {
		$addressId = $this->request->param('address_id','','intval');
		$type = $this->request->param('type');
		if($type){
			$memberId = $this->user['member_id'];
			$where = ['address_id'=>$addressId,'member_id'=>$memberId];
			$modelAddress = new DeliveryAddress();
			$result = $modelAddress->where($where)->delete();
			if($result){
				$this->success('删除成功','',$addressId);
			}else{
				$this->error('删除失败');
			}
		}
		$this->view->engine->layout(false);
		$this->assign('address_id',$addressId);
		return $this->fetch();
	}

	/**
	 *添加用户地址
	 */
	public function addAddress() {
		$memberId	= $this->user['member_id'];
		$this->assign('member_id',$memberId);
		$this->view->engine->layout(false);
		return $this->fetch();
	}
	/**
	 *修改用户地址
	 */
	public function editAddress() {
		$addressId = $this->request->param('address_id','','intval');
		$memberId = $this->user['member_id'];
		$modelAddress = new DeliveryAddress();
		$addressOne = $modelAddress->where(['address_id'=>$addressId,'member_id'=>$memberId])->order('is_default','DESC')->find();
		$this->view->engine->layout(false);
		$this->assign('address_one',$addressOne);
		return $this->fetch();
	}
}
