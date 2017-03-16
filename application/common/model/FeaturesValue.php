<?php
/**
 * 木筑生活馆规格值模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-12-02 10:08
 */

namespace app\common\model;
use think\Model;

class FeaturesValue extends Model
{

    /**
     * 通过ID获取属性值
     * @param $id string
     * @return array
     */
    public function getFeatureValueById($id){
        if(!$id) return array();
        $res = $this->where("feature_id IN ('".$id."')")->select();
        return $res;
    }
}