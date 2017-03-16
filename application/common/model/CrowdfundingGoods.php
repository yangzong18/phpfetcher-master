<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/15  14:35
 */
namespace app\common\model;

use think\Cache;
use think\Model;

class CrowdfundingGoods extends Model
{

    const VERIFY1 = 1;      // 审核通过
    const VERIFY0 = 0;      // 审核失败
    const VERIFY10 = 3;    // 等待审核
    const DELETE = 0;

    const GOODS_STATE_OFF = 0;    //下架中
    const GOODS_STATE_PRE = 1;    //预热中
    const GOODS_STATE_SEND= 2;    //众筹中
    const GOODS_STATE_SUCCESS = 3;    //筹款成功
    const GOODS_STATE_RETURN  = 4;    //回报中
    const GOODS_STATE_PROJECT_SUCCESS = 5;    //项目成功
    const GOODS_STATE_FAIL  = 6;    //筹款失败
    const GOODS_STATE_PROJECT_FAIL = 7;    //项目失败

    //众筹商品状态描述
    public static  $goodsStatus = array(
        self::GOODS_STATE_OFF   => '下架中',
        self::GOODS_STATE_PRE   => '预热中',
        self::GOODS_STATE_SEND  => '众筹中',
        self::GOODS_STATE_SUCCESS => '筹款成功',
        self::GOODS_STATE_RETURN  => '回报中',
        self::GOODS_STATE_PROJECT_SUCCESS => '项目成功',
        self::GOODS_STATE_FAIL  =>'筹款失败',
        self::GOODS_STATE_PROJECT_FAIL =>'项目失败'
    );

    /**
     * 获取商品信息
     */
    public function getGoodsById($goodsId,$online){

        $goodsInfo = $this->_rGoodsCache($goodsId);

        if(empty($goodsInfo)){
            $goodsObj = $this->_getGoodsInfo(array('id' => $goodsId));
            $goodsInfo = $goodsObj->toArray();

            $this->_wGoodsCache($goodsId, $goodsInfo);
        }

        if( $online ) {//获取出售商品
            if (empty($goodsInfo) || $goodsInfo['verify'] != self::VERIFY1 || $goodsInfo['is_delete'] != self::DELETE) {
                return array();
            }
        }

        return $goodsInfo;
    }


    /**
     * 下单变更库存销量
     * @param $id int
     * @param $number int
     * @return array
     */
    public function createOrderUpdateStorage($id,$number)
    {
        $where['id'] = $id;
        $condition['sale_number'] = array('exp','sale_number+'.$number);

        $result = $this->where($where)->update($condition);

        if (!$result) {
            return callback(false,'变更商品库存与销量失败');
        } else {
            //如果更新成功则删除缓存
            $this->_dGoodsCache($id);
            return callback(true);
        }
    }


    /**
     * 取消订单变更库存销量
     * @param $id int
     * @param $number int
     * @return array
     */
    public function cancelOrderUpdateStorage($id,$number)
    {
        $where['id'] = $id;
        $condition['sale_number'] = array('exp','sale_number-'.$number);

        $result = $this->where($where)->update($condition);

        if (!$result) {
            return callback(false,'变更商品库存与销量失败');
        } else {
            //如果更新成功则删除缓存
            $this->_dGoodsCache($id);
            return callback(true);
        }
    }



    /**
     * @param $condition   array  查询条件
     * @return array|false|\PDOStatement|string|Model
     */
    private function _getGoodsInfo($condition){

        return $this->where($condition)->find();

    }


    /**
     * 删除商品的缓存信息
     * @param $goodsId string
     * @return bool
     */
    private function _dGoodsCache($goodsId)
    {
        $goodsId = 'crowdfunding_'.$goodsId;
        return Cache::rm($goodsId);
    }

    /**
     * 读取商品的缓存信息
     * @param $goodsId
     * @return bool
     */
    private function _rGoodsCache($goodsId)
    {
        $goodsId = 'crowdfunding_'.$goodsId;
        return Cache::get($goodsId);
    }


    /**
     * 写入商品的信息的缓存
     * @param $goodsId   string   商品的ID
     * @param $goodsInfo  array    商品详细信息
     * @return bool
     */
    private function _wGoodsCache($goodsId,$goodsInfo)
    {
        $goodsId = 'crowdfunding_'.$goodsId;
        //缓存区域
        return Cache::set($goodsId, $goodsInfo);
    }

	public function getcount($where){
		return $this->where($where)->count('id');
	}
}
