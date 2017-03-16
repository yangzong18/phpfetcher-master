<?php
/**
 * 配送方式
 * 
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use think\Validate;
use think\Cache;

class DistributeMethodCtrl extends Auth
{
    private $key = 'distribute_method_ctrl';

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
    } 

    /**
     * 配送方式列表展示
     */
    public function index() {
        $param = $this->request->param();
        $name  = isset($param['name']) ? $param['name'] : '';
        $dataList = Cache::get($this->key);
		$dataList = $this->bubbleSort($dataList);
//         $dataList = array();
//         Cache::clear($this->key);
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
     * 添加配送方式页
     */
    public function add() {
        return $this->fetch();
    }
    
    /**
     * 添加配送方式方法
     */
    public function addPost() {
        $param = $this->request->param();
        $addData = array();
        $addData['name'] = isset($param['name']) ? trim($param['name']) : '';
        $addData['url'] = isset($param['url']) ? trim($param['url']) : '';
        $addData['sort'] = empty($param['sort']) ? 0 :trim($param['sort']);
        $addData['image'] = isset($param['image']) ? trim($param['image']) : '';
        // 参数验证
        $checkRule = array();
        $checkMsg  = array();
        $checkRule['name'] = 'require';
        $checkMsg['name.require'] = '配送方式不能为空';
        $checkRule['url'] = 'require';
        $checkMsg['url.require'] = '链接不能为空';
        $checkMsg['url.url'] = '无效的链接';
        $checkRule['sort'] = 'number';
        $checkMsg['sort.number'] = '排序必须为数字';
        $checkRule['image'] = 'require';
        $checkMsg['image.require'] = '图片不能为空';
        $validate = new Validate($checkRule, $checkMsg);
        $result   = $validate->check($addData);
        if(!$result) {
            $this->error($validate->getError());
        }
        $dataList = Cache::get($this->key);
        if (empty($dataList)) {
            $dataList = array();
        }
        // 保存数据
        $id = count($dataList);
        $addData['id'] = $id;
        $dataList[] = $addData;
		$dataList = $this->bubbleSort($dataList);
        $result = Cache::set($this->key, $dataList);
        if ($result) {
            $this->success('添加成功', url('index'));
        } else {
            $this->error('添加失败');
        }
    }
    /**
     *  编辑配送方式
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
     * 编辑配送方式方法
     */
    public function editPost() {
        $param = $this->request->param();
        $editData = array();
        $id = isset($param['id']) ? trim($param['id']) : '';
        $editData['id'] = isset($param['id']) ? trim($param['id']) : '';
        $editData['name'] = isset($param['name']) ? trim($param['name']) : '';
        $editData['url'] = isset($param['url']) ? trim($param['url']) : '';
        $editData['sort'] = empty($param['sort']) ? 0 :trim($param['sort']);
        $editData['image'] = isset($param['image']) ? trim($param['image']) : '';
        // 参数验证
        $checkRule = array();
        $checkMsg  = array();
        $checkRule['id'] = 'require|number';
        $checkMsg['id.require'] = '编辑失败';
        $checkMsg['id.number'] = '编辑失败';
        $checkRule['name'] = 'require';
        $checkMsg['name.require'] = '配送方式不能为空';
        $checkRule['url'] = 'require';
        $checkMsg['url.require'] = '链接不能为空';
        $checkMsg['url.url'] = '无效的链接';
        $checkRule['image'] = 'require';
        $checkMsg['image.require'] = '图片不能为空';
        $checkRule['sort'] = 'number';
        $checkMsg['sort.number'] = '排序必须为数字';
        $validate = new Validate($checkRule, $checkMsg);
        $result   = $validate->check($editData);
        if(!$result) {
            $this->error($validate->getError());
        }
        $dataList = Cache::get($this->key);
        if (!array_key_exists($id, $dataList)) {
            $this->error('编辑失败');
        }
        // 保存数据
        $dataList[$id] = $editData;
        $dataList = $this->bubbleSort($dataList);
        $result = Cache::set($this->key, $dataList);
        if ($result) {
            $this->success('编辑成功', url('index'));
        } else {
            $this->error('编辑失败');
        }
    }

    /**
     * 删除配送方式方法
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
        $dataList = $this->bubbleSort($dataList);
        Cache::set($this->key, $dataList);
        $this->success('删除成功');
    }

    /**
     * 删除选中的配送方式方法
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
        $dataList = $this->bubbleSort($dataList);
        Cache::set($this->key, $dataList);
        $this->success('删除成功');
    }

    /**
     *  排序编辑
     */
    public function sort() {
        $id   = $this->request->param('id', 0, 'intval');
        $sort = $this->request->param('sort', 0, 'intval');
        $dataList = Cache::get($this->key);
        if (!array_key_exists($id, $dataList)) {
            $this->success('编辑失败', url('index'));
        }
        $dataList[$id]['sort'] = $sort;
        $dataList = $this->bubbleSort($dataList);
        Cache::set($this->key, $dataList);
        $this->success('编辑成功', url('index'));
    }

    /**
     *  从新排序，并更新 id 为 key
     */
    public function bubbleSort($dataList) {
		array_multisort(array_column($dataList,'id'),SORT_DESC,$dataList);
        $dataList = array_values($dataList);
        $cnt = count($dataList);
        for ($i = 0; $i < $cnt; $i++) {
            for ($j = 0; $j < $cnt - $i - 1; $j++) {
                if ($dataList[$j]['sort'] > $dataList[$j + 1]['sort']) {
                    $temp = $dataList[$j];
                    $dataList[$j] = $dataList[$j + 1];
                    $dataList[$j + 1] = $temp;
                }
            }
        }
        foreach ($dataList as $key => $data) {
            $dataList[$key] = $data;
        }
        return $dataList;
    }
}
