<?php
/**
 * 我的收藏模型
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷 at 2016/12/24
 */

namespace app\shop\model;
use app\common\model\Goods;
use app\common\model\GoodsSku;
use app\common\model\LogsDecorationGoods;
use think\Model;

class Favorites extends Model{

    /**
     * 获取收藏商品列表
     * @param $where
     * @param $whereOr
     * @param $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getFavoriteGoodsList( $where, $whereOr, $field ,$search_name='' ) {
        $join = [['mgt_goods g','g.goods_id=f.goods_id']];
		$paginate_query=['query' => ['search'=>$search_name] ];
        $list = $this->field($field)->alias('f')->join($join)->where($where)->where($whereOr)->paginate(10,false,$paginate_query);
        return $list;
    }

    /**
     * 获取收藏整装商品列表
     * @param $where
     * @param $whereOr
     * @param $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getFavoriteLogsGoodsList( $where, $whereOr, $field,$search_name='' ) {
        $join = [['mgt_logs_decoration_goods g','g.id=f.logs_id']];
		$paginate_query=['query' => ['search'=>$search_name] ];
        $list = $this->field($field)->alias('f')->join($join)->where($where)->where($whereOr)->paginate(8,false,$paginate_query);
        return $list;
    }

    /**
     * 获取收藏普通商品个数
     * @param $where
     * @param $whereOr
     * @return mixed
     */
    public function getFavoriteGoodsCount( $where, $whereOr ) {
        $join = [['mgt_goods g','g.goods_id=f.goods_id']];
        $count = $this->alias('f')->join($join)->where($where)->where($whereOr)->count();
        return $count;
    }

    /**
     * 获取收藏整装商品个数
     * @param $where
     * @param $whereOr
     * @return mixed
     */
    public function getFavoriteLogsGoodsCount( $where, $whereOr ) {
        $join = [['mgt_logs_decoration_goods g','g.id=f.logs_id']];
        $count = $this->alias('f')->join($join)->where($where)->where($whereOr)->count();
        return $count;
    }

    /**
     * 获取收藏设计作品列表
     * @param $where
     * @param $whereOr
     * @param $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getFavoriteProductionList( $where, $whereOr, $field,$search_name='' ) {
        $join = [['mgt_designer_production g','g.production_id=f.production_id']];
		$paginate_query=['query' => ['search'=>$search_name] ];
        $list = $this->field($field)->alias('f')->join($join)->where($where)->where($whereOr)->paginate(8,false,$paginate_query);
        foreach( $list as $k=>$v ){
            $list[$k]['cover'] = json_decode($v['imgs'])[0];
        }
        return $list;
    }

    /**
     * 获取收藏整装商品个数
     * @param $where
     * @param $whereOr
     * @return mixed
     */
    public function getFavoriteProductionCount( $where, $whereOr ) {
        $join = [['mgt_designer_production g','g.production_id=f.production_id']];
        $count = $this->alias('f')->join($join)->where($where)->where($whereOr)->count();
        return $count;
    }

}
