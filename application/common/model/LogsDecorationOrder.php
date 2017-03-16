<?php
/**
 * 原木整装商品订单模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-12-08 13:24
 */

namespace app\common\model;
use think\Db;
use think\Model;
use think\Config;

class LogsDecorationOrder extends Model{

    //订单状态常量
    const LOGS_ORDER_VERIFY = 1;
    const LOGS_ORDER_NEW = 2;
    const LOGS_ORDER_PAY = 3;
    const LOGS_ORDER_SUCCESS = 4;
    const LOGS_ORDER_CANCEL = 5;

    //订单状态描述
    public static  $order_state_arr=array(
        self::LOGS_ORDER_VERIFY =>'等待审核',
        self::LOGS_ORDER_NEW =>'待支付',
        self::LOGS_ORDER_PAY =>'已支付',
        self::LOGS_ORDER_SUCCESS =>'已完成',
        self::LOGS_ORDER_CANCEL =>'交易关闭',
    );

    //进度状态常量
    const LOGS_SPEED_MEASURE = 1;
    const LOGS_SPEED_PAY = 2;
    const LOGS_SPEED_DESIGN = 3;
    const LOGS_SPEED_DESIGNED = 4;
    const LOGS_SPEED_SIGN = 5;
    const LOGS_SPEED_ACCEPT = 6;
    const LOGS_SPEED_ACCEPTED = 7;
    const LOGS_SPEED_EXCHANGE = 8;
    const LOGS_SPEED_EXCHANGEED = 9;

    //进度状态描述
    public static  $speed_state_arr=array(
        ''=>'所有订单',
        self::LOGS_SPEED_MEASURE =>'待免费量房',
        self::LOGS_SPEED_PAY => '待付诚意金',
        self::LOGS_SPEED_DESIGN =>'待设计',
        self::LOGS_SPEED_DESIGNED =>'待确认',
        self::LOGS_SPEED_SIGN =>'待签合同',
        self::LOGS_SPEED_ACCEPT =>'待验收',
        self::LOGS_SPEED_ACCEPTED =>'已验收',
        self::LOGS_SPEED_EXCHANGE =>'待兑换商品',
        self::LOGS_SPEED_EXCHANGEED =>'已兑换商品'
    );

    /**
     * 取得订单状态文字输出
     */
    function orderStatus($orderStatus) {
        return self::$order_state_arr[$orderStatus];
    }

    /**
     * 取得进度状态文字输出
     */
    function SpeedStatus($speedStatus) {
        return self::$speed_state_arr[$speedStatus];
    }

    /**
     * @param $where
     * @param string $fields
     * @return int 统计订单数量
     */

    public function getCount($where,$fields='*'){
        return $this->where($where)->count($fields);
    }

    /**
     * 取得订单支付类型文字输出形式
     *
     * @param array $payment_code
     * @return string
     */
    function orderPaymentName($payment_code) {
        return str_replace(
            array('offline','online','alipay','tenpay','chinabank','predeposit'),
            array('货到付款','在线付款','支付宝','财付通','网银在线','站内余额支付'),
            $payment_code);
    }

    /**
     * 添加订单日志
     * @param $data array
     * @return int
     */
    public function addOrderLog($data) {
        $data['log_role'] = str_replace(array('buyer','seller','system','admin'),array('买家','商家','系统','管理员'), $data['log_role']);
        $data['log_time'] = time();
        return Db::name('order_log')->insert($data);
    }


    /**
     * 查询设计师交易成功订单个数
     * @param $where
     * @return array
     */
    function getLogsOrderByDesigner( $where ) {
        $list = $this->where($where)->whereTime('payment_time', 'month')->field('id, designer_id,count(id) as designerNum')
                ->group('designer_id')->order('designerNum desc')->limit(4)->select();
        
        if( !empty($list) ){
            $countList = [];
            foreach ($list as $val) {
                $countList[$val->designer_id] = ['designer_id'=>$val->designer_id,'count'=>$val->designerNum];
            }
            return $countList;
        }
        return [];
    }

    /**
     * 查询订单单条信息
     * @param $condition $field
     * @return
     */
    public function getOrderInfo( $where , $field = '*' ){
        $orderInfo = $this->where( $where )->field( $field )->find();
        if( !empty($orderInfo) ) {
            $orderInfo['state_desc'] = $this->orderStatus( $orderInfo['order_status'] );
        }

        return $orderInfo;
    }

    /**
     * 查询订单信息
     */
    public function getOrderList( $where ,$field = '*' ){
        return $this->where( $where )->field( $field )->select();
    }

    /**
     *保存订单信息
     * @param $where $data
     * @return
     *
     */
    public function updateOrderInfo( $data , $where) {
        return $this->where( $where )->update( $data );
    }

    /**
     * 查询设计师信息
     */
    public function getDesignerInfo( $where ,$field ) {
        return Db::name('designer')->where($where)->field($field)->find();
    }

    /**
     * 查询订单支付信息
     */
    public function getOrderPayInfo($condition = array()) {
        return Db::name('logs_decoration_order_pay')->where($condition)->find();
    }

    /**
     * 更改订单支付信息
     *
     * @param unknown_type $data
     * @param unknown_type $condition
     */
    public function editOrderPay($data,$condition) {
        return DB::name('logs_decoration_order_pay')->where($condition)->update($data);
    }

    /**
     * 判断商品是否兑换
     * @param $where
     * @return array
     */
    public function checkExchange( $where ) {
        $result = $this->where( $where )->find();
        if( !$result) {//假如订单信息不存在
            return ['code'=> 0, 'msg'=> '订单信息无效'];
        }
        if( $result->order_status == self::LOGS_ORDER_CANCEL ) {//假如订单状态是已完成
            return ['code'=> 0, 'msg'=> '商品已兑换'];
        }
        return ['code' => 1];
    }

}
