<?php
/**
 * 后台菜单模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 周平 <lecheng406@sina.com> at 2016-11-16
 */
namespace app\shop\model;

use think\Model;
use think\Cache;

class Navigation extends Model
{
    /**
     * 根据类型获取导航地址
     */
    public function inquire( $type ) {
        $where = array( 'type' => $type );
        $data  = $this->where( $where )->order('sort desc')->find();
        $url   = isset( $data['url'] ) ? $data['url']: '#';
        if ( $url != '#' && strpos( $url, 'https' ) === false && strpos( $url, 'http' ) === false ) {
        	$url = url( $url );
        }
        return $url;
    }

    /**
     * 根据类型获取导航地址,查询整条
     */
    public function inquireInfo( $type ) {
        $where = array( 'type' => $type );
        $data  = $this->where( $where )->order('sort desc')->find();
        $url   = isset( $data['url'] ) ? $data['url']: '#';
        if ( $url != '#' && strpos( $url, 'https' ) === false && strpos( $url, 'http' ) === false ) {
            $data['url'] = url( $url );
        }
        return $data;
    }
}