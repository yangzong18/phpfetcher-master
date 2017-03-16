<?php
/**
 * 全景展示页面
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang at 2016/12/29
 */
namespace app\shop\controller;

use app\common\controller\Shop;

class Scenery extends Shop {

    /**
     * 全景展示
     */
	public function index( $rid = 1 ){
        switch ( $rid ) {
            case 2:
                return $this->fetch('index_two');
                break;
            case 3:
                return $this->fetch('index_thread');
                break;
            default:
                return $this->fetch();
                break;
        }
	}


    /**
     * 全景展示详情页
     */
    public function detail(){
        return $this->fetch();
    }


}
