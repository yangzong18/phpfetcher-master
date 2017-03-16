<?php
/**
 * create by: PhpStorm
 * desc:整装联盟分类
 * author:yangmeng
 * create time:2016/11/16
 */
namespace app\admin\controller;

use app\admin\model\UnionCate;
use app\common\controller\Auth;
use think\controller;

class UnionCategory extends Auth
{
    protected $model;
    protected $field = '*';
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new UnionCate();
    }

    /**
     * 整装分类列表
     */
    public function index() {

        $param = $this->request->param();
        $cate_name  = '';
        // 获取参数，构建sql查询语句
        if (isset($param['cate_name'])) {
            $cate_name  = trim($param['cate_name']);
            $datas = $this->model
                ->field( $this->field )
                ->where( array( 'is_delete' => 0 ))
                ->where('cate_name', 'like', "%$cate_name%")
                ->order(array("px" => "asc"))
                ->paginate();
        } else {
            $datas = $this->model
                ->field( $this->field )
                ->where( array( 'is_delete' => 0 ))
                ->order(array("px" => "asc"))
                ->paginate();
        }
        // 变量输出
        $this->assign("cate_name", $cate_name);
        $this->assign("datas", $datas);
        return $this->fetch();
    }

    /**
     * 添加分类
     */
    public function add() {
        return $this->fetch();
    }

    /**
     * 添加分类方法
     */
    public function addPost() {
        $param = array();
        $param['cate_name'] = $this->request->param('cate_name');
        $param['cate_description'] = $this->request->param('cate_description');
        $param['px']      = $this->request->param('px', 'intval', 0);

        if ( trim( $param['cate_name'] ) == '' )  $this->error('分类名称不能为空');
        if ( trim( $param['cate_description'] ) == '' )  $this->error('分类说明不能为空');

        //进行分类添加
        if ( $this->model->data( $param )->save() ) {
            $this->success('添加成功',url('index'));
        } else {
            $this->error('添加失败');
        }
    }

    /**
     * 编辑分类
     */
    public function edit() {
        $param = $this->request->param();
        $id = $param['id'];

        //获取详细信息
        $info = $this->model
            ->field($this->field)
            ->where(array('cate_id' => $id))
            ->find();
        $this->assign("info", $info);

        return $this->fetch();
    }

    /**
     * 编辑分类方法
     */
    public function editPost() {
        //编辑信息
        $param['cate_id'] = $this->request->param('cate_id');
        $param['cate_name'] = $this->request->param('cate_name','','trim');
        $param['cate_description'] = $this->request->param('cate_description','','trim');
        $param['px'] = $this->request->param('px','','intval');

        if ( empty($param['cate_name']) )  $this->error('分类名称不能为空');
        if ( empty($param['cate_description']) )  $this->error('分类说明不能为空');

        //编辑操作
        if ( $this->model->update($param, array('cate_id' => $param['cate_id'])) ) {
            $this->success('编辑成功',url('index'));
        } else {
            $this->error('编辑失败');
        }
    }

    /**
     * 删除分类方法
     */
    public function delete() {
        $id = $this->request->param('id', 0, 'intval');
        if (!is_numeric($id)){
            $this->error('删除失败');
        }
        if ( $this->model->save( array('is_delete' => 1),array('cate_id' => $id)) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 删除选中的分类方法
     */
    public function deleteChecked() {
        $id_list  = explode(',', $this->request->param('id_list'));
        if (empty($id_list)){
            $this->error('删除失败');
        }
        $where = array('cate_id'=>array('in',$id_list));
        if ( $this->model->save( array('is_delete' => 1),$where) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
}