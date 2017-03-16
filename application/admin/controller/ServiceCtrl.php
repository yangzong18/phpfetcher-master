<?php
/**
 * 服务设置
 * 
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use think\Cache;

class ServiceCtrl extends Auth
{
    private $key = 'service_ctrl';

    public function __construct(){
        parent::__construct();
    }

    /**
     * 服务设置
     */
    public function add(){
        // 获取缓存数据
        $service = Cache::get($this->key);
        if (empty($service)) {
            $service = array();
        }
        // $service = array();
        // $result = Cache::clear($this->key, $service);
        $service['tel'] = isset($service['tel']) ? trim($service['tel']) : "";
        $service['time'] = isset($service['time']) ? trim($service['time']) : "";
        $this->assign("service", $service);
        return $this->fetch();
    }

    /**
     * 服务设置方法
     */
    public function addPost(){
        // 重新缓存数据
        $param = $this->request->param();
        $service = array();
        $service['tel'] = isset($param['tel']) ? trim($param['tel']) : "";
        $service['time'] = isset($param['time']) ? trim($param['time']) : "";
        $result = Cache::set($this->key, $service);
        $this->success('设定成功');
    }
}