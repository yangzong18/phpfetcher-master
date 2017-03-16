<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu  at: 2016/12/1  16:40
 */
namespace app\shop\model;

use think\Model;

class Type extends Model{

    /**
     * 根据ID获取一条分类
     * @param $id int ID
     * @return array
     */
    public function getOneType($id){
        if(!$id){
            return array();
        }
        $res = $this->where("type_id = '".$id."'")->find();
        return $res;
    }

}