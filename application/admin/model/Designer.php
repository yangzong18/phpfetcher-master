<?php
/**
 * 设计师模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: luoTing at 2016-11-22
 */

namespace app\admin\model;
use think\Model;

class Designer extends Model{

    /**
     * 性别修改器
     * @param $value designer_sex_name值
     * @param $data 数据库所有字段
     */
    protected function getDesignerSexNameAttr($value,$data) {
        $status = array(1=>'男', 2=>'女');
        return  $status[$data['designer_sex']];
    }
}