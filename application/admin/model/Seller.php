<?php
/**
 * 商家管理员模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-10 13:24
 */
namespace app\admin\model;

use think\Model;

class Seller extends Model
{
    /**
     * 状态修改器
     * @param $value status_text值
     * @param $data 数据库所有字段
     */
    protected function getStatusTextAttr($value,$data) {
        $status = array(1=>'启用', 0=>'停用');
        return  $status[$data['status']];
    }
}