<?php
/**
 * Created by PhpStorm.
 * User: LUOTING
 * Date: 2017/1/5
 * Time: 11:38
 */

namespace app\common\model;
use think\Model;

class MobileLogsDecorationOrder extends Model{

    //订单状态常量
    const LOGS_ORDER_DESIGN = 1;
    const LOGS_ORDER_DESIGNED = 2;

    //订单状态描述
    public static  $order_state_arr=array(
        self::LOGS_ORDER_DESIGN =>'待设计',
        self::LOGS_ORDER_DESIGNED =>'已设计',
    );

    /**
     * 取得订单状态文字输出
     * @param $orderStatus
     * @return mixed
     */
    function orderStatus($orderStatus) {
        return self::$order_state_arr[$orderStatus];
    }

    /**
     * 获取手机端列表
     * @param $where
     * @param $field
     * @return mixed
     */
    public function getMobileOrderList( $where, $field ) {
        return $this->field($field)->where($where)->select();
    }

    /**
     * 获取手机端单条记录
     * @param $where
     * @param $field
     * @return mixed
     */
    public function getMobileOrderInfo( $where, $field = '*') {
        return $this->field($field)->where($where)->find();
    }
}