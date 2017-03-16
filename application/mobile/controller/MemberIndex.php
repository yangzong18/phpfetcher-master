<?php
/**
 * 用户首页
 * 罗婷 2017/1/17
 */

namespace app\mobile\controller;
use app\common\model\Favorites;
use app\common\model\MobileLogsDecorationOrder;
use app\common\model\Order;

class MemberIndex extends MobileMember{

    const DELETE_STATE0 = 0;//正常
    const DELETE_STATE1 = 1;//回收站
    const DELETE_STATE2 = 2;//永久删除
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
    }

    public function index() {
        //订单统计
        $orderModel = new Order();
        //未付款
        $countOne 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$orderModel::ORDER_STATE_NEW,'refund_state'=>['in',[0,3]],'delete_state'=>self::DELETE_STATE0],'order_id');
        //待发货
        $countTwo 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$orderModel::ORDER_STATE_PAY,'refund_state'=>['in',[0,3]],'delete_state'=>self::DELETE_STATE0],'order_id');
        //待收货
        $countThree 	= $orderModel->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$orderModel::ORDER_STATE_SEND,'refund_state'=>['in',[0,3]],'delete_state'=>self::DELETE_STATE0],'order_id');
        //已收货
        $countFive 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'order_state'=>$orderModel::ORDER_STATE_SUCCESS,'refund_state'=>['in',[0,3]],'delete_state'=>self::DELETE_STATE0],'order_id');
        //退款、售后
        $countFour 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'refund_state'=>['in',[1,2]],'delete_state'=>self::DELETE_STATE0],'order_id');
        $orderCount = ['new'=>$countOne,'pay'=>$countTwo,'send'=>$countThree,'refund'=>$countFour,'delivery'=>$countFive];
        //我的收藏统计
        $favoriteModel = new Favorites();
        $where = ['member_id'=>$this->user['member_id'], 'type'=>0];
        $field = 'f.goods_id,goods_name,goods_price,goods_image_main,is_delete,goods_verify,f.price,f.id';
        $join = [['mgt_goods g','g.goods_id=f.goods_id']];
        $favoriteCount = $favoriteModel->field('f.id')->alias('f')->join($join)->where($where)->count();
        //我的设计统计
        $mobileDesign = new MobileLogsDecorationOrder();
        $designCount = $mobileDesign->where(['member_id'=>$this->user['member_id'], 'delete_status'=>0])
                       ->count('id');
        $data = ['account'=>$this->user['phone'],'orderCount'=>$orderCount,'favoriteCount'=>$favoriteCount,'designCount'=>$designCount];
        $this->returnJson($data);
    }
}