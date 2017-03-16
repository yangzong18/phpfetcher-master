<?php
/**
 * 设计之家
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷 at 2016/12/12
 */

namespace app\shop\controller;
use app\common\controller\Shop;
use app\common\model\LogsDecorationOrder;
use app\shop\model\Designer;
use app\shop\model\DesignerLevel;
use app\shop\model\DesignerProduction;
use think\Db;

class Designers extends Shop{
    protected $model;
    protected $designerLevel;

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Designer();
        $this->designerLevel = new DesignerLevel();
    }

    /**
     * 设计之家列表
     */
    public function index() {
        $levelId = $this->request->param('level', '', 'intval');

        $where = [];
        $where['is_delete'] = 0;
        if( $levelId ) {
            $where['level_id'] = $levelId;
            $this->assign('level_id', $levelId);
        }
        $designerList = $this->model->getDesignerList( $where ,'designer_id,level_id,designer_year,designer_avatar,designer_name','','',true);
        $levelList = $this->designerLevel->getDesignerLevelList();
        //设计之星
        $this->assign('teamDesigner', $this->getTeamDesigners());
        $this->assign('newCases', $this->getNewCases());
        $this->assign('designerList', $designerList);
        $this->assign('levelList', $levelList);
        $this->assign('page',$this->dealPage($designerList->render()));
        return $this->fetch();
    }

    /**
     * 设计师详情页面
     */
    public function detail() {
        $id = $this->request->param('designer_id', '', 'intval');
        if( !$id )
            $this->error('参数错误');
        //增加浏览量
        $this->model->where(['designer_id' => $id])->setInc('hot', 1);
        //获取设计师信息
        $info = $this->model->getDesignerInfo( ['designer_id' => $id] );
        if ( !$info ) {
            $this->error('设计师不存在');
        }

        $levelList = $this->designerLevel->getDesignerLevelList();
        //获取设计师作品信息
        $productionModel = new DesignerProduction();
        //计算经验值，计算方式，设计师的作品数量*10
        $number= $productionModel->where(['designer_id' => $id, 'is_delete'=>0])->count();
        $field = 'production_id, production_name, designer_id, building_area, remark, imgs';
        $production = $productionModel->productionList( ['designer_id' => $id, 'is_delete' => 0, 'is_show'=>1] ,$field, 0, true,4);
        //处理图片
        foreach($production as $key => $val) {
            $production[$key]['imgs'] = json_decode($production[$key]['imgs'])[0];
        }
        $this->assign('info', $info);
        $this->assign('production', $production);
        $this->assign('page',$this->dealPage($production->render()));
        $this->assign('levelList', $levelList);
        //设计之星
        $this->assign('teamDesigner', $this->getTeamDesigners());
        $this->assign('newCases', $this->getNewCases());
        $this->assign('experience', $number*10);
        return $this->fetch();
    }

    /**
     * 获取本月设计之星
     */
    public function getTeamDesigners() {
        $logOrder = new LogsDecorationOrder();
        $list = $logOrder->getLogsOrderByDesigner([]);//查询原木整装订单

        $where = [];
        $where = ['is_delete' => 0];
        $field = 'designer_id,level_id,designer_avatar,designer_name';
        switch( count($list) ){
            case 0://本月没有交易成功的设计师
                return $this->model->getDesignerList($where, $field, 4,'designer_name asc');
                break;
            case 4://本月有4位交易成功的设计师
                $where['designer_id'] = ['in', array_keys($list)];
                return $this->model->getDesignerList($where, $field);
                break;
            default://4位不足
                $where['designer_id'] = ['in', array_keys($list)];
                $result = $this->model->getDesignerList($where, $field);
                $resultList = [];
                foreach( $result as $key => $val) {
                    $resultList[$val['designer_id']] = $result[$key]->toArray();
                }
                $where['designer_id'] = ['not in', array_keys($list)];
                $result1 = $this->model->getDesignerList($where, $field, 4-count($list),'designer_name asc');
                $mergeList = [];
                foreach( $list as $key => $val ) {
                    $mergeList[] = $resultList[$key];
                }
                foreach( $result1 as $key => $val ) {
                    $mergeList[] = $result1[$key]->toArray();
                }
                return $mergeList;
                break;
        }
    }

    /**
     * 获取最新案例
     */
    public function getNewCases() {
        //获取设计师作品信息
        $productionModel = new DesignerProduction();
        $field = 'production_id, production_name, designer_id, building_area, remark, imgs';
        $production = $productionModel->productionList( ['is_show' => 1,'is_delete'=>0] ,$field, 4, false,4,'upload_time desc');
        //处理图片
        foreach($production as $key => $val) {
            $production[$key]['imgs'] = json_decode($production[$key]['imgs'], true)[0];
        }
        return $production;
    }

    /**
     * 设计师切换页面，弹窗，用于原木整装设计师切换
     */
    public function change() {
        $where = array( 'is_delete' => 0 );
        $designerList = $this->model->getDesignerList( $where ,'designer_id,level_id,designer_year,designer_avatar,designer_name','','',true);
        $levelList = $this->designerLevel->getDesignerLevelList();
        //拼接设计师列表和设计师设计师等级
        foreach ($designerList as $key => $designer) {
            $designer['level_name'] = '';
            foreach ($levelList as $level) {
                if ( $level['id'] == $designer['level_id'] ) {
                    $designer['level_name'] = $level['name'];
                    break 1;
                }
            }
            $designerList[$key] = $designer;
        }
        //设计之星
        $this->assign('designerList', $designerList);
        return $this->fetch();
    }

}