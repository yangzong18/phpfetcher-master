<?php
/**
 * 设计师级别管理
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷 at 2016/11/21
 */

namespace app\admin\controller;
use app\admin\model\DesignerLevel;
use app\common\controller\Auth;
use think\Validate;

class DesignerLevels extends Auth{

    protected $model;
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new DesignerLevel();
    }

    /**
     * 设计师级别列表
     */
    public function index() {
        $designerLevel = $this->model->where('is_delete', 0)->paginate(10);
        $this->assign('designerLevel', $designerLevel);
        return $this->fetch();
    }

    /**
     * 级别新增页
     */
    public function add() {
        return $this->fetch();
    }

    /**
     * 级别新增方法
     */
    public function addPost() {
        $levelName = $this->request->param('levelName', '', 'trim');
        $levelYear = $this->request->param('levelYear', '', 'trim');
        $remark    = $this->request->param('remark', '', 'trim');
        $year = explode('-', $levelYear);
        if( strpos($levelYear, '-') == false || empty($year) || 
            count($year) !=2 || !is_numeric($year[0]) || intval($year[0]) >= intval($year[1]) )
            $this->error('年限范围无效');

        $levelInfo = [];
        $levelInfo['level_name'] = $levelName;
        $levelInfo['level_year'] = $levelYear;
        $levelInfo['remark'] = $remark;
		$result = $this->validateLevels($levelInfo);
		if( !$result['code'] ) $this->error($result['msg']);

        if( !$this->model->save($levelInfo) ) $this->error('添加失败');
        $this->success('添加成功', 'index');
    }

    /**
     * 级别新增方法
     */
    public function editPost() {
        $levelName = $this->request->param('levelName', '', 'trim');
        $levelYear = $this->request->param('levelYear', '', 'trim');
        $remark    = $this->request->param('remark', '', 'trim');
        $year = explode('-', $levelYear);
        if( strpos($levelYear, '-') == false || empty($year) || 
           count($year) !=2 || !is_numeric($year[0]) || intval($year[0]) >= intval($year[1]) )
           $this->error('年限范围无效');
        $levelInfo['level_name'] = $levelName;
        $levelInfo['level_year'] = $levelYear;
        $levelInfo['remark'] = $remark;
        $levelInfo['level_id'] = $this->request->param('levelId', '', 'intval');
		$result = $this->validateLevels($levelInfo);
		if( !$result['code'] ) $this->error($result['msg']);

        if( !$this->model->update($levelInfo) ) $this->error('编辑失败');
        $this->success('编辑成功', 'index');
    }

    /**
     * 级别编辑页
     */
    public function edit() {
        $id     = $this->request->param('id', 0, 'intval');
        $data   = $this->model->where('level_id', $id)->find();
        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 批量删除级别方法
     */
    public function deleteChecked() {
        $levelId  = explode(',', $this->request->param('id_list'));
        $where   = array( 'level_id' => array( 'in', $levelId ) );
        if( count( $levelId ) <= 0 )
            $this->error('删除失败');

        if( !$this->model->save(array( 'is_delete' => 1 ), $where) ) {
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }
	/**
	 * 编辑、新增验证提交数据
	 */
	protected function validateLevels( $dataInfo,$type=1) {
		$rule = [
			'level_name'  => 'require|desc',
			'level_year'   => 'require',
			'remark' => 'desc',
			'level_id' =>'require|number'
		];
		$msg = [
			'level_name.require'  => '名称不能为空',
			'level_name.desc'		=> '名称含有非法字符串',
			'level_year.require' => '年限范围不能为空',
			'remark.desc' 			=> '说明含有非法字符',
			'level_id.require'		=> '级别Id不能为空',
			'level_id.require'		=> '级别Id必须是数字'
		];
		$validate = new Validate($rule, $msg);
		$validate->scene('addLevel', ['level_name', 'level_year', 'remark']);
		$validate->scene('editLevel', ['level_name', 'level_year', 'remark','level_id']);
		switch ($type) {
			case 1: //新增
				$result = $validate->scene('addLevel')->check($dataInfo);
				break;
			case 2: //编辑
				$result = $validate->scene('editLevel')->check($dataInfo);
				break;
		}
		if (!$result) {
			return ['code' => 0, 'msg' => $validate->getError()];
		}
		return ['code' => 1];
	}
}
