<?php
/**
 * 众筹商品模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/9  15:49
 */
namespace app\seller\model;

use think\Cache;
use think\Model;

class CrowdfundingGoods extends Model
{

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
     * 添加一条众筹商品
     * @param $data array
     * @return int id
     */
    public function addCrowdfundingGoods($data){
        return $this->insertGetId($data);
    }


    /**
     * 获取一条众筹商品
     * @param $id int
     * @return array
     */
    public function getCrowdfundingGoods($id){
        if(!$id){
            return array();
        }
        $res = $this->_rGoodsCache($id);
        if(!$res){
            $result = $this->where("id = '".$id."'")->find();
            $this->_wGoodsCache($id,$result);
            return $result;
        }

        return $res;
    }


    /**
     * 保存一条众筹商品
     * @param $data array
     * @param $where array
     * @return bool
     */
    public function saveCrowdfundingGoods($data,$where){
        if(!$data){
            return false;
        }
        $res = $this->save($data,$where);
        if($res){
            $this->_dGoodsCache($where['id']);
        }
        return $res;
    }


    /**
     * 删除一条商品
     * @param $where array
     * @return bool
     */
    public function deleteCrowdfundingGoods($where){
        if(!$where){
            return false;
        }
        $this->_dGoodsCache($where['id']);
        return $this->save(array('is_delete'=>1),$where);
    }


    /**
     * 审核通过一条商品
     * @param $where array
     * @param $data array
     * @return bool
     */
    public function checkCrowdfundingGoods($where,$data){
        if(!$where || !$data){
            return false;
        }
        $this->_dGoodsCache($where['id']);
        $data['sell_time'] = time();
        return $this->save($data,$where);
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

}