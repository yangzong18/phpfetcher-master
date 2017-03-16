<?php
/**
 * 商品详情页
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang at 2016/12/01
 */

namespace app\shop\controller;
use app\common\controller\Shop;
use app\common\model\Goods as NormalGoods;
use app\common\model\GoodsSku;
use app\common\model\Attachment;
use app\shop\model\Features;
use app\shop\model\FeaturesValue;
use think\Db;

class Goods extends Shop{

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * 商品详情页面
     */
    public function index() {
        $goodsId    = $this->request->param('gk', '0');
        //查询商品信息
        $where      = array( 'goods_id' => $goodsId, 'is_delete' => 0 );
        $goodsModel = new NormalGoods();
        $goods      = $goodsModel->where( $where  )->find();
        //如果商品不存在，则提示错误
        if ( !$goods ) {
            $this->error('商品不存在');
        }
        unset($where['is_delete']);
        //查询商品是否被收藏
        $fav = 0;
        //李武修改
        if( $this->login == 1 && !empty($goodsId)){
            if(Db::name('favorites')->where(['goods_id'=>$goodsId,'member_id'=>$this->user['member_id']])->value('id')){
                $fav = 1;
            }
        }

        $this->assign('fav',$fav);

        //查询商品额外信息
        $extra = Db::name('goods_extra')->where( $where )->find();
        //查询商品所有的sku
        $skuModel = new GoodsSku();
        $goodsList= $skuModel->where( $where )->order('goods_price asc')->select();
        //查询商品所有的规格和规格值
        $saleFeatureList = Db::name('goods_sale_feature')->where( $where )->select();
        $featureList     = $this->formatFeature( $saleFeatureList );

        //-------颜色 对应 图片--------
        $featureColor = null;
        $featureColorGroup = array();
        $featureColorGroupUrl = array();
        if($featureList){
            foreach($featureList as $k=>$v){
                if($v['is_color'] == 1){
                    $featureColor = $v;
                }
            }
        }
        if($featureColor){
            foreach($featureColor['feature_value'] as $k=>$v){
                foreach($saleFeatureList as $key=>$val){
                    if($v['id'] == $val['feature_value_id']){
                        $featureColorGroup[$v['id']][] = $val['group_id'];
                    }
                }
            }
        }
        //查询商品附图
        $attachmentModel = new Attachment();
        if($featureColorGroup){
            foreach($featureColorGroup as $k=>$v){
                if($v){
                    $where           = array('is_delete' => 0, 'business_sn'=>'group_id', 'business_id'=> array('in', $v) );
                    $colorUrl  = $attachmentModel->field('attachment_url ')->where( $where )->order('sort asc')->select();
                    if($colorUrl){
                        foreach($colorUrl as $key=>$val){
                            $featureColorGroupUrl[$k][] = $val['attachment_url'];
                        }

                    }
                }
            }
        }
        //--------  颜色对应图片  end ---------

        //将分组查询出来，以便于查询附图
        $groupIdList = array();
        foreach ($saleFeatureList as $saleFeature) {
            $key = $saleFeature['group_id'];
            if ( !in_array($saleFeature['group_id'], $groupIdList) ) {
                array_push($groupIdList, $key);
            } 
        }
        //查询商品附图
        $where           = array('is_delete' => 0, 'business_sn'=>'group_id', 'business_id'=> array('in', $groupIdList) );
//        $attachmentModel = new Attachment();
        $attachmentList  = $attachmentModel->field('business_id, attachment_url as address')->where( $where )->order('sort asc')->select();
        //将附图分类, 拼接规格和规格指
        foreach ($goodsList as $key => $unit) {
            $unit = $unit->toArray();
            $unit['attachment_list'] = array();
            foreach ($attachmentList as $tag => $attachment) {
                if ( $unit['group_id'] == $attachment['business_id'] ) {
                    array_push( $unit['attachment_list'], $attachment->toArray() );
                    unset( $attachmentList[$tag] );
                }
            }
            $unit['value_id_list'] = array();
            foreach ($saleFeatureList as $tag => $saleFeature) {
                if ( $saleFeature['group_id'] == $unit['group_id'] ) {
                    array_push($unit['value_id_list'], $saleFeature['feature_value_id']);
                    unset($saleFeatureList[$tag]);
                }
            }
            unset($unit['sku_name']);
            sort($unit['value_id_list']);
            $goodsList[$key] = $unit;
        }
        //验证是否无规格商品
        $hideSpec = 0;
        $storage  = 0;
        $sku      = '';
        if ( count( $featureList ) == 1 && $featureList[0]['feature_id'] == 1 
            && count( $featureList[0]['feature_value'] ) == 1 && $featureList[0]['feature_value'][0]['id'] == 1 ) {
            $hideSpec = 1;
            $storage  = $goodsList[0]['goods_storage'];
            $sku      = $goodsList[0]['goods_sku'];
        }
        //将价格最低的的sku附图作为默认的商品
        //----2017.2.15  ss.wu 修改没有颜色规格的情况下默认图片  start -----
        if(empty($featureColorGroupUrl)){
            foreach($goodsList as $k=>$v){
                if(!empty($v['attachment_list'])){
                    $goods['default_goods'] = $v;
                }
            }
            if(empty($goods['default_goods'])){
                $goods['default_goods'] = $goodsList[0];
            }
        }else{
            $goods['default_goods'] = $goodsList[0];
        }
        //----------- 2017.2.15  end ---------------

        $this->assign('colorUrl',$featureColorGroupUrl);
        $this->assign( 'goods', $goods );
        $this->assign( 'extra', $extra );
        $this->assign( 'hideSpec', $hideSpec );
        $this->assign( 'storage', $storage );
        $this->assign( 'sku', $sku );
        $this->assign( 'featureList', $featureList );
        $this->assign( 'featureLength', count( $featureList )  );
        $this->assign( 'goods_group', json_encode( $goodsList ) );
        return $this->fetch();
    }


    /**
     * 格式化特征和特征值
     * @param array $data 传入的数组，里面含有feature_id和featur_value_id一对一关系
     * @return array $featureList 格式化的结果
     */
    private function formatFeature( $data ) {
        $featureModel      = new Features();
        $featureValueModel = new FeaturesValue();
        $featureList   = array();
        $featureIdList = array();
        $featureValueIdList = array();
        foreach ($data as $key => $feature) {
            //录入id
            if ( !in_array( $feature['feature_id'] , $featureIdList) ) {
                array_push($featureIdList, $feature['feature_id']);
            }
            //录入特征值id
            array_push($featureValueIdList, $feature['feature_value_id']);
        }
        //进行特征和特征值的查询
        if ( count( $featureIdList ) > 0 ) {
            //特征查询
            $where       = array( 'feature_id' => array( 'in',  $featureIdList));
            $featureList = $featureModel->field('feature_id,attribute_name,is_color')->where( $where )->order('sort asc')->select();
            //特征值查询
            $where       = array( 'id' => array( 'in', $featureValueIdList ));
            $featureValueList = $featureValueModel->field('id,feature_id,feature_value')->where( $where )->order('sort asc')->select();
            //拼接特征和特征值
            foreach ($featureList as $key => $feature) {
                $feature = $feature->toArray();
                $feature['feature_value'] = array();
                foreach ($featureValueList as $tag => $featureValue) {
                    if ( $feature['feature_id'] == $featureValue['feature_id'] ) {
                        array_push($feature['feature_value'], $featureValue->toArray());
                        unset( $featureValueList[$tag] );
                    }
                }
                $featureList[$key] = $feature;
            }
        }

        return $featureList;
    }


    //猜你喜欢
    public function favGoods(){
        $goodsList = Db::name('goods')->where(['is_delete'=>0, 'goods_verify'=>1])
            ->field('goods_id,goods_image_main,goods_name,goods_price,goods_sale_number')
            ->limit(3)->select();
        foreach( $goodsList as $key => $val) {
            $goodsList[$key]['goods_url'] = url('shop/goods/index', ['gk' => $val['goods_id']]);
        }
        return $goodsList;
    }
}
