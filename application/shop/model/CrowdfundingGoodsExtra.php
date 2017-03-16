<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/13  14:30
 */
namespace app\shop\model;

use think\Model;

class CrowdfundingGoodsExtra extends Model
{
    /**
     * 获取商品的扩展信息
     * @param $where array
     * @return array
     */
    public function getCrowdfundingGoodsExtra($where){

        if(!$where){
            return array();
        }
        return $this->where($where)->find();
    }
}