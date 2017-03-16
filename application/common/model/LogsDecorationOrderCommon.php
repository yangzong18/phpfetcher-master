<?php
/**
 * create by: PhpStorm
 * desc:整装订单信息扩展表
 * author:yangmeng
 * create time:2016/12/15
 */
namespace app\common\model;
use think\Model;
use think\Db;
use app\common\model\LogsDecorationOrder as order;
use think\Config;

class LogsDecorationOrderCommon extends Model{


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
     * 取订单扩展单条数据
     */
    public function getOrderCommonInfo($condition = array(), $field = '*') {
        return $this->field($field)->where($condition)->find();
    }

    /**
     * 插入订单扩展信息
     * @param array $data
     * @return int 返回 insert_id
     */
    public function addOrderCommon($data) {
        return $this->insertGetId($data);
    }

    /**
     * 更新订单扩展信息
     * @param array $data,$where
     * @return int 返回 insert_id
     */
    public function updateOrderCommon($data,$where) {
        return $this->where($where)->update($data);
    }

    /**
     * 保存量房信息
     * @param $data $orderId
     * *@return int $result 插入数据状态
     */
    public function insertMeasureInfo( $data , $orderId) {

        //保存数据，并修改进度状态
        Db::startTrans();
        $orderCommonId = $this->addOrderCommon( $data );
        if(!$orderCommonId){
            Db::rollback();
            return '订单扩展数据添加失败';
        }
        $updateInfo = array('speed_status'=>self::LOGS_SPEED_PAY,'order_status'=>self::LOGS_ORDER_NEW);
        if( !(new order())->updateOrderInfo( $updateInfo,array('id'=>$orderId)) ){
            Db::rollback();
            return '订单状态修改失败';
        }

        Db::commit();
        return true;
    }

    /**
     * 保存设计师图片信息
     * @param $data $orderId
     * *@return int $result 插入数据状态
     */
    public function insertDesignImage( $data , $orderId) {

        Db::startTrans();
        //$res = $this->updateOrderCommon($data,array('id'=>$orderCommonId));
        $res = $this->update($data);

        if(!$res){
            Db::rollback();
            return '订单扩展数据添加失败';
        }

        $updateInfo = array('speed_status'=>Order::LOGS_SPEED_DESIGNED);
        if( !(new order())->updateOrderInfo( $updateInfo,array('id'=>$orderId)) ){
            Db::rollback();
            return '订单状态修改失败';
        }

        Db::commit();
        return true;
    }


    /**
     * 保存合同信息
     * @param $data $orderId
     * @return int $result 插入数据状态
     */
    public function insertContractInfo( $data , $orderCommonId, $orderId) {

        //合同数据
        $param['contractNumber'] = $data['contractNumber'];
        $param['contractTime'] = $data['contractTime'];
        $param['contractDesc'] = $data['contractDesc'];

        //判断条件
        if(empty($param['contractNumber']))      return '合同编号不能为空';
        if(strlen($param['contractNumber'])>16)      return '合同编号应小于16字符';
        if(empty($param['contractTime']))       return '合同签订日期不能为空';
        if(mb_strlen($param['contractDesc'],'utf-8')>140)      return '合同备注说明应小于140字';

        $updateInfo['contract_info'] = json_encode($param);

        Db::startTrans();
        $res = $this->updateOrderCommon($updateInfo,array('id'=>$orderCommonId));

        if(!$res){
            Db::rollback();
            return '订单扩展数据添加失败';
        }

        $speedInfo = array('speed_status'=>Order::LOGS_SPEED_ACCEPT);
        if( !(new order())->updateOrderInfo( $speedInfo,array('id'=>$orderId)) ){
            Db::rollback();
            return '订单状态修改失败';
        }

        Db::commit();
        return true;
    }
}