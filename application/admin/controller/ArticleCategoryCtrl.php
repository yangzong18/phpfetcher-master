<?php
/**
 * 文章分类
 * 
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\admin\controller;
use app\admin\model\Article;
use app\common\controller\Auth;
use app\admin\model\ArticleCategory;
use app\admin\controller\Log;

class ArticleCategoryCtrl extends Auth
{
    protected $model;
    protected $field = '*';

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new ArticleCategory();
    } 

    /**
     * 文章分类列表展示
     */
    public function index() {
        $param = $this->request->param();
        $name  = '';
        // 获取参数，构建sql查询语句
        if (isset($param['name'])) {
            $name  = trim($param['name']);
            $tblRows = $this->model
                            ->field( $this->field )
                            ->where('name', 'like', "%$name%")
                            ->where(array('is_delete'=>0))
                            ->order(array("sort" => "asc"))
                            ->paginate();
        } else {
            $tblRows = $this->model
                            ->field( $this->field )
                            ->where(array('is_delete'=>0))
                            ->order(array("sort" => "asc"))
                            ->paginate();
        }
        // 变量输出
        $this->assign("name", $name);
        $this->assign("tblRows", $tblRows);
        return $this->fetch();
    }

    /**
     * 添加文章分类页
     */
    public function add() {
        return $this->fetch();
    }
    
    /**
     * 添加文章分类方法
     */
    public function addPost() {
        $param = $this->request->param();
        $addData = array();
        $addData['name'] = trim( $param['name'] );
        $addData['sort'] = trim( $param['sort'] );
        if ( empty($addData['name']) ) {
            $this->error('名称不能为空');
        }
        if ( $this->model->data( $addData )->save() ) {
            $this->model->expand(1);
            $this->success('添加成功', url('index'));
        } else {
            $this->error('添加失败');
        }
    }

    /**
     *  编辑文章分类
     */
    public function edit() {
        $param = $this->request->param();
        $id = $param['id'];
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
     * 编辑文章分类方法
     */
    public function editPost() {
        $param = $this->request->param();
        $editData = array();
        $editData['name'] = trim( $param['name'] );
        $editData['sort'] = trim( $param['sort'] );
        if ( empty($editData['name']) ) {
            $this->error('名称不能为空');
        }
        $id = trim($param['id']);
        if ( $this->model->save($param, array('id' => $id)) ) {
            $this->model->expand(1);
            $this->success('编辑成功', url('index'));
        } else {
            $this->error('编辑失败');
        }
    }

    /**
     * 删除文章分类方法
     */
    public function delete() {
        $id = $this->request->param('id', 0, 'intval');
        if (!is_numeric($id)){
            $this->error('参数错误');
        }
		$articalModel = new Article();
		$result = $articalModel->where(['article_category_id'=>$id])->find();
		if($result){
			$this->error('分类下有文章，不能进行删除');
		}
        if ( $this->model->save( array('is_delete'=>1),array('id' => $id)) ) {
            $this->model->expand(1);
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 删除选中的文章分类方法
     */
    public function deleteChecked() {
        $id_list  = explode(',', $this->request->param('id_list'));
        if (empty($id_list)){
            $this->error('删除失败');
        }
		$articalModel = new Article();
		$result = $articalModel->where(['article_category_id'=>['in',$id_list]])->find();
		if($result){
			$this->error('分类下有文章，不能进行删除');
		}
        $where = array( 'id'=>array( 'in' , $id_list ) );
        if ( $this->model->save( array('is_delete' => 1) ,$where ) ){
			$this->model->expand(1);
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
