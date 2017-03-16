<?php
/**
 * 设计师作品管理
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷 at 2016/11/22
 */

namespace app\admin\controller;
use app\admin\model\Designer;
use app\common\controller\Auth;
use app\common\model\LogsDecorationGoods;
use think\Validate;
use app\common\model\DesignerProduction;

class DesignerProductions extends Auth{

    protected $model;
    protected $search = [ 'production_name' => '名称',
                            'style' => '风格',
                            'designer_id' => '设计师'];
    protected $houseType;
    protected $style;

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new DesignerProduction();
        $this->houseType = ( new LogsDecorationGoods() )->getHouseType();
        $this->style = $this->model->getStyle();
    }

    /**
     * 作品列表
     */
    public function index() {
        $searchType = $this->request->param('searchType');
        $searchValue = $this->request->param('searchValue', '', 'trim');
        $condition = array('is_delete' => 0);
        if( $searchType != '' && $searchValue != '') {
            switch($searchType){
                case 'production_name'://作品名称
                    $condition[$searchType] = array('like', '%'.$searchValue.'%');
                    break;
                case 'style'://风格
                    $stid_arr=array();
                    foreach($this->style as $stid=>$v){
                        if($v==$searchValue || strpos($v,$searchValue)!==false){
                            $stid_arr[]=$stid;
                        }
                    }
                    if(count($stid_arr)>0) $condition[$searchType] = array('in',$stid_arr);
                    break;
                case 'designer_id'://设计师名称
                    $designerId = ( new Designer() )->where( ['designer_name' => ['like','%'.$searchValue.'%']] )->column('designer_id');
                    if( empty($designerId) ) {
                        $designerId = [0];
                    }
                    $condition[$searchType] = ['in', $designerId];
                    break;
            }
        }

        $productions = $this->model->where($condition)->order('production_id desc')->paginate(10, false,['query'=>['searchType'=>$searchType,'searchValue'=>$searchValue,'is_delete'=>0]]);
        
        //处理图片
        foreach($productions as $val) {
            $val->imgs = json_decode($val->imgs)[0];
        }
        $this->getDesigner();
        $this->assign('productions', $productions);
        $this->assign('style', $this->style);
        $this->assign('search', $this->search);
        $this->assign('searchType', $searchType);
        $this->assign('searchValue', $searchValue);
        return $this->fetch();
    }

    /**
     * 添加作品页
     */
    public function add() {
        $this->getDesigner( ['is_delete'=> 0] );
        $this->assign('houseType', $this->houseType);
        $this->assign('style', $this->style);
        return $this->fetch();
    }

    /**
     * 添加作品方法
     */
    public function addPost() {
        $productionInfo = [];
        $productionInfo['production_name'] = $this->request->param('name', '', 'trim');
        $productionInfo['house_type'] = $this->request->param('house_type', '', 'intval');
        $productionInfo['designer_id'] = $this->request->param('designerId', '', 'intval');
        $productionInfo['building_area'] = $this->request->param('area', '', 'trim');
        $productionInfo['imgs'] = $this->request->param('img_url/a', '', 'trim');
		$productionInfo['remark'] = $this->request->param('remark', '', 'trim');
        //验证提交数据
        $result = $this->validateProduction($productionInfo);
        if( !$result['code'] ) $this->error($result['msg']);

        //限制字数
        if(mb_strlen($productionInfo['production_name'],'utf-8') > 40){
            $this->error('作品名称最长应不超过40个汉字');
        }

        $productionInfo['imgs'] = json_encode($productionInfo['imgs']);
        $productionInfo['order_sn'] = $this->request->param('order_sn', '', 'trim');
        $productionInfo['is_show'] = $this->request->param('is_show', '', 'trim');
        $productionInfo['style'] = $this->request->param('style', '', 'trim');
        $productionInfo['upload_time'] = time();
        if( !$this->model->save( $productionInfo ) ) {//新增作品
            $this->error('添加失败');
        }
        $this->success('添加成功','index');
    }

    /**
     * 编辑作品页
     */
    public function edit() {
        $id     = $this->request->param('id/d', 0, 'trim');
        $data   = $this->model->where('production_id', $id)->find();
		if(!$data)  $this->error('没有这样的作品');
        $data->imgs = json_decode( $data->imgs );

        $this->getDesigner( ['is_delete'=> 0] );
        $this->assign('style', $this->style);
        $this->assign('houseType', $this->houseType);
        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 编辑作品方法
     */
    public function editPost() {
        $productionInfo = [];
        $productionInfo['production_name'] = $this->request->param('name', '', 'trim');
        $productionInfo['house_type'] = $this->request->param('house_type', '', 'intval');
        $productionInfo['building_area'] = $this->request->param('area', '', 'trim');
        $productionInfo['designer_id'] = $this->request->param('designerId', '', 'intval');
        $productionInfo['imgs'] = $this->request->param('img_url/a', '', 'trim');
		$productionInfo['remark'] = $this->request->param('remark', '', 'trim');
        //验证提交数据
        $result = $this->validateProduction($productionInfo);
        if( !$result['code'] ) $this->error($result['msg']);

        //限制字数
        if(mb_strlen($productionInfo['production_name'],'utf-8') > 40){
            $this->error('作品名称最长应不超过40个汉字');
        }

        $productionInfo['imgs'] = json_encode($productionInfo['imgs']);
        $productionInfo['order_sn'] = $this->request->param('order_sn', '', 'trim');
        $productionInfo['is_show'] = $this->request->param('is_show', '', 'trim');
        $productionInfo['style'] = $this->request->param('style', '', 'trim');
        $productionInfo['upload_time'] = time();
        $productionInfo['production_id'] = $this->request->param('productionId', '', 'trim');

        if( !$this->model->update($productionInfo) ) {//更新作品
            $this->error('编辑失败');
        }
        $this->success('编辑成功','index');

    }

    /**
     * 获取设计师列表
     * @param array $where 查询条件
     */
    protected function getDesigner( $where = array() ) {
        $designer = ( new Designer() )->where( $where )->select();

        $designerList = array();
        foreach($designer as $key => $val ) {
            $designerList[$val['designer_id']]['id'] = $val['designer_id'];
            $designerList[$val['designer_id']]['name'] = $val['designer_name'];
        }

        $this->assign('designerList', $designerList);
    }

    /**
     * 批量删除方法
     */
    public function deleteChecked() {
        $productionId  = explode(',', $this->request->param('id'));
        $where   = array( 'production_id' => array( 'in', $productionId ) );
        if( count( $productionId ) <= 0 )
            $this->error('删除失败');

        if( !$this->model->save(array( 'is_delete' => 1 ), $where) ) {
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }

    /**
     * 编辑、新增验证提交数据
     */
    protected function validateProduction( $dataInfo ) {
        $rule = [
            'production_name'  => 'require|desc',
            'designer_id'   => 'require',
            'house_type' => 'require',
            'building_area' => 'number|between:0,100000',
			'remark' => 'desc'
        ];
        $msg = [
            'production_name.require'  => '名称不能为空',
			'production_name.desc'		=> '名称含有非法字符串',
            'designer_id.require' => '设计师不能为空',
            'house_type.require' => '户型不能为空',
            'building_area.number' => '面积必须是数字',
            'building_area.between' => '面积必须是正数',
			'remark.desc' 			=> '说明含有非法字符',
        ];

        $validate = new Validate($rule, $msg);
        if( !$validate->check($dataInfo) ){
            return ['code' => 0, 'msg' => $validate->getError()];
        }
        if( empty($dataInfo['imgs']) ) {
            return ['code' => 0, 'msg' => '上传图片不能为空'];
        }
        return ['code' => 1];
    }

}
