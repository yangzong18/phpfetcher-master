<?php
/**
 * 服务网点设置
 * 
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use think\Validate;
use think\Cache;

class ServicePointCtrl extends Auth
{
    private $key = 'service_point_ctrl';

    public function __construct(){
        parent::__construct();
    }

    /**
     * 服务网点设置
     */
    public function add(){
        // 获取缓存数据
        $service = Cache::get($this->key);
        if (empty($service)) {
            $service = array();
        }
        // $service = array();
        // $result = Cache::clear($this->key, $service);
        $service['abstract'] = isset($service['abstract']) ? trim($service['abstract']) : "";
        $service['url'] = isset($service['url']) ? trim($service['url']) : "";
        //$service['image'] = isset($service['image']) ? trim($service['image']) : "";
        $this->assign("service", $service);
        return $this->fetch();
    }

    /**
     * 服务网点设置方法
     */
    public function addPost(){
        // 重新缓存数据
        $param = $this->request->param();
        $service = array();
        $service['abstract'] = isset($param['abstract']) ? trim($param['abstract']) : "";
        $service['url'] = isset($param['url']) ? trim($param['url']) : "";
        //$service['image'] = isset($param['image']) ? trim($param['image']) : "";
        // 数据验证
        $checkRule = array();
        $checkMsg  = array();
        $checkRule['abstract'] = 'require';
        $checkMsg['abstract.require'] = '摘要不能为空';
        $checkRule['url'] = 'require';
        $checkMsg['url.require'] = '链接不能为空';

        $validate = new Validate($checkRule, $checkMsg);
        $result   = $validate->check($service);
        if(!$result) {
            $this->error($validate->getError());
        }
        $result = Cache::set($this->key, $service);
        $this->success('设定成功');
    }
}