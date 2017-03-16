<?php
/**
 * 普通商品购买逻辑
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 
 */
namespace app\common\logic;
use app\common\logic\BuyOne;
use app\common\model\DeliveryAddress;
use app\common\model\Goods;
use app\common\model\LogsDecorationOrder;
use app\common\model\Order;
use think\Config;
use think\db;
use think\Exception;
use think\Cookie;
class Buy {
	/**
	 * 会员信息
	 * @var array
	 */
	private $_member_info = array();

	/**
	 * 下单数据
	 * @var array
	 */
	private $_order_data = array();

	/**
	 * 表单数据
	 * @var array
	 */
	private $_post_data = array();

	/**
	 * buy_1.logic 对象
	 * @var obj
	 */
	private $_logic_buy_1;

	public function __construct() {
		$this->_logic_buy_1 = new BuyOne();
	}
    /**
     * 生成订单的逻辑
     * @param array $member 用户信息
     * @param int   $cart 1代表来自购物车,2代表来自立即购买
     * @param array $data 前端传递的参数
     * @return int $result 购买的最终状态
     */
    public function order( $member, $cart, $data ) {
        //参数设定
        $this->member = $member;
        $this->cart   = $cart;
        $this->data   = $data;
        //第一步,参数验证
        $this->verification();
    }

    /**
     * 参数验证，并将数据格式化
     */

	public function buyStep1($cartId,$ifCart,$memberId){
		//得到购买商品信息
		if ($ifCart) {
			$result = $this->getCartList($cartId, $memberId);
		} else {
			$result = $this->getGoodsList($cartId, $memberId);
		}
		if(!$result['state']) {
			return $result;
		}
		//得到页面所需要数据：商品列表等信息
		$result = $this->getBuyStepData($result['data']);
		return $result;
	}

	/**
	 * 购买第二步
	 * @param array $post 提交的订单数据
	 * @param int $member_id 用户ID
	 * @param string $member_name 用户账号
	 * @param string $member_email 用户邮箱
	 * @return array
	 */
	public function buyStep2($post, $member_id, $member_name, $member_email)
	{
		$this->_member_info['member_id'] = $member_id;
		$this->_member_info['member_name'] = $member_name;
		$this->_member_info['member_email'] = $member_email;
		$this->_post_data = $post;
		try {

			// 启动事务
			Db::startTrans();

			//第1步 表单验证
			$this->_createOrderStep1();

			//第2步 得到购买商品信息
			$this->_createOrderStep2();

			//第3步 得到购买相关金额计算等信息
			$this->_createOrderStep3();

			//第4步 生成订单
			$this->_createOrderStep4();

			//第5步 订单后续处理
			$this->_createOrderStep5();

			// 提交事务
			Db::commit();

			return callback(true,'',$this->_order_data);
		}catch (Exception $e){
			// 回滚事务
			Db::rollback();
			return callback(false, $e->getMessage());
		}

	}

	/**
	 * 订单后续其它处理
	 *
	 */
	private function _createOrderStep5()
	{
		$ifcart = $this->_post_data['ifcart'];
		$goods_buy_quantity = $this->_order_data['goods_buy_quantity'];
		$input_buy_items = $this->_order_data['input_buy_items'];
		$goods_list = $this->_order_data['goods_list'];

		//为保证数据准确，不使用队列
		//变更库存和销量
		$queue_obj = new Queue();
		$result = $queue_obj->createOrderUpdateStorage($goods_buy_quantity);
		if (!$result['state'])  throw new Exception('订单保存失败[商品信息已更改，请重新购买]');

		//更新总库存数量
		$goods_id_arr = array();
		foreach($goods_list as $rt) {
			if(!array_key_exists($rt['goods_id'],$goods_id_arr)){
				$goods_id_arr[$rt['goods_id']]=0;
			}
			$goods_id_arr[$rt['goods_id']]+=$goods_buy_quantity[$rt['goods_sku']];
		}

		$goods_model = new Goods();
		$boolt = $goods_model->changeAllKCNumber($goods_id_arr);
		if(!$boolt) throw new Exception('订单保存失败[变更总库存失败]');

		//删除购物车中的商品
		$rs = $this->delCart($ifcart,$this->_member_info['member_id'],array_keys($input_buy_items));
		if(!$rs['state']){
			throw new Exception('删除购物车失败: '.$rs['msg']);
		}
		Cookie::set('cart_goods_num','',['prefix'=>'think_','expire'=>-3600]);

	}

	/**
	 * 删除购物车商品
	 * @param unknown $ifcart
	 * @param unknown $cart_ids
	 */
	public function delCart($ifcart, $member_id, $cart_ids) {
		if (!$ifcart || !is_array($cart_ids)) return callback(true);
		$queue_obj = new Queue();
		return $queue_obj->delCart(array('member_id'=>$member_id,'cart_ids'=>$cart_ids));
	}

	/**
	 * 生成订单
	 * @param array $input
	 * @throws Exception
	 * @return array array(支付单sn,订单列表)
	 */
	private function _createOrderStep4()
	{

		extract($this->_order_data);

		$member_id = $this->_member_info['member_id'];
		$member_name = $this->_member_info['member_name'];
		$member_email = $this->_member_info['member_email'];


		//订单数据模型
		$model_order = new Order();

		//存储生成的订单数据
		$order_list = array();

		//每个店铺订单是货到付款还是线上支付,店铺ID=>付款方式[在线支付/货到付款]
		$store_pay_type_list    = $this->_logic_buy_1->getStorePayTypeList(array_keys($store_cart_list));

		//产生付款单号
		$pay_sn = $this->_logic_buy_1->makePaySn($member_id);
		$order_pay = array();
		$order_pay['pay_sn'] = $pay_sn;
		$order_pay['member_id'] = $member_id;
		$order_pay_id = $model_order->addOrderPay($order_pay);
		if (!intval($order_pay_id))  throw new Exception('订单保存失败[未生成支付单]');

		//收货人信息
		list($reciver_info,$reciver_name) = $this->_logic_buy_1->getReciverAddr($input_address_info);

		foreach ($store_cart_list as $store_id => $goods_list)
		{
			$order = array();
			$order_common = array();
			$order_goods = array();
			$order_sn = $this->_logic_buy_1->makeOrderSn($order_pay_id);
			$order['order_sn'] = $order_sn;
			$order['pay_sn'] = $pay_sn;
			$order['store_id'] = $store_id;
			$order['store_name'] = $goods_list[0]['store_name'];
			$order['member_id'] = $member_id;
			$order['member_name'] = $member_name;
			$order['member_email'] = $member_email;
			$order['add_time'] = time();
			$order['payment_code'] = $store_pay_type_list[$store_id];
            if( $this->_order_data['goods_list'][0]['goods_type'] == 2 ) {//假如是兑换商品下单
                $order['order_state'] = Order::ORDER_STATE_PAY;
                $order['order_type'] = 2;
                //修改整装订单状态和进度
                $updateData = ['order_status'=>LogsDecorationOrder::LOGS_ORDER_CANCEL,'speed_status'=>LogsDecorationOrder::LOGS_SPEED_EXCHANGEED];
                if( !( new LogsDecorationOrder() )->save( $updateData,['id' => intval($this->_order_data['logs_order_id'])] ) ) {
                    throw new Exception('订单保存失败[修改整装订单状态失败]');
                }
            } else {//假如是普通商品下单
                $order['order_state'] = Order::ORDER_STATE_NEW;
            }
			$order['goods_real_amount'] = $store_final_order_total[$store_id];
			$order['order_amount'] = $store_final_order_total[$store_id];
			$order['shipping_fee'] = '0.00';
			$order['goods_amount'] = $store_goods_total[$store_id];
			$order['order_device_from'] = $order_from;
			$order_id = $model_order->addOrder($order);
			if (!intval($order_id))  throw new Exception('订单保存失败[未生成订单数据]');
			$order['order_id'] = $order_id;
			$order_list[$order_id] = $order;


			$order_common['order_id'] = $order_id;
			$order_common['store_id'] = $store_id;
			$order_common['reciver_info']= $reciver_info;
			$order_common['reciver_name'] = $reciver_name;
			$order_common['reciver_city_id'] = $input_city_id;

			$order_id = $model_order->addOrderCommon($order_common);
			if (!intval($order_id))  throw new Exception('订单保存失败[未生成订单扩展数据]');

			//生成order_goods订单商品数据
			$i = 0;
			foreach ($goods_list as $goods_info)
			{
				if (!$goods_info['state'] || !$goods_info['storage_state']) {
					throw new Exception('部分商品已经下架或库存不足，请重新选择');
				}
				//如果不是优惠套装
				$order_goods[$i]['order_id'] = $order_id;
				$order_goods[$i]['goods_id'] = $goods_info['goods_id'];
				$order_goods[$i]['goods_sku'] = $goods_info['goods_sku'];
				$order_goods[$i]['store_id'] = $store_id;
				$order_goods[$i]['goods_name'] = $goods_info['goods_name'];
				$order_goods[$i]['goods_price'] = $goods_info['goods_price'];
				$order_goods[$i]['goods_num'] = $goods_info['goods_number'];
				$order_goods[$i]['goods_image'] = $goods_info['goods_image_main'];
				$order_goods[$i]['catogary_id'] = $goods_info['category_id'];
				$order_goods[$i]['goods_pay_price'] = $goods_info['goods_total'];
				$order_goods[$i]['sku_name'] = $goods_info['sku_name'];


				if((array_key_exists('remark_'.$goods_info['goods_sku'],$this->_post_data) && $this->_post_data['remark_'.$goods_info['goods_sku']]!='') ||
					(array_key_exists('phone_'.$goods_info['goods_sku'],$this->_post_data) && $this->_post_data['phone_'.$goods_info['goods_sku']]!='')){
					$remark_bz='-';
					if(!empty($this->_post_data['remark_'.$goods_info['goods_sku']])){
						$remark_bz=trim($this->_post_data['remark_'.$goods_info['goods_sku']]);
						if(mb_strlen($remark_bz,'utf-8') > 200){
							throw new Exception('字符串长度不能超过200个');
						}
					}
					$remark_phone='-';
					if(!empty($this->_post_data['phone_'.$goods_info['goods_sku']])){
						$remark_phone=trim($this->_post_data['phone_'.$goods_info['goods_sku']]);
						if(!preg_match("/^1([3-9]{1})([0-9]{1})([0-9]{8})$/",$remark_phone) && !preg_match("/^([0-9]{3,4})-([0-9]{7,9})$/",$remark_phone)){
							throw new Exception('请输入手机号码(11位数字)或座机:区号-号码的形式');
						}
					}
					$order_goods[$i]['remark'] = $remark_bz.'{}'.$remark_phone;
				}else{
					$order_goods[$i]['remark'] = '';
				}
				$i++;
			}

			$insert = $model_order->addOrderGoods($order_goods);
			if (intval($insert)<=0) throw new Exception('订单保存失败[未生成商品数据]');
		}

		//保存数据
		$this->_order_data['pay_sn'] = $pay_sn;
		$this->_order_data['order_list'] = $order_list;
	}

	/**
	 * 得到购买相关金额计算等信息
	 *
	 */
	private function _createOrderStep3()
	{
		$goods_list = $this->_order_data['goods_list'];
		$store_cart_list = $this->_order_data['store_cart_list'];

		//商品金额计算(分别对每个商品/优惠套装小计、每个店铺小计)
		list($store_cart_list,$store_goods_total) = $this->_logic_buy_1->calcCartList($store_cart_list);

		//将赠品追加到购买列表(如果库存0，则不送赠品)
		$append_premiums_to_cart_list = $this->_logic_buy_1->appendPremiumsToCartList($store_cart_list,$this->_member_info['member_id']);

		if($append_premiums_to_cart_list === false) {
			throw new Exception('抱歉，您购买的商品库存不足，请重新购买');
		} else {
			list($store_cart_list,$goods_buy_quantity) = $append_premiums_to_cart_list;
		}

		//保存数据
		$this->_order_data['store_goods_total'] = $store_goods_total;
		$this->_order_data['store_final_order_total'] = $store_goods_total;
		$this->_order_data['store_cart_list'] = $store_cart_list;
		$this->_order_data['goods_buy_quantity'] = $goods_buy_quantity;
	}

	/**
	 * 得到购买商品信息
	 *
	 */
	private function _createOrderStep2()
	{
		$post = $this->_post_data;
		$input_buy_items = $this->_order_data['input_buy_items'];

		if ($post['ifcart']) {//购物车购买
			$result = $this->getCartList($this->_post_data['cart_id'],$this->_member_info['member_id']);
			if(!$result['state']) throw new Exception($result['msg']);
			$store_cart_list = $result['data']['store_cart_list'];
			$goods_list = $result['data']['goods_list'];
		}else{//立即购买

			//来源于直接购买
			$goods_id = key($input_buy_items);
			$quantity = current($input_buy_items);

			//商品信息[得到最新商品属性及促销信息]
			$goods_info = $this->_logic_buy_1->getGoodsOnlineInfo($goods_id,intval($quantity));
			if(empty($goods_info))  throw new Exception('商品已下架或不存在');

			//进一步处理数组
			$store_cart_list = array();
			$goods_list = array();
			$goods_list[0] = $store_cart_list[$goods_info['store_id']][0] = $goods_info;
		}

		//保存数据
		$this->_order_data['goods_list'] = $goods_list;
		$this->_order_data['store_cart_list'] = $store_cart_list;
	}

	/**
	 * 订单生成前的表单验证与处理
	 *
	 */
	private function _createOrderStep1()
	{
		$post = $this->_post_data;

		//取得商品ID和购买数量
		$input_buy_items = $this->_parseItems($post['cart_id']);
		if (empty($input_buy_items))  throw new Exception('所购商品无效或库存不足');

		//验证收货地址
		$input_address_id = intval($post['address_id']);
		if ($input_address_id <= 0) {
			throw new Exception('请选择收货地址');
		} else {
            $info = DeliveryAddress::get($input_address_id);
            if( !$info ) throw new Exception('收货地址无效');
			$input_address_info = $info->toArray();
			if ($input_address_info['member_id'] != $this->_member_info['member_id']) {
				throw new Exception('请选择收货地址');
			}
		}

		//收货地址城市编号
		$input_city_id = intval($input_address_info['city_id']);

		//付款方式:在线支付/货到付款(online/offline)
		$post['pay_name'] = 'online';
		$input_pay_name = $post['pay_name'];

		//保存数据
		$this->_order_data['input_buy_items'] = $input_buy_items;
		$this->_order_data['input_city_id'] = $input_city_id;
		$this->_order_data['input_pay_name'] = $input_pay_name;
		$this->_order_data['input_address_info'] = $input_address_info;
		$this->_order_data['order_from'] = (array_key_exists('order_from',$post) && intval($post['order_from']) == 2) ? 2 : 1;
	    //兑换商品
        if( array_key_exists('is_exchange',$this->_post_data) && $this->_post_data['is_exchange'] == 1 ) {
            $this->_order_data['is_exchange'] = 1;
            $this->_order_data['logs_order_id'] = $this->_post_data['logs_order_id'];
        }else {
			$this->_order_data['is_exchange'] = 0;
		}
    }

	/**
	 * 第一步：处理立即购买
	 *
	 * @param array $cart_id 购物车
	 * @param int $member_id 会员编号
	 * @param int $store_id 店铺编号
	 */
	public function getGoodsList($cartId) {
		//取得POST ID和购买数量
		$buyItems = $this->_parseItems($cartId);
		if (empty($buyItems)) {
			return callback(false, '所购商品无效或库存不足');
		}
		$goodsSku = key($buyItems);
		$quantity = current($buyItems);
		//商品信息[得到最新商品属性及促销信息]
		$goodsInfo= $this->_logic_buy_1->getGoodsOnlineInfo($goodsSku,intval($quantity));
		if(empty($goodsInfo)) {
			return callback(false, '商品已下架或不存在');
		}
		//进一步处理数组
		$storeCartList = array();
		$goodsList = array();
		$goodsList[0] = $storeCartList[$goodsInfo['store_id']][0] = $goodsInfo;
		return callback(true, '', array('goods_list' => $goodsList, 'store_cart_list' => $storeCartList));
	}

	/**
	 * 购买第一步：返回商品、促销、地址、发票等信息，然后交前台抛出
	 * @param unknown $member_id
	 * @param unknown $data 商品信息
	 * @return
	 */
	public function getBuyStepData($data) {
		$storeCartList = $data['store_cart_list'];
		//定义返回数组
		$result = array();
		//商品金额计算(分别对每个商品/优惠套装小计、每个店铺小计)
		list($storeCartList,$storeGoodsTotal) = $this->_logic_buy_1->calcCartList($storeCartList);
		$result['store_cart_list'] = $storeCartList;
		$result['store_goods_total'] = $storeGoodsTotal;
		return callback(true,'',$result);
	}

	/**
	 * 第一步：处理购物车
	 * @param array $cart_id 购物车
	 * @param int $member_id 会员编号
	 */
	public function getCartList($cartId,$memberId) {
		//取得POST ID和购买数量
		$buyItems = $this->_parseItems($cartId);
		if (empty($buyItems))  return callback(false, '所购商品无效或库存不足');
		if (count($buyItems) > 50) return callback(false, '一次最多只可购买50种商品');
		//购物车列表
		$condition = array('cart_id'=>array('in',array_keys($buyItems)),'member_id'=>$memberId);
		$cartList  =  DB::name('cart')->where($condition)->select();
		//购物车列表 [得到最新商品属性及促销信息]
		$goods_list = $this->_logic_buy_1->getGoodsCartList($cartList);
		//以店铺下标归类
		$storeCartList = $this->_getStoreCartList($goods_list);
		if (empty($storeCartList) || !is_array($storeCartList)) {
			return callback(false, '提交数据错误');
		}
		return callback(true, '', array('store_cart_list' => $storeCartList,'goods_list'=>$goods_list));
	}

	/**
	 * 将下单商品列表转换为以店铺ID为下标的数组
	 * @param array $cart_list
	 * @return array
	 */
	private function _getStoreCartList($cart_list) {
		if (empty($cart_list) || !is_array($cart_list)) return $cart_list;
		$new_array = array();
		foreach ($cart_list as $cart) {
			//2017.2.10 ss.wu 添加判断
			if(isset($cart['store_id'])){
				$new_array[$cart['store_id']][] = $cart;
			}
		}
		return $new_array;
	}
	/**
	 * 得到所购买的id和数量
	 */
	private function _parseItems($cartId) {
		//存放所购商品ID和数量组成的键值对
		$buyItems = array();
		if (is_array($cartId)) {
			foreach ($cartId as $value) {
				if (preg_match_all('/^(.*)\|(\d{1,6})$/', $value, $match)) {
					if (intval($match[2][0]) > 0) {
						$buyItems[$match[1][0]] = $match[2][0];
					}
				}
			}
		}
		return $buyItems;
	}
}
