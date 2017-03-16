<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu  at: 2016/11/21  14:24
 */
namespace app\common\logic;

use app\common\model\GoodsCategory as GoodsCategory;
use think\Controller;
use think\model;
use think\Db;

class Feature extends Controller
{



    /**
     * 根据分类ID获取特征值
     */
    public function getTypes(){
        $ids = $this->request->param('ids');
        if(empty($ids)){
            $this->error('请输入正确的ID');
        }
        $model = new GoodsCategory();
        $res = $model->where('category_id',$ids)->find();    //根据分类ID取得类型ID
        if($res['type_id']){
            //根据类型ID取得特征ID
            $types = Db::name('type_feature')->field('feature_id')->where('type_id',$res['type_id'])->select();
            if($types && is_array($types)){
                $temp = array();
                foreach($types as $k=>$v){
                    $temp[] = $v['feature_id'];
                }
                //根据特征ID取得特征名称
                $searchStr = implode("','",$temp);
                $features = Db::name('features')->field('feature_id,attribute_name')->where(" sales_attribute=1 AND feature_id in ('".$searchStr."')" )->select();

                if($features && is_array($features)){
                    $temp1 = array();
                    $temp2 = array();
                    foreach($features as $key=>$val){
                        $temp1[] = $val['feature_id'];
                        $temp2[$val['feature_id']] = $val['attribute_name'];
                    }


                    //根据特征ID取得特征值
                    $searchStrOne = implode("','",$temp1);
                    $featuresValue = Db::name('features_value')->field('id,feature_id,feature_value')->where(" is_delete=0 AND feature_id in ('".$searchStrOne."')" )->select();

                    if($featuresValue && is_array($featuresValue)){
                        foreach($featuresValue as $ke=>$va){
                            $featuresValue[$ke]['attribute_name'] = $temp2[$va['feature_id']];
                        }
                    }
                }
            }
        }

        if($featuresValue){
            $this->success('','',$featuresValue);
        }else{
            $this->error('未找到相关数据');
        }



    }

    /**
     * 通过分类ID获取关联类型下面的规格以及对应的规格值
     * @param string $categoryId 分类ID
     * @param string $storeId 店铺ID
     * @return array|false 规格列表
     */
    public function specifications( $categoryId, $storeId ) {
        $specificationsList = array();
        //查询分类中的
        $categoryModel = new GoodsCategory();
        $category      = $categoryModel->field('category_id, type_id')->where( array( 'category_id' => $categoryId ) )->order('sort asc')->find();
        //通过typeId查询相关联的特征
        if ( !isset( $category['type_id'] ) ) {
            return $specificationsList;
        }
        $whereType = array();
        $whereType['is_delete'] = 0;
        $whereType['type_id'] = $category['type_id'];
        $type = Db::name('type')->where($whereType)->find();
        if($type){
        //查询关联的规格ID
        $where = array( 'type_id' => $category['type_id'], 'type' => 1 );
        $link  = Db::name('type_feature')->where( $where )->column('feature_id');
        if ( count( $link ) > 0 ) {
            //规格查询
            $where = array( 'feature_id' => array( 'in',  $link), 'is_delete' => 0);
            $specificationsList = Db::name('features')->where( $where )->order('sort asc')->select();
            //规格值查询
            $where['store_id'] = $storeId;
            //$where['category_id'] = $categoryId;
            $valueList = Db::name('features_value')->where( $where )->order('sort asc')->select();
            foreach ($specificationsList as $key => $specifications) {
                $specifications['features_value'] = array();
                //拼接规格值
                foreach ($valueList as $tag => $value) {
                    if ( $value['feature_id'] == $specifications['feature_id'] ) {
                        array_push($specifications['features_value'], $value);
                        unset($valueList[$tag]);
                    }
                }
                $specificationsList[$key] = $specifications;
            }
        }
        }
        return $specificationsList;
    }


    /**
     * 通过分类ID获取关联类型下面的属性以及对应的属性值
     * @param string $categoryId 分类ID
     * @return array 属性列表
     */
    public function attribute( $categoryId ) {
        $specificationsList = array();
        $attributeList = array();
        //查询分类中的
        $categoryModel = new GoodsCategory();
        $category      = $categoryModel->field('category_id, type_id')->where( array( 'category_id' => $categoryId ) )->order('sort asc')->find();
        //通过typeId查询相关联的特征
        if ( !isset( $category['type_id'] ) ) {
            return $attributeList;
        }
        //查询关联的属性ID
        $where = array( 'type_id' => $category['type_id'], 'type' => 2 , 'visual' => 1);
        $link  = Db::name('type_feature')->where( $where )->column('feature_id');
        if ( count( $link ) > 0 ) {
            //属性查询
            $where = array( 'feature_id' => array( 'in',  $link), 'is_delete' => 0);
            $specificationsList = Db::name('features')->where( $where )->order('sort asc')->select();
            //属性值查询
            $valueList = Db::name('features_value')->where( $where )->order('sort asc')->select();
            foreach ($specificationsList as $key => $specifications) {
                $specifications['features_value'] = array();
                //拼接规格值
                foreach ($valueList as $tag => $value) {
                    if ( $value['feature_id'] == $specifications['feature_id'] ) {
                        array_push($specifications['features_value'], $value);
                        unset($valueList[$tag]);
                    }
                }
                $specificationsList[$key] = $specifications;
            }
        }
        return $specificationsList;
    }

    /**
     * 通过类型列表，来获取类型下面所有的属性和属性值
     * @param array $typeIdList 类型
     * @return array $attributeList 属性列表
     */
    public function getFeatureByType( $typeIdList ) {
        $attributeList = array();
        if ( empty( $typeIdList ) ) {
            return $attributeList;
        }
        //查询关联的属性ID
        $where = array( 'type_id' => array( 'in', $typeIdList ), 'type' => 2, 'visual' => 1 );
        $link  = Db::name('type_feature')->field('type_id, feature_id')->where( $where )->select();
        if ( !$link ) {
            return $attributeList;
        }
        $featureIdList = array();
        foreach ($link as $feature) {
            array_push($featureIdList, $feature['feature_id']);
        }
        //属性查询
        $where = array( 'feature_id' => array( 'in', array_values( $featureIdList ) ), 'is_delete' => 0);
        $specificationsList = Db::name('features')->where( $where )->order('sort asc')->select();
        //属性值查询
        $valueList = Db::name('features_value')->where( $where )->order('sort asc')->select();
        foreach ($specificationsList as $key => $specifications) {
            $specifications['features_value'] = array();
            //拼接规格值
            foreach ($valueList as $tag => $value) {
                if ( $value['feature_id'] == $specifications['feature_id'] ) {
                    array_push($specifications['features_value'], $value);
                    unset($valueList[$tag]);
                }
            }
            foreach ($link as $tag => $attribute) {
                if ( $attribute['feature_id'] == $specifications['feature_id'] ) {
                    $link[$tag]['feature'] = $specifications;
                    break 1;
                }
            }
        }

        foreach ($link as $feature) {
            $key = $feature['type_id'];
            if ( !array_key_exists($key, $attributeList) ) {
                $attributeList[ $key  ] = array($feature['feature']);
            } else {
                array_push($attributeList[ $key  ], $feature['feature']);
            }
        }
        //拼接前端所需要的fid
        foreach ($attributeList as $key => $attribute) {
            $number = count($attribute);
            foreach ($attribute as $tag => $feature) {
                foreach ($feature['features_value'] as $work => $value) {
                    $fid  = array_fill(0, $number, 0);
                    $fid[$tag] = $value['id'];
                    $feature['features_value'][$work]['fid'] =  join('_', $fid);
                }
                $attribute[$tag] = $feature;
            }
            $attributeList[$key] = $attribute;
        }
        return $attributeList; 
    }
}
