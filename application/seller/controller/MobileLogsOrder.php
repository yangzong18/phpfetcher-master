<?php
/**
 * 手机端整装设计管理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷 at 2017-1-5
 */
namespace app\seller\controller;
use app\common\controller\Auth;
use app\common\model\Designer;
use app\common\model\LogsDecorationGoods;
use app\common\model\MobileLogsDecorationOrder;
use think\Db;

class MobileLogsOrder extends Auth{
    protected $search = ['order_sn' => '订单编号', 'goods_name' => '商品名称',];

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->orderModel = new MobileLogsDecorationOrder();
        $this->orderState = MobileLogsDecorationOrder::$order_state_arr;
        $this->orderState['']='全部订单';
    }

    /*
     * 获取订单列表
     * */
    private function _getOrder(){
        //搜索条件
        $searchType = $this->request->param('searchType');
        $searchValue = $this->request->param('searchValue', '', 'trim');
        $orderState  = $this->request->param('orderState');

        $where = [];
        $where['delete_status'] = 0;
        if( $orderState != ''){
            $where['order_status'] = $orderState;
        }
        if( $searchType != '' && $searchValue != ''){
            switch($searchType){
                case 'order_sn':
                    $searchType="CAST(order_sn as char)";
                    $where[$searchType] = ['like', '%'.$searchValue.'%'];
                    break;
                case 'goods_name':
                    $logsId = ( new LogsDecorationGoods )->where(['name'=>['like','%'.$searchValue.'%']])->column('id');
                    if( !empty($logsId) ) {
                        $where['logs_goods_id'] = ['in', $logsId];
                    }
                    break;
            }
        }

        //订单数据查询
        $datas = $this->orderModel->where( $where )->order('id desc')->paginate(10,'',['query' => $this->request->param()]);
        $page  = $datas->render();
        //查询商品信息
        $goodsIdList = array();
        $designerIdList = array();
        $designerModel = new Designer();
        $goodsModel = new LogsDecorationGoods();
        foreach ($datas as $key => $order) {
            $datas[$key] = $order->toArray();
            if ( !in_array($order->logs_goods_id, $goodsIdList) ) {
                array_push($goodsIdList, $order->logs_goods_id);
            }
            array_push($designerIdList, $order->designer_id);
        }

        if ( count( $datas ) > 0 ) {
            //商品查询
            $goodsList = $goodsModel->field('id,name,cover,type')->where( ['id' => ['in', $goodsIdList]] )->select();
            //设计师查询
            $designerList = $designerModel->field('designer_id,designer_name')->where( ['designer_id'=>['in',$designerIdList]] )->select();
            //商品数据拼接
            foreach ($datas as $key => $order) {
                $order['created_at'] = date('Y-m-d H:i:s', $order['created_at']);
                //李武修改
                $order['goods_name']='';
                $order['goods_cover']='';
                $order['goods_type']='';
                $order['designer_name']='';
                foreach ($goodsList as $tag => $goods) {
                    if ( $goods->id == $order['logs_goods_id'] ) {
                        $order['goods_name'] = $goods->name;
                        $order['goods_cover']= $goods->cover;
                        $order['goods_type'] = $goods->type;
                        break 1;
                    }
                }
                foreach ($designerList as $designer) {
                    if ( $designer->designer_id == $order['designer_id'] ) {
                        $order['designer_name'] = $designer->designer_name;
                        break 1;
                    }
                }
                $datas[$key] = $order;
            }
        }

        $this->assign('search', $this->search);
        $this->assign('searchType', $searchType);
        $this->assign('searchValue', $searchValue);
        $this->assign('searchOrderState',$orderState);
        $this->assign('orderState',$this->orderState);
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

    public function detail() {
        //获取订单信息
        $orderId = $this->request->param('orderId', 0, 'intval');
        $where = ['id'=>$orderId, 'delete_status'=>0];
        $orderInfo = $this->orderModel->where( $where )->find();

        if(!$orderInfo){
            die('wrong logs order info');
        }
        $orderInfo['order_state'] = $this->orderModel->orderStatus($orderInfo['order_status']);
        if( $orderInfo['order_status'] == 2 ) {
            $orderInfo['design_image'] = json_decode($orderInfo['design_image']);
        }
        //查询整装商品
        $logsInfo = (new LogsDecorationGoods())->field('id,cover,name,prize,category_id')
                     ->where(['id'=>$orderInfo['logs_goods_id'],'is_delete'=>0])
                     ->find();

        //设计师信息查询
        $designerModel = new Designer();
        $designer = $designerModel->where('designer_id', $orderInfo['designer_id'] )->find();
        //查询设计师级别
        $level = Db::name('designer_level')->where('level_id', $designer['level_id'])->find();
        $designer['level_name'] = isset( $level['level_name'] ) ? $level['level_name'] : '';

        $this->assign('logsInfo',$logsInfo);
        $this->assign('orderInfo',$orderInfo);
        $this->assign('designer', $designer);
        return $this->fetch();
    }

    /**
     * 上传设计
     */
    public function orderDesign(){
        //页面参数
        $orderId = $this->request->param('orderId', '', 'intval');
        $orderInfo = $this->orderModel->getMobileOrderInfo( ['id' => $orderId] );

        //设计师信息查询
        $field = 'designer_name,designer_phone';
        $where = ['designer_id' => $orderInfo['designer_id']];
        $designerModel = new Designer();
        $designer = $designerModel->field($field)->where($where)->find();

        //设计页面提交表单
        $type = $this->request->param('type');
        if($type){
            $image = $this->request->param('img_url/a','');
            if( empty($image) ) $this->error( '设计图不能为空' );
            $data['design_image'] = json_encode($image);
            $data['order_status'] = 2;
            $data['finish_time'] = time();
            $data['id'] = $orderId;
            //保存数据，并修改进度状态
            if ( $this->orderModel->update($data))  $this->success('保存成功','',1);
            else $this->error( '保存失败' );
        }

        $this->assign('designer',$designer);
        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }
}