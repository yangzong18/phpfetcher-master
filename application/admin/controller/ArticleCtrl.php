<?php
/**
 * 文章管理
 * 
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\admin\model\Article;
use app\admin\model\ArticleCategory;
use think\Validate;

class ArticleCtrl extends Auth
{
    protected $model;
    protected $field = '*';

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Article();
    } 

    /**
     * 文章列表展示
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

        if (isset($param['article_category_id'])  && 
            !empty($param['article_category_id']) && 
            is_numeric($param['article_category_id'])) 
        {
            $article_category_id = trim($param['article_category_id']);
            $where = $where ? $where .' AND `article_category_id`="'. $article_category_id .'"' : 
                                           '`article_category_id`="'. $article_category_id .'"';
        } else {
            $article_category_id = '';
        }

        if (isset($param['show']) && 
            !empty($param['show'] && 
            is_numeric($param['show']))) 
        {
            $show  = trim($param['show']);
            $where = $where ? $where .' AND `show`="'. $show .'"' : '`show`="'. $show .'"';
        } else {
            $show = '';
        }
        // 构建搜索条件
        $search = array();
        $search['title'] = $title;
        $search['show']  = $show;
        $search['article_category_id'] = $article_category_id;
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
        // 获取缓存数据，新增 article_category_name 参数
        $cacheData = ( new ArticleCategory() )->select();
        foreach ($tblRows as $key => $row) {
            $row = $row->toArray();
            foreach ($cacheData as $category) {
                if ( $category->id == $row['article_category_id'] ) {
                    $row['article_category_name'] = $category->name;
                    break 1;
                }
            }
            $row['time'] = date("Y-m-d H:i:s", $row['time']);
            $tblRows[$key] = $row;
        }
        // 变量输出
        $this->assign("search", $search);
        $this->assign("tblRows", $tblRows);
        $this->assign("cacheData", $cacheData);
        return $this->fetch();
    }

    /**
     * 文章新增
     */
    public function add(){
        // 获取缓存数据，新增 article_category_name 参数
        $cacheData = ArticleCategory::getCache();
        $this->assign("cacheData", $cacheData);
        return $this->fetch();
    }

    /**
     * 文章新增方法
     */
    public function addPost(){
        $param = $this->request->param();
        // 获取参数
        $addData = array();
        $addData['title'] = isset($param['title']) ? trim($param['title']) : "";
        $addData['url']   = isset($param['url']) ? trim($param['url']) : "";
        $addData['show']  = isset($param['show']) ? trim($param['show']) : "";
        $addData['sort']  = isset($param['sort']) ? trim($param['sort']) : "";
        $addData['time']  = time();
        $addData['content'] = isset($param['editorContent']) ? trim($param['editorContent']) : "";
        $addData['article_category_id'] = isset($param['article_category_id']) ? 
                                          trim($param['article_category_id']) : "";
        // 参数验证
        $checkRule = array();
        $checkMsg  = array();
        $cacheData = ArticleCategory::getCache();
        $idList = array();
        foreach ($cacheData as $category) {
            array_push($idList, $category->id);
        }
        $checkRule['title'] = 'require|max:100';
        $checkMsg['title.require'] = '标题不能为空';
        $checkMsg['title.max'] = '标题最多不能超过100个字符';
        $checkRule['article_category_id'] = 'require|in:'. implode(',', $idList);
        $checkMsg['article_category_id.require'] = '所属分类不能为空';
        $checkMsg['article_category_id.in'] = '所属分类无效';
        if ( !empty($addData['url']) ) {
            $checkRule['url'] = 'url';
            $checkMsg['url.url'] = '无效的链接';
        }
        if ( !empty($addData['show']) ) {
            $checkRule['show'] = 'in:0,1';
            $checkMsg['show.in'] = '无效的显示';
        }
        if ( !empty($addData['sort']) ) {
            $checkRule['sort'] = 'number';
            $checkMsg['sort.number'] = '无效的排序';
        }
        $checkRule['content'] = 'require';
        $checkMsg['content.require'] = '内容不能为空';
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
     * 文章编辑
     */
    public function edit(){
        $id = $this->request->param('id', 0, 'intval');
        $tblRow = $this->model
                       ->field($this->field)
                       ->where(['id' => $id])
                       ->find();
        if (empty($tblRow)) {
            $this->error('加载失败');
        }
        $cacheData = ArticleCategory::getCache();
        $this->assign("tblRow", $tblRow);
        $this->assign("cacheData", $cacheData);
        return $this->fetch(); 
    }

    /**
     * 编辑文章方法
     */
    public function editPost() {
        $param = $this->request->param();
        // 获取参数
        $editData = array();
        $editData['id'] = isset($param['id']) ? trim($param['id']) : "";
        $editData['title'] = isset($param['title']) ? trim($param['title']) : "";
        $editData['url']   = isset($param['url']) ? trim($param['url']) : "";
        $editData['show']  = isset($param['show']) ? trim($param['show']) : "";
        $editData['sort']  = isset($param['sort']) ? trim($param['sort']) : "";
        $editData['time']  = time();
        $editData['content'] = isset($param['editorContent']) ? trim($param['editorContent']) : "";
        $editData['article_category_id'] = isset($param['article_category_id']) ? 
                                           trim($param['article_category_id']) : "";
        // 参数验证
        $checkRule = array();
        $checkMsg  = array();
        $cacheData = ArticleCategory::getCache();
        $idList = array();
        foreach ($cacheData as $category) {
            array_push($idList, $category->id);
        }
        $checkRule['id'] = 'require|number';
        $checkMsg['id.require'] = '编辑失败';
        $checkMsg['id.number'] = '编辑失败';
        $checkRule['title'] = 'require|max:100';
        $checkMsg['title.require'] = '标题不能为空';
        $checkMsg['title.max'] = '标题最多不能超过100个字符';
        $checkRule['article_category_id'] = 'require|in:'. implode(',', $idList);
        $checkMsg['article_category_id.require'] = '所属分类不能为空';
        $checkMsg['article_category_id.in'] = '所属分类无效';
        if ( !empty($editData['url']) ) {
            $checkRule['url'] = 'url';
            $checkMsg['url.url'] = '无效的链接';
        }
        if ( !empty($editData['show']) ) {
            $checkRule['show'] = 'in:0,1';
            $checkMsg['show.in'] = '无效的显示';
        }
        if ( !empty($editData['sort']) ) {
            $checkRule['sort'] = 'number';
            $checkMsg['sort.number'] = '无效的排序';
        }
        $checkRule['content'] = 'require';
        $checkMsg['content.require'] = '内容不能为空';
        $validate = new Validate($checkRule, $checkMsg);
        $result   = $validate->check($editData);
        if(!$result){
            $this->error($validate->getError());
        }
        // 保存数据
        $id = $editData['id'];
        if ( $this->model->save($editData, array('id' => $id)) ) {
            $this->success('编辑成功', url('index'));
        } else {
            $this->error('编辑失败');
        }
    }

    /**
     * 删除文章方法
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
     * 删除选中的文章分类方法
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
