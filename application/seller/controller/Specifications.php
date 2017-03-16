<?php
/**
 * 商品规格管理-设置规格具体的值
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-17 16:57
 */
namespace app\seller\controller;
use app\common\controller\Auth;
use think\Db;

class Specifications extends Auth
{
    protected $model;
    /**
     * 构造器
     */
    public function __construct() {
    	parent::__construct();
    } 

    /**
     * 规格显示页
     */
	public function add() {
        return $this->fetch();
	}

    /**
     * 规格添加方法
     */
    public function addPost() {
        $param = $this->request->param();
        //判断有无需要编辑的规格值
        if ( isset( $param['edit_value'] ) && is_array( $param['edit_value'] ) && count( $param['edit_value'] ) > 0 ) {
            foreach ($param['edit_value'] as $value) {
                $data = explode(',', $value);
                if ( count( $data ) == 3 && is_numeric( $data[0] ) && $data[0] > 0
                     && is_numeric( $data[1] ) && trim( $data[2] ) != '' ) {
                    //进行编辑
                    $where  = array( 'id' => $data[0] );
                    $newData= array( 'feature_value' => $data[2], 'sort' => $data[1] );
                    $result = Db::name('features_value')->where( $where )->update( $newData );
                }
            }
        }
        //判断是否有新家的规格值
        if ( isset( $param['add_value'] ) && is_array( $param['add_value'] ) && count( $param['add_value'] ) > 0
             && isset( $param['feature_id'] ) && is_numeric( $param['feature_id'] ) && $param['feature_id'] > 0 
             && isset( $param['category_id'] ) && trim( $param['category_id'] )!='' ) {
            //拼接要添加的规格值
            $time    = time();
            $addData = array();
            foreach ($param['add_value'] as $value) {
                $data = explode(',', $value);
                if ( count( $data ) == 2 && is_numeric( $data[0] ) && trim( $data[1] ) != '' ) {
                    array_push( $addData , array(
                        'feature_id'    => $param['feature_id'],
                        'feature_value' => $data[1],
                        'is_self_define'=> 1,
                        'sort'          => $data[0],
                        'created_by'    => $this->user['member_id'],
                        'created_at'    => $time,
                        'store_id'      => $this->user['store_id'],
                        'category_id'   => $param['category_id'],
                    ));
                }
            }

            //批量增加
            if ( count( $addData ) > 0  ) {
                if ( !Db::name('features_value')->insertAll( $addData ) ) {
                    $this->error('保存失败');
                }
            }
        }
        $this->success('保存成功');
    }

    /**
     * 删除规格指
     */
    public function delete() {
        $id = $this->request->param('id');
        if ( Db::name('features_value')->where('id', $id )->update(array( 'is_delete' =>1 )) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
}
