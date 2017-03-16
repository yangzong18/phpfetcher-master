<?php
/**
 * 设计师作品、事例展示
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: luoTing at 2016-11-22
 */

namespace app\common\model;
use app\common\model\GoodsCategory;
use think\Model;
use think\Config;


class DesignerProduction extends Model{

    protected $type = [
        'upload_time'  =>  'timestamp:Y/m/d',
    ];
    private $cidNum;  //存放分类ID



    /**
     * 获取原木整装商品风格，即二级分类
     */
    public function getStyle() {
        $this->cidNum = Config::get('logs_goos_category_id');
        $category = new GoodsCategory();
        //读取二级分类
        //$category_id = $category->getOneId('原木整体家具');
        $topCategory = $category->getNextLevel($this->cidNum);

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
}