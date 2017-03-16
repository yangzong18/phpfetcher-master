<?php
/**
 * 后台菜单模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-10-25 8:57
 */
namespace app\admin\model;

use think\Model;
use think\Cache;

class Menu extends Model
{

    /**
     * 对数据进行规整，增加children和parent信息
     * @param int $from 默认0来自缓存， 1来自数据库并缓存
     * @return array $menuList 菜单数组
     */
    public function expand( $from = 0 ) {
    	$menuList  = array();
    	if ( $from == 0 && ( $menuList = Cache::get('menu') ) != false ) {
    		return $menuList;
    	}

    	//数据查询，保证子节点在父节点后
    	$menuObjectList = $this->order('parent_id asc')->select();
    	$menuTempList   = array();
    	$menuIdList     = array();    
    	//强所有的菜单对象转为数组, 并找到顶层的菜单
        foreach ($menuObjectList as $key => $menu) {
            if ( $menu->parent_id == 0 ) {
            	$menuId = $menu->id;
            	//首先找到顶层节点
            	array_push($menuIdList, $menuId);
            	$menuList[$menuId] = $menu->toArray();
                $menuList[$menuId]['children_id_list'] = array();
            	continue;
            }
            array_push($menuTempList, $menu->toArray());

        }
        //对子节点进行排序，保证sort小的在前
        $menuTempList = $this->selectSort( $menuTempList, 'sort' );
        //采用自顶向下的方式查找，并排序菜单, 保证父节点在前
        while ( count( $menuTempList ) > 0  ) {
        	foreach ($menuTempList as $key => $menu) {
        		//$parentId = $menu['parent_id'];
        		if ( in_array($menu['parent_id'], $menuIdList) ) {
        			//保存该节点
        			array_push($menuIdList, $menu['id']);
                    //如果子节点要显示，才计入子节点
                    if ( $menu['status'] == 1 ) {
                        array_push($menuList[$menu['parent_id']]['children_id_list'], $menu['id']);
                    }
        			$menu['children_id_list'] = array();
        			$menuList[$menu['id']] = $menu;
        			//去掉改节点，减少遍历次数
        			unset( $menuTempList[$key] );
        		}
        	}
        }
        //对每个节点进行父节点和子节点的查找, 并格式化子节点
        foreach ($menuList as $key => $menu) {
        	//格式化子节点
        	$menuList[$key]['has_children'] = count($menu['children_id_list']) == 0 ? 0 : 1;
        	$menuList[$key]['children_id_list'] = join(',', $menu['children_id_list']);
            $menuList[$key]['url'] = strtolower($menu['app'].'.'.$menu['controller'].'.'.$menu['action']);
        	//如果没有父节点，则父节点列表则是他的本生
        	if ( $menu['parent_id'] == 0 ) {
        		$menuList[$key]['parent_id_list'] = '0';
        	} else {
        		//如果有父节点，则继承父节点的父节点
        		$parentId = $menu['parent_id'];
        		$menuList[$key]['parent_id_list'] = $menuList[$parentId]['parent_id_list'].','.$parentId;
        	}
        }
        //缓存菜单
        Cache::set('menu', $menuList);
        return $menuList;
    } 
    
    /**
     * 对所有的菜单排序，保证父节点在前
     * @return array $menuList 菜单数组
     */
    public function sortMenu( ) {
        //数据查询，保证子节点在父节点后
    	$menuObjectList = $this->order('parent_id asc')->select();
    	$menuList       = array();
    	$menuTempList   = array();
    	$menuIdList     = array();    
    	//强所有的菜单对象转为数组, 并找到顶层的菜单
        foreach ($menuObjectList as $key => $menu) {
            if ( $menu->parent_id == 0 ) {
            	$menuId = $menu->id;
            	//首先找到顶层节点
            	array_push($menuIdList, $menuId);
            	$menuList[$menuId] = $menu->toArray();
            	continue;
            }
            array_push($menuTempList, $menu->toArray());

        }
        //采用自顶向下的方式查找，并排序菜单, 保证父节点在前
        while ( count( $menuTempList ) > 0  ) {
        	foreach ($menuTempList as $key => $menu) {
        		$parentId = $menu['parent_id'];
        		if ( in_array($parentId, $menuIdList) ) {
        			//保存该节点
        			array_push($menuIdList, $menu['id']);
        			$menuId = $menu['id'];
        			$menuList[$menuId] = $menu;
        			//去掉改节点，减少遍历次数
        			unset( $menuTempList[$key] );
        		}
        	}
        }
        return $menuList;
    }

    /**
     * 选择排序
     * @param array $data 要排序的数组
     * @param string $key 要排序的key
     * @return array $result 排序的结果
     */
    public function selectSort( $data, $key ) {
        $number = count( $data );
        for ($i=0; $i < $number-1; $i++) { 
            $minValue = $data[$i][$key];
            //定义最小方法
            $min = $i;
            for ($j=$i+1; $j < $number; $j++) { 
                if ( $data[$j][$key] < $minValue ) {
                    $minValue = $data[$j][$key];
                    $min      = $j;
                }
            }
            //进行交换
            $temp     = $data[$i];
            $data[$i] = $data[$min];
            $data[$min] = $temp;
        }
        return $data;
    }

    /**
     * 根据传入的数据缓存menu
     * @param array $menuList 菜单数组
     * @return bool $result 缓存的结果，true or false
     */
    public function cacheMenu( $menuList, $key = 'menu' ) {
        Cache::set($key, $menuList);
    }

}
