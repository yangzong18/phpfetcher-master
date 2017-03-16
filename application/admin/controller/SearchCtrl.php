<?php
/**
 * 搜索设置
 * 
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use think\Cache;

class SearchCtrl extends Auth
{
    private $key = 'search_ctrl';

    public function __construct(){
        parent::__construct();
    }

    /**
     * 搜索设置
     */
    public function add(){
        // 获取缓存数据
        $search = Cache::get($this->key);
        // $search = "";
        // $result = Cache::clear($this->key, $search);
        $this->assign("search", $search);
        return $this->fetch();
    }

    /**
     * 搜索设置方法
     */
    public function addPost(){
        // 重新缓存数据
        $param = $this->request->param();
        $search = isset($param['search']) ? trim($param['search']) : '';
        $result = Cache::set($this->key, $search);
        $this->success('设定成功');
    }

    
}