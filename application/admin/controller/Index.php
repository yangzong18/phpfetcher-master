<?php
/**
 * 管理平台首页
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-10-26 13:27
 */
namespace app\admin\controller;
use think\Controller;

class Index extends Controller
{
    /**
     * 外边页
     */
	public function index() {
       return $this->fetch();
	}


}
