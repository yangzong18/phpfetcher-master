<?php
/**
 * 区域模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: Luo Ting 2016-11-21
 */

namespace app\mobile\model;
use think\Cache;
use think\Model;

class Area extends Model{

    /**
     * 查询区域列表
     * @param array $condition 条件
     * @param string $fields 字段
     * @return Object array
     */
    public function getAreaList($condition = array(), $fields = '*') {
        return $this->where($condition)->field($fields)->select();
    }

    /**
     * 查询地址详情
     * @param array $condition 条件
     * @param string $fields 字段
     * @return Object
     */
    public function getAreaInfo($condition = array(), $fields = '*') {
        return $this->where($condition)->field($fields)->find();
    }

    /**
     * 获取一级地址（省级）名称数组
     * @return array 键为id 值为名称
     */
    public function getTopLevelAreas() {
        $data = $this->getCache(1);
        $arr = array();
        foreach ($data['children'][0] as $i) {
            $arr[$i] = $data['name'][$i];
        }

        return $arr;
    }

    /**
     * 根据上级获取下级列表
     * @param $id 上级id
     * @return 下属区域列表
     */
    public function getNextAreaList( $id ,$deep=1) {
        if($deep<1 || $deep>4) $deep=1;
        $data = $this->getCache($deep);
        $arr = array();
        foreach ($data['children'][$id] as $i) {
            $arr[$i] = $data['name'][$i];
        }

        return $arr;
    }

    /**
     * 获取地区缓存
     * @return array
     */
    public function getAreas($deep=1) {
        if($deep<1 || $deep>4) $deep=1;
        return $this->getCache($deep);
    }

    /**
     * 获取全部地区名称数组
     * @return array 键为id 值为名称字符串
     */
    public function getAreaNames($deep=1) {
        $data = $this->getCache($deep);
        return $data['name'];
    }

    /**
     * 获取用于前端js使用的全部地址数组
     * @return array
     */
    public function getAreaArrayForJson($parentId,$deep=1) {
        if($deep<1 || $deep>4) $deep=1;
        $data = $this->getCache($deep);
        $arr = array();
        if(array_key_exists($parentId,$data['children'])) {
            $sub_arear_arr = $data['children'][$parentId];
            foreach ($sub_arear_arr as $vv) {
                $arr[] = array('id'=>$vv, 'name'=>$data['name'][$vv]);
            }
        }
        return $arr;
    }

    /**
     * 获取区域缓存
     * @param int $deep 1 - 4
     * @return 区域列表
     */
    protected function getCache($deep=1)
    {
        if($deep<1 || $deep>4) $deep=1;

        if( ($areaList = Cache::get('area_'.$deep) ) != false ){
            return $areaList;
        }

        $areaList = array();

        // 数据查询
        //$area_all_array = $this->limit(false)->select();
        $area_all_array = $this->getAreaList(array('area_deep'=>$deep));
        foreach ( $area_all_array as $a ) {
            $areaList['name'][$a['area_id']] = $a['area_name'];
            $areaList['parent'][$a['area_id']] = $a['area_parent_id'];
            $areaList['children'][$a['area_parent_id']][] = $a['area_id'];

            if ( $a['area_deep'] == 1 && $a['area_region'] )
                $areaList['region'][$a['area_region']][] = $a['area_id'];
        }

        //缓存区域
        Cache::set('area_'.$deep, $areaList);
        return $areaList;
    }

}
