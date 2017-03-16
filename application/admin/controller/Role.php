<?php
/**
 * 系统权限配置，用户角色管理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: Luo Tingting at 2016-11-14
 */

namespace app\admin\controller;
use app\admin\model\Menu;
use app\admin\model\ManagerRole;
use app\common\controller\Auth;
use think\Db;
use think\Cache;
use think\Validate;

class Role extends Auth
{
    protected $model;

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new ManagerRole();
    }

    /**
     * 角色管理列表
     */
    public function index() {
        $roleList = $this->model->where('is_delete',0)->paginate(10);
        $this->assign("roles", $roleList);
        return $this->fetch();
    }
	protected $field = 'id, parent_id as parentid, app, controller, action, data, type, status, name, icon, sort as listorder';

    /**
     * 新增角色页
     */
    public function add() {
		$menuList = ( new Menu() )->expand();
		$result = $this->createTree($menuList);
		$this->assign('roleList',json_encode($result));
        return $this->fetch();
    }

    /**
     *新增角色方法
     */
    public function addPost(){
        $roleName   = $this->request->param('roleName');
        $roleValues = $this->request->param('roleValues');
		$roleValues = explode(',',$roleValues);
        $status     = $this->request->param('status','','intval');
        $remark     = $this->request->param('remark','','trim');

		$data = ['name'=>$roleName,'roleValues'=>$roleValues,'remark'=>$remark];
		$result = $this->validateData($data);
		if( !$result['code'] ) $this->error($result['msg']);
        $roleInfo = array(
            'name' => $roleName,
            'status' => $status,
            'remark' => $remark
        );
        //开启事务
        Db::startTrans();
        $roleId = Db::name('manager_role')->insertGetId($roleInfo);//新增角色
        if( !$roleId ) { //新增角色失败
            $this->error('添加失败');
        }
        foreach( $roleValues as $k => $v ) {
            $roleMenus[] = array('role_id' => $roleId, 'menu_id' => $v);
        }
        //新增角色菜单操作
        $result = Db::name('manager_role_auth')->insertAll($roleMenus);
        if( !$result ) {
            Db::rollback();
            $this->error('添加失败');
        }
        //提交事务
        Db::commit();
        $this->success('添加成功', 'index');
    }

    /**
     * 编辑角色页
     */
    public function edit() {
        $id     = $this->request->param('id', 0, 'intval');
        $data   = $this->model->where('role_id', $id)->find();
        $menuArray =  Db::name('manager_role_auth')
                      ->where('role_id', $data->role_id)
                      ->column('menu_id');
		$menuList = ( new Menu() )->expand();
		$result = $this->createTreeDetail($menuList,$menuArray,$detail=true);
        $this->assign('roleList', json_encode($result));
        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     *编辑角色方法
     */
    public function editPost(){
        $roleId     = $this->request->param('roleId', 0, 'intval');
        $roleName   = $this->request->param('roleName');
        $roleValues = $this->request->param('roleValues');
		$roleValues = explode(',',$roleValues);
        $status     = $this->request->param('status','','intval');
        $remark     = $this->request->param('remark','','trim');
		$data = ['name'=>$roleName,'roleValues'=>$roleValues,'remark'=>$remark];
		$result = $this->validateData($data);
		if( !$result['code'] ) $this->error($result['msg']);
        $roleInfo = array(
            'name' => $roleName,
            'status' => $status,
            'role_id' => $roleId,
            'remark' => $remark
        );
        //开启事务
        Db::startTrans();
        if( $this->model->update($roleInfo) ) {//编辑角色权限表
           //删除角色菜单表
            Db::name('manager_role_auth')->where('role_id', $roleId)->delete();
            foreach( $roleValues as $k => $v ) {
                $roleMenus[] = array('role_id' => $roleId, 'menu_id' => $v);
            }
            $result = Db::name('manager_role_auth')->insertAll($roleMenus);//新增角色菜单
            if( !$result ) {
                Db::rollback();
                $this->error('编辑失败');
            }
        } else {
            $this->error('编辑失败');
        }
        // 提交事务
        Db::commit();
        $this->success('编辑成功','index');
    }

    /**
     * 查看角色页
     */
    public function detail() {
        $id     = $this->request->param('id', 0, 'intval');
        $data   = $this->model->where('role_id', $id)->find();
        $menuArray =  Db::name('manager_role_auth')
            ->where('role_id', $data->role_id)
            ->column('menu_id');
		$menuList = ( new Menu() )->expand();
		$result = $this->createTreeDetail($menuList,$menuArray);
		$this->assign('roleList', json_encode($result));
        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 删除角色方法
     */
    public function delete() {
        $roleId  = $this->request->param('id', 'intval', 0);
        if ( $this->model->save(array( 'is_delete' =>1 ), array( 'role_id' => $roleId )) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 批量删除角色方法
     */
    public function deleteChecked() {
        $roleId  = explode(',', $this->request->param('id_list'));
        $where   = array( 'role_id' => array( 'in', $roleId ) );
        if ( count( $roleId ) > 0 && $this->model->save(array( 'is_delete' =>1 ), $where) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

	/**
	 * yp 2017-2-13    新生成数组方便zTree 插件使用   编辑和新增的时候使用
	 * @param $array
	 * @return array|mixed
	 */
	private function createTree($array){
		$result = array();
		foreach($array as $key => $val){
			$tmp['id'] = $array[$key]['id'];
			$tmp['name'] = $array[$key]['name'];
			$tmp['pId']  = $array[$key]['parent_id'];
			if($array[$key]['name'] == '首页'){
				$tmp['checked']  = true;
				$tmp['doCheck']  = false;
			}else{
				$tmp['checked']  = false;
				$tmp['doCheck']  = true;
			}
			$result[] = $tmp;
		}
		return $result;
	}

	private function createTreeDetail($roleList,$menuList,$detail=false){
		$result = array();
		foreach($roleList as $key => $val){
			$tmp['id'] = $val['id'];
			$tmp['name'] = $val['name'];
			$tmp['pId']  = $val['parent_id'];
			if($val['name'] == '首页'){
				$tmp['checked']  = true;
				$tmp['doCheck']  = false;
			}else{
				if(in_array($val['id'],$menuList)){
					$tmp['checked']  = true;
					$tmp['doCheck']  = true;
				}else{
					$tmp['checked']  = false;
					$tmp['doCheck']  = true;
				}
			}
			if($detail){
				$tmp['chkDisabled']  = false;
			}else{
				$tmp['chkDisabled']  = true;
			}
			$result[] = $tmp;
		}
		return $result;
	}
	/**
	 * 获取菜单
	 */
	private function getChildMenus($parent_id) {
		$menuList = ( new Menu() )->expand();
		$roleList = array();
		foreach($menuList as $k => $val) {
			if( $val['parent_id'] == $parent_id ) {
				$roleList[$val['id']]['id'] = $val['id'];
			}
		}
		return $roleList;
	}

	/**
	 * 添加、编辑表单提交数据验证
	 * @param $data 待验证数据
	 * @param $isEdit 是否是编辑
	 * @return array
	 */
	private function validateData( $data)
	{
		$rule = [
			'name' => 'require|desc',
			'roleValues' => 'require',
			'remark' => 'desc',
		];
		$msg = [
			'name.require' => '角色名字不能为空',
			'name.unique' => '角色名字已存在',
			'name.desc' => '角色名字含有特殊字符',
			'roleValues.require' => '权限选择不能为空',
			'remark.desc' => '描述含有特殊字符',
		];
		$validate = new Validate($rule, $msg);
		$result = $validate->check($data);
		if (!$result) {
			return ['code' => 0, 'msg' => $validate->getError()];
		}
		return ['code' => 1];
	}
}
