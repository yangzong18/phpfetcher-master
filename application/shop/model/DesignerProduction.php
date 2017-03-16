<?php
/**
 * 设计师作品
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: luoTing at 2016-12-14
 */

namespace app\shop\model;
use think\Db;
use think\Model;


class DesignerProduction extends Model{

    protected $type = [
        'upload_time'  =>  'timestamp:Y/m/d',
    ];
    private $cidNum = '6aa3a1e261ea54ca94225bb7756b6701';  //存放分类ID
    private $cidName = '原木整体家具';  //存放分类名称

    /**
     * 获取作品列表
     * @param $where
     * @param $field
     * @param $limit
     * @param $isPage 是否分页
     * @param $number 每页显示个数
     * @param string $order
     * @return \think\paginator\Collection
     */
    public function productionList( $where, $field = '*', $limit = 0, $isPage = false, $number = 2,$order ='production_id desc') {
        if( $isPage ) {
            return $this->where( $where )->field($field)->order($order)->paginate($number, false, ['query'=>$where]);
        }
        return $this->where( $where )->field($field)->order($order)->limit($limit)->select();
    }

    /**
     * 获取原木整装商品风格，即二级分类
     */
    public function getStyle() {
        $category = new GoodsCategory();
        //读取二级分类
        $category_id = $category->getOneId($this->cidName);
        $topCategory = $category->getNextLevel($category_id);

        $style= [];
        $info = [];
        foreach( $topCategory as $val ) {
            $info = $val->toArray();
            $style[$info['category_id']] = $info['name'];
        }
        unset($info);
        return $style;
    }

    /**
     * 获取实例展示详情
     */
    public function getProductionInfo( $where ,$field = '*'){
        return $this->where( $where )->field( $field )->find();
    }
}