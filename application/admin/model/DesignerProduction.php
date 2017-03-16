<?php
/**
 * 设计师作品
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: luoTing at 2016-11-22
 */

namespace app\admin\model;
use app\common\model\GoodsCategory;
use think\Model;
use think\Config;


class DesignerProduction extends Model{

    protected $type = [
        'upload_time'  =>  'timestamp:Y/m/d',
    ];
    private $cidNum;  //存放分类ID

    public function __construct() {
        parent::__construct();
        $this->cidNum = Config::get('logs_goos_category_id');
    }

    /**
     * 获取原木整装商品风格，即二级分类
     */
    public function getStyle() {
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
}