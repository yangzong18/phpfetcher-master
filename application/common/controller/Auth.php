<?php
/**
 * 权限验证与参数验证控制器, 比如需要登陆验证的控制器，均继承该控制器
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-10-26 15:03
 */
namespace app\common\controller;

use think\Db;
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
        // echo "<pre>";
        // print_r($this->user);
        // exit;
        //判断是否多地登录,不要修改
//        $memberInfo = Db::name('member')->where('member_id',$this->user->member_id)->find();
//        if( !empty($memberInfo) && $memberInfo['session_id'] != session_id() ){
//            Session::delete('seller');
//            $this->success('帐号已在别处登录', $this->request->domain().'/seller/login');
//        }
	}

	/**
	 * 如果是Get请求，要从获取用户有权限的菜单
	 */
	public function userMenu() {
		$app        = $this->request->module();
		$controller = $this->request->controller();
		$action     = $this->request->action();
		$url  = strtolower($app.'.'.$controller.'.'.$action);
        //TODO, 暂时从数据库读取数据
        $menuList = ( new Menu() )->expand(1);
        //如果包含有超级管理员，则拥有所有权限，否则只有查询到的权限
        if ( !in_array(1, $this->user['role']) ) {
            foreach ($menuList as $key => $menu) {
                if ( !in_array( $key , $this->user['auth_list']) ) {
                    unset($menuList[$key]);
                }else{
					//如果有子菜单, 过滤子菜单中没有权限的
					if ( $menu['has_children'] == 1 ) {
						$childrenIdList  = explode(',', $menu['children_id_list']);
						foreach ($childrenIdList as $tag => $childrenId) {
							if ( !in_array( $childrenId , $this->user['auth_list']) ) {
								unset($childrenIdList[$tag]);
							}
						}
						//查找所有的子节点
						$menu['children_id_list'] = array_values($childrenIdList);
						$menu['has_children']     = count( $menu['children_id_list'] ) == 0 ? 0 : 1;
					}
					$menuList[$key] = $menu;
				}
            }
        }
        $parentId    = 0;
        $topMenuList = array();
        $navList     = array();
		$authUrl = [];
        foreach ($menuList as $key => $menu) {
            //如果是父节点，并且有子节点，那么他的链接应该是默认的排在最前的子节点
            if ( $menu['has_children'] == 1 && in_array(1, $this->user['role']) ) {
                $childrenIdList  = explode(',', $menu['children_id_list']);
                //查找所有的子节点
                $menu['children_id_list'] = array_values($childrenIdList);
            }
            //判定当前的地址
            if ( $menu['url'] == $url  ) {
                $menu['current'] =  1 ;
                $parentIdList = explode(',', $menuList[$key]['parent_id_list']);
                foreach ($parentIdList as $parent) {
                    if ( $parent == 0 ) {
                        continue;
                    }
                    //获取第一个子菜单
                    $subMenuList = array();
                    foreach ($menuList as $menuOpt) {
                        if ($menuOpt['parent_id'] == $parent && $menuOpt['status'] == 1 ){
                            $subMenuList[] = $menuOpt;
                        }
                    }
                    //如果没有可显示的子节点，那么就是父节点本身
                    if ( count( $subMenuList ) == 0 ) {
                        array_push($subMenuList, $menuList[$parent]);
                    }
                    //重新对子菜单进行排序
                    ksort($subMenuList);
                    $menuList[$parent]['current'] = 1;
                    $menuList[$parent]['url'] = $subMenuList[0]['url'];
                    $menuList[$parent]['app'] = $subMenuList[0]['app'];
                    $menuList[$parent]['controller'] = $subMenuList[0]['controller'];
                    $menuList[$parent]['action'] = $subMenuList[0]['action'];
                    if (in_array($menuList[$parent], $navList)){
                        continue;
                    }
                    array_push($navList, $menuList[$parent]);
                }
                $parentId = count( $parentIdList ) > 1 ? $parentIdList[1] : $menu['id'];
                if (in_array($menu, $navList)){
                    continue;
                }
                array_push($navList, $menu);
            } else {
                $menu['current'] = 0;
            }
            $menuList[$key] = $menu;
			$authUrl[$key] = $menu['url'];
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
		if(!in_array($url,$authUrl)){
			$this->error('没有此权限');
		}
        if ( $parentId == 0 ) {
            $this->error('未找到匹配的菜单项，请添加');
        }
        $this->assign('navList', $navList);
        $this->assign('topMenu', $topMenuList[$parentId]);
        $this->assign('topMenuList', Tools::selectSort( array_values($topMenuList), 'sort'));
        $this->assign('menuList', $menuList);
        $this->assign('seller', $this->user);
	}

	/**
	 * 参数过滤
	 */
	public function filter() {
        
	}

    /**
     * 文件路径检测与创建
     * nameList: 文件路径列表
     * base_dir: 文件起始位置
     */
    public function checkDirectory($nameList, $base_dir=".")
    {
        if (count($nameList) <= 1){
            return;
        }
        $dir = $base_dir .DS. array_shift($nameList);
        if (!file_exists($dir)){
            mkdir($dir);
        }
        self::checkDirectory($dir, $nameList);
    }
}
