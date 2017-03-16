<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/1  17:36
 */
namespace app\shop\model;

use think\Model;

class FeaturesValue extends Model{

    /**
     * 通过ID获取属性值
     * @param $id string
     * @return array
     */
    public function getFeatureValueById($id){
        if(!$id){
            return array();
        }
        $res = $this->where("is_delete = '0' AND feature_id IN ('".$id."')")->select();
        return $res;
    }
}