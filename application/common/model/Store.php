<?php
/**
 * 订单模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-12-02 10:08
 */

namespace app\common\model;
use think\Model;
use think\Db;

class Store extends Model
{

    /**
     * 查询店铺信息
     *
     * @param array $condition 查询条件
     * @return array
     */
    public function getStoreInfo($condition) {
        $store_info = $this->where($condition)->find()->toArray();

        if(!empty($store_info))
        {
            //售前售后客服信息 暂不使用
            //if(!empty($store_info['store_presales'])) $store_info['store_presales'] = unserialize($store_info['store_presales']);
            //if(!empty($store_info['store_aftersales'])) $store_info['store_aftersales'] = unserialize($store_info['store_aftersales']);

            //商品数
            //$model_goods = new Goods();
            //$store_info['goods_count'] = $model_goods->getGoodsCommonOnlineCount(array('store_id' => $store_info['store_id']));
        }
        return $store_info;
    }

    /**
     * 查询店铺列表
     *
     * @param array $condition 查询条件
     * @param int $page 分页数
     * @param string $order 排序
     * @param string $field 字段
     * @param string $limit 取多少条
     * @return array
     */
    public function getStoreList($condition, $page = null, $order = '', $field = '*', $limit = '') {
        $result = $this->where($condition)->field($field)->order($order)->limit($limit)->page($page)->select()->toArray();
        return $result;
    }

}