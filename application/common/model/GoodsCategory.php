<?php
/**
 * Created by PhpStorm.
 * Project: code
 * User: Administrator
 * Date: 2016/11/14
 * Time: 16:29
 * Author: ss.wu
 * Update by laijunliang at 2016-11-25
 */
namespace app\common\model;

use think\Model;
use think\Cache;

class GoodsCategory extends Model {
	/**
	 * 初始化查询分类，如果有，则从缓存中取如果没有，则查询并传入缓存
	 */
	public function cacheCategory() {
		$categoryList  = Cache::get('goods_category');
    	if (  $categoryList !== false ) {
    		return $categoryList;
    	}
		$temp = $this->field('category_id, name, parent_id')->where(array('is_delete'=>0))->order('sort asc')->select();
		foreach ($temp as $category) {
			$key = $category->category_id;
			$categoryList[$key] = $category->toArray();
		}
		 //缓存菜单
        Cache::set('goods_category', $categoryList);
        return $categoryList;
	}
    
    /**
     * 根据子节点向上找到所有的父级
     */
	public function getParentId( $id ) {
		$categoryList = $this->cacheCategory();
        $parentList = array();
        $parentId   = '0';
        foreach ($categoryList as $key => $category) {
        	if ( $category['category_id'] == $id ) {
        		array_push($parentList, $category);
        		$parentId = $category['parent_id'];
        	}
        }
        $number = 0;
        while ( $parentId !== '0' && $number < 5 ) {
        	foreach ($categoryList as $key => $category) {
        		if ( $category['category_id'] == $parentId ) {
        			array_push($parentList, $category);
        		    $parentId = $category['parent_id'];
                    break 1;
        		}
        	}
            $number++;
        }
        return array_reverse($parentList);
	}

    /**
     * 根据名称查询某个分类的ID
     * @param $name string
     * @return  string
     */
    public function getOneId($name){
        if(!$name){
            return '';
        }

        $result = $this->field('category_id')
            ->where(array('name' => $name))
            ->find();

        if($result){
            return $result['category_id'];
        }else{
            return '';
        }
    }

    /**
     * 获取某个分类下的子分类
     * @param $cid string 分类ID
     * @return array
     */
    public function getNextLevel($cid){
//        if(!$cid){
//            return array();
//        }
        $result = $this->field('*')
            ->where(array('parent_id' => $cid,'is_delete'=>0))
            ->order('sort asc')
            ->select();

        return $result;

    }
}