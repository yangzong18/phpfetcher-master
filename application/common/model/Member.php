<?php
/**
 * 用户模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-10 13:24
 */
namespace app\common\model;

use think\Cache;
use think\Model;

class Member extends Model
{
    protected $insert = ['status' => 1,'created_at'];
    protected $type = ['created_at'=> 'timestamp:Y/m/d H:i:s'];

    /**
     * 最近登录时间修改器
     * @param $value last_login_at值
     */
    protected function getLastLoginAtAttr($value) {
        if( $value ) return date('Y-m-d H:i:s', $value);
        else return '此用户未登录过';
    }

    /**
     * 状态修改器
     * @param $value status_text值
     * @param $data 数据库所有字段
     */
    protected function getStatusTextAttr($value,$data) {
        $status = array(1=>'启用', 0=>'停用');
        return  $status[$data['status']];
    }

    /**
     * 创建时间自动完成
     */
    protected function setCreatedAtAttr() {
        return time();
    }

    /**
     * 定义全局的查询范围
     * @param $query
     */
    protected function base($query)
    {
        $query->where('is_delete',0);
    }

    /**
     * 取得会员详细信息（优先查询缓存）
     * 如果未找到，则缓存所有字段
     * @param int $member_id
     * @param string $field 需要取得的缓存键值, 例如：'*','member_name,member_sex'
     * @return array
     */
    public function getMemberInfoByID($member_id)
    {
        $cache_key = 'member_'.$member_id;
        $member_info = Cache::get($cache_key);
        if (empty($member_info)) {
            $member_info = $this->getMemberInfo(array('member_id'=>$member_id),'*',true);
            Cache::set($cache_key,$member_info);
        }
        return $member_info;
    }

	/**
	 * 更改数据信息
	 */
	public function editMemberInfo($data,$where)
	{
		$cache_key = 'member_'.$where['member_id'];
		$result = $this->where($where)->update($data);
		if($result){
			Cache::rm($cache_key);
		}
		return $result;
	}

    /**
     * 会员详细信息（查库）
     * @param array $condition
     * @param string $field
     * @return array
     */
    public function getMemberInfo($condition, $field = '*', $master = false) {
        $info = $this->where($condition)->field($field)->master($master)->find();
        if( $info )
            return $info->toArray();
        else
            return array();
    }

}