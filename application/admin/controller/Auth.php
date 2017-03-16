<?php
/**
 * 权限验证与参数验证控制器, 比如需要登陆验证的控制器，均继承该控制器
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-10-26 15:03
 */
namespace app\admin\controller;

use think\Session;
use think\Controller;
use app\admin\model\Menu;
use Util\Tools;

class Auth extends Controller {

    protected $user;
    
	/**
	 * 初始化构造器
	 */
	public function __construct() {
		parent::__construct();
		//登陆验证
		$this->loginCheck();
		if ( strtolower( $this->request->method() ) == 'get' ) {
			$this->userMenu();
		}
	}
    
    /**
	 * 登陆验证
	 */
	public function loginCheck() {
		//如果session不存在或者为空，则跳转到登陆页面
        if ( !Session::has('seller') ) {
        	$this->redirect($this->request->domain().'/seller/login');
        }
        $this->user = unserialize( Session::get('seller') );
	}

	/**
	 * 如果是Get请求，要从获取用户有权限的菜单
	 */
	public function userMenu() {
		$app        = $this->request->module();
		$controller = $this->request->controller();
		$action     = $this->request->action();
		$url  = strtolower($app.'.'.$controller.'.'.$action);
        $menuList = ( new Menu() )->expand();
        //如果包含有超级管理员，则拥有所有权限，否则只有查询到的权限
        if ( !in_array(1, $this->user['role']) ) {
            foreach ($menuList as $key => $menu) {
                if ( !in_array( $key , $this->user['auth_list']) ) {
                    unset($menuList[$key]);
                }
            }
        }
        $parentId    = 0;
        $topMenuList = array();
        foreach ($menuList as $key => $menu) {
            //如果是父节点，并且有子节点，那么他的链接应该是默认的排在最前的子节点
    		if ( $menu['has_children'] == 1 ) {
    			$childrenIdList  = explode(',', $menu['children_id_list']);
                //查找所有的子节点
                $menu['children_id_list'] = $childrenIdList;
    		}
        	//判定当前的地址
        	if ( $menu['url'] == $url ) {

        		$menu['current'] = 1;
        		$parentIdList = explode(',', $menuList[$key]['parent_id_list']);
        		foreach ($parentIdList as $parent) {
                    if ( $parent == 0 ) {
                        continue;
                    }
        			$menuList[$parent]['current'] = 1;
        		}
        		$parentId = count( $parentIdList ) > 1 ? $parentIdList[1] : $menu['id'];
        	} else {
        	    $menu['current'] = 0;
        	}
        	$menuList[$key] = $menu;
        }
        foreach ($menuList as $key => $menu) {
            if ( $menu['parent_id'] == 0 ) {
                unset($menuList[$key]);
                //如果有子节点，那么默认应该是子节点信息
                if ( $menu['has_children'] == 1 ) {
                    $childrenId = $menu['children_id_list'][0];
                    while ( $menuList[$childrenId]['has_children'] == 1 ) {
                        $childrenId = $menuList[$childrenId]['children_id_list'][0];
                    }
                    $menu['app']        = $menuList[$childrenId]['app'];
                    $menu['controller'] = $menuList[$childrenId]['controller'];
                    $menu['action']     = $menuList[$childrenId]['action'];
                }
                $topMenuList[$key]  = $menu;
            } 
        }

        $this->assign('topMenu', $topMenuList[$parentId]);
        $this->assign('topMenuList', $topMenuList);
        $this->assign('menuList', $menuList);
	}

	/**
	 * 参数过滤
	 */
	public function filter() {
        
	}
}
