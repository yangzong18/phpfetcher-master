<?php
/**
 * 商品分类导航管理
 * 
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\admin\model\GoodsCategory;
use think\Validate;
use think\Cache;

class CategoryNavigateCtrl extends Auth
{
    private $key = 'category_navigate_ctrl';

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
    } 

    /**
     * 商品分类导航列表展示
     */
    public function index() {
        $param = $this->request->param();
        $name  = isset($param['name']) ? $param['name'] : '';
        $dataList = Cache::get($this->key);
        if (empty($dataList)){
            // 展示所有顶级商品名称
            $myGoodsCategory = new GoodsCategory();
            $firstGoodsCategory = $myGoodsCategory->getFirstLevel();
            $dataList = $firstGoodsCategory;
            foreach ($dataList as $key => $dataArray) {
                $dataArray['controller'] = '';
                $dataList[$key] = $dataArray;
            }
            Cache::set($this->key, $dataList);
        }
        $searchData = array();
        if (!empty($name)) {
            foreach ($dataList as $key => $dataArray) {
                if (mb_strstr($dataArray['name'], $name)) {
                    $searchData[] = $dataArray;
                }
            }
        } else {
            $searchData = $dataList;
        }
        // 变量输出
        $this->assign("name", $name);
        $this->assign("searchData", $searchData);
        return $this->fetch();
    }

    /**
     *  编辑商品分类导航
     */
    public function edit() {
        $param = $this->request->param();
        $id = $param['id'];
        $dataList = Cache::get($this->key);
        $dataArray = $dataList[$id];
        if (empty($dataArray)) {
            $this->error('加载失败');
        }
        $this->assign("dataArray", $dataArray);
        return $this->fetch();
    }

    /**
     * 编辑商品分类导航方法
     */
    public function editPost() {
        $param = $this->request->param();
        $editData = array();
        $category_id = isset($param['category_id']) ? trim($param['category_id']) : '';
        $editData['category_id'] = isset($param['category_id']) ? trim($param['category_id']) : '';
        $editData['controller'] = isset($param['controller']) ? trim($param['controller']) : '';
        // 参数验证
        $checkRule = array();
        $checkMsg  = array();
        $checkRule['category_id'] = 'require';
        $checkMsg['category_id.require'] = '编辑失败';
        $checkRule['controller'] = 'require';
        $checkMsg['controller.require'] = '顶级分类控制器不能为空';
        $validate = new Validate($checkRule, $checkMsg);
        $result   = $validate->check($editData);
        if(!$result) {
            $this->error($validate->getError());
        }
        if (!preg_match("/^[a-zA-Z0-9_\/]{1,}$/", $editData['controller'])){
            $this->error("顶级分类控制器必须有字母数字下划线构成");
        }
        $dataList = Cache::get($this->key);
        if (!array_key_exists($category_id, $dataList)) {
            $this->error('编辑失败，无效的数据');
        }
        // 保存数据
        $controller_len = strlen($editData['controller']);
        // 获取大写字母的位置
        $positionList = array();
        for ($i = 0; isset($editData['controller'][$i]); $i++){
            $num = ord($controller_len[$i]);
            if ($num >= 65 && $num <= 90){
                $positionList[$i] = $$editData['controller'][$i];
            }
        } 
        // 从尾部开始替换并替换为小写
        krsort($positionList);
        foreach ($positionList as $index => $value) {
            $editData['controller'] = substr_replace($editData['controller'], "_". $value, $index, 1);
        }
        $dataList[$category_id]['controller'] = strtolower($editData['controller']);
        $result = Cache::set($this->key, $dataList);
        if ($result) {
            $this->success('编辑成功', url('index'));
        } else {
            $this->error('编辑失败');
        }
    }

    /**
     * 删除商品分类导航方法
     */
    public function delete() {
        $param = $this->request->param();
        $id = $param['id'];
        $dataList = Cache::get($this->key);
        $dataArray = $dataList[$id];
        if (empty($dataArray)) {
            $this->error('删除失败');
        }
        unset($dataList[$id]);
        Cache::set($this->key, $dataList);
        $this->success('删除成功');
    }

    /**
     * 删除选中的商品分类导航方法
     */
    public function deleteChecked() {
        $id_list = $this->request->param('id_list');
        $idList = explode(',', $id_list);
        if (empty($idList)) {
            $this->error('删除失败');
        }
        $dataList = Cache::get($this->key);
        foreach ($idList as $key => $id) {
            unset($dataList[$id]);
        }
        Cache::set($this->key, $dataList);
        $this->success('删除成功');
    }
}