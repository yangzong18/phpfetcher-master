<?php
/**
 * 区域接口
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: Luo Ting at 2016-11-21
 */
namespace app\api\controller;
use app\common\model\Area;
use think\Controller;
use think\Request;

class Areas extends Controller{

    /**
     * 通过父地址ID,json输出地址数组
     */
    public function getAreaByParentId() {
        $id = $this->request->param('parent_id', '', 'intval');
        $parentId = Request::instance()->has('parent_id','get')&&is_numeric( $id ) && $id > 0 ? $id : 1;
        $areaList = ( new Area())->getNextAreaList($parentId);
        echo json_encode( array( $parentId => $areaList ) );
    }

    /**
     * 通过省级地址ID,json输出地址数组
     */
    public function getProvinceId() {
        $areaList = ( new Area())->getTopLevelAreas();
        echo json_encode( $areaList );

    }
}