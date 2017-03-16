<?php
/**
 * 规格管理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-14 9:57
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\admin\model\Features;

class Specifications extends Auth
{
    protected $model;
    /**
     * 构造器
     */
    public function __construct() {
    	parent::__construct();
        $this->model = new Features();
    } 

    /**
     * 规格显示页
     */
	public function index() {
        $where = array( 'is_delete' => 0, 'sales_attribute' => 1 );
        $datas = $this->model->where( $where )->paginate();
        $this->assign("datas", $datas);
        return $this->fetch();
	}

	/**
     * 添加规格页
     */
    public function add() {
        return $this->fetch();
    }
    
    /**
     * 添加规格方法
     */
    public function addPost() {
        $attributeName = $this->request->param('attribute_name');
        $sort          = $this->request->param('sort', 'intval', 0);  
        if ( trim($attributeName) == '' ) {
            $this->error('规格名称不能为空');
        }
        $param = array(
            'attribute_name' => $attributeName,
            'sort'           => $sort,
            'sales_attribute'=> 1    //规格就是销售属性
        );
        //进行规格添加
        if ( $this->model->data( $param )->save() ) {
            $this->success('添加成功', 'index');
        } else {
            $this->error('添加失败');
        }
    }

    /**
     *  编辑规格
     */
    public function edit() {
        $id     = $this->request->param('id', 0, 'intval');
        $data   = $this->model->where(array("feature_id" => $id))->find();
        $this->assign('data', $data);
        return $this->fetch();
    }

   

    /**
     * 编辑规格方法
     */
    public function editPost() {
        $featureId     = $this->request->param('feature_id', 'intval', 0);
        $attributeName = $this->request->param('attribute_name');
        $sort          = $this->request->param('sort', 'intval', 0);  
        if ( trim($attributeName) == '' ) {
            $this->error('规格名称不能为空');
        }
        $param = array(
            'attribute_name' => $attributeName,
            'sort'           => $sort
        );
        //进行规格编辑
        $this->model->save($param, array( 'feature_id' => $featureId ));
        $this->success('编辑成功', 'index');
    }

    /**
     *  排序编辑
     */
    public function sort() {
        $id   = $this->request->param('id', 0, 'intval');
        $sort = $this->request->param('sort', 0, 'intval');
        $this->model->save( array( 'sort' => $sort ), array( 'feature_id' => $id) );
        $this->success('编辑成功', url('index'));
    }

    /**
     * 删除规格方法
     */
    public function delete() {
        $featureId  = $this->request->param('id', 'intval', 0);
        if ( $this->model->save(array( 'is_delete' =>1 ), array( 'feature_id' => $featureId )) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 批量删除规格方法
     */
    public function deleteChecked() {
        $featureId  = explode(',', $this->request->param('id_list'));
        $where      = array( 'feature_id' => array( 'in', $featureId ) );
        if ( count( $featureId ) > 0 && $this->model->save(array( 'is_delete' =>1 ), $where) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }



}
