<?php
/**
 * Created by 长虹.
 * User: 李武
 * Date: 2017/1/3
 * Time: 10:56
 * Desc: 手机端地区接口
 */
namespace app\mobile\controller;
use app\mobile\model\Area;
use app\common\model\DeliveryAddress;
use think\Request;
use think\Cache;
use think\Model;
class Address extends MobileMember
{
    /*
     * 获取地区列表接口
     *
     * @param string memebr_id 用户ID  必传
     * @param int address_id  地区ID   非必传
     *
     * */
    public function getAddress()
    {
        $request = Request::instance();
        $where = array('member_id' => $this->user['member_id']);
        if ($request->has('address_id', 'post')) {
            $where['address_id'] = $request->post('address_id');
        }
        $model_address = new DeliveryAddress();
        $addressList = $model_address->where($where)->order('is_default', 'DESC')->select();
        $addressList = Model::getResultByFild($addressList);
        if(!empty($addressList)){
            $this->returnJson($addressList);
        }else{
            $this->returnJson('',1,'地址信息不存在');
        }
    }

    /**
     * 获取省市区三级联动数据
     * @param int $parent_id 父级ID 默认值1
     * @param int $deep  层级 默认值0
     */
    public function areaList(){
        $parentId = $this->request->param('parent_id','1','intval');
        $deep = $this->request->param('deep','0','intval');
        $modelArea = new Area();
        $areaList = $modelArea->getAreaArrayForJson($parentId,$deep+1);
        if(!empty($areaList)) {
            $this->returnJson($areaList);
        }else{
            $this->returnJson('',1,'地址信息不存在');
        }
    }

    /**
     *添加用户地址验证以及操作
     * @param string|json $addressInfo 非空 需要添加的地区信息地区
     */
    public function addAddressPost()
    {
        //请求参数处理
        $requset = Request::instance();
        if (!$requset->has('addressInfo', 'post')) $this->returnJson('',1,'参数错误');
        $addressInfo = $requset->post('addressInfo');
        $addressInfoArr = json_decode($addressInfo,true);
        if(!is_array($addressInfoArr) || empty($addressInfoArr)) $this->returnJson('',1,'参数错误');
        if( !isset($addressInfoArr['area_info']) ) $this->returnJson('',1,'参数错误');
        //验证提交数据
        $addressInfoArr['member_id'] = trim($this->user['member_id']);
        $result = validateAddress($addressInfoArr);
        if (!$result['code']) $this->returnJson('',1,$result['msg']);
        //处理数据
        $data = array();
        $data['member_id'] = trim($this->user['member_id']);
        $data['true_name'] = trim($addressInfoArr['true_name']);
        $data['province_id'] = trim($addressInfoArr['province_id']);
        $data['city_id'] = trim($addressInfoArr['city_id']);
        $data['area_id'] = trim($addressInfoArr['area_id']);
        $data['area_info'] = trim($addressInfoArr['area_info']);
        $data['address'] = trim($addressInfoArr['address']);
        $data['mob_phone'] = trim($addressInfoArr['mob_phone']);
        $data['tel_phone'] = array_key_exists('tel_phone',$addressInfoArr)? trim($addressInfoArr['tel_phone']):'';
        $data['member_email'] = array_key_exists('member_email',$addressInfoArr)? trim($addressInfoArr['member_email']):'';
        $data['is_delete'] = 0;
        $data['country_id'] = 1;
        if(array_key_exists('is_default',$addressInfoArr) && $addressInfoArr['is_default']>0){
            $data['is_default'] = intval($addressInfoArr['is_default']);
        }else{
            $data['is_default'] = 0;
        }

        //验证提交数据
        $result = validateAddress($data, 1);
        if (!$result['code']) $this->returnJson('',1,$result['msg']);
        $address = new DeliveryAddress();
        $count = $address->getCount(array('member_id' => $data['member_id']));
        if ($count >= 10) {
            $this->returnJson('',1,'收货地址最多只能有十条');
        } else {
            $re = $address->insert($data);
            if ($re) {
                $data['address_id'] = $re;
                $this->returnJson($data);
            } else {
                $this->returnJson('',1,'增加地址失败');
            }
        }
    }


    /**
     *修改用户地址验证以及操作
     * @param string|json $addressInfo 非空 需要添加的地区信息地区
     */
    public function editAddressPost()
    {
        //参数处理
        $requset = Request::instance();
        if (!$requset->has('addressInfo', 'post')) $this->returnJson('',1,'参数错误');
        $addressInfo = $requset->post('addressInfo');
        $addressInfoArr = json_decode($addressInfo,true);
        if(!is_array($addressInfoArr) || empty($addressInfoArr)) $this->returnJson('',1,'参数错误');
        $addressId = array_key_exists('address_id',$addressInfoArr) ? intval($addressInfoArr['address_id']):0;
        if( !$addressId ) $this->returnJson('',1,'参数错误');
        if( !isset($addressInfoArr['area_info']) ) $this->returnJson('',1,'参数错误');
        //验证提交数据
        $result = validateAddress($addressInfoArr, 2);
        if (!$result['code']) $this->returnJson('',1,$result['msg']);
        //数据处理
        $data = array();
        $data['true_name'] = trim($addressInfoArr['true_name']);
        $data['province_id'] = trim($addressInfoArr['province_id']);
        $data['city_id'] = trim($addressInfoArr['city_id']);
        $data['area_id'] = trim($addressInfoArr['area_id']);
        $data['area_info'] = trim($addressInfoArr['area_info']);
        $data['address'] = trim($addressInfoArr['address']);
        $data['mob_phone'] = trim($addressInfoArr['mob_phone']);
        $data['tel_phone'] = array_key_exists('tel_phone',$addressInfoArr)? trim($addressInfoArr['tel_phone']):'';
        $data['member_email'] = array_key_exists('member_email',$addressInfoArr)? trim($addressInfoArr['member_email']):'';
        if(array_key_exists('is_default',$addressInfoArr) && $addressInfoArr['is_default']>0){
            $data['is_default'] = intval($addressInfoArr['is_default']);
        }else{
            $data['is_default'] = 0;
        }
        $data['is_delete'] = 0;
        $data['country_id'] = 1;

        $address = new DeliveryAddress();
        $info = $address->getOneAdd(['address_id'=>$addressId]);
        if( !$info ) $this->returnJson('', 1, '地址数据错误');
//        if( count(array_diff($data, $info->toArray()) ) == 0) $this->returnJson('',1,'地址没有做出任何修改');
        $where = ['member_id'=>$this->user['member_id'],'address_id'=>$addressId];
        $re = $address->updateData($data,$where);
        if ($re === false) {
            $this->returnJson('',1,'修改地址失败');
        } else {
            $this->returnJson($data);
        }
    }


    //删除地址
    public function deleteAddress()
    {
        $request = Request::instance();
        if (!$request->has('address_id', 'post')) $this->returnJson('',1,'参数错误');
        $modelAddress = new DeliveryAddress();
        $deleteData = array('member_id'=>$this->user['member_id'],'address_id' => $request->post('address_id'));
        $deleteResult = $modelAddress->where($deleteData)->delete();
        if ($deleteResult) {
            $this->returnJson('',0,'操作成功');
        } else {
            $this->returnJson('',1,'操作失败');
        }
    }

    /**
     * 设置默认地址
     */
    public function setDefaultAddress() {
        //参数判断
        if (!$this->request->has('address_id', 'post')) $this->returnJson('',1,'参数错误');
        //数据处理
        $addressId = $this->request->post('address_id', '', 'trim');
        $address = new DeliveryAddress();
        $info = $address->getOneAdd( ['address_id' => $addressId] );
        if( !$info ) $this->returnJson('', 1, '地址数据错误');

        $where = ['member_id' => $this->user['member_id'], 'address_id' => $addressId];
        $re = $address->updateData(['is_default'=>1], $where);
        if ($re) {
            $this->returnJson('',0,'操作成功');
        } else {
            $this->returnJson('',1,'操作失败');
        }
    }
}
