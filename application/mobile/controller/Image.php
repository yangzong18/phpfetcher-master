<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2017/1/3  10:48
 */
namespace app\mobile\controller;

use app\index\controller\Common;
use think\Config;
use think\Db;

class Image extends Mobile{


    /**
     * 上传图片
     * @param image
     */
    public function upload(){
        $common = new Common();
        $common->upload();
    }


    /**
     * 获取首页轮播图
     * @param type 默认为1首页轮播图，0表示首页大屏广告，1表示首页滚动广告，2表示商品分类广告
     */
    public function getBanner(){

        $where = array();
        $where['is_delete'] = 0;

        $type = $this->request->param('type','','intval');
        if($type){
            $where['type'] = $type;
        }else{
            $where['type'] = 1;
        }
        $res = Db::name('advertise')->where($where)->select();
        if($res){
            $this->returnJson($res,0,'获取成功');
        }else{
            $this->returnJson($res,1,'未找到数据');
        }


    }
}