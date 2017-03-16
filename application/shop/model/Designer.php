<?php
/**
 * 前端设计师模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang  at: 2016/12/08  9:07
 */
namespace app\shop\model;
use think\Model;

class Designer extends Model{

    /**
     * * 查找设计师列表
     * @param array $where 查询条件
     * @param string $field
     * @param array $where
     * @param string $field
     * @param int $limit
     * @param string $order
     * @return \think\paginator\Collection
     */
    public function getDesignerList( $where = [], $field = '*', $limit = 0, $order='', $isPage = false) {
        if( $isPage ) {
            return $this->where( $where )->field($field)->paginate(12);
        }
        return $this->where( $where )->field($field)->order($order)->limit($limit)->select();
    }

    /**
     * 查询单条记录
     * @param array $where 查询条件
     * @param string $field
     * @return array|false
     */
    public function getDesignerInfo( $where = [], $field = '*') {
        $arr = $this->where( $where )->field($field)->find();
        if(empty($arr)){
            return array();
        }else{
            return $arr->toArray();
        }

    }
}