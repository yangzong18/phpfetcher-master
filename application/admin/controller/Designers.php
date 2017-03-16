<?php
/**
 * 设计师管理
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷 at 2016/11/22
 */

namespace app\admin\controller;
use app\admin\model\Designer;
use app\admin\model\DesignerLevel;
use app\admin\model\DesignerProduction;
use app\common\controller\Auth;
use think\Db;
use think\Validate;

class Designers extends Auth{

    protected $model;
    protected $search = ['designer_name' => '姓名',
                           'designer_phone' => '联系方式',
                           'company' => '公司名称'];

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Designer();
    }

    /**
     * 获取设计师列表
     */
    public function index() {
        $searchType = $this->request->param('searchType');
        $searchValue = $this->request->param('searchValue', '', 'trim');
		if(preg_match('/[\'\!\@\#\$\%\^\&\*\+\_\=\:\<\>\"]/', $searchValue)){
			$this->error('查询内容含有特殊字符');
		}
        $condition = array('is_delete' => 0);
        if( $searchType != '' && $searchValue != '')
            $condition[$searchType] = array('like', '%'.$searchValue.'%');

        $designers = $this->model->where($condition)->paginate(10);
        //处理作品个数
        $countByDesigner = $this->getProductionCount();
        //处理相关订单个数
        $orderByDesigner = $this->getOrderCount();

        $this->assign('order', $orderByDesigner);
        $this->assign('count', $countByDesigner);
        $this->assign('designers', $designers);
        $this->assign('search', $this->search);
        $this->assign('searchType', $searchType);
        $this->assign('searchValue', $searchValue);
        $this->getDesignerLevel();
        return $this->fetch();
    }

    /**
     * 新增页
     */
    public function add() {
       $this->getDesignerLevel( ['is_delete'=> 0] );
       return $this->fetch();
    }

    /**
     * 新增方法
     */
    public function addPost() {
        $designerInfo = [];
        $designerInfo['designer_name'] = $this->request->param('name', '', 'trim');
        $designerInfo['designer_sex'] = $this->request->param('sex', '', 'intval');
        $designerInfo['designer_phone'] = $this->request->param('phone', '', 'trim');
        $designerInfo['designer_year'] = $this->request->param('year', '', 'trim');
        $designerInfo['level_id'] = $this->request->param('levelId','','intval');
        $designerInfo['designer_avatar'] = $this->request->param('img_url', '', 'trim');
		$designerInfo['company'] = $this->request->param('company', '', 'trim');
		$designerInfo['designer_style'] = $this->request->param('style', '', 'trim');
		$designerInfo['designer_idea'] = $this->request->param('idea', '', 'trim');

        //验证提交数据
        $result = $this->validateDesigner($designerInfo);
        if( !$result['code'] ) $this->error($result['msg']);
        if( !$this->model->save( $designerInfo ) ) {//新增设计师
            $this->error('添加失败');
        }
        $this->success('添加成功','index');

    }

    /**
     * 编辑页
     */
    public function edit() {
        $id     = $this->request->param('id/d', 0, 'trim');
        $data   = $this->model->where('designer_id', $id)->find();
		if(!$data) $this->error('没有这样的设计师');
        $this->getDesignerLevel( ['is_delete'=> 0] );
        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 编辑方法
     */
    public function editPost() {
        $designerInfo = [];
        $designerInfo['designer_name'] = $this->request->param('name', '', 'trim');
        $designerInfo['designer_sex'] = $this->request->param('sex', '', 'intval');
        $designerInfo['designer_phone'] = $this->request->param('phone', '', 'trim');
        $designerInfo['designer_year'] = $this->request->param('year', '', 'trim');
        $designerInfo['level_id'] = $this->request->param('levelId','','intval');
        $designerInfo['designer_avatar'] = $this->request->param('img_url', '', 'trim');
		$designerInfo['company'] = $this->request->param('company', '', 'trim');
		$designerInfo['designer_style'] = $this->request->param('style', '', 'trim');
		$designerInfo['designer_idea'] = $this->request->param('idea', '', 'trim');
        //验证提交数据
        $result = $this->validateDesigner($designerInfo);
        if( !$result['code'] ) $this->error($result['msg']);
		$designerInfo['designer_id'] = $this->request->param('designerId', '', 'intval');
        if( !$this->model->update($designerInfo) ) {//更新设计师
            $this->error('编辑失败');
        }
        $this->success('编辑成功','index');

    }

    /**
     * 获取设计师级别列表
     * @param $where 查询条件
     */
    protected function getDesignerLevel( $where = array() ) {
        $level = ( new DesignerLevel() )->where($where)->select();

        $levelList = array();
        $selectYear= 0;
        foreach($level as $key => $val ) {
            $levelList[$val['level_id']]['id'] = $val['level_id'];
            $levelList[$val['level_id']]['name'] = $val['level_name'];
            $levelList[$val['level_id']]['year'] = explode('-', $val['level_year'])[1];
            if( $key == 0 ) {
                $selectYear = $levelList[$val['level_id']]['year'];
            }
        }
        $this->assign('selectYear', $selectYear );
        $this->assign('levelList', $levelList);
    }

    /**
     * 批量删除级别方法
     */
    public function deleteChecked() {
        $designerId  = explode(',', $this->request->param('id_list'));
        $where   = array( 'designer_id' => array( 'in', $designerId ) );
        if( count( $designerId ) <= 0 )
            $this->error('删除失败');

        if( !$this->model->save(array( 'is_delete' => 1 ), $where) ) {
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }

    /**
     * 编辑、新增验证提交数据
     * @param $dataInfo 待验证数据
     * @return array
     */
    protected function validateDesigner( $dataInfo ) {
        $rule = [
            'designer_name'  => 'require|desc',
            'designer_sex'   => 'number',
            'designer_phone' => 'require|regex:/^1[34578]\d{9}$/',
            'designer_year' => 'number|between:0,120',
            'level_id'=> 'require',
            'designer_avatar' => 'require',
			'company'  => 'desc',
			'designer_style'  => 'desc',
			'designer_idea'  => 'desc',
        ];
        $msg = [
            'designer_name.require'  => '名称不能为空',
			'designer_name.desc'	=>'名称含有特殊字符',
            'designer_sex.num'   => '性别必须是数字',
            'designer_phone.require' => '手机号不能为空',
            'designer_phone.regex' => '手机号无效',
            'designer_year.number' => '设计年限必须是数字',
            'designer_year.between' => '设计年限必须在0~120之间',
            'level_id.require' => '级别不能为空',
            'designer_avatar.require' => '设计师照片不能为空',
			'company.desc'  => '公司含有特殊字符',
			'designer_style.desc'  => '设计风格含有特殊字符',
			'designer_idea.desc'  => '设计理念含有特殊字符',
        ];

        $validate = new Validate($rule, $msg);
        if( !$validate->check($dataInfo) ){
            return ['code' => 0, 'msg' => $validate->getError()];
        }
        return ['code' => 1];
    }


    /**
     * 获取设计师作品数量
     * @return array
     */
    protected function getProductionCount() {
        $list = Db::name('designer_production')
            ->field('production_id,designer_id,count(production_id) as count')
//            ->where(['is_delete'=>0,'is_show'=>1])
            ->where(['is_delete'=>0])
            ->group('designer_id')
            ->select();
        $countByDesigner = array();
        foreach( $list as $val ) {
            $countByDesigner[$val['designer_id']] = $val['count'];
        }
        return $countByDesigner;
    }

    /**
     * 获取设计师订单数量
     * @return array
     */
    protected function getOrderCount() {
        $list = Db::name('logs_decoration_order')
            ->field('id,designer_id,count(id) as count')
            ->group('designer_id')
            ->select();
        $orderByDesigner = array();
        foreach( $list as $val ) {
            $orderByDesigner[$val['designer_id']] = $val['count'];
        }
        return $orderByDesigner;
    }
}
