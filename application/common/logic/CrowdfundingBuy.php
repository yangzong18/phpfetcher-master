<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/15  13:31
 */
namespace app\common\logic;

use app\common\model\CrowdfundingGoods;
use app\common\model\CrowdfundingOrder;
use app\common\model\DeliveryAddress;
use think\Db;
use think\Exception;

class CrowdfundingBuy
{

    /**
     * 会员信息
     * @var array
     */
    private $_member_info = array();

    /**
     * 表单数据
     * @var array
     */
    private $_post_data = array();

    /**
     * 下单数据
     * @var array
     */
    private $_order_data = array();



    /**
     * 商品购买第一步
     * @param $goodsId string
     * @param $memberId int
     * @return array
     */
    public function buyStep1($goodsId,$memberId){
        $result = $this->getGoodsList($goodsId, $memberId);

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

            //订单生成后添加处理订单的延时任务
            $order_list = $this->_order_data['order_list'];
            $goods_list = $this->_order_data['goods_list'];
            $task = new Task();
            if($order_list){
                foreach($order_list as $k=>$v){
                    $key_start = 'crowdfunding_order_'.$k;
                    $method_start = '/crontab/task/dealCrowdfundingOrderStart';
                    $param_start = array('order_id'=>$k,'goods_id'=>$goods_list['goods_id'],'goods_number'=>$goods_list['goods_number']);
                    $time_start = 15*60;
                    $task->addTask($key_start,$method_start,$time_start,$param_start);
                }
            }


            return callback(true,'',$this->_order_data);
        }catch (Exception $e){
            // 回滚事务
            Db::rollback();
            return callback(false, $e->getMessage());
        }

    }


    /**
     * 订单生成前的表单验证与处理
     *
     */
    private function _createOrderStep1()
    {
        $post = $this->_post_data;

        //取得商品ID和购买数量
        $input_buy_items = $this->_parseItems($post['goods_id']);
        if (empty($input_buy_items))  throw new Exception('所购商品无效');

        //验证收货地址
        $input_address_id = intval($post['address_id']);
        if ($input_address_id <= 0) {
            throw new Exception('请选择收货地址');
        } else {
            $input_address_info = DeliveryAddress::get($input_address_id)->toArray();
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

    }


    /**
     * 得到购买商品信息
     *
     */
    private function _createOrderStep2()
    {

        $input_buy_items = $this->_order_data['input_buy_items'];


        //来源于直接购买
        $goods_id = key($input_buy_items);
        $quantity = current($input_buy_items);

        //商品信息[得到最新商品属性及促销信息]
        $goodsInfo = $this->_getGoodsOnlineInfo($goods_id,intval($quantity));
        if(empty($goodsInfo))  throw new Exception('商品已下架或不存在');

        //判断众筹是否开始
        if(time() < $goodsInfo['start_at']){
            throw new Exception('众筹活动尚未开始');
        }
        //判断众筹是否结束
        if(time() > $goodsInfo['end_at']){
            throw new Exception('众筹活动已结束');
        }
        //购买限制判断
        if($goodsInfo['limit'] < $quantity){
            throw new Exception('超出购买限制');
        }
        //库存与购买数量判断
        if(($goodsInfo['quotient']-$goodsInfo['sale_number']) < $quantity){
           throw new Exception('商品库存不足');
        }

        //判断是否锁定
        if($goodsInfo['is_pause'] == 1){
            throw new Exception('商品已被锁定,暂停购买');
        }

        $goodsInfo['goods_number'] = $quantity;


        //保存数据
        $this->_order_data['goods_list'] = $goodsInfo;
    }


    /**
     * 得到购买相关金额计算等信息
     *
     */
    private function _createOrderStep3()
    {
        $goods_list = $this->_order_data['goods_list'];

        //商品金额计算
        $store_goods_total =  ncPriceFormat($goods_list['one_price']*$goods_list['goods_number']);
        //运费
        if($goods_list['is_self'] == 0){
            $store_goods_total +=  $goods_list['freight'];
        }
        //保存数据
        $this->_order_data['store_goods_total'] = $store_goods_total-$goods_list['freight'];
        $this->_order_data['store_final_order_total'] = $store_goods_total;
        $this->_order_data['goods_buy_quantity'] = $goods_list['goods_number'];
    }

    /**
     * 生成订单
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
        $model_order = new CrowdfundingOrder();

        //存储生成的订单数据
        $order_list = array();

        //产生付款单号
        $pay_sn = $this->makePaySn($member_id);
        $order_pay = array();
        $order_pay['pay_sn'] = $pay_sn;
        $order_pay['member_id'] = $member_id;
        $order_pay_id = $model_order->addCrowdfundingOrderPay($order_pay);
        if (!intval($order_pay_id))  throw new Exception('订单保存失败[未生成支付单]');

        //收货人信息
        list($receiver_info,$receiver_name) = $this->getReceiverAddr($input_address_info);

            $order = array();
            $order_common = array();
            $order_goods = array();
            $order_sn = $this->makeOrderSn($order_pay_id);
            $order['order_sn'] = $order_sn;
            $order['pay_sn'] = $pay_sn;
            $order['store_id'] = $goods_list['store_id'];
            $order['store_name'] = $goods_list['store_name'];
            $order['member_id'] = $member_id;
            $order['member_name'] = $member_name;
            $order['member_email'] = $member_email;
            $order['add_time'] = time();
            $order['payment_code'] = $input_pay_name;
            $order['order_state'] = CrowdfundingOrder::ORDER_STATE_NEW;
            $order['order_amount'] = $store_final_order_total;
            $order['shipping_fee'] = $goods_list['is_self'] == 0?$goods_list['freight']:'0.00';
            $order['goods_amount'] = $store_goods_total;
            $order['order_device_from'] = $order_from;
            $order_id = $model_order->addCrowdfundingOrder($order);
            if (!intval($order_id))  throw new Exception('订单保存失败[未生成订单数据]');
            $order['order_id'] = $order_id;
            $order_list[$order_id] = $order;

            $order_common['order_id'] = $order_id;
            $order_common['store_id'] = $goods_list['store_id'];
            $order_common['receiver_info']= $receiver_info;
            $order_common['receiver_name'] = $receiver_name;
            $order_common['receiver_city_id'] = $input_city_id;

            $order_id = $model_order->addCrowdfundingOrderCommon($order_common);
            if (!intval($order_id))  throw new Exception('订单保存失败[未生成订单扩展数据]');


            //如果不是优惠套装
            $order_goods['order_id'] = $order_id;
            $order_goods['goods_id'] = $goods_list['goods_id'];
            $order_goods['store_id'] = $goods_list['store_id'];
            $order_goods['goods_name'] = $goods_list['goods_name'];
            $order_goods['goods_price'] = $goods_list['one_price'];
            $order_goods['goods_num'] = $goods_list['goods_number'];
            $order_goods['goods_image'] = $goods_list['image_main'];
            $order_goods['specification'] = $goods_list['specification'];
            $order_goods['goods_pay_price'] = $store_goods_total;
            $order_goods['type'] = $goods_list['type'];
//            $order_goods['remark'] = trim($this->_post_data['remark_'.$goods_list['goods_sku']]).'\r\n'.trim($this->_post_data['phone_'.$goods_list['goods_sku']]);


            $insert = $model_order->addCrowdfundingOrderGoods($order_goods);
            if (intval($insert)<=0) throw new Exception('订单保存失败[未生成商品数据]');


        //保存数据
        $this->_order_data['pay_sn'] = $pay_sn;
        $this->_order_data['order_list'] = $order_list;
    }


    /**
     * 订单后续其它处理
     *
     */
    private function _createOrderStep5()
    {

        $goods_list = $this->_order_data['goods_list'];
        $order_list = $this->_order_data['order_list'];

        //为保证数据准确，不使用队列
        //变更库存和销量
        $queue_obj = new CrowdfundingGoods();
        $result = $queue_obj->createOrderUpdateStorage($goods_list['goods_id'],$goods_list['goods_number']);
        if (!$result['state'])  throw new Exception('订单保存失败[商品信息已更改，请重新购买]');

    }




    /**
     * 取得收货人地址信息
     * @param array $address_info
     * @return array
     */
    public function getReceiverAddr($address_info = array())
    {
        $receiver_info['phone'] = trim($address_info['mob_phone'].($address_info['tel_phone'] ? ','.$address_info['tel_phone'] : null),',');
        $receiver_info['mob_phone'] = $address_info['mob_phone'];
        $receiver_info['tel_phone'] = $address_info['tel_phone'];
        $receiver_info['address'] = $address_info['area_info'].' '.$address_info['address'];
        $receiver_info['area'] = $address_info['area_info'];
        $receiver_info['street'] = $address_info['address'];
        $receiver_info = serialize($receiver_info);
        $receiver_name = $address_info['true_name'];
        return array($receiver_info, $receiver_name);
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
     * 取得所购买商品
     * @param $goodsId string
     * @return array
     */
    public function getGoodsList($goodsId){
        //取得POST ID和购买数量
        $buyItems = $this->_parseItems($goodsId);
        if (empty($buyItems)) {
            return callback(false, '所购商品无效');
        }
        $goodsId = key($buyItems);
        $quantity = current($buyItems);
        //商品信息
        $goodsInfo= $this->_getGoodsOnlineInfo($goodsId,intval($quantity));
        $time = time();

        //是否取得商品信息
        if(empty($goodsInfo)) {
            return callback(false, '商品已下架或不存在');
        }
        //判断众筹是否开始
        if( $time < $goodsInfo['start_at']){
            return callback(false, '众筹活动尚未开始');
        }
        //判断众筹是否结束
        if( $time > $goodsInfo['end_at']){
            return callback(false, '众筹活动已结束');
        }
        //购买限制判断
        if($goodsInfo['limit'] < $quantity){
            return callback(false,'超出购买限制');
        }
        //库存与购买数量判断
        if(($goodsInfo['quotient']-$goodsInfo['sale_number']) < $quantity){
            return callback(false,'商品库存不足');
        }

        //计算商品购买总价
        $goodsInfo['pay_total_price'] = ncPriceFormat($goodsInfo['one_price']*$goodsInfo['goods_number']+$goodsInfo['freight']);

        return $goodsInfo;


    }


    /**
     * 直接购买时返回最新的在售商品信息（需要在售）
     *
     * @param int $goodsId 所购商品ID
     * @param int $quantity 购买数量
     * @return array
     */
    private function _getGoodsOnlineInfo($goodsId,$quantity) {
        $modelGoods = new CrowdfundingGoods();
        //取目前在售商品
        $goodsInfo = $modelGoods->getGoodsById($goodsId, true);
        if(empty($goodsInfo)) return null;
        $newArray = array();
        $newArray['goods_name']			= $goodsInfo['name'];
        $newArray['goods_number'] 		= $quantity;
        $newArray['goods_id'] 			= $goodsInfo['id'];
        $newArray['store_id'] 			= $goodsInfo['store_id'];
        $newArray['store_name'] 	    = (!array_key_exists('store_name',$goodsInfo)) ? '-' : $goodsInfo['store_name'];
        $newArray['total_price'] 		= $goodsInfo['total_price'];
        $newArray['quotient'] 		    = $goodsInfo['quotient'];
        $newArray['limit'] 		        = $goodsInfo['limit'];
        $newArray['one_price'] 		    = $goodsInfo['one_price'];
        $newArray['start_at'] 			= $goodsInfo['start_at'];
        $newArray['end_at'] 			= $goodsInfo['end_at'];
        $newArray['image_main'] 	    = $goodsInfo['image_main'];
        $newArray['sale_number'] 		= $goodsInfo['sale_number'];
        $newArray['is_self'] 		    = $goodsInfo['is_self'];
        $newArray['freight'] 		    = $goodsInfo['freight'];
        $newArray['is_delete'] 		    = $goodsInfo['is_delete'];
        $newArray['specification'] 		= $goodsInfo['specification'];
        $newArray['type'] 		        = $goodsInfo['type'];
        $newArray['is_pause'] 		    = $goodsInfo['is_pause'];
        $newArray['state'] 				= true;

        return $newArray;
    }




    /**
     * 得到所购买的id和数量
     */
    private function _parseItems($cartId) {
        //存放所购商品ID和数量组成的键值对
        $buyItems = array();
        if (preg_match_all('/^(\d*)\|(\d{1,6})$/', $cartId, $match)) {
            if (intval($match[2][0]) > 0) {
                $buyItems[$match[1][0]] = $match[2][0];
            }
        }
        return $buyItems;
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
}