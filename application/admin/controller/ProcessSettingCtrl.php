<?php
/**
 * create by: PhpStorm
 * desc:流程管理设置
 * author:yangmeng
 * create time:2016/11/24
 */
namespace app\admin\controller;
use app\admin\model\ProcessCategory;
use app\admin\model\ProcessSetting;
use app\common\controller\Auth;
class ProcessSettingCtrl extends Auth
{
    protected $model;
    protected $modelCate;
    protected $field = '*';

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->modelCate = new ProcessCategory();
        $this->model = new ProcessSetting();
    }

    /**
     * 流程管理列表
     */
    public function index() {

        $param = $this->request->param();
        $title  = '';
        // 获取参数，构建sql查询语句
        if (isset($param['title'])) {
            $title  = trim($param['title']);
            $datas = $this->model
                ->field( $this->field )
                ->where( array( 'is_delete' => 0 ))
                ->where('title', 'like', "%$title%")
                ->order(array("sort" => "asc"))
                ->paginate();
        } else {
            $datas = $this->model
                ->field( $this->field )
                ->where( array( 'is_delete' => 0 ))
                ->order(array("sort" => "asc"))
                ->paginate();
        }

        //查询所属流程分类
        foreach($datas as $k=>$v){
            $category = $this->modelCate
                ->where(array('cate_id' => $v['cate_id']))
                ->find();
            $datas[$k]['category_name'] = $category['name'];
        }


        // 变量输出
        $this->assign("title", $title);
        $this->assign("datas", $datas);
        return $this->fetch();
    }

    /**
     * 添加流程子模块
     */
    public function add() {
        //获取流程分类
        $data = $this->modelCate->where(array('is_delete'=>0))->select();
        $this->assign('category',$data);

        return $this->fetch();
    }

    /**
     * 添加流程子模块方法
     */
    public function addPost() {

        //流程子模块信息
        $param = array();
        $param['title'] = $this->request->param('title');
        $param['img_url'] = $this->request->param('img_url','','trim');
        $param['cate_id'] = $this->request->param('category_id');
        $param['sort'] = $this->request->param('sort','','intval');
        $param['content'] = $this->request->param('content');
        $param['address'] = $this->request->param('address');

        //判断条件
        if(empty($param['title']))      $this->error('流程子模块标题不能为空');
        if(empty($param['img_url']))    $this->error('请上传流程子模块图片');
        if($param['cate_id'] == 0)      $this->error('请选择流程分类');
        if(empty($param['content']))    $this->error('流程子模块说明不能为空');

        if ( strpos( $param['address'], 'http://') !== 0 && strpos( $param['address'], 'https://') !== 0 ) {
            $this->error('错误的跳转地址');
        }

        //添加方法
        if($this->model->save($param)){
            $this->success('添加成功',url('index'));
        }else{
            $this->error('添加失败');
        }
    }

    /**
     * 编辑流程子模块信息
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
        $data = $this->modelCate->where(array('is_delete'=>0))->select();
        $this->assign('category',$data);

        return $this->fetch();
    }

    /**
     * 编辑流程子模块方法
     */
    public function editPost() {
        //流程子模块信息
        $param['id'] = $this->request->param('id');
        $param['title'] = $this->request->param('title');
        $param['img_url'] = $this->request->param('img_url','','trim');
        $param['cate_id'] = $this->request->param('cate_id');
        $param['sort'] = $this->request->param('sort','','intval');
        $param['content'] = $this->request->param('content');
        $param['address'] = $this->request->param('address');

        //判断条件
        if(empty($param['title'])) $this->error('流程子模块标题不能为空');
        if(empty($param['img_url']))    $this->error('请上传流程子模块图片');
        if(empty($param['cate_id']))      $this->error('请新增流程分类');
        if(empty($param['content']))      $this->error('流程子模块说明不能为空');

        if ( strpos( $param['address'], 'http://') !== 0 && strpos( $param['address'], 'https://') !== 0 ) {
            $this->error('错误的跳转地址');
        }

        //编辑操作
        $id = trim($param['id']);
        if ( $this->model->update($param, array('id' => $id)) ) {
            $this->success('编辑成功',url('index'));
        } else {
            $this->error('编辑失败');
        }
    }

    /**
     * 查看流程子模块详情
     */
    public function detail(){
        $id = $this->request->param('id');
        $info = $this->model->where('id',$id)->find();

        //查询整装分类信息
        $cate_info = $this->modelCate->where('cate_id',$info['cate_id'])->find();
        $info['cate_name'] = $cate_info['name'];

        if(empty($info)){
            $this->error('流程子模块信息错误！');
        }else{
            $this->assign('info',$info);
            return $this->fetch();
        }
    }

    /**
     * 删除流程子模块方法
     */
    public function delete() {
        $id = $this->request->param('id', 0, 'intval');
        if (!is_numeric($id)){
            $this->error('删除失败');
        }
        if ( $this->model->save( array('is_delete'=>1),array('id' => $id)) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 删除选中的流程子模块方法
     */
    public function deleteChecked() {
        $id_list  = explode(',', $this->request->param('id_list'));
        if (empty($id_list)){
            $this->error('删除失败');
        }
        $where = array( 'id'=>array( 'in' , $id_list ) );
        if ( $this->model->save( array('is_delete' => 1) ,$where ) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
}