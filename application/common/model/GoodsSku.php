<?php
/**
 * 商品销售库存模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-12-02 10:08
 */

namespace app\common\model;
use think\Cache;
use think\Model;
use think\Db;
use app\common\model\GoodsSaleFeature;
class GoodsSku extends Model
{

	const STATE1 = 1;       // 出售中
	const STATE0 = 0;       // 下架
	const STATE10 = 3;     // 违规
	const VERIFY1 = 1;      // 审核通过
	const VERIFY0 = 0;      // 审核失败
	const VERIFY10 = 3;    // 等待审核
	const DELETE = 0;
	/*
     * 销售属性
     * */
	public $GoodsSaleFeature=null;

	/*
	 * 获取某个商品的一系列的销售熟悉
	 * */
	public function getSaleFeature()
	{
		$GoodsSaleFeature_Model = new GoodsSaleFeature();
		$this->GoodsSaleFeature = $GoodsSaleFeature_Model->where('group_id',$this['group_id'])->select();
		Model::callByResult($this->GoodsSaleFeature,'getFName');
	}


	public function getOnlineInfoBySku($goodsSku)
	{
		$goodsInfo = $this->_rGoodsCache($goodsSku);
		if(empty($goodsInfo)){
			$goodsObj = $this->_getGoodsInfo(array('goods_sku' => $goodsSku));
			$goodsInfo = $goodsObj->toArray();
			$this->_wGoodsCache($goodsSku, $goodsInfo);
		}
		if (empty($goodsInfo) || $goodsInfo['goods_verify'] != self::VERIFY1 || $goodsInfo['is_delete'] != self::DELETE) {
			return array();
		}
		return $goodsInfo;
	}

    /**
     * 获取商品信息
     * @param $goodsSku
     * @param $online 是否获取出售商品
     * @return array|mixed
     */
    public function getInfoBySku($goodsSku, $online = false)
    {
        $goodsInfo = $this->_rGoodsCache($goodsSku);
        if(empty($goodsInfo)){
            $goodsObj = $this->_getGoodsInfo(array('goods_sku' => $goodsSku));
			if(!empty($goodsObj) && is_object($goodsObj)){
				$goodsInfo = $goodsObj->toArray();
				$this->_wGoodsCache($goodsSku, $goodsInfo);
			}
        }
        if( $online ) {//获取出售商品
            if (empty($goodsInfo) || $goodsInfo['goods_verify'] != self::VERIFY1 || $goodsInfo['is_delete'] != self::DELETE) {
                return array();
            }
        }
        return $goodsInfo;
    }

	/**
	 * 写入商品的信息的缓存
	 * @param $goodsSku   string   商品的sku码
	 * @param $goodsInfo  array    商品详细信息
	 */
	private function _wGoodsCache($goodsSku,$goodsInfo)
	{
		//缓存区域
		return Cache::set($goodsSku, $goodsInfo);
	}

	/**
	 * 更新商品SUK数据
	 * @param array $update
	 * @param int|array $goodsid_array
	 * @return boolean|unknown
	 */
	public function editGoodsBySKU($update, $goodssku_array) {
		if (empty($goodssku_array))  return true;
		$condition['goods_sku'] = array('in', $goodssku_array);
		$update['goods_edittime'] = time();
		$result = Db::name('goods_sku')->where($condition)->update($update);
		if ($result) {
			foreach ((array)$goodssku_array as $value) {
				$this->_dGoodsCache($value);
			}
		}
		return $result;
	}

	/**
	 * 删除商品的缓存信息
	 * @param $goodsSku
	 * @param string $fields
	 */
	private function _dGoodsCache($goodsSku)
	{
		return Cache::rm($goodsSku);
	}

	/**
	 * 读取商品的缓存信息
	 * @param $goodsSku
	 * @param string $fields
	 */
	private function _rGoodsCache($goodsSku)
	{
		return Cache::get($goodsSku);
	}

	/**
	 * @param $condition   array  查询条件
	 * @return array|false|\PDOStatement|string|Model
	 */
	private function _getGoodsInfo($condition){
		$fields = 'goods_name,gs.goods_storage_alarm as goods_storage_alarm,gs.goods_market_price as goods_market_price,goods_type,goods_sku,goods_verify,is_delete,category_id,g.goods_id as goods_id,store_id,gs.goods_price as goods_price,goods_image_main,gs.goods_storage as goods_storage,group_id,store_name,sku_name';
		$join = [['mgt_goods_sku gs','g.goods_id=gs.goods_id']];
		$goodsInfo = $this->db()->name('goods')->field($fields)->alias('g')->join($join)->where($condition)->find();
		//查询商品信息
		$where = array('group_id' =>$goodsInfo['group_id']);
		$saleFeatureList = $this->db()->name('goods_sale_feature')->where( $where )->field('feature_id,feature_value_id')->select();
		$goodsInfo['feature'] = $this->formatFeature($saleFeatureList);
		return $goodsInfo;

	}

	/**
	 * 格式化特征和特征值
	 * @param array $data 传入的数组，里面含有feature_id和featur_value_id一对一关系
	 * @return array $featureList 格式化的结果
	 */
	private function formatFeature( $data ) {
		$featureList   = array();
		$featureIdList = array();
		$featureValueIdList = array();
		foreach ($data as $key => $feature) {
			//录入id
			if ( !in_array( $feature['feature_id'] , $featureIdList) ) {
				array_push($featureIdList, $feature['feature_id']);
			}
			//录入特征值id
			array_push($featureValueIdList, $feature['feature_value_id']);
		}
		//进行特征和特征值的查询
		$featureDes = array();
		if ( count( $featureIdList ) > 0 ) {
			//特征查询
			$where       = array( 'feature_id' => array( 'in',  $featureIdList) );
			$featureList = DB::name('features')->field('feature_id,attribute_name,is_color')->where( $where )->order('sort asc')->select();
			//特征值查询
			$where       = array( 'id' => array( 'in', $featureValueIdList ) );
			$featureValueList = DB::name('features_value')->field('id,feature_id,feature_value')->where( $where )->order('sort asc')->select();
			//拼接特征和特征值
			foreach ($featureList as $key => $feature) {
				foreach ($featureValueList as $tag => $featureValue) {
					if ( $feature['feature_id'] == $featureValue['feature_id'] ) {
						$featureDes[] = array('feature'=>$feature['attribute_name'],'feature_value'=>$featureValue['feature_value']);
					}
				}
			}
		}
		unset($featureList);
		unset($featureIdList);
		unset($featureValueIdList);
		unset($featureValueList);
		return $featureDes;
	}

}
