<?php
/**
 * 友情链接
 * 
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\admin\model\StoreyConfig;
use app\admin\model\StoreyTemplate;
use app\admin\model\GoodsCategory;
use think\Validate;

class StoreyConfigCtrl extends Auth
{
    protected $model;
    protected $field = '*';
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new StoreyConfig();
    }

    /**
     * 楼层管理列表
     */
    public function index() {
        $tblRows = $this->model->order(array('sort'=>'asc','id'=> 'desc'))->paginate();
        $myGoodsCategory = new GoodsCategory();
        // $myGoodsCategory->flushCache();
        $myStoreyTemplate = new StoreyTemplate();
        $storeyTemplatedata = $myStoreyTemplate->getCache();
        // 获取一级分类 
        $allLevelGoodsCategory = $myGoodsCategory->getAllLevel();
        foreach ($tblRows as $key => $row){
            $goodsCategory = isset($allLevelGoodsCategory[$row['goods_category_id']]) ? 
                             $allLevelGoodsCategory[$row['goods_category_id']] : array();
            $row['goods_category_name'] = isset($goodsCategory['name']) ? $goodsCategory['name'] : '';
            $row['storey_template_name'] = isset($storeyTemplatedata[$row['storey_template_id']]['name']) ? 
                                           $storeyTemplatedata[$row['storey_template_id']]['name'] : '';
            $row['is_disable'] = $row['is_disable'] ? "是" : "否";
            $tblRows[$key] = $row;
        }
        $this->assign("tblRows", $tblRows);

        return $this->fetch();
    }

    /*
     * 添加楼层管理模板
     */
    public function add() {
        // $this->model->flushCache();
        // $storeyName = array('楼层一','楼层二','楼层三','楼层四','楼层五','楼层六','楼层七','楼层八','楼层九','楼层十');
        // 获取一级分类
        $myGoodsCategory = new GoodsCategory();
        $firstLevelGoodsCategory = $myGoodsCategory->getFirstLevel();
        // 获取楼层配置信息
        $storeyConfigData = $this->model->getAllCache();
        // 获取已经进行了楼层配置的商品一级分类 category_id
        $categoryIdList = array();
        foreach ($storeyConfigData as $storey_config_id => $storeyConfig){
            $categoryIdList[] = $storeyConfig['goods_category_id'];
        }
        // 数据过滤，去除已经存在楼层配置的商品一级分类
        foreach ($firstLevelGoodsCategory as $category_id => $goodsCategory){
            if (in_array($category_id, $categoryIdList)){
                unset($firstLevelGoodsCategory[$category_id]);
            }
        }
        $this->assign("firstLevelGoodsCategory", $firstLevelGoodsCategory);
        // 获取楼层模板
        $myStoreyTemplate = new StoreyTemplate();
        $storeyTemplateData = $myStoreyTemplate->getCache();
        $this->assign("storeyTemplateData", $storeyTemplateData);
        return $this->fetch();
    }

    /*
     * 添加楼层管理方法
     */
    public function addPost() {
        // 图片数量
		$sort = $this->request->param('sort','','trim');
		if(isset($sort)){
			if(!preg_match('/^[0-9]*$/',$sort)){
				$this->error('排序参数不正确');
			}
		}else{
			$sort = 0;
		}
        $img_number = $this->request->param('img_number','','intval');
		$url = $this->request->param('url','','trim');
		if(empty($url)){
			$this->error('添加失败，url不能为空。');
		}
		$rule = [
			['url','url','url格式不正确'],
		];

		$data = [
			'url'  => $url
		];
		$validate = new validate($rule);
		$result   = $validate->check($data);
		if(!$result){
			$this->error($validate->getError());
		}
        if (empty($img_number)){
            $this->error('添加失败，模板图片数量不能为空。');
        }
        $goods_category_id = $this->request->param('goods_category_id');
        if (empty($goods_category_id)){
            $this->error('添加失败，楼层不能为空。');
        }
        $storey_template_id = $this->request->param('storey_template_id','','intval');
        if (empty($storey_template_id)){
            $this->error('添加失败，楼层模板不能为空。');
        }
        // 生成 unique_name 用于模板参数唯一标记
        $unique_name = "param_list_". uniqid();
        // 获取图片配置参数
        $imgParamData = array();
        $myGoodsCategory = new GoodsCategory();
        // 根据一级分类 id 获取二级分类
        $secondLevelGoodsCategory = $myGoodsCategory->getSecondLevel();
        $secondLevelGoodsCategory = isset($secondLevelGoodsCategory[$goods_category_id]) ? 
                                    $secondLevelGoodsCategory[$goods_category_id] : array();
        foreach ($secondLevelGoodsCategory as $key => $goodsCategory){
            $second_level_id = $goodsCategory['category_id'];
            $paramData = array();
            for ($i = 1; $i <= $img_number; $i++){
                // 获取楼层详细配置列表
                $img_link_name = $second_level_id .'_img_link_'. $i;
                $img_description_name = $second_level_id .'_img_description_'. $i;
                $link_value = $this->request->param($img_link_name);
                $description_value = $this->request->param($img_description_name);

                //获取图片
                //现图片上传begin
                $img_path_name = $second_level_id .'_preview_'. $i;
                $path_value = $this->request->param($img_path_name);
                //现图片上传end

                $paramData['name'] = $goodsCategory['name'];
                $paramData['category_id'] = $second_level_id;
                $paramData['parent_category_id'] = $goods_category_id;
                $paramData['link_'. $i] = $link_value;
                $paramData['path_'. $i] = $path_value;
                $paramData['description_'. $i] = $description_value;

                $paramData['forEdit'][$i] = array('link' => $link_value,
                                                  'path' => $path_value,
                                                  'description' => $description_value);
            }
            $imgParamData[$second_level_id] = $paramData;
        }
        //楼层管理信息
        $param = array();
        $param['goods_category_id'] = $goods_category_id;
        $param['storey_template_id'] = $storey_template_id;
		$param['url'] 				= $url;
        $param['parameter'] = json_encode($imgParamData);
        $param['sort'] = $sort;
        $param['is_disable'] = 0;
        $param['unique_name'] = $unique_name;
        $param['time'] = time();

        //添加方法
        if($this->model->save($param)){
            $this->model->flushCache();
            $this->model->makeIndexIncludeFile();
            $this->success('添加成功',url('index'));
        }else{
            $this->error('添加失败');
        }
    }

    /**
     * 编辑楼层管理
     */
    public function edit() {
        //获取详细信息
        $param = $this->request->param();
        $id = $param['id'];
        $id = $this->request->param('id','','intval');
        if (empty($id)){
            $this->error('编辑失败，无效的数据。');
        }
        // 获取一级分类
        $myGoodsCategory = new GoodsCategory();
        $firstLevelGoodsCategory = $myGoodsCategory->getFirstLevel();
        // 获取楼层配置信息
        $storeyConfigData = $this->model->getAllCache();
        // 防止当前楼层配置被过滤
        unset($storeyConfigData[$id]);
        // 获取已经进行了楼层配置的商品一级分类 category_id
        $categoryIdList = array();
        foreach ($storeyConfigData as $storey_config_id => $storeyConfig){
            $categoryIdList[] = $storeyConfig['goods_category_id'];
        }
        // 数据过滤，去除已经存在楼层配置的商品一级分类
        foreach ($firstLevelGoodsCategory as $category_id => $goodsCategory){
            if (in_array($category_id, $categoryIdList)){
                unset($firstLevelGoodsCategory[$category_id]);
            }
        }
        $this->assign("firstLevelGoodsCategory", $firstLevelGoodsCategory);
        // 获取楼层模板
        $myStoreyTemplate = new StoreyTemplate();
        $storeyTemplateData = $myStoreyTemplate->getCache();
        $this->assign("storeyTemplateData", $storeyTemplateData);
        $tblRow = $this->model
                       ->field($this->field)
                       ->where(array('id' => $id))
                       ->find();
        if (empty($tblRow)){
            $this->error('编辑失败，无效的数据。');
        }
        // 这部分为修补由于新增二级商品分类造成的数据参数缺失引起的 bug
        // 图片数量控制
        $img_number = $storeyTemplateData[$tblRow['storey_template_id']]['img_number'];
        $parameter = json_decode($tblRow['parameter'], true);
        // 根据一级分类 id 获取二级分类
        $goods_category_id = $tblRow['goods_category_id'];
        $secondLevelGoodsCategory = $myGoodsCategory->getSecondLevel();
        $secondLevelGoodsCategory = isset($secondLevelGoodsCategory[$goods_category_id]) ? 
                                    $secondLevelGoodsCategory[$goods_category_id] : array();
        foreach ($secondLevelGoodsCategory as $key => $goodsCategory){
            // 判断该二级商品分类是否存在配置参数，如果没有则添加空数据
            $second_level_id = $goodsCategory['category_id'];
            if (array_key_exists($second_level_id, $parameter)) {
                // 需求未定，由于楼层模板中图片数量发生改变，导致楼层配置中的图片失效问题
                continue;
            }
            // 添加空数据
            $paramData = array();
            for ($i = 1; $i <= $img_number; $i++) {
                $paramData['name'] = $goodsCategory['name'];
                $paramData['category_id'] = $second_level_id;
                $paramData['parent_category_id'] = $goods_category_id;
                $paramData['link_'. $i] = '';
                $paramData['path_'. $i] = '';
                $paramData['description_'. $i] = '';
                $paramData['forEdit'][$i] = array('link' => '',
                                                  'path' => '',
                                                  'description' => '');
            }
            $parameter[$second_level_id] = $paramData;
        }

        foreach ($parameter as $key => $opt) {
            $parameter[$key]['name'] = isset($opt['name']) ? $opt['name'] : '';
        }
        $tblRow['parameter'] = $parameter;
        $tblRow['img_number'] = $img_number;
        $this->assign("tblRow", $tblRow);
        return $this->fetch();
    }

    /**
     * 编辑楼层管理方法
     */
    public function editPost() {

        // 参数接受
		$sort = $this->request->param('sort','','trim');
		if(isset($sort)){
			if(!preg_match('/^[0-9]*$/',$sort)){
				$this->error('排序参数不正确');
			}
		}else{
			$sort = 0;
		}
        $id = $this->request->param('id','','intval');
		$url = $this->request->param('url','','trim');
        if (empty($url)){
            $this->error('url不能为空');
        }
		$rule = [
			['url','url','url格式不正确'],
		];

		$data = [
			'url'  => $url
		];
		$validate = new validate($rule);
		$result   = $validate->check($data);
		if(!$result){
			$this->error($validate->getError());
		}
        // 图片数量
        $img_number = $this->request->param('img_number','','intval');
        if (empty($img_number)){
            $this->error('编辑失败，模板图片数量不能为空。');
        }
        // 一级分类 id
        $goods_category_id = $this->request->param('goods_category_id');
        if (empty($goods_category_id)){
            $this->error('编辑失败，楼层不能为空。');
        }
        $storey_template_id = $this->request->param('storey_template_id','','intval');
        if (empty($storey_template_id)){
            $this->error('编辑失败，楼层模板不能为空。');
        }
        // 获取原始数据
        $tblRow = $this->model
                       ->field($this->field)
                       ->where(array('id' => $id))
                       ->find();
        if (empty($tblRow)){
            $this->error('编辑失败，无效的数据。');
        }
        // 获取楼层配置信息
        $storeyConfigData = $this->model->getAllCache();
        // 获取已经进行了楼层配置的商品一级分类 category_id
        $categoryIdList = array();
        foreach ($storeyConfigData as $storey_config_id => $storeyConfig){
            $categoryIdList[] = $storeyConfig['goods_category_id'];
        }
        // 判断该数据是否属于已经存在楼层配置的商品一级分类
        if (in_array($goods_category_id, $categoryIdList)){
            // 如果传送过来的楼层 goods_category_id 不是当前被编辑的楼层 id 则返回 array()
            if ($goods_category_id != $tblRow['goods_category_id']){
                $this->error('编辑失败，无效的楼层选择。');
            }
        }
        $oldParameter = json_decode($tblRow['parameter'], true);
        // 获取图片配置参数
        $imgParamData = array();
        $myGoodsCategory = new GoodsCategory();
        // 根据一级分类 id 获取二级分类
        $secondLevelGoodsCategory = $myGoodsCategory->getSecondLevel();
        $secondLevelGoodsCategory = isset($secondLevelGoodsCategory[$goods_category_id]) ? 
                                    $secondLevelGoodsCategory[$goods_category_id] : array();
        foreach ($secondLevelGoodsCategory as $key => $goodsCategory){
            $second_level_id = $goodsCategory['category_id'];
            $paramData = array();
            for ($i = 1; $i <= $img_number; $i++){
                // 获取楼层详细配置列表
                $img_link_name = $second_level_id .'_img_link_'. $i;
                $link_value = $this->request->param($img_link_name);

                $path_name = $second_level_id .'_preview_'. $i;
                $path_value = $this->request->param($path_name);

                $img_description_name = $second_level_id .'_img_description_'. $i;
                $description_value = $this->request->param($img_description_name);

                // 新旧数据替换
                if (isset($oldParameter[$second_level_id])){
                    $link_value = empty($link_value) ? '' : $link_value;
                    $path_value = empty($path_value) ? '' : $path_value;
                    $description_value = empty($description_value) ? '' : $description_value;
                }
                $paramData['name'] = $goodsCategory['name'];
                $paramData['category_id'] = $second_level_id;
                $paramData['parent_category_id'] = $goods_category_id;
                $paramData['link_'. $i] = $link_value;
                $paramData['path_'. $i] = $path_value;
                $paramData['description_'. $i] = $description_value;
                $paramData['forEdit'][$i] = array('link' => $link_value,
                                                  'path' => $path_value,
                                                  'description' => $description_value);
            }
            $imgParamData[$second_level_id] = $paramData;
        }
        // 是否删除
        $is_disable = $this->request->param('is_disable','','intval');
        if (!in_array($is_disable, array(0, 1))){
            $this->error('编辑无效，是否删除状态无效');
        }
        //楼层管理信息
        $param = array();
        $param['goods_category_id'] = $goods_category_id;
        $param['storey_template_id'] = $storey_template_id;
		$param['url'] 				= $url;
        $param['parameter'] = json_encode($imgParamData);
        $param['sort'] = $sort;
        $param['is_disable'] = $is_disable;
        $param['time'] = time();

        //编辑方法
        if($this->model->save($param, array('id' => $id))){
            $this->model->flushCache();
            $this->model->makeIndexIncludeFile();
            $this->success('编辑成功',url('index'));
        }else{
            $this->error('编辑失败');
        }
    }

    /*
     * ajax 请求动态楼层配置
     */
    public function changeConfig() {
        $id = $this->request->param('id');
        $goods_category_id = $this->request->param('goods_category_id');
        // 获取楼层配置信息
        $storeyConfigData = $this->model->getAllCache();
        // 获取已经进行了楼层配置的商品一级分类 category_id
        $categoryIdList = array();
        foreach ($storeyConfigData as $storey_config_id => $storeyConfig){
            $categoryIdList[] = $storeyConfig['goods_category_id'];
        }
        // 判断该数据是否属于已经存在楼层配置的商品一级分类
        if (in_array($goods_category_id, $categoryIdList)){
            // 如果是且传送过来的楼层 goods_category_id 不是当前被编辑的楼层 id 则返回 array()
            if (empty($id) || $goods_category_id != $storeyConfigData[$id]['goods_category_id']){
                return json_encode(array());
            }
        }
        $myGoodsCategory = new GoodsCategory();
        // 根据一级分类 id 获取二级分类
        $secondLevelGoodsCategory = $myGoodsCategory->getSecondLevel();
        $secondLevelGoodsCategory = isset($secondLevelGoodsCategory[$goods_category_id]) ? 
                                    $secondLevelGoodsCategory[$goods_category_id] : array();
        echo json_encode($secondLevelGoodsCategory);
    }

    /**
     * 删除楼层管理方法
     */
    public function delete() {
        $id = $this->request->param('id', 0, 'intval');
        if (!is_numeric($id)){
            $this->error('删除失败');
        }
        if ($this->model->destroy(array('id' => $id))) {
            $this->model->flushCache();
            $this->model->makeIndexIncludeFile();
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 删除选中的楼层管理方法
     */
    public function deleteChecked() {
        $id_list = $this->request->param('id_list');
        $id_list = trim($id_list);
        if (empty($id_list)){
            $this->error('删除失败');
        }
        if ( $this->model->where('id', 'in', $id_list)->delete() ) {
            $this->model->flushCache();
            $this->model->makeIndexIncludeFile();
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
}
