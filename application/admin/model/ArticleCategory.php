<?php
/**
 * 菜单分类模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\admin\model;

use think\Model;
use think\Cache;

class ArticleCategory extends Model
{
    /**
     * 对数据进行规整
     * @param int $from 默认0来自缓存， 1来自数据库并缓存
     * @return array $dataArray 菜单数组
     */
    public function expand( $from = 0 ) {
        $dataArray  = array();
		$result = $this->getCache();
        if ( $from == 0 && (!empty($result))) {
            return $dataArray;
        }
        //查询数据并缓存
        $tblRows = $this->where(['is_delete'=>0])->order('sort desc,id asc')->select();
        foreach ($tblRows as $key => $row) {
            $dataArray[$row['id']] = $row;
        }
        self::setCache($dataArray);
        return $dataArray;
    }
    /**
     * 获取菜单分类缓存
     * @param string $key 索引值
     * @return array $result 菜单分类数组，以id为一级索引
     */
    public static function getCache( $key = 'article_category' ) {
        $result = Cache::get($key);
        if (empty($result)) {
			$result = self::where(['is_delete'=>0])->order('sort desc,id asc')->select();
            return $result;
        }
        return $result;
    }
    /**
     * 根据传入的数据缓存menu
     * @param array $dataArray 菜单分类数组，以id为一级索引
     * @return bool $result 缓存的结果，true or false
     */
    public static function setCache( $dataArray, $key = 'article_category' ) {
        Cache::set($key, $dataArray);
    }
}
