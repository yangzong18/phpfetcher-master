<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/2  9:54
 */
namespace app\shop\model;

use think\Db;
use think\Model;

class Goods extends Model{

    public function goodsSaleFeature(){

        return $this->hasMany('goods_sale_feature','goods_id');
    }


    /**
     * 根据商品ID获取商品商品
     * @param $id array/string 商品ID可以是数组
     * @return array
     */
    public function getGoodsDetail($id){

        if(!$id) return array();
        if(is_array($id)){
            $searchStr = implode("','",$id);
        }else{
            $searchStr = $id;
        }

        $res = $this->where("goods_id IN ('".$searchStr."')")->select();
        if($res){
            foreach($res as $k=>$v){
                $res[$k]['goods_sale_feature'] = Db::name('goods_sale_feature')->where("goods_id = '".$v->goods_id."'")->find();
                $res[$k]['goods_basic_feature'] = Db::name('goods_basic_feature')->where("goods_id = '".$v->goods_id."'")->find();
                $res[$k]['goods_sku'] = Db::name('goods_sku')->where("goods_id = '".$v->goods_id."'")->find();
            }
        }


        return $res;
    }
}