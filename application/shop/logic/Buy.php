<?php
/**
 * 下单逻辑处理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷婷 <ljl6907603@sina.cn> at 2016-11-10 15:10
 */

namespace app\shop\logic;

use think\Db;

class Buy {

    /**
     * 购物车中商品信息处理
     * @param $cartList  购物车列表
     * @return array
     */
    public function getCartList( $cartList ) {
        if (empty($cartList) || !is_array($cartList)) return $cartList;
        //得到goods_sku,goods_id
        $skuList = []; $goodsIdList = [];
        foreach( $cartList as $val ) {
            $skuList[] = $val['goods_sku'];
            $goodsIdList[] = $val['goods_id'];
        }
        //查询商品sku
        $goodsSkuList = Db::name('goods_sku')->where( ['goods_sku'=> ['in', $skuList]] )->select();
        $groupList = [];
        foreach($goodsSkuList as $val) {
            $goodsSkuList[$val['goods_sku']] = $val;
            $groupList[] = $val['group_id'];
        }
        //查询商品
        $goodsList = Db::name('goods')->where( ['goods_id'=> ['in', $goodsIdList], 'is_delete' => 0] )->select();
        foreach($goodsList as $val) {
            $goodsList[$val['goods_id']] = $val;
        }
        //根据group_id查询商品包含规格信息
        $saleFeature = Db::name('goods_sale_feature')->where(['group_id'=> ['in', $groupList]])->select();
        $goodsFeatureList = [];
        foreach( $saleFeature as $key => $val ) {
            $goodsFeatureList[$val['group_id']][] = $val;
        }
        //查询所有的规格信息
        $featureAll = Db::name('features')
                      ->field('feature_id, attribute_name')
                      ->where(['is_delete'=>0, 'sales_attribute'=> 1])
                      ->select();
        $featureList = [];
        foreach( $featureAll as $key => $val ) {
            $featureList[$val['feature_id']] = $val;
        }
        //查询所有的规格值数据
        $featureValueAll = Db::name('features_value')
                      ->field('id, feature_id, feature_value')
                      ->where(['is_delete'=>0, 'feature_id'=> ['in', array_keys($featureList)]])
                      ->select();
        $featureValueList = [];
        foreach( $featureValueAll as $key => $val ) {
            $featureValueList[$val['id']] = $val;
        }
        //购物车列表增加商品信息
        foreach($cartList as $key => $val) {
            if( in_array($val['goods_sku'], array_keys($goodsSkuList)) ){
                $cartList[$key]['goods_price'] = $goodsSkuList[$val['goods_sku']]['goods_price'];
                $cartList[$key]['goods_storage_number'] = $goodsSkuList[$val['goods_sku']]['goods_number'];
                $cartList[$key]['goods_storage'] = $goodsSkuList[$val['goods_sku']]['goods_storage'];
                $cartList[$key]['group_id'] = $goodsSkuList[$val['goods_sku']]['group_id'];
                $cartList[$key]['goods_storage_alarm'] = $goodsSkuList[$val['goods_sku']]['goods_storage_alarm'];

                $cartList[$key]['goods_name'] = $goodsList[$val['goods_id']]['goods_name'];
                $cartList[$key]['goods_verify'] = $goodsList[$val['goods_id']]['goods_verify'];
                $cartList[$key]['goods_image_main'] = $goodsList[$val['goods_id']]['goods_image_main'];
                foreach($goodsFeatureList[$goodsSkuList[$val['goods_sku']]['group_id']] as $value) {
                    $cartList[$key]['feature'][] = ['feature'=>$featureList[$value['feature_id']]['attribute_name'],
                                                    'feature_value'=>$featureValueList[$value['feature_value_id']]['feature_value']];
                }
            }
        }
       return $cartList;
    }
}
