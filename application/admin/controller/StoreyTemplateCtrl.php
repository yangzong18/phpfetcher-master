<?php
/**
 * create by: PhpStorm
 * desc:楼层模板管理
 * author:yangmeng
 * create time:2016/11/21
 * modified by:修改人
 * modified time:修改时间
 * modified mark:修改备注 
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\admin\model\StoreyTemplate;
use app\admin\model\StoreyConfig;
class StoreyTemplateCtrl extends Auth
{
    protected $model;
    protected $field = '*';
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new StoreyTemplate();
    }

    /**
     * 楼层模板列表
     */
    public function index() {
        $datas = $this->model->order(array('sort'=>'asc'))->paginate();
        $this->assign("datas", $datas);

        return $this->fetch();
    }

    /*
     * 添加楼层模板
     */
    public function add() {
        return $this->fetch();
    }

    /*
     * 添加楼层模板方法
     */
    public function addPost() {
        //判断条件
		$sort = $this->request->param('sort','','trim');
		if(isset($sort)){
			if(!preg_match('/^[0-9]*$/',$sort)){
				$this->error('排序参数不正确');
			}
		}else{
			$sort = 0;
		}
        $img_number = $this->request->param('img_number','','intval');
        if (empty($img_number)){
            $this->error('楼层布局图片数量不能为空。');
        }
        $name = $this->request->param('name');
        if(empty($name)) {
            $this->error('楼层模板名称不能为空。');
        }
        $fileHtml = request()->file('template_html');

        if( empty($fileHtml) ) $this->error('楼层模板不能为空');

        $fileInfo = $fileHtml->move('.'. DS .'Upload'. DS .'file_material');
        $template_html = '';
        if($fileInfo){
            // 成功上传后 获取上传信息
            $extension = $fileInfo->getExtension();
            $template_html = $fileInfo->getSaveName();
            $template_html = DS .'Upload'. DS .'file_material'. DS . $template_html;
        }

        if (empty($template_html)){
            @unlink($template_html);
            $this->error('添加失败，模板文件不能为空。');
        }
        if ($extension != 'html'){
            @unlink($template_html);
            $this->error('添加失败，模板文件类型必须为 html。');
        }
        //上传图片修改
        $template_img = $this->request->param('template_img','','trim');
        if(empty($template_img))    $this->error('模板图片不能为空');

        //楼层模板信息
        $param = array();
        $param['name'] = $name;
        $param['template_path'] = $template_html;
        $param['img_url'] = $template_img;
        $param['img_number'] = $img_number;
        $param['sort'] = $sort;
        $param['time'] = time();

        //添加方法
        if($this->model->save($param)){
            $this->model->flushCache();
            $myStoreyConfig = new StoreyConfig();
            $myStoreyConfig->makeIndexIncludeFile();
            $this->success('添加成功',url('index'));
        }else{
            $this->error('添加失败');
        }
    }

    /**
     * 编辑楼层模板
     */
    public function edit() {
        $param = $this->request->param();
        $id = $param['id'];

        //获取详细信息
        $info = $this->model
            ->field($this->field)
            ->where(array('id' => $id))
            ->find();
        if (empty($info)){
            $this->error('编辑失败');
        }
        $this->assign("info", $info);

        return $this->fetch();
    }

    /**
     * 编辑楼层模板方法
     */
    public function editPost() {
        //判断条件
        $id = $this->request->param('id','','intval');
        if (empty($id)){
            $this->error('编辑失败');
        }
        $sort = $this->request->param('sort','','trim');
		if(isset($sort)){
			if(!preg_match('/^[0-9]*$/',$sort)){
				$this->error('排序参数不正确');
			}
		}else{
			$sort = 0;
		}
        $img_number = $this->request->param('img_number','','intval');
        if (empty($img_number)){
            $this->error('楼层布局图片数量不能为空。');
        }
        $name = $this->request->param('name');
        if(empty($name)) {
            $this->error('楼层模板名称不能为空。');
        }
        // 获取模板缓存
        $tempalteData = $this->model->getCache();
        $tempalteData = $tempalteData[$id];
        $template_html = '';
        $fileHtml = request()->file('template_html');
        if (!empty($fileHtml)){
            $fileInfo = $fileHtml->move('.'. DS .'Upload'. DS .'file_material');
            if($fileInfo){
                // 成功上传后 获取上传信息
                $extension = $fileInfo->getExtension();
                $template_html = $fileInfo->getSaveName();
                $template_html = DS .'Upload'. DS .'file_material'. DS . $template_html;
            }
            if (empty($template_html)){
                @unlink($template_html);
                $this->error('编辑失败，模板文件不能为空。');
            }
            if ($extension != 'html'){
                @unlink($template_html);
                $this->error('编辑失败，模板文件类型必须为 html。');
            }
        }

        //楼层模板信息
        $param = array();
        $param['name'] = $name;
        if (!empty($template_html)){
            $param['template_path'] = $template_html;
        }
        $param['img_url'] = $this->request->param('img_url');

        if(  empty($param['img_url']) ) $this->error('模板图片不能为空');

        $param['sort'] = $sort;
        $param['img_number'] = $img_number;
        $param['time'] = time();

        //编辑方法
        if ($this->model->save($param, array('id' => $id))){
            // 删除以前的模板图片，以及模板文件
            if (!empty($template_html) && is_file( '.'. $tempalteData['template_path'] ) ){
                unlink('.'. $tempalteData['template_path']);
            }
            // 刷新缓存
            $this->model->flushCache();
            // 重新生成前端楼层 include 页面
            $myStoreyConfig = new StoreyConfig();
            if ($img_number != $tempalteData['img_number']){
                $myStoreyConfig->removeInvalidConfig($id);
            }
            $myStoreyConfig->makeIndexIncludeFile();
            $this->success('编辑成功',url('index'));
        }else{
            $this->error('编辑失败');
        }
    }

    /**
     * 删除楼层模板方法
     */
    public function delete()
    {
        $id = $this->request->param('id', 0, 'intval');
        if (!is_numeric($id))  $this->error('参数错误');

        $myStoreyConfig = new StoreyConfig();
        $StoreyConfig = $myStoreyConfig->where(array('storey_template_id'=>$id))->find();
        if(!empty($StoreyConfig) || is_object($StoreyConfig)) $this->error('存在已配置的楼层，不能删除该模板');

        if ( $this->model->destroy(array('id' => $id)) ) {
            $this->model->flushCache();
            // 重新生成前端楼层 include 页面
            $myStoreyConfig = new StoreyConfig();
            $myStoreyConfig->removeInvalidConfig($id);
            $myStoreyConfig->makeIndexIncludeFile();
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 删除选中的楼层模板方法
     */
    public function deleteChecked() {
        $id_list = $this->request->param('id_list');
        $id_list = trim($id_list);
        if (empty($id_list)) $this->error('参数错误');

        $myStoreyConfig = new StoreyConfig();
        $StoreyConfig = $myStoreyConfig->where('storey_template_id', 'in', $id_list)->select();
        if(!empty($StoreyConfig)) $this->error('存在已配置的楼层，不能删除该模板');
        if ( $this->model->where('id', 'in', $id_list)->delete() ) {
            $this->model->flushCache();
            // 重新生成前端楼层 include 页面
            $myStoreyConfig = new StoreyConfig();
            $myStoreyConfig->removeInvalidConfig($id_list);
            $myStoreyConfig->makeIndexIncludeFile();
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
}
