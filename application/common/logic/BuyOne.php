<?php
/**
 * 购买行为
 *
 * @copyright  Copyright (c) 2007-2014 CHShop Inc. (http://www.changhong.com.cn)
 * @license    http://www.changhong.com.cn
 * @link       http://www.changhong.com.cn
 * @since      File available since Release v1.1
 */
namespace app\common\logic;
use app\common\model\GoodsSku;
use app\common\model\Cart;
use think\db;

class BuyOne {

    /**
     * 取得商品最新的属性[购物车]
     * @param $cartList 购物车列表
     * @param int $type 0=>只读购物车中出售的商品 1=>读取购物车中所有商品
     * @return array
     */
	public function getGoodsCartList($cartList, $type = 0) {
        if( $type == 1) {//购物车列表显示
            $cartList = $this->_getAllCartList($cartList);
        } else {//购物车确认订单
            $cartList = $this->_getCartList($cartList);
        }
		return $cartList;
	}
	/**
	 * 取得商品最新的属性及促销[立即购买]
	 * @param int $goods_id
	 * @param int $quantity
	 * @return array
	 */
	public function getGoodsOnlineInfo($goodsSku,$quantity) {
		$goodsInfo = $this->_getGoodsOnlineInfo($goodsSku,$quantity);
		return $goodsInfo;
	}
	/**
	 * 商品金额计算(分别对每个商品/优惠套装小计、每个店铺小计)
	 * @param unknown $store_cart_list 以店铺ID分组的购物车商品信息
	 * @return array
	 */
	public function calcCartList($storeCartList) {
		if (empty($storeCartList) || !is_array($storeCartList)) return array($storeCartList,array(),0);
		//存放每个店铺的商品总金额
		$storeGoodsTotal = array();
		$tmpAmount = 0;
		foreach ($storeCartList as $storeId => $storeCart) {
			foreach ($storeCart as $key => $cartInfo) {
				$storeCart[$key]['goods_total'] = ncPriceFormat($cartInfo['goods_price'] * $cartInfo['goods_number']);
				$tmpAmount += $storeCart[$key]['goods_total'];
			}
			$storeCartList[$storeId] = $storeCart;
			$storeGoodsTotal[$storeId] = ncPriceFormat($tmpAmount);
		}
		return array($storeCartList,$storeGoodsTotal);
	}

	/**
	 * 追加赠品到下单列表,并更新购买数量
	 * @param array $store_cart_list 购买列表
	 */
	public function appendPremiumsToCartList($store_cart_list, $member_id)
	{
		if (empty($store_cart_list)) return array();

		//取得每种商品的库存
		$goods_storage_quantity = $this->_getEachGoodsStorageQuantity($store_cart_list);

		//取得每种商品的购买量
		$goods_buy_quantity = $this->_getEachGoodsBuyQuantity($store_cart_list);

		foreach ($goods_buy_quantity as $goods_id => $quantity) {
			$goods_storage_quantity[$goods_id] -= $quantity;
			//商品库存不足，请重购买
			if ($goods_storage_quantity[$goods_id] < 0) return false;
		}

		return array($store_cart_list,$goods_buy_quantity);
	}
	/**
	 * 生成支付单编号(两位随机 + 从2000-01-01 00:00:00 到现在的秒数+微秒+会员ID%1000)，该值会传给第三方支付接口
	 * 长度 =2位 + 10位 + 3位 + 3位  = 18位
	 * 1000个会员同一微秒提订单，重复机率为1/100
	 * @return string
	 */
	public function makePaySn($member_id) {
		return mt_rand(10,99)
		. sprintf('%010d',time() - 946656000)
		. sprintf('%03d', (float) microtime() * 1000)
		. substr($member_id,-3);
	}

	/**
	 * 取得收货人地址信息
	 * @param array $address_info
	 * @return array
	 */
	public function getReciverAddr($address_info = array())
	{
		$reciver_info['phone'] = trim($address_info['mob_phone'].($address_info['tel_phone'] ? ','.$address_info['tel_phone'] : null),',');
		$reciver_info['mob_phone'] = $address_info['mob_phone'];
		$reciver_info['tel_phone'] = $address_info['tel_phone'];
		$reciver_info['address'] = $address_info['area_info'].' '.$address_info['address'];
		$reciver_info['area'] = $address_info['area_info'];
		$reciver_info['street'] = $address_info['address'];
		$reciver_info = serialize($reciver_info);
		$reciver_name = $address_info['true_name'];
		return array($reciver_info, $reciver_name);
	}

	/**
	 * 计算本次下单中每个店铺订单是货到付款还是线上支付,店铺ID=>付款方式[online在线支付offline货到付款]
	 * @param array $store_id_array 店铺ID数组
	 * @param boolean $if_offpay 是否支持货到付款 true/false
	 * @param string $pay_name 付款方式 online/offline
	 * @return array
	 */
	public function getStorePayTypeList($store_id_array)
	{
		$store_pay_type_list = array();
		foreach ($store_id_array as $store_id) {
			$store_pay_type_list[$store_id] = 'online';
		}
		return $store_pay_type_list;
	}

	/**
	 * 订单编号生成规则，n(n>=1)个订单表对应一个支付表，
	 * 生成订单编号(年取1位 + $pay_id取13位 + 第N个子订单取2位)
	 * 1000个会员同一微秒提订单，重复机率为1/100
	 * @param $pay_id 支付表自增ID
	 * @return string
	 */
	public function makeOrderSn($pay_id) {
		//记录生成子订单的个数，如果生成多个子订单，该值会累加
		static $num;
		if (empty($num)) {
			$num = 1;
		} else {
			$num ++;
		}
		return (date('y',time()) % 9+1) . sprintf('%013d', $pay_id) . sprintf('%02d', $num);
	}


	/**
	 * 直接购买时返回最新的在售商品信息（需要在售）
	 *
	 * @param int $goods_id 所购商品ID
	 * @param int $quantity 购买数量
	 * @return array
	 */
	private function _getGoodsOnlineInfo($goodsSku,$quantity) {
		$modelGoods = new GoodsSku();
		//取目前在售商品
		$goodsInfo = $modelGoods->getInfoBySku($goodsSku, true);
		if(empty($goodsInfo)) return null;
		$newArray = array();
		$newArray['goods_name']			= $goodsInfo['goods_name'];
		$newArray['goods_number'] 			= $quantity;
		$newArray['goods_id'] 			= $goodsInfo['goods_id'];
		$newArray['store_id'] 			= $goodsInfo['store_id'];
		$newArray['store_name'] 	= (!array_key_exists('store_name',$goodsInfo)) ? '-' : $goodsInfo['store_name'];
		$newArray['category_id']		= $goodsInfo['category_id'];
		$newArray['goods_name'] 		= $goodsInfo['goods_name'];
		$newArray['goods_price'] 		= $goodsInfo['goods_price'];
		$newArray['store_id'] 			= $goodsInfo['store_id'];
		$newArray['goods_image_main'] 	= $goodsInfo['goods_image_main'];
		$newArray['goods_storage'] 		= $goodsInfo['goods_storage'];
		$newArray['goods_storage_alarm'] = $goodsInfo['goods_storage_alarm'];
		$newArray['state'] 				= true;
		$newArray['storage_state'] 		= intval($goodsInfo['goods_storage']) < intval($quantity) ? false : true;
		$newArray['cart_id'] 			= $goodsInfo['goods_sku'];
		$newArray['goods_sku'] 			= $goodsInfo['goods_sku'];
		$newArray['sku_name'] = $goodsInfo['sku_name'];
		$newArray['group_id']			= $goodsInfo['group_id'];
		$newArray['feature']			= $goodsInfo['feature'];
        $newArray['goods_type']		= $goodsInfo['goods_type'];
		return $newArray;
	}
	/**
	 * 取得每种商品的库存
	 * @param array $store_cart_list 购买列表
	 * @return array 商品ID=>库存
	 */
	private function _getEachGoodsStorageQuantity($store_cart_list) {
		if(empty($store_cart_list) || !is_array($store_cart_list)) return array();
		$goods_storage_quangity = array();
		foreach ($store_cart_list as $store_cart) {
			foreach ($store_cart as $cart_info) {
				$goods_storage_quangity[$cart_info['goods_sku']] = $cart_info['goods_storage'];
			}
		}

		return $goods_storage_quangity;
	}

	/**
	 * 取得每种商品的购买量
	 * @param array $store_cart_list 购买列表
	 * @return array 商品ID=>购买数量
	 */
	private function _getEachGoodsBuyQuantity($store_cart_list) {
		if(empty($store_cart_list) || !is_array($store_cart_list)) return array();
		$goods_buy_quangity = array();
		foreach ($store_cart_list as $store_cart) {
			foreach ($store_cart as $cart_info) {
				if(!array_key_exists($cart_info['goods_sku'],$goods_buy_quangity)){
					$goods_buy_quangity[$cart_info['goods_sku']] = 0;
				}
				$goods_buy_quangity[$cart_info['goods_sku']] += $cart_info['goods_number'];
			}
		}
		return $goods_buy_quangity;
	}

	/**
	 * 得到所购买的id和数量
	 *
	 */
	private function _parseItems($cartId) {
		//存放所购商品ID和数量组成的键值对
		$buyItems = array();
		if (is_array($cartId)) {
			foreach ($cartId as $value) {
				if (preg_match_all('/^(\d{1,10})\|(\d{1,6})$/', $value, $match)) {
					$buyItems[$match[1][0]] = $match[2][0];
				}
			}
		}
		return $buyItems;
	}

	/**
	 * 下单确认购物车中商品信息处理
	 * @param $cartList  购物车列表
	 * @return array
	 */
    protected function _getCartList( $cartList ) {
        if (empty($cartList) || !is_array($cartList)) return $cartList;
        //通过sku获取商品信息
        $goodsSku = new GoodsSku();
        foreach ( $cartList as $key => $val ){
            $goodsInfo = $goodsSku->getInfoBySku($val['goods_sku'], true);//缓存读取数据
            if( !empty($goodsInfo) ) {//商品正常
                $cartList[$key]['category_id'] = $goodsInfo['category_id'];
                $cartList[$key]['goods_id'] = $goodsInfo['goods_id'];
                $cartList[$key]['goods_price'] = $goodsInfo['goods_price'];
                $cartList[$key]['goods_storage'] = $goodsInfo['goods_storage'];
                $cartList[$key]['group_id'] = $goodsInfo['group_id'];
                $cartList[$key]['store_id'] = $goodsInfo['store_id'];
                $cartList[$key]['goods_storage_alarm'] = $goodsInfo['goods_storage_alarm'];
                $cartList[$key]['goods_name'] = $goodsInfo['goods_name'];
                $cartList[$key]['goods_verify'] = $goodsInfo['goods_verify'];
                $cartList[$key]['goods_image_main'] = $goodsInfo['goods_image_main'];
				$cartList[$key]['sku_name'] = $goodsInfo['sku_name'];
                $cartList[$key]['goods_type'] = $goodsInfo['goods_type'];
                $cartList[$key]['feature'] = $goodsInfo['feature'];
				$cartList[$key]['goods_sku'] 			= $goodsInfo['goods_sku'];
                $cartList[$key]['store_name'] = (!array_key_exists('store_name',$goodsInfo)) ? '-' : $goodsInfo['store_name'];
                $cartList[$key]['state'] = 1;
				$cartList[$key]['cart_id'] 			= $val['cart_id'];
                $cartList[$key]['storage_state'] = intval($goodsInfo['goods_storage']) < intval($cartList[$key]['goods_number']) ? false : true;
            } else {//商品不正常（下架，不存在，删除等）
                $cartList[$key]['state'] = 0;
            }
        }
        return $cartList;
    }

    /**
     * 购物车列表中商品信息处理
     * @param $cartList  购物车列表
     * @return array
     */
    protected function _getAllCartList( $cartList ) {
        if (empty($cartList) || !is_array($cartList)) return $cartList;
        //通过sku获取商品信息
        $goodsSku = new GoodsSku();
        $cartModel= new Cart();
        foreach ( $cartList as $key => $val ){
            $goodsInfo = $goodsSku->getInfoBySku($val['goods_sku']);//缓存读取数据
            if( !$goodsInfo || empty($goodsInfo) ) {
                unset($cartList[$key]);
                continue;
            }
            //update by laijunliang at 2017/02/08, 判定库存小于购物车数量，则修改购物车数量
            if ( $val['goods_number'] > $goodsInfo['goods_storage'] ) {
            	//将购物车的商品数量设置为最大的商品库存
                $cartModel->where('cart_id', $val['cart_id'])->update( [ 'goods_number' => $goodsInfo['goods_storage'] ] );
                $cartList[$key]['goods_number'] = $goodsInfo['goods_storage'];
            }
            //update by laijunliang at 2017/02/15读取规格从缓存中读取
            if ( trim( $goodsInfo['sku_name'] ) != '' ) {
            	$featureList = json_decode( $goodsInfo['sku_name'], true );
            	$featureInfo = array();
            	foreach ($featureList as $feature) {
            		array_push($featureInfo, array(
            			'feature' => $feature[0],
            			'feature_value' => $feature[1],
            		));
            	}
            	$cartList[$key]['feature'] = $featureInfo;
            } else {
                $cartList[$key]['feature'] = array();
            }
            $cartList[$key]['category_id'] = $goodsInfo['category_id'];
            $cartList[$key]['goods_id'] = $goodsInfo['goods_id'];
            $cartList[$key]['goods_price'] = $goodsInfo['goods_price'];
            $cartList[$key]['goods_storage'] = $goodsInfo['goods_storage'];
            $cartList[$key]['group_id'] = $goodsInfo['group_id'];
            $cartList[$key]['store_id'] = $goodsInfo['store_id'];
            $cartList[$key]['goods_storage_alarm'] = $goodsInfo['goods_storage_alarm'];
            $cartList[$key]['goods_name'] = $goodsInfo['goods_name'];
            $cartList[$key]['goods_verify'] = $goodsInfo['goods_verify'];
            $cartList[$key]['goods_image_main'] = $goodsInfo['goods_image_main'];
            $cartList[$key]['sku_name'] = $goodsInfo['sku_name'];
			$cartList[$key]['goods_sku'] 			= $goodsInfo['goods_sku'];
            $cartList[$key]['feature'] = $goodsInfo['feature'];
            $cartList[$key]['store_name'] = (!array_key_exists('store_name',$goodsInfo)) ? '-' : $goodsInfo['store_name'];
            $cartList[$key]['cart_id'] 			= $val['cart_id'];
            $cartList[$key]['goods_type'] = $goodsInfo['goods_type'];
            //添加是否删除字段 罗婷
            $cartList[$key]['is_delete'] = $goodsInfo['is_delete'];
            $cartList[$key]['storage_state'] = intval($goodsInfo['goods_storage']) < intval($cartList[$key]['goods_number']) ? false : true;
            if( $goodsInfo['is_delete'] == 0 && $goodsInfo['goods_verify'] == 1 && $goodsInfo['goods_storage'] > 0)
                $cartList[$key]['state'] = 1;
            else
                $cartList[$key]['state'] = 0;
        }
        return $cartList;
    }
	/**
	 * 格式化特征和特征值
	 * @param array $data 传入的数组，里面含有feature_id和featur_value_id一对一关系
	 * @return array $featureList 格式化的结果
	 */
	private function formatFeature( $data ) {
		$featureModel      = new Features();
		$featureValueModel = new FeaturesValue();
		$featureList   = array();
		$featureIdList = array();
		$featureValueIdList = array();
		foreach ($data as $key => $feature) {
			//录入id
			if ( !in_array( $feature['feature_id'] , $featureIdList) ) {
				array_push($featureIdList,$feature['feature_id']);
			}
			//录入特征值id
			array_push($featureValueIdList,$feature['feature_value_id']);
		}
		//进行特征和特征值的查询
		if ( count( $featureIdList ) > 0 ) {
			//特征查询
			$where       = array( 'feature_id' => array( 'in',  $featureIdList) );
			$featureList = $featureModel->field('feature_id,attribute_name,is_color')->where( $where )->order('sort asc')->select();
			//特征值查询
			$where       = array( 'id' => array( 'in', $featureValueIdList ) );
			$featureValueList = $featureValueModel->field('id,feature_id,feature_value')->where( $where )->order('sort asc')->select();
			$featureDes = array();
			//拼接特征和特征值
			foreach ($featureList as $key => $feature) {
				$feature = $feature->toArray();
				foreach ($featureValueList as $tag => $featureValue) {
					if ( $feature['feature_id'] == $featureValue['feature_id'] ) {
						$featureDes[$feature['attribute_name']] =  $featureValue['feature_value'];
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

