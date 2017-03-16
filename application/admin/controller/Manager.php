<?php
/**
 * 系统权限配置，管理员管理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: Luo Tingting at 2016-11-15
 */
namespace app\admin\controller;
use app\admin\model\ManagerRole;
use app\common\controller\Auth;
use app\common\model\Member;
use app\admin\model\Seller;
use think\Db;
use think\Validate;
use Util\Tools;

class Manager extends Auth{

    protected $model;
    protected $seller;

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Member();
        $this->seller = new Seller();
    }

    /**
     * 管理员列表
     */
    public function index() {
        //1.管理员id
        $seller = $this->seller->where('is_delete', 0)->column('member_id');
        //2.管理员具体信息
        $memberList = $this->model
            ->where( array('member_id' => array('in', $seller)) )
            ->paginate(10);
        //3.管理员角色关联关系
        $managerRoleRelation = Db::name('manager_role_relation')
                               ->where( array('member_id' => array('in', $seller)) )
                               ->select();
        $list = array();//关联关系
        foreach( $managerRoleRelation as $key => $val) {
            $list[$val['member_id']] = $val['role_id'];
        }

        $this->assign('managerList', $memberList);
        $this->assign('relationList', $list);
        $this->getRole();
        return $this->fetch();
    }

    /**
     * 查看详情
     */
    public function detail() {
        $id     = $this->request->param('id', 0, 'trim');
        $data   = $this->model->where('member_id', $id)->find();
        $roleId = Db::name('manager_role_relation')
            ->where('member_id', $id)
            ->value('role_id');

        $this->getRole();
        $this->assign('data', $data);
        $this->assign('roleId', $roleId);
        return $this->fetch();
    }

    /**
     * 管理员新增页
     */
    public function add() {
        $this->getRole( ['status'=>1,'is_delete'=>0] );
        return $this->fetch();
    }

    /**
     * 管理员新增
     */
    public function addPost() {
        $memberName = $this->request->param('memberName', '', 'trim');
        $memberPassword = $this->request->param('memberPassword', '', 'trim');
        $memberRPassword = $this->request->param('memberRPassword', '' ,'trim');
        $phone = $this->request->param('phone', '', 'trim');
        $roleId = $this->request->param('roleId', '', 'intval');
        $account = $this->request->param('account', '', 'trim');
        $email = $this->request->param('email', '', 'trim');
        $memberInfo = array(
            'member_name' => $memberName,
            'password' => $memberPassword,
            'repartPassword' => $memberRPassword,
            'phone' => $phone,
            'role_id' => $roleId,
            'account' => $account,
            'email' => $email
        );
        //验证提交数据
        $result = $this->validateData($memberInfo);
        if( !$result['code'] ) $this->error($result['msg']);

        unset($memberInfo['role_id']);
        unset($memberInfo['repartPassword']);
        $memberInfo['department'] = $this->request->param('department', '', 'trim');
        $memberInfo['remark'] = $this->request->param('remark', '', 'trim');
        $memberInfo['status'] = $this->request->param('status', '', 'intval');
        $memberInfo['member_id'] = Tools::guid();

        //开启事务
        Db::startTrans();
        if( $this->model->save($memberInfo) ) {//新增会员
            $managerRole = ['member_id' => $memberInfo['member_id'], 'role_id' => $roleId];
            //添加会员角色关联
            $result = Db::name('manager_role_relation')->insert($managerRole);
            if( !$result ) {
                Db::rollback();
                $this->error('添加管理员角色关系失败');
            }
            $sellerInfo = ['member_id' => $memberInfo['member_id'], 'status' => $memberInfo['status'],
                          'store_id' => 1, 'member_name' => $memberName];
            if( !$this->seller->save($sellerInfo) ) {
                Db::rollback();
                $this->error('添加管理员失败');
            }
        } else {
             $this->error('添加会员失败');
        }
        // 提交事务
        Db::commit();
        $this->success('添加成功', 'index');
    }

    /**
     * 管理员编辑页
     */
    public function edit() {
        $id     = $this->request->param('id', 0, 'trim');
        $data   = $this->model->where('member_id', $id)->find();
        $roleId = Db::name('manager_role_relation')
                  ->where('member_id', $id)
                  ->value('role_id');
        $this->getRole( ['status'=>1,'is_delete'=>0] );
        $this->assign('data', $data);
        $this->assign('roleId', $roleId);
        return $this->fetch();
    }

    /**
     * 管理员编辑
     */
    public function editPost() {
        $memberId = $this->request->param('memberId', '', 'trim');
        $memberName = $this->request->param('memberName', '', 'trim');
        $memberPassword = $this->request->param('memberPassword', '', 'trim');
        $memberRPassword = $this->request->param('memberRPassword', '' ,'trim');
        $roleId = $this->request->param('roleId', '', 'intval');
        $email = $this->request->param('email', '', 'trim');

        $updateInfo = array(
            'member_name' => $memberName,
            'role_id' => $roleId,
            'email' => $email
        );
        //验证提交数据
        $result = $this->validateData($updateInfo, 1);
        if( !$result['code'] ) $this->error($result['msg']);
        if(!empty($memberPassword)){
            if( $memberPassword !== $memberRPassword) $this->error('两次密码不一致');
            $updateInfo['password'] = $memberPassword;
        }

        unset($updateInfo['role_id']);
        $updateInfo['department'] = $this->request->param('department', '', 'trim');
        $updateInfo['remark'] = $this->request->param('remark', '', 'trim');
        $updateInfo['status'] = $this->request->param('status', '', 'intval');
        $updateInfo['member_id'] = $memberId;

        //开启事务
        Db::startTrans();
        if( $this->model->update($updateInfo) ) {//更新会员
            $result = Db::name('manager_role_relation')
                      ->where('member_id', $memberId)
                      ->update(['role_id'=> $roleId]);//更新会员角色关联
            if( $result === false ) {
                Db::rollback();
                $this->error('更新管理员角色关联失败');
            }
            if( $this->seller->where('member_id', $memberId)
                ->update([ 'status' => $updateInfo['status'], 'member_name' => $memberName]) ===false){
                Db::rollback();
                $this->error('更新管理员失败');
            }
        } else {
            $this->error('更新会员失败');
        }
        // 提交事务
        Db::commit();
        $this->success('编辑成功', 'index');
    }

    /**
     * 获取角色信息
     * @param array $were 查询条件
     */
    private function getRole( $were = array() ) {
        $list = ( new ManagerRole() )->where($were)->select();
        $roleList = array();
        foreach($list as $key => $val ) {
            $roleList[$val['role_id']]['id'] = $val['role_id'];
            $roleList[$val['role_id']]['name'] = $val['name'];
        }

        $this->assign('roleList', $roleList);
    }

    /**
     * 删除管理员方法
     */
    public function delete() {
        $managerId  = $this->request->param('id', 'intval', 0);
        //只需要将seller表中删除即可
        if( !$this->seller->save(array( 'is_delete' => 1 ), array( 'member_id' => $managerId )) ) {
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }

    /**
     * 批量删除管理员方法
     */
    public function deleteChecked() {
        $managerId  = explode(',', $this->request->param('id_list'));
        $where   = array( 'member_id' => array( 'in', $managerId ) );

        if( count( $managerId ) <= 0 )
            $this->error('删除失败');
        //只需要将seller表中删除即可
        if( !$this->seller->save(array( 'is_delete' => 1 ), $where) ) {
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }

    /**
     * 添加、编辑表单提交数据验证
     * @param $data 待验证数据
     * @param $isEdit 是否是编辑
     * @return array
     */
    private function validateData( $data, $isEdit = 0 ){
        $rule = [
            'account'  => 'require|unique:member|desc',
            'member_name'   => 'require|desc',
            'phone' => 'require|regex:/^1[34578]\d{9}$/|unique:member',
            'role_id'=> 'require',
            'email'=> 'email',
            'password'=> 'require',
            'repartPassword' => 'require|confirm:password',
        ];
        $msg = [
            'account.require'  => '登录帐号不能为空',
            'account.unique'  => '登录帐号已存在',
			'account.desc'  => '登录帐号含有特殊字符',
            'password.require'  => '密码不能为空',
            'repartPassword.require'  => '确认密码不能为空',
            'repartPassword.confirm'  => '两次输入密码不一致',
            'member_name.require'   => '姓名不能为空',
			'member_name.desc'		=> '姓名含有特殊字符',
            'phone.require' => '联系方式不能为空',
            'phone.regex' => '手机号无效',
            'phone.unique'  => '手机号码已存在',
            'role_id.require' => '角色不能为空',
            'email.email' => '邮箱格式不正确'
        ];

        $validate = new Validate($rule, $msg);
        $validate->scene('edit', ['member_name','role_id', 'email']);
        if( $isEdit == 1) {//假如是编辑
            if( !$validate->scene('edit')->check($data) ){
                return ['code' => 0, 'msg' => $validate->getError()];
            }
            return ['code' => 1];
        } else {//新增
            if( !$validate->check($data) ){
                return ['code' => 0, 'msg' => $validate->getError()];
            }
            return ['code' => 1];
        }

    }
}
