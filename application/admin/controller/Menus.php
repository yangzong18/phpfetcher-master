<?php
/**
 * 菜单控制器
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-10-24 17:08
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\admin\model\Menu;

class Menus extends Auth
{
    
    protected $model;
    protected $field = 'id, parent_id as parentid, app, controller, action, data, type, status, name, icon, sort as listorder';
    /**
     * 构造器
     */
    public function __construct() {
    	parent::__construct();
    	$this->model = new Menu();
    } 

    /**
     * 菜单显示页
     */
	public function index() {
        $menuObjectList = $this->model->field( $this->field )->order(array("sort" => "asc"))->select();
        $menuList = array();
        foreach ($menuObjectList as $key => $menuObject) {
            $menuList[$key] = $menuObject->toArray();
        }
        $tree = new \Util\Tree();
        $tree->icon = array('&nbsp;&nbsp;&nbsp;│ ', '&nbsp;&nbsp;&nbsp;├─ ', '&nbsp;&nbsp;&nbsp;└─ ');
        $tree->nbsp = '&nbsp;&nbsp;&nbsp;';
        foreach ($menuList as $key=> $menu) {
            $menuList[$key]['parentid_node'] = ( $menu['parentid'] ) ? ' class="child-of-node-' . $menu['parentid'] . '"' : '';
            $menuList[$key]['style']         = empty( $menu['parentid'] ) ? '' : 'display:none;';
            $menuList[$key]['str_manage']    = '<a href="add?parent_id='.$menu['id'].'">添加子菜单</a> | <a href="edit?id='.$menu['id'].'">编辑</a> | <a class="js-ajax-delete" href="delete?id='.$menu['id'].'">删除</a> ';
            $menuList[$key]['status']        = $menu['status'] ? '显示' : '隐藏';
            $menuList[$key]['app']           = $menu['app']."/".$menu['controller']."/".$menu['action'];
        }
        $tree->init($menuList);
        $str = "<tr id='node-\$id' \$parentid_node style='\$style'>
                    <td style='padding-left:20px;'  class='text-center'><input name='listorders[\$id]' type='text' size='3' value='\$listorder' class='input input-order'></td>
                    <td>\$id</td>
                    <td>\$app</td>
                    <td>\$spacer\$name</td>
                    <td>\$status</td>
                    <td>\$str_manage</td>
                </tr>";
        $categorys = $tree->get_tree(0, $str);
        $this->assign("categorys", $categorys);
        return $this->fetch();
	}

	/**
     * 添加菜单页
     */
    public function add() {
        $tree     = new \Util\Tree();
        $parentId = $this->request->param('parent_id', 0, 'intval');
        $menuObjectList = $this->model->field( $this->field )->order(array("sort" => "asc"))->select();
        $menuList = array();
        foreach ($menuObjectList as $key => $menuObject) {
            $menuList[$key] = $menuObject->toArray();
            $menuList[$key]['selected'] = $menuObject->id == $parentId ? 'selected' : '';
        }
        $option = "<option value='\$id' \$selected>\$spacer \$name</option>";
        $tree->init($menuList);
        $selectCategorys = $tree->get_tree(0, $option);
        $this->assign("selectCategorys", $selectCategorys);
        return $this->fetch();
    }
    
    /**
     * 添加菜单方法
     */
    public function addPost() {
        $param = $this->request->param();
        if ( trim( $param['name'] ) == '' ) {
            $this->error('名称不能为空');
        }
        if ( trim( $param['app'] ) == '' ) {
            $this->error('应用名称不能为空');
        }
        if ( trim( $param['controller'] ) == '' ) {
            $this->error('控制器不能为空');
        }
        if ( trim( $param['action'] ) == '' ) {
            $this->error('方法不能为空');
        }
        if ( $this->model->data( $param )->save() ) {
            //改动触发菜单缓存更新
            $this->model->expand(1);
            $this->success('添加成功', url('index'));
        } else {
            $this->error('添加失败');
        }
    }


    /**
     *  编辑菜单
     */
    public function edit() {
        $tree   = new \Util\Tree();
        $id     = $this->request->param('id', 0, 'intval');
        $menu   = $this->model->where(array("id" => $id))->find();
        $menuObjectList = $this->model->field( $this->field )->order(array("sort" => "asc"))->select();
        $menuList = array();
        foreach ($menuObjectList as $key => $menuObject) {
            $menuList[$key] = $menuObject->toArray();
            $menuList[$key]['selected'] = $menuObject->id == $menu->parent_id ? 'selected' : '';
        }
        $option = "<option value='\$id' \$selected>\$spacer \$name</option>";
        $tree->init($menuList);
        $selectCategorys = $tree->get_tree(0, $option);
        $this->assign("data", $menu);
        $this->assign("selectCategorys", $selectCategorys);
        return $this->fetch();
    }

    /**
     * 编辑菜单方法
     */
    public function editPost() {
        $param = $this->request->param();
        if ( trim( $param['name'] ) == '' ) {
            $this->error('名称不能为空');
        }
        if ( trim( $param['app'] ) == '' ) {
            $this->error('应用名称不能为空');
        }
        if ( trim( $param['controller'] ) == '' ) {
            $this->error('控制器不能为空');
        }
        if ( trim( $param['action'] ) == '' ) {
            $this->error('方法不能为空');
        }
        $id = $param['id'];
        unset($param['id']);
        if ( $this->model->save($param, array('id'=>$id)) ) {
            //改动触发菜单缓存更新
            $this->model->expand(1);
            $this->success('编辑成功', url('index'));
        } else {
            $this->error('编辑失败');
        }
    }

    /**
     * 删除菜单方法
     */
    public function delete() {
        $id = $this->request->param('id', 0, 'intval');
        //子节点判断
        $count = $this->model->where(array('parent_id'=>$id))->count();
        if ( $count > 0 ) {
            $this->error('删除失败, 请先删除子节点');
        }
        if ( $this->model->destroy(array('id'=>$id)) ) {
            //改动触发菜单缓存更新
            $this->model->expand(1);
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }


    /**
     * 删除菜单方法
     */
    public function sort() {
        $id   = $this->request->param('id', 0, 'intval');
        $sort = $this->request->param('sort', 0, 'intval');
        $this->model->save( array( 'sort' => $sort ), array( 'id' => $id) );
        $this->success('编辑成功');
    }



}
