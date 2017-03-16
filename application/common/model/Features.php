<?php
/**
 * 木筑生活馆规格模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-12-02 10:08
 */

namespace app\common\model;
use think\Model;
use app\common\model\GoodsSaleFeature;

class Features extends Model
{
    /**
     * 根绝id获取features
     * @param $id string
     * @return array
     */
    public function getFeaturesById($id){
        if(!$id) return array();
        $res = $this->where("is_delete = '0' AND feature_id IN ('".$id."')")->select();
        return $res;
    }
}