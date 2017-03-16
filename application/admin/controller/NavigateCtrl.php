<?php
/**
 * 导航管理
 * 
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\admin\model\Navigation;
use think\Validate;

class NavigateCtrl extends Auth
{
    protected $model;
    protected $field = '*';

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Navigation();
    } 

    /**
     * 导航列表展示
     */
    public function index() {
        $param  = $this->request->param();
        $where  = '';
        // 获取参数，构建sql查询语句
        if (isset($param['title']) && !empty($param['title'])) {
            $title = trim($param['title']);
            $where = $where ? $where .' AND `title` like "%'. $title .'%"' : '`title` like "%'. $title .'%"';
        } else {
            $title = '';
        }
        if (isset($param['location']) && is_numeric($param['location'])){
            $location  = trim($param['location']);
            $where = $where ? $where .' AND `location`="'. $location .'"' : '`location`="'. $location .'"';
        } else {
            $location = '';
        }
        // 构建搜索条件
        $search = array();
        $search['title'] = $title;
        $search['location']  = $location;
        // 获取数据
        if (!empty($where)) {
            $tblRows = $this->model
                            ->field( $this->field )
                            ->where($where)
                            ->order(array("sort" => "asc"))
                            ->paginate();
        } else {
            $tblRows = $this->model
                            ->field( $this->field )
                            ->order(array("sort" => "asc"))
                            ->paginate();
        }
        foreach ($tblRows as $key => $row) {
            $tblRows[$key]['new_open'] = $row['new_open'] ? '是' : '否';
        }
        // 变量输出
        $this->assign("search", $search);
        $this->assign("tblRows", $tblRows);
        return $this->fetch();
    }

    /**
     * 导航新增
     */
    public function add(){
        return $this->fetch();
    }

    /**
     * 导航新增方法
     */
    public function addPost(){
        $param = $this->request->param();
        // 获取参数
        $addData = array();
        $addData['type'] = isset($param['type']) ? trim($param['type']) : "";
        $addData['title']   = isset($param['title']) ? trim($param['title']) : "";
        $addData['url']  = isset($param['url']) ? trim($param['url']) : "";
        $addData['location']  = isset($param['location']) ? trim($param['location']) : "";
        $addData['new_open']  = isset($param['new_open']) ? trim($param['new_open']) : "";
        $addData['sort'] = isset($param['sort']) ? trim($param['sort']) : "";
        $addData['item_id'] = $addData['type'];
        // 参数验证
        $checkRule = array();
        $checkMsg  = array();
        $checkRule['type'] = 'require|in:0,1,2,3,4,5,6';
        $checkMsg['type.in'] = '导航类型无效';
        $checkMsg['type.require'] = '导航类型不能为空';
        $checkRule['title'] = 'require|max:100';
        $checkMsg['title.require'] = '标题不能为空';
        $checkMsg['title.max'] = '标题最多不能超过100个字符';
        $checkRule['url'] = 'require|url';
        $checkMsg['url.require'] = '链接不能为空';
        $checkMsg['url.url'] = '无效的链接';
        $checkRule['location'] = 'require|in:0,1,2';
        $checkMsg['location.require'] = '所在位置不能为空';
        $checkMsg['location.in'] = '所在位置无效';
        $checkRule['new_open'] = 'require|in:0,1';
        $checkMsg['new_open.require'] = '新窗口打开不能为空';
        $checkMsg['new_open.in'] = '新窗口打开无效';
        $validate = new Validate($checkRule, $checkMsg);
        $result   = $validate->check($addData);
        if(!$result){
            $this->error($validate->getError());
        }
        // 保存数据
        if ( $this->model->data( $addData )->save() ) {
            $this->success('添加成功', url('index'));
        } else {
            $this->error('添加失败');
        }
    }

    /**
     * 导航编辑
     */
    public function edit(){
        $id = $this->request->param('id', 0, 'intval');
        $tblRow = $this->model
                       ->field($this->field)
                       ->where(array('id' => $id))
                       ->find();
        if (empty($tblRow)) {
            $this->error('加载失败');
        }
        $this->assign("tblRow", $tblRow);
        return $this->fetch(); 
    }

    /**
     * 编辑导航方法
     */
    public function editPost() {
        $param = $this->request->param();
        // 获取参数
        $addData = array();
        $addData['id'] = isset($param['id']) ? trim($param['id']) : "";
        $addData['type'] = isset($param['type']) ? trim($param['type']) : "";
        $addData['title']   = isset($param['title']) ? trim($param['title']) : "";
        $addData['url']  = isset($param['url']) ? trim($param['url']) : "";
        $addData['location']  = isset($param['location']) ? trim($param['location']) : "";
        $addData['new_open']  = isset($param['new_open']) ? trim($param['new_open']) : "";
        $addData['sort'] = isset($param['sort']) ? trim($param['sort']) : "";
        $addData['item_id'] = $addData['type'];
        // 参数验证
        $checkRule = array();
        $checkMsg  = array();
        $checkRule['id'] = 'number';
        $checkMsg['id.in'] = '编辑失败';
        $checkRule['type'] = 'require|in:0,1,2,3,4,5,6';
        $checkMsg['type.in'] = '导航类型无效';
        $checkMsg['type.require'] = '导航类型不能为空';
        $checkRule['title'] = 'require|max:100';
        $checkMsg['title.require'] = '标题不能为空';
        $checkMsg['title.max'] = '标题最多不能超过100个字符';
        $checkRule['url'] = 'require|url';
        $checkMsg['url.require'] = '链接不能为空';
        $checkMsg['url.url'] = '无效的链接';
        $checkRule['location'] = 'require|in:0,1,2';
        $checkMsg['location.require'] = '所在位置不能为空';
        $checkMsg['location.in'] = '所在位置无效';
        $checkRule['new_open'] = 'require|in:0,1';
        $checkMsg['new_open.require'] = '新窗口打开不能为空';
        $checkMsg['new_open.in'] = '新窗口打开无效';
        $validate = new Validate($checkRule, $checkMsg);
        $result   = $validate->check($addData);
        if(!$result){
            $this->error($validate->getError());
        }
        // 保存数据
        if ( $this->model->save($addData, array('id' => $addData['id'])) ) {
            $this->success('编辑成功', url('index'));
        } else {
            $this->error('编辑失败');
        }
    }

    /**
     * 删除导航方法
     */
    public function delete() {
        $id = $this->request->param('id', 0, 'intval');
        if (!is_numeric($id)){
            $this->error('删除失败');
        }
        if ( $this->model->destroy(array('id' => $id)) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 删除选中的导航分类方法
     */
    public function deleteChecked() {
        $id_list = $this->request->param('id_list');
        $id_list = trim($id_list);
        if (empty($id_list)){
            $this->error('删除失败');
        }
        if ( $this->model->where('id', 'in', $id_list)->delete() ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     *  排序编辑
     */
    public function sort() {
        $id   = $this->request->param('id', 0, 'intval');
        $sort = $this->request->param('sort', 0, 'intval');
        $this->model->save( array( 'sort' => $sort ), array( 'id' => $id) );
        $this->success('编辑成功', url('index'));
    }
}