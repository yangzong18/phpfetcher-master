<?php
/**
 * 众筹商品
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: junliang.lai <ljl6907603@sina.cn> at: 2016/12/19  14:03
 */
namespace app\seller\controller;

use app\common\controller\Auth;
use app\common\model\CrowdfundingOrder as Order;
use app\common\model\CrowdfundingGoods as Goods;
use app\common\logic\CrowdOrderLogic;
use Excel\Excel;
use think\Db;
use app\common\logic\Task;
use think\Model;

class CrowdOrder extends Auth {
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
    }

    /*
     * 获取订单列表
     * */
    private function _getOrder(){
        $param = $this->request->param();
        $goodsModel = new Goods();
        $where = array( 'delete_state' => 0 );
        //如果有传入商品名称，则需要查询商品可能的ID，再去查询数据
        if ( isset( $param['goods_name'] ) && trim( $param['goods_name'] ) != '' ) {
            $whereGoods  = array( 'goods_name' => array( 'like', '%'.$param['goods_name'].'%' ) );
            $orderIdList = Db::name( 'crowdfunding_order_goods' )->where( $whereGoods )->column('order_id');
            if( !empty($orderIdList) ) $where['order_id'] = array( 'in', $orderIdList );
            else $where['order_id'] = array( 'in', array(0) );
        }
        //搜索状态
        if ( isset( $param['order_state'] ) && trim( $param['order_state'] ) != '' ) {
            $where['order_state'] = $param['order_state'];
        }else{
            $param['order_state'] = '';
        }
        //如果传入订单编号
        if ( isset( $param['order_sn'] ) && trim( $param['order_sn'] ) != '' ) {
            $where['CAST(order_sn as char)'] = array('like', '%'.$param['order_sn'].'%');;
        }

        //如果传入时间
        if(!empty($param['start'])){
            $where['add_time'] = array('gt',strtotime($param['start']));
        }
        if(!empty($param['end'])){
            $where['add_time'] = array('lt',strtotime($param['end']));
        }
        if(!empty($param['start']) && !empty($param['end'])){
            $where['add_time'] = array(array('gt',strtotime($param['start'])),array('lt',strtotime($param['end'])),'and');
        }

        //订单数据查询
        $orderModel = new Order();
        $datas = $orderModel->where( $where )->order('order_id desc')->paginate(10,'',['query' => $this->request->param()]);
        $page  = $datas->render();
        //查询商品信息
        $orderIdList = array();
        foreach ($datas as $key => $order) {
            $datas[$key] = $order->toArray();
            array_push($orderIdList, $order->order_id);
        }

        if ( count( $datas ) > 0 ) {
            //查询order_common中的商品id信息
            $where     = array( 'order_id' => array('in', $orderIdList) );
            $orderGoods = Db::name( 'crowdfunding_order_goods' )->where( $where )->select();
            //获取所有的商品ID
            $goodsId   = array();
            foreach ($orderGoods as $goods) {
                if ( !in_array($goods['goods_id'], $goodsId) ) {
                    array_push($goodsId, $goods['goods_id']);
                }
            }
            //实时查询众筹商品的商品信息
            $goodsModel = new Goods();
            $crowdList  = $goodsModel->field('id, quotient, start_at, end_at, state')->where( array( 'id' => array( 'in', $goodsId ) ) )->select();
            //拼接订单商品和商品信息
            $goodsList  = array();
            foreach ($orderGoods as $key => $order) {
                foreach ($crowdList as $crowd) {
                    if ( $order['goods_id'] == $crowd->id ) {
                        $order['quotient'] = $crowd->quotient;
                        $order['start_at'] = $crowd->start_at;
                        $order['end_at'] = $crowd->end_at;
                        $order['state']  = $crowd->state;
                        $goodsList[ $order['order_id'] ] = $order;
                    }
                }
            }
            $success = array( Goods::GOODS_STATE_SUCCESS, Goods::GOODS_STATE_RETURN, Goods::GOODS_STATE_PROJECT_SUCCESS );
            foreach ($datas as $key => $order) {
                $orderId        = $order['order_id'];
                $order['goods'] = $goodsList[$orderId];
                $order['rebund']= in_array($order['goods']['state'], $success) ? 1 : 0;
                $order['order_state_name'] = Order::$order_state_arr[ $order['order_state'] ];
                $order['goods_state_name'] = Goods::$goodsStatus[ $order['goods']['state'] ];
                $datas[$key] = $order;
            }
        }
        $this->assign("orderStatus", Order::$order_state_arr);
        $this->assign('pageTotal',$datas->total());
        $this->assign("param", $param);
        $this->assign("page", $page);
        $this->assign("datas", $datas);
    }
    /**
     * 原木整装订单首页
     */
    public function index() {
        $this->_getOrder();
        return $this->fetch();
    }
    /*
     * 导出订单
     *
     * */
    public function outOrder(){
        $this->_getOrder();
        $this->assign('url_request',$this->request->query());
        return $this->fetch();
    }

    /*
     * 导出订单到Excel
     * */
    public function exportOrder()
    {
        $params = $this->request->query();
        if(empty($params))$this->error('请传递查询参数',url('/seller/crowd_order/index'));
        $params_arr = array();
        parse_str($params,$params_arr);
        if(array_key_exists('page',$params_arr)) unset($params_arr['page']);
        if(empty($params_arr)) $this->error('请传递查询参数',url('/seller/crowd_order/index'));

        $param = $this->request->param();
        $where = array( 'delete_state' => 0 );
        //如果有传入商品名称，则需要查询商品可能的ID，再去查询数据
        if ( isset( $param['goods_name'] ) && trim( $param['goods_name'] ) != '' ) {
            $whereGoods  = array( 'goods_name' => array( 'like', '%'.$param['goods_name'].'%' ) );
            $orderIdList = Db::name( 'crowdfunding_order_goods' )->where( $whereGoods )->column('order_id');
            $where['order_id'] = array( 'in', $orderIdList );
        }

        //如果传入时间
        if(!empty($param['start'])){
            $where['add_time'] = array('gt',strtotime($param['start']));
        }

        if(!empty($param['end'])){
            $where['add_time'] = array('lt',strtotime($param['end']));
        }

        //订单数据查询
        $orderModel = new Order();
        $datas = $orderModel->where( $where )->order('order_id desc')->select();
        $orderList = Model::getResultByFild($datas);

        if(empty($orderList)) $this->error('没有可以被导出的订单数据',url('/seller/crowd_order/index'));

        $this->createExcel($orderList);
    }

    /**
     * 生成excel
     *
     * @param array $data
     */
    private function createExcel($data = array())
    {
        $excel_obj = new Excel();
        $excel_data = array();
        //设置样式
        $excel_obj->setStyle(array('id'=>'s_title','Font'=>array('FontName'=>'宋体','Size'=>'12','Bold'=>'1')));
        //header
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'订单号');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'买家');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'下单时间');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'支付时间');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'订单总额');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'订单状态');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'物流单号');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'买家Email');
        //data
        foreach ((array)$data as $k=>$v){
            $tmp = array();
            $tmp[] = array('data'=>$v['order_sn']);
            $tmp[] = array('data'=>$v['member_name']);
            $tmp[] = array('data'=>date('Y-m-d H:i:s',$v['add_time']));
            $payment_time = ($v['payment_time']<=0) ? '-' : date('Y-m-d H:i:s',$v['payment_time']);
            $tmp[] = array('data'=>$payment_time);
            $tmp[] = array('format'=>'Number','data'=>ncPriceFormat($v['order_amount']));
            $tmp[] = array('data'=>Order::$order_state_arr[$v['order_state']]);
            $tmp[] = array('data'=>$v['shipping_code']);
            $tmp[] = array('data'=>$v['member_email']);
            $excel_data[] = $tmp;
        }
        $excel_data = $excel_obj->charset($excel_data,CHARSET);
        $excel_obj->addArray($excel_data);
        $excel_obj->addWorksheet($excel_obj->charset('众筹订单',CHARSET));
        $excel_obj->generateXML($excel_obj->charset('众筹订单',CHARSET).'-'.date('Y-m-d-H',time()));
    }

    /**
     * 设置物流信息
     */
    public function delivery() {
        $orderId = $this->request->param('order_id', 0, 'intval');
        $orderLogic = new CrowdOrderLogic();
        $order = $orderLogic->inquire( $orderId );
        $this->assign("order", $order);
        return $this->fetch();
    }

    /**
     * 设置订单的物流信息
     */
    public function shipping() {
        $goodsId      = $this->request->param( 'goods_id', 0, 'intval' );
        $orderId      = $this->request->param( 'order_id', 0, 'intval' );
        $shippingCode = $this->request->param( 'code');
        if ( trim( $shippingCode ) == '' ) {
            $this->error('物流单号不能为空');
        }
        //查询商品信息
        $goodsModel = new Goods();
        $where = array( 'is_delete' => 0, 'id' => $goodsId );
        $goods = $goodsModel->where( $where )->find();
        if ( !$goods ) {
            $this->error('商品不存在');
        }
        //商品状态是否筹款成功
        $success = array( Goods::GOODS_STATE_SUCCESS, Goods::GOODS_STATE_RETURN, Goods::GOODS_STATE_PROJECT_SUCCESS );
        if ( !in_array( $goods['state'], $success ) ) {
            $this->error('当众筹状态不正确');
        }
        //查询订单状态
        $where = array( 'order_id' => $orderId );
        $orderModel = new Order();
        $order      = $orderModel->where( $where )->find();
        if ( $order['order_state'] != Order::ORDER_STATE_PAY ) {
            //$this->error('订单状态不正确');
        }
        //修改该订单的物流单号和订单状态
        $data = array( 'order_state' => Order::ORDER_STATE_SEND, 'shipping_code' => $shippingCode, 'shipping_time'=>time() );
        $result = $orderModel->where( $where )->update( $data );
        //只要有一个填写运单，则设置为回报中
        if ( $result && $goods['state'] == Goods::GOODS_STATE_SUCCESS ) {
            $goodsModel->where( array( 'id' => $goodsId ) )->update( array( 'state' => Goods::GOODS_STATE_RETURN ) );
        }
        $this->success('运单号设置成功','',1);
    }

    /**
     * 订单详情页
     */
    public function detail() {
        $orderId = $this->request->param('id', 0, 'intval');
        $orderLogic = new CrowdOrderLogic();
        $order = $orderLogic->inquire( $orderId );
        $this->assign("order_info", $order);
        return $this->fetch();
    }
}
