<?php
/**
 * 我的收藏
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷 at 2016/12/24
 */

namespace app\shop\controller;
use app\common\controller\Member;
use app\shop\model\Favorites;
use think\Db;

class Favorite extends Member {
    protected $model;

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Favorites();
    }

    /**
     * 我的收藏页
     */
    public function index() {
        $type = $this->request->param('type', 0, 'intval') == 0 ? 0 : $this->request->param('type', 0, 'intval');
        $goodsType = $this->request->param('goods_type', 'all', 'trim');
        //搜索商品名字
        $searchName = $this->request->param('search');
		$where=['member_id'=>$this->user->member_id, 'f.type'=>$type];
		if(!empty($searchName)){
			switch($type){
				case 0:
					$goodsId = Db::name('goods')->where('goods_name','like','%'.$searchName.'%')->column('goods_id');
					if( empty($goodsId) ) $goodsId = array('0');
					$where['f.goods_id']=['IN',$goodsId];
					break;
				case 1:
					$logsId = Db::name('logs_decoration_goods')->where('name','like','%'.$searchName.'%')->column('id');
					if( empty($logsId) ) $logsId = array(0);
					$where['f.logs_id']=['IN',$logsId];
					break;
				case 2:
					$productionId = Db::name('designer_production')->where('production_name','like','%'.$searchName.'%')->column('production_id');
					if( empty($productionId) ) $productionId = array(0);
					$where['f.production_id']=['IN',$productionId];
					break;
			}
		}

        $whereOr = '';
        $lostWhereOr = 'is_delete =1 or goods_verify !=1';
        $descWhere = $lostWhere = $where;
        switch($goodsType){
            case 'all':
                break;
            case 'desc':
                $where['f.price'] = ['exp','> g.goods_price'];
                $where['g.goods_verify'] = 1;
                break;
            case 'lost':
                $whereOr = $lostWhereOr;
                break;
        }
        //列表分类读取
        switch( $type ) {
            case 0://假如是收藏的普通商品
                $field = 'f.goods_id,goods_name,goods_price,goods_image_main,is_delete,goods_verify,f.price,f.id';
                $list = $this->model->getFavoriteGoodsList($where, $whereOr, $field,$searchName);
                $this->assign('goodsType', $goodsType);
				$this->assign('tp', $type);
                $this->assign('list', $list);
                $this->assign('search_name', $searchName);
                //获取将降价个数
                $descWhere['f.price'] = ['exp','> g.goods_price'];
                $descWhere['g.goods_verify'] = 1;
                $this->assign('descCount', $this->model->getFavoriteGoodsCount($descWhere, ''));
                //获取失效个数
                $this->assign('lostCount', $this->model->getFavoriteGoodsCount($lostWhere, $lostWhereOr));
                $this->assign('page', $this->dealPage($list->render()));
                return $this->fetch();
                break;
            case 1://假如是收藏的整装商品
                $field = 'name,cover,is_delete,goods_verify,acreage,f.id,f.logs_id';
                $list = $this->model->getFavoriteLogsGoodsList($where, $whereOr, $field,$searchName);
                $this->assign('goodsType', $goodsType);
				$this->assign('tp', $type);
                $this->assign('list', $list);
                $this->assign('search_name', $searchName);
                //获取失效个数
                $this->assign('lostCount', $this->model->getFavoriteLogsGoodsCount($lostWhere, $lostWhereOr));
                $this->assign('page', $this->dealPage($list->render()));
                return $this->fetch('logsindex');
                break;
            case 2:
                $field = 'production_name,building_area,is_delete,f.id,f.production_id,imgs,is_show';
                $lostWhereOr = 'is_delete =1 or is_show =0';
                if( $goodsType == 'lost') {//说明是
                    $whereOr = $lostWhereOr;
                }
                $list = $this->model->getFavoriteProductionList($where, $whereOr, $field,$searchName);
                $this->assign('goodsType', $goodsType);
				$this->assign('tp', $type);
                $this->assign('list', $list);
                $this->assign('search_name', $searchName);
                //获取失效个数
                $this->assign('lostCount', $this->model->getFavoriteProductionCount($lostWhere, $lostWhereOr));
                $this->assign('page', $this->dealPage($list->render()));
                return $this->fetch('productionsindex');
                break;
        }
    }

    /**
     * 删除我的收藏
     */
    public function delete() {
        $id = $this->request->param('id','','trim');
        if( $id == '')
            $this->error('删除失败');
        //数据处理
        $arr = explode(',', $id);
        $where = [];
        $where['member_id'] = $this->user->member_id;
        $where['id'] =  ['in', $arr];

        if( !$this->model->where( $where )->delete() ) {
            $this->error('删除失败');
        }
        $this->success('删除成功');
    }

}
