<?php
/**
 * Created by PhpStorm.
 * User: zhusunjing
 * Date: 2016/11/21
 * Time: 10:50
 */
namespace app\shop\controller;
use app\common\controller\Member;
use app\shop\model\DeliveryAddress;
use think\Controller;
use think\Request;
class Address extends Member
{

	/**
	 *添加用户地址验证以及操作
	 */
	public function addAddressPost()
	{
		$data = array();
		$data['member_id'] = $this->request->param('member_id', '', 'trim');
		$data['true_name'] = $this->request->param('true_name', '', 'trim');
		$data['province_id'] = $this->request->param('province_id', '', 'trim');
		$data['city_id'] = $this->request->param('city_id', '', 'trim');
		$data['area_id'] = $this->request->param('area_id', '', 'trim');
		$data['area_info'] = $this->request->param('area_info', '', 'trim');
		$data['address'] = $this->request->param('address', '', 'trim');
		$data['tel_phone'] = $this->request->param('tel_phone', '', 'trim');
		$data['mob_phone'] = $this->request->param('mob_phone', '', 'trim');
		$data['member_email'] = $this->request->param('member_email', '', 'trim');
		$data['is_default'] = $this->request->param('is_default','','intval') ? $this->request->param('is_default','','intval'): 0;
		$data['is_delete'] = 0;
		$data['country_id'] = 1;
		//验证提交数据
		$result = validateAddress($data, 1);
		if (!$result['code']) $this->error($result['msg']);
		$address = new DeliveryAddress();
		$count = $address->getCount(array('member_id' => $data['member_id']));
		if ($count >= 10) {
			$this->error('收货地址最多只能有十条', '', '');
		} else {
			$re = $address->insert($data);
			if ($re) {
				$data['address_id'] = $re;
				$this->success('地址添加成功', '', $data);
			} else {
				$this->error('增加地址失败');
			}
		}
	}

	/**
	 *修改用户地址
	 *
	 *  2016-12-7
	 *
	 * yp
	 */
	public function editAddressPost()
	{
		$addressId = $this->request->param('address_id', '', 'intval');
		$data = array();
		$data['true_name'] = $this->request->param('true_name', '', 'trim');
		$data['province_id'] = $this->request->param('province_id', '', 'trim');
		$data['city_id'] = $this->request->param('city_id', '', 'trim');
		$data['area_id'] = $this->request->param('area_id', '', 'trim');
		$data['area_info'] = $this->request->param('area_info', '', 'trim');
		$data['address'] = $this->request->param('address', '', 'trim');
		$data['tel_phone'] = $this->request->param('tel_phone', '', 'trim');
		$data['mob_phone'] = $this->request->param('mob_phone', '', 'trim');
		$data['member_email'] = $this->request->param('member_email', '', 'trim');
		$data['is_default'] = $this->request->param('is_default', '', 'intval');
		$data['is_delete'] = 0;
		$data['country_id'] = 1;
		//验证提交数据
		$result = validateAddress($data, 2);
		if (!$result['code']) $this->error($result['msg']);
		$address = new DeliveryAddress();
		$info = $address->getOneAdd(['address_id'=>$addressId])->toArray();
		$flag = false;
		foreach ($data as $key => $value) {
			if($value == $info[$key]){
				continue;
			}else{
				$flag = true;
				break;
			}
		}
		if (!$flag) {
			$this->error('地址没有做出任何修改');
		}
		$where = ['member_id'=>$this->user['member_id'],'address_id'=>$addressId];
		$re = $address->updateData($data,$where);
		if ($re) {
			$data['address_id'] = $addressId;
			$this->success('地址修改成功', '', $data);
		} else {
			$this->error('修改地址失败');
		}
	}

	//删除地址
	public function deleteAddress()
	{
		$request = Request::instance();
		if (!$request->has('address_id', 'post')) {
			$this->error('address_id不能为空');
		}
		$modelAddress = new DeliveryAddress();
		$deleteData = array('member_id'=>$this->user['member_id'],'address_id' => $request->post('address_id'));
		$deleteResult = $modelAddress->where($deleteData)->delete();
		if ($deleteResult) {
			$this->success('操作成功');
		} else {
			$this->error('操作失败');
		}
	}

	//查看地址
	public function getAddress()
	{
		$request = Request::instance();
		if (!$request->has('member_id', 'post'))  $this->error('member_id不能为空');
		$where = array('member_id' => $request->post('member_id'));
		if ($request->has('address_id', 'post')) {
			$where['address_id'] = $request->post('address_id');
		}
		$model_address = new DeliveryAddress();
		$addressList = $model_address->where($where)->order('is_default', 'DESC')->select();
		if($addressList){
			$this->success('获取地址成功', '', $addressList);
		}else{
			$this->error('地址信息不存在');
		}

	}

	/**
	 * 根据传进来的address_id 查找一个地址信息
	 */
	public function getAddressOne()
	{
		$request = Request::instance();
		if ($request->has('address_id', 'post')) {
			$where['address_id'] = $request->post('address_id');
		}
		$model_address = new DeliveryAddress();
		$addressOne = $model_address->where($where)->order('is_default', 'DESC')->find();
		if ($addressOne) {
			$this->success('操作成功', '', $addressOne);
		} else {
			$this->success('操作失败', '', $addressOne);
		}
	}
}
