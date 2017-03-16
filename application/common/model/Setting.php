<?php
/**
 * 配置模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-11-23 9:16
 */

namespace app\common\model;
use think\Model;
use think\Cache;

class Setting extends Model{

    /**
     * 获取配置数据
     * @return array $settingList 菜单数组
     */
    public function inquire( ) {
    	$settingList  = array();
    	if ( ( $settingList = Cache::get('setting') ) != false ) {
    		return $settingList;
    	}
    	//数据查询，保证子节点在父节点后
    	$temp = $this->select();
    	//格式化为key,value的格式
    	foreach ($temp as $setting) {
    		$key = $setting['key'];
    		$settingList[$key] = $setting['content'];
    	}
        //缓存菜单
        Cache::set('setting', $settingList);
        return $settingList;
    }

    /**
     * 清除数据并重新生成缓存
     */
    public function rebuild() {
    	Cache::rm('setting');
    	$this->inquire();
    }
}