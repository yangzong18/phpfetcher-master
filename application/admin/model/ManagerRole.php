<?php
/**
 * 角色模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: luoting at 2016-11-15 8:57
 */

namespace app\admin\model;
use think\Model;

class ManagerRole extends Model{

    /**
     * 状态修改器
     * @param $value status_text值
     * @param $data 数据库所有字段
     */
    protected function getStatusTextAttr($value,$data) {
        $status = array(1=>'启用', 2=>'停用');
        return  $status[$data['status']];
    }

	public function getOneInfo($where){
		return $this->where($where)->find();
	}
}
