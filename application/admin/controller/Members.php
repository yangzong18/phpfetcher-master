<?php
/**
 * 会员管理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: Luo Tingting at 2016-11-16
 */

namespace app\admin\controller;

use app\api\controller\SentTemplatesSMS;
use app\common\controller\Auth;
use app\common\model\LogsDecorationOrder;
use app\common\model\Member;
use app\admin\model\Seller;
use Excel\Excel;
use think\Db;
use think\Model;
use think\Validate;
use Util\Tools;

class Members extends Auth{

    protected $model;
    protected $regularMember = 1;
    protected $twoLevelMember = 2;
    protected $seller;
    protected $search = ['phone' => '手机号',
        'account' => '帐号',
        'member_name' => '姓名'];

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Member();
        $this->seller = new Seller();
    }

    /**
     * 普通会员列表
     */
    public function  regularIndex() {
        $searchType = $this->request->param('searchType');
        $searchValue = $this->request->param('searchValue', '', 'trim');
		if(preg_match('/[\'\!\@\#\$\%\^\&\*\+\_\=\:\<\>\"]/', $searchValue)){
			$this->error('查询内容含有特殊字符');
		}
        $list = $this->seller->column('member_id');
        $where = ['type' => $this->regularMember, 'member_id' => ['not in', $list]];
        if( $searchType != '' && $searchValue != '')
            $where[$searchType] = array('like', '%'.$searchValue.'%');

        $memberList = $this->model->where( $where )->paginate(10);
        //查询原木整装数据
        $idList = [];
        foreach( $memberList as $val) {
            $idList[] = $val['member_id'];
        }
        if( empty($idList) ) $idList = array(0);

        $order = new LogsDecorationOrder();
        $orderList = $order->field('member_id,count(id) as count')
                            ->where( ['delete_status'=>0,'member_id'=>['in', $idList]] )
                            ->group('member_id')->select();
        $idList = [];
        foreach( $orderList as $val) {
            $idList[$val['member_id']] = $val['count'];
        }
        //输出
        $this->assign('memberList', $memberList);
        $this->assign('search', $this->search);
        $this->assign('searchType', $searchType);
        $this->assign('searchValue', $searchValue);
        $this->assign('type', $this->regularMember);
        $this->assign('count', $idList);
        return $this->fetch();
    }

    /**
     * 二级代理用户列表
     */
    public function  twoLevelIndex() {
        $searchType = $this->request->param('searchType');
        $searchValue = $this->request->param('searchValue', '', 'trim');
        $where = ['type' => $this->twoLevelMember];
        if( $searchType != '' && $searchValue != '')
            $where[$searchType] = array('like', '%'.$searchValue.'%');

        $memberList = $this->model->where( $where )->paginate(10);
        //查询原木整装数据
        $idList = [];
        foreach( $memberList as $val) {
            $idList[] = $val['member_id'];
        }
        if( empty($idList) ) $idList = array(0);

        $order = new LogsDecorationOrder();
        $orderList = $order->field('member_id,count(id) as count')
            ->where( ['delete_status'=>0,'member_id'=>['in', $idList]] )
            ->group('member_id')->select();
        $idList = [];
        foreach( $orderList as $val) {
            $idList[$val['member_id']] = $val['count'];
        }
        $this->assign('memberList', $memberList);
        $this->assign('search', $this->search);
        $this->assign('searchType', $searchType);
        $this->assign('searchValue', $searchValue);
        $this->assign('type', $this->twoLevelMember);
        $this->assign('count', $idList);
        return $this->fetch();
    }

    /**
     * 添加二级代理
     */
    public function addAgent() {
        return $this->fetch();
    }

    /**
     * 二级代理新增提交
     */
    public function addAgentPost() {
        $company = $this->request->param('company', '', 'trim');
        $companyPhone = $this->request->param('company_phone', '', 'trim');
        $memberPassword = $this->request->param('memberPassword', '', 'trim');
        $memberRPassword = $this->request->param('memberRPassword', '' ,'trim');
        $name = $this->request->param('Name', '', 'trim');
        $phone  = $this->request->param('phone', '', 'trim');
        $email  = $this->request->param('email', '', 'trim');

        $memberInfo = [];
        $memberInfo['department'] = $company;
        $memberInfo['member_name'] = $name;
        $memberInfo['phone'] = $phone;
        $memberInfo['email'] = $email;
        $memberInfo['password'] = $memberPassword;
        $memberInfo['repartPassword'] = $memberRPassword;

        //验证提交数据
        $result = $this->validateMember($memberInfo, 3);
        if( !$result['code'] ) $this->error($result['msg']);

        $memberInfo['member_id'] = Tools::guid();
        $memberInfo['type'] =  $this->twoLevelMember;
        $memberInfo['account'] = $phone;
        unset($memberInfo['repartPassword']);

        if( !$this->model->save($memberInfo) ) {//注册会员
            $this->error('添加二级代理失败');
        }
        $this->success('添加二级代理成功', 'twoLevelIndex');
    }

    /**
     * 编辑二级代理
     */
    public function editAgent() {
        $memberId = $this->request->param('id', '', 'trim');
        $data = $this->model->where('member_id', $memberId)->find();
        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 二级代理编辑提交
     */
    public function editAgentPost() {
        $memberId = $this->request->param('id', '', 'trim');
        if( $memberId == '') $this->error('编辑二级代理失败');
        $company = $this->request->param('company', '', 'trim');
        $companyPhone = $this->request->param('company_phone', '', 'trim');
        $name = $this->request->param('Name', '', 'trim');
        $email  = $this->request->param('email', '', 'trim');
        $memberPassword = $this->request->param('memberPassword', '', 'trim');
        $memberRPassword = $this->request->param('memberRPassword', '' ,'trim');

        $memberInfo = [];
        $memberInfo['department'] = $company;
        $memberInfo['member_name'] = $name;
        $memberInfo['email'] = $email;

        //验证提交数据
        $result = $this->validateMember($memberInfo, 4);
        if( !$result['code'] ) $this->error($result['msg']);
        if( $memberPassword !== $memberRPassword)
            $this->error('两次密码不一致');
        if( $memberPassword != '')//用户更改密码
            $memberInfo['password'] = $memberPassword;
        $memberInfo['member_id'] = $memberId;

        if( !$this->model->update($memberInfo) ) {
            $this->error('编辑二级代理失败');
        }
        $this->success('编辑二级代理成功', 'twoLevelIndex');
    }

    /**
     *重置帐号
     */
    public function resetAccount() {
        $memberId = $this->request->param('memberid', '', 'trim');
        $account = $this->request->param('account', '', 'trim');
        $type = $this->request->param('type', '', 'intval');
        $this->assign('memberId', $memberId);
        $this->assign('account', $account);
        $this->assign('type', $type);
        $this->view->engine->layout(false);
        return $this->fetch();
    }

    /**
     *重置帐号处理方法
     */
    public function resetAccountPost() {
        $memberId = $this->request->param('memberId', '', 'trim');
        if( $memberId == '') $this->error('重置帐号失败');
        //处理数据
        $account = $this->request->param('account', '', 'trim');
        $reAccount = $this->request->param('re_account', '', 'trim');
        //验证提交数据
        if( $account == '' || $reAccount == '' || $reAccount !== $account)
            $this->error('提交数据不能为空且不同');

        if( !preg_match("/^1[34578]\d{9}$/", $account) )
            $this->error('请填写正确的帐号');
        $checkMemberId = $this->model->where('phone', $account)->value('member_id');
        if( $checkMemberId && $checkMemberId != $memberId )
            $this->error('新帐号已存在，请重新输入');

        $memberInfo = [];
        $memberInfo['account'] = $account;
        $memberInfo['phone'] = $account;
        $memberInfo['member_id'] = $memberId;
        if( !$this->model->update($memberInfo) ) {
            $this->error('重置帐号失败');
        }
        $sms = new SentTemplatesSMS();
        $code = generateCode();
        //往新账号发送短信
        $sms->sent($account, [], "reset_account");
        //假如该帐号登录了手机，强制前端重新登录,罗婷
        Db::name('mobile_member_token')->where('member_id',$memberId)->delete();
        if( $this->request->param('type', '', 'intval') == $this->regularMember)
            $url = 'regularIndex';
        else
            $url = 'twoLevelIndex';
        $this->success('重置帐号成功', $url, 1);
    }

    /**
     * 删除会员方法
     */
    public function delete() {
        $memberId  = $this->request->param('id', 'intval', 0);
        if ( !$this->model->save(array( 'is_delete' => 1 ), array( 'member_id' => $memberId )) ) {
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }

    /**
     * 批量删除二级代理方法
     */
    public function deleteChecked() {
        $memberId  = explode(',', $this->request->param('id_list'));
        $where   = array( 'member_id' => array( 'in', $memberId ) );
        if( count( $memberId ) <= 0 )
            $this->error('删除失败');

        if( !$this->model->where($where)->delete() ) {
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }


    /**
     * 编辑、新增验证提交数据
     * @param $dataInfo 待验证数据
     * @param $type 场景类型(1=>会员新增 2=>会员编辑 3=>二级代理新增 4=>二级代理编辑)
     * @return array
     */
    protected function validateMember( $dataInfo, $type = 0 ) {
        $rule = [
            'department'  => 'require',
            'member_name'   => 'require',
            'phone' => 'require|unique:member|regex:/^1[34578]\d{9}$/',
            'password'=> 'require',
            'repartPassword' => 'require|confirm:password',
            'email' => 'email'
        ];
        $msg = [
            'department.require'  => '公司名称不能为空',
            'member_name.require'   => '姓名不能为空',
            'phone.require' => '手机号不能为空',
            'phone.regex' => '手机号无效',
            'phone.unique' => '手机号已存在',
            'password.require'  => '密码不能为空',
            'repartPassword.require'  => '确认密码不能为空',
            'repartPassword.confirm'  => '两次输入密码不一致',
            'email.email' => '邮箱格式不正确'
        ];

        $validate = new Validate($rule, $msg);
        $validate->scene('addAgent', ['department', 'member_name', 'phone', 'email','password','repartPassword']);
        $validate->scene('editAgent', ['department', 'member_name', 'email']);
        switch($type){
            case 3: //新增二级代理
                $result = $validate->scene('addAgent')->check( $dataInfo );
                break;
            case 4: //编辑二级代理
                $result = $validate->scene('editAgent')->check( $dataInfo );
                break;
        }
        if( !$result ) {
            return ['code' => 0, 'msg' => $validate->getError()];
        }
        return ['code' => 1];

    }

    /**
     * 导出用户到excel表中
     */
    public function exportMember()
    {
        $searchType = $this->request->param('searchType');
        $searchValue = $this->request->param('searchValue', '', 'trim');
        $type = $this->request->param('type');
        $where = ['type' => $type];
        if( $type == $this->regularMember ) {//假如是普通用户
            $sellerList = $this->seller->column('member_id');
            $where['member_id'] = ['not in', $sellerList];
        }
        //查询条件
        if( $searchType != '' && $searchValue != '')
            $where[$searchType] = array('like', '%'.$searchValue.'%');

        //用户查询
        $dataList = $this->model->where( $where )->select();
        $list = Model::getResultByFild($dataList);
        if( empty($list) ) $this->error('没有可以被导出的用户数据');

        $this->createExcel($list, $type);
    }

    /**
     * 生成excel
     *
     * @param array $data
     * @param array $type 用户类型
     */
    private function createExcel( $data = [], $type)
    {
        $excel_obj = new Excel();
        $excel_data = array();
        //设置样式
        $excel_obj->setStyle(array('id'=>'s_title','Font'=>array('FontName'=>'宋体','Size'=>'12','Bold'=>'1')));
        //假如是普通用户
        if( $type == $this->regularMember) {
            //header
            $excel_data[0][] = array('styleid'=>'s_title','data'=>'创建时间');
            $excel_data[0][] = array('styleid'=>'s_title','data'=>'称呼');
            $excel_data[0][] = array('styleid'=>'s_title','data'=>'帐号');
            $excel_data[0][] = array('styleid'=>'s_title','data'=>'手机号码');
            $excel_data[0][] = array('styleid'=>'s_title','data'=>'邮箱地址');
            //data
            foreach ((array)$data as $k=>$v){
                $tmp = array();
                $tmp[] = array('data'=>$v['created_at']);
                $tmp[] = array('data'=>$v['member_name']);
                $tmp[] = array('data'=>$v['account']);
                $tmp[] = array('data'=>$v['phone']);
                $tmp[] = array('data'=>$v['email']);
                $excel_data[] = $tmp;
            }
        } else if( $type == $this->twoLevelMember ) {//假如是二级代理
            //header
            $excel_data[0][] = array('styleid'=>'s_title','data'=>'创建时间');
            $excel_data[0][] = array('styleid'=>'s_title','data'=>'公司名称');
            $excel_data[0][] = array('styleid'=>'s_title','data'=>'姓名');
            $excel_data[0][] = array('styleid'=>'s_title','data'=>'帐号');
            $excel_data[0][] = array('styleid'=>'s_title','data'=>'邮箱地址');
            //data
            foreach ((array)$data as $k=>$v){
                $tmp = array();
                $tmp[] = array('data'=>$v['created_at']);
                $tmp[] = array('data'=>$v['department']);
                $tmp[] = array('data'=>$v['member_name']);
                $tmp[] = array('data'=>$v['account']);
                $tmp[] = array('data'=>$v['email']);
                $excel_data[] = $tmp;
            }
        }

        $excel_data = $excel_obj->charset($excel_data,CHARSET);
        $excel_obj->addArray($excel_data);
        $excel_obj->addWorksheet($excel_obj->charset('用户列表',CHARSET));
        $excel_obj->generateXML($excel_obj->charset('用户列表',CHARSET).'-'.date('Y-m-d-H',time()));
    }

}
