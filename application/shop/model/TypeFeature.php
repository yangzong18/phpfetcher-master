<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/1  16:43
 */
namespace app\shop\model;

use think\Model;

class TypeFeature extends Model{


    /**
     * 根据type_id获取feature
     * @param $type_id int
     * @return array
     */
    public function getTypeFeature($type_id){
        if(!$type_id){
            return array();
        }

        $res = $this->where("type_id = '".$type_id."'")->select();

        return $res;
    }
}