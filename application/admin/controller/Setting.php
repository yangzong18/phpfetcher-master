<?php
/**
 * 配置管理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-01-09 9:57
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\common\model\Setting as Config;

class Setting extends Auth
{
    protected $model;
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Config();
    } 
    
    //基础配置
    public function index() {
        $this->assign( 'settingList', $this->model->inquire() );
        return $this->fetch();
    }

    //配置设置
    public function addPost() {
        $param = $this->request->param();
        foreach ($param as $key => $content) {
            if ( trim( $content ) == '' ) {
                $this->error($key.'不能为空');
            }
            $where = array( 'key' => $key );
            $data  = array( 'content' => $content );
            $this->model->save( $data, $where);
        }
        //清除缓存并重新生成
        $this->model->rebuild();
        $this->success('编辑成功');
    }
}
