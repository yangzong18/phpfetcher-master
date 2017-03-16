<?php
/**
 * create by: PhpStorm
 * desc:整装联盟信息
 * author:yangmeng
 * create time:2016/11/16
 */
namespace app\admin\controller;

use app\common\controller\Auth;
use app\admin\model\Union;
use app\admin\model\UnionCate;
use think\controller;

class UnionInfo extends Auth
{
    protected $modelCate;
    protected $model;
    protected $field = '*';
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->modelCate = new UnionCate();
        $this->model = new Union();
    }

    /**
     * 联盟信息列表
     */
    public function index() {


        $param = $this->request->param();
        $union_name  = '';
        // 获取参数，构建sql查询语句
        if (isset($param['union_name'])) {
            $union_name  = trim($param['union_name']);
            $datas = $this->model
                ->field( $this->field )
                ->where( array( 'is_delete' => 0 ))
                ->where('union_name', 'like', "%$union_name%")
                ->order(array("px" => "asc"))
                ->paginate();
        } else {
            $datas = $this->model
                ->field( $this->field )
                ->where( array( 'is_delete' => 0 ))
                ->order(array("px" => "asc"))
                ->paginate();
        }

        //查询列表联盟分类
        foreach($datas as $k=>$v){
            $category = $this->modelCate
                ->where(array('cate_id' => $v['cate_id']))
                ->find();
            $datas[$k]['category_name'] = $category['cate_name'];
        }

        $this->assign("union_name", $union_name);
        $this->assign("datas", $datas);
        return $this->fetch();
    }

    /**
     * 添加联盟信息
     */
    public function add() {
        //获取联盟分类
        $data = $this->modelCate->where( array('is_delete'=>0) )->select();
        $this->assign('category',$data);

        return $this->fetch();
    }

    /**
     * 添加联盟方法
     */
    public function addPost() {

        //整装联盟信息
        $param = array();
        $param['union_name'] = $this->request->param('union_name');
        $param['log_pic'] = $this->request->param('log_pic','','trim');
        $param['cate_id'] = $this->request->param('cate_id');
        $param['px'] = $this->request->param('px','','intval');
        $param['address'] = $this->request->param('address');
        $param['brand'] = $this->request->param('brand');
        $param['content'] = $this->request->param('content');

        //判断条件
        if(empty($param['union_name'])) $this->error('整装联盟名称不能为空');
        if(empty($param['log_pic']))    $this->error('请上传联盟logo图片');
        if($param['cate_id'] == 0)      $this->error('请选择联盟分类');
        if(empty($param['address']))      $this->error('整装联盟详细地址不能为空');
        if(empty($param['brand']))      $this->error('整装联盟品牌说明不能为空');
        if(empty($param['content']))    $this->error('整装联盟详情不能为空');

        //添加方法
        if($this->model->save($param)){
            $this->success('添加成功',url('index'));
        }else{
            $this->error('添加失败');
        }

    }

    /**
     * 编辑联盟信息
     */
    public function edit() {

        //获取联盟详细信息
        $param = $this->request->param();
        $id = $param['id'];
        $info = $this->model
            ->field($this->field)
            ->where(array('id' => $id))
            ->find();

        $cate_id = $info['cate_id'];
        $this->assign('cate_id',$cate_id);
        $this->assign("info", $info);

        //联盟分类
        $data = $this->modelCate->where( array( 'is_delete' => 0 ) )->select();
        $this->assign('category',$data);

        return $this->fetch();
    }

    /**
     * 编辑联盟方法
     */
    public function editPost() {
        //整装联盟信息
        $param['id'] = $this->request->param('id');
        $param['union_name'] = $this->request->param('union_name');
        $param['log_pic'] = $this->request->param('log_pic','','trim');
        $param['cate_id'] = $this->request->param('cate_id');
        $param['px'] = $this->request->param('px','','intval');
        $param['address'] = $this->request->param('address');
        $param['brand'] = $this->request->param('brand');
        $param['content'] = $this->request->param('content');

        //判断条件
        if(empty($param['union_name'])) $this->error('整装联盟名称不能为空');
        if(empty($param['cate_id'])) $this->error('请新增分类');
        if(empty($param['log_pic']))    $this->error('请上传联盟logo图片');
        if(empty($param['address']))      $this->error('整装联盟详细地址不能为空');
        if(empty($param['brand']))      $this->error('整装联盟品牌说明不能为空');
        if(empty($param['content']))    $this->error('整装联盟详情不能为空');

        //编辑操作
        $id = trim($param['id']);
        if ( $this->model->update($param, array('id' => $id)) ) {
            $this->success('编辑成功',url('index'));
        } else {
            $this->error('编辑失败');
        }
    }

    /**
     * 查看联盟详情
     */
    public function detail(){
        $id = $this->request->param('id');
        $info = $this->model->where('id',$id)->find();

        //查询整装分类信息
        $cate_info = $this->modelCate->where('cate_id',$info['cate_id'])->find();
        $info['cate_name'] = $cate_info['cate_name'];

        if(empty($info)){
            $this->error('整装联盟信息错误！');
        }else{
            $this->assign('info',$info);
            return $this->fetch();
        }
    }

    /**
     * 删除联盟方法
     */
    public function delete() {
        $id = $this->request->param('id', 0, 'intval');
        if (!is_numeric($id)){
            $this->error('删除失败');
        }
        if ( $this->model->save(array( 'is_delete' => 1 ),array( 'id' => $id )) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 删除选中的联盟方法
     */
    public function deleteChecked() {

        $id_list  = explode(',', $this->request->param('id_list'));

        $where = array( 'id' => array( 'in' , $id_list ));
        if (empty($id_list)){
            $this->error('删除失败');
        }
        if ( $this->model->save( array( 'is_delete' => 1 ), $where )) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
}