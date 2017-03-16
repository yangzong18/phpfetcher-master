<?php
/**
 * 众筹商品扩展模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/9  16:03
 */
namespace app\seller\model;

use think\Model;

class CrowdfundingGoodsExtra extends Model
{

    /**
     * 添加一条商品扩展数据
     */
    public function addCrowdfundingGoodsExtra($data){
        $this->insert($data);
    }


    /**
     * 获取一条众筹扩展商品
     */
    public function getCrowdfundingGoodsExtra($id){
        if(!$id){
            return array();
        }
        return $this->where("crowdfunding_goods_id = '".$id."'")->find();
    }


    /**
     * 保存一条众筹商品扩展
     * @param $data array
     * @param $where array
     * @return bool
     */
    public function saveCrowdfundingGoodsExtra($data,$where){
        if(!$data){
            return false;
        }
        return $this->save($data,$where);
    }
}