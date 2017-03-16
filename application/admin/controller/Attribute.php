<?php
/**
 * 属性管理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-17 13:41
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\admin\model\Features;
use app\admin\model\FeaturesValue;
use think\Db;

class Attribute extends Auth
{
    protected $model;
    /**
     * 构造器
     */
    public function __construct() {
    	parent::__construct();
        $this->model = new Features();
    } 

    /**
     *  编辑规格
     */
    public function edit() {
        $id     = $this->request->param('id', 0, 'intval');
        $where  = array("feature_id" => $id);
        $data   = $this->model->where( $where )->find();
        //属性值查找
        $featuresValueModel = new FeaturesValue();
        $attributeValueList = $featuresValueModel->field('id, feature_id, feature_value, sort')->where( $where )->order('sort asc')->select();
        //关系查找
        $link = Db::name('type_feature')->where( $where )->find();
        $data['visual'] = isset( $link['visual'] ) ? $link['visual'] : 0;
        $this->assign('data', $data);
        $this->assign('attributeValueList', $attributeValueList);
        return $this->fetch();
    }

   

    /**
     * 编辑规格方法
     */
    public function editPost() {
        $param = $this->request->param();
        if ( !isset( $param['feature_id'] ) 
             || !is_numeric( $param['feature_id'] ) 
             || 0 >= $param['feature_id'] ) {
            $this->error('错误的属性ID');
        }
        if ( !isset( $param['attribute_name'] ) || trim( $param['attribute_name'] ) == '' ) {
            $this->error('属性名称不能为空');
        }
        if ( !isset( $param['sort'] ) || !is_numeric($param['sort']) ) {
            $param['sort'] = 1;
        }
        if ( !isset( $param['visual'] ) || !in_array($param['visual'], array(0,1)) ) {
            $param['visual'] = 0;
        }
        //进行属性编辑
        $where = array( 'feature_id' => $param['feature_id'] );
        $data  = array(
            'attribute_name' => $param['attribute_name'],
            'sort'           => $param['sort']
        );
        $this->model->save( $data, $where );
        //进行属性和类型管理的可视化编辑
        //因为属性对类型只能是一对一，所以就直接可编辑
        Db::name('type_feature')->where( $where )->update( array( 'visual' => $param['visual'] ) );
        //进行属性值的编辑
        $featuresValueModel = new FeaturesValue();
        if ( isset( $param['edit_value_id'] ) 
             && is_array( $param['edit_value_id'] ) 
             && count( $param['edit_value_id'] ) > 0 ) {
            //循环编辑
            foreach ($param['edit_value_id'] as $key => $id) {
                $where = array( 'id' => $id );
                $data  = array( 
                    'feature_value' => $param['edit_value'][$key], 
                    'sort' => $param['edit_value_sort'][$key] 
                );
                //进行编辑
                $featuresValueModel->save( $data, $where );
            }
        }
        //进行属性值的添加
        if ( isset( $param['add_value'] ) 
             && is_array( $param['add_value'] ) 
             && count( $param['add_value'] ) > 0 ) {
            //拼接数据
            $data = array();
            foreach ($param['add_value'] as $key => $value) {
                array_push($data, array(
                    'feature_id'    => $param['feature_id'],
                    'feature_value' => $value,
                    'sort' => $param['add_value_sort'][$key]
                ));
            }
            $featuresValueModel->saveAll( $data );
        }
        $this->success('编辑成功');
    }

}
