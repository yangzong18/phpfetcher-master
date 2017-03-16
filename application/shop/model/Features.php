<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/1  17:27
 */
namespace app\shop\model;

use think\Model;

class Features extends Model{

    /**
     * 根绝id获取features
     * @param $id string
     * @return array
     */
    public function getFeaturesById($id){
        if(!$id){
            return array();
        }

        $res = $this->where("is_delete = '0' AND feature_id IN ('".$id."')")->select();

        return $res;
    }
}
