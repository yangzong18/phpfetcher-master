<?php
/**
 * 原木整装订单管理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-30 17:59
 */
namespace app\seller\controller;
use app\api\controller\SentTemplatesSMS;
use think\Db;
use app\common\controller\Auth;
use app\common\model\LogsDecorationGoods as Goods;
use app\common\model\LogsDecorationOrder as Order;
use app\common\model\LogsDecorationOrderCommon as OrderCommon;
use app\common\model\LogsDecorationOrderSpeed;
use app\common\model\Designer;
use think\Validate;
use think\Config;
use think\Model;
use Excel\Excel;

class LogsOrder extends Auth
{

    //设计图类型
    public $houseStyle = array(
                                 1 => '整体效果图',
                                 2 => '客厅',
                                 3 => '卧室',
                                 4 => '书房',
                                 5 => '餐厅',
                                 6 => '儿童房',
                                 7 => '厨房',
                                 8 => '卫生间');

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->orderModel = new Order();
        $this->orderCommonModel = new OrderCommon();
    }

    /*
     * 获取订单列表
     * */
    private function _getOrder(){
        $param = $this->request->param();
        if ( !isset($param['speed_status']) ) $param['speed_status'] = '';
        $goodsModel = new Goods();
        $where = array( 'delete_status' => 0 );
        if( isset( $param['member_id'] ) ) {//用户查看相关订单入口
            $where['member_id'] =  $param['member_id'];
        }
        //搜索状态
        if ( isset( $param['speed_status'] ) && trim( $param['speed_status'] ) != '' ) {
            $where['speed_status'] = $param['speed_status'];
        }
        //如果有传入商品名称，则需要查询商品可能的ID，再去查询数据
        if ( isset( $param['goods_name'] ) && trim( $param['goods_name'] ) != '' ) {
            $whereGoods  = array( 'name' => array( 'like', '%'.$param['goods_name'].'%' ) );
            $goodsIdList = Db::name( 'logs_decoration_goods' )->where( $whereGoods )->column('id');
            if( !empty($goodsIdList) ){
                $where['logs_goods_id'] = array( 'in', $goodsIdList );
            } else {
                $where['logs_goods_id'] = array( 'in', array(0) );
            }
        }

        //订单编号
        if ( isset( $param['order_sn'] ) && trim( $param['order_sn'] ) != '' ) {
            $where['order_sn'] = array( 'like', '%'.$param['order_sn'].'%' ) ;
        }

        //如果传入时间
        if(!empty($param['start'])){
            $where['created_at'] = array('gt',strtotime($param['start']));
        }

        if(!empty($param['end'])){
            $where['created_at'] = array('lt',strtotime($param['end']));
        }
        if(!empty($param['start']) && !empty($param['end'])){
            $where['created_at'] = array(array('gt',strtotime($param['start'])),array('lt',strtotime($param['end'])),'and');
        }

        //订单数据查询
        $orderModel = new Order();
        $datas = $orderModel->where( $where )->order('id desc')->paginate(10,'',['query' => $this->request->param()]);
        $page  = $datas->render();
        //查询商品信息
        $goodsIdList = array();
        $desingerIdList = array();
        $designerModel = new Designer();
        foreach ($datas as $key => $order) {
            $datas[$key] = $order->toArray();
            if ( !in_array($order->logs_goods_id, $goodsIdList) ) {
                array_push($goodsIdList, $order->logs_goods_id);
            }
	    array_push($desingerIdList, $order->designer_id);
        }

        if ( count( $datas ) > 0 ) {
            //商品查询
            $goodsList = $goodsModel->field('id, name, cover, type')->where( array( 'id' => array( 'in', $goodsIdList ) ) )->select();
            //设计师查询
            $desingerList = $designerModel->field('designer_id,designer_name')->where( array('designer_id'=>array('in',$desingerIdList)) )->select();
            //商品数据拼接
            foreach ($datas as $key => $order) {
                $order['created_at'] = date('Y-m-d H:i:s', $order['created_at']);
                $order['order_status_name']= $orderModel->orderStatus( $order['order_status'] );
                $order['speed_name'] = $orderModel->SpeedStatus( $order['speed_status'] );
                $order['goods_cover']='';
                $order['goods_name']='';
                foreach ($goodsList as $tag => $goods) {
                    if ( $goods->id == $order['logs_goods_id'] ) {
                        $order['goods_name'] = $goods->name;
                        $order['goods_cover']= $goods->cover;
                        $order['goods_type'] = $goods->type;
                        break 1;
                    }
                }
                foreach ($desingerList as $designer) {
                    if ( $designer->designer_id == $order['designer_id'] ) {
                        $order['designer_name'] = $designer->designer_name;
                        break 1;
                    }
                }
                $datas[$key] = $order;
            }
        }
        $this->assign("speedStatus", Order::$speed_state_arr);
        $this->assign('param',$param);
        $this->assign('pageTotal',$datas->total());
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

    /**
     * 订单详情
     */
    public function detail() {
        //获取订单信息
        $orderId = $this->request->param('orderId','','intval');
        $orderModel = new Order();

        $where = array( 'id'=>$orderId, 'delete_status'=>0 );
        $orderInfo = $orderModel->where( $where )->find();

        if(!$orderInfo){
            die('wrong logs order info');
        }
        $orderInfo['order_state'] = $orderModel->orderStatus($orderInfo['order_status']);
        $orderInfo['speed_state'] = $orderModel->speedStatus($orderInfo['speed_status']);

        //查询整装商品
        $logsInfo = (new Goods())->field('id,cover,name,prize,category_id')
            ->where(array('id'=>$orderInfo['logs_goods_id']))
            ->find();
        //查询户型图
        $houseImage = Db::name('logs_decoration_order_diagram')
            ->where( array('logs_order_id'=>$orderId) )
            ->column('image_url');

        //设计师信息查询
        $designerModel = new Designer();
        $designer      = $designerModel->where( array( 'designer_id' => $orderInfo['designer_id'] ) )->find();
        //查询设计师级别
        $level = Db::name('designer_level')->where( array( 'level_id' => $designer['level_id'] ) )->find();
        $designer['level_name'] = isset( $level['level_name'] ) ? $level['level_name'] : '';

        //查询orderCommon表,进程是待设计后面
        if( $orderInfo['speed_status'] > $orderModel::LOGS_SPEED_MEASURE ) {
            $commonModel = new OrderCommon();
            $commonIfo = $commonModel->getOrderCommonInfo( ['order_id'=>$orderId] );
            //量房数据
            $commonIfo->measure_info = json_decode($commonIfo->measure_info, true);

            //量房图片
            if( $commonIfo->measure_image != null ) {
                $commonIfo->measure_image = json_decode($commonIfo->measure_image, true);
            }

            //预约线下
            if( $commonIfo->offline_info != null ) {
                $commonIfo->offline_info = json_decode($commonIfo->offline_info, true);
            }

            //签订合同
            if( $commonIfo->contract_info != null ) {
                $commonIfo->contract_info = json_decode($commonIfo->contract_info, true);
            }

            //通知验收
            if( $commonIfo->accept_info != null ) {
                $commonIfo->accept_info = json_decode($commonIfo->accept_info, true);
            }

            //设计图片
            if( $commonIfo->design_image != null ) {
                $commonIfo->design_image = json_decode($commonIfo->design_image, true);
            }
            //修改申请记录
            if( $commonIfo->is_modify ) {
                $commonIfo->modify_info = json_decode($commonIfo->modify_info, true);
            }
            $this->assign('commonInfo', $commonIfo);
        }
        //假如是待验收和交易完成，查询装修进度
        if( $orderInfo['speed_status'] >= $orderModel::LOGS_SPEED_ACCEPT ) {
            $speedModel = new LogsDecorationOrderSpeed();
            $speedList = $speedModel->getOrderSpeedInfo( ['order_id'=>$orderId] );

            $count = count($speedList)+1;
            $this->assign('count',$count);
            $this->assign('speedList', $speedList);
        }
        $orderInfo['payment_name'] = $orderModel->orderPaymentName($orderInfo['payment_code']);
        $this->assign('logsInfo',$logsInfo);
        $this->assign('orderInfo',$orderInfo);
        $this->assign('houseImage',$houseImage);
        $this->assign('designer', $designer);
        $this->assign('houseStyle', $this->houseStyle);

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
        $param = $this->request->param();
//        if(empty($param['goods_name']) && empty($param['start']) && empty($param['end'])){
//            $this->error('请传递查询参数');
//        }
        $goodsIdList = array();
        $desingerIdList = array();
        $where = array( 'delete_status' => 0 );
        //如果有传入商品名称，则需要查询商品可能的ID，再去查询数据
        if ( isset( $param['goods_name'] ) && trim( $param['goods_name'] ) != '' ) {
            $whereGoods  = array( 'name' => array( 'like', '%'.$param['goods_name'].'%' ) );
            $goodsIdList = Db::name( 'logs_decoration_goods' )->where( $whereGoods )->column('id');
            $where['logs_goods_id'] = array( 'in', $goodsIdList );
        }
        //如果传入时间
        if(!empty($param['start'])){
            $where['created_at'] = array('gt',strtotime($param['start']));
        }

        if(!empty($param['end'])){
            $where['created_at'] = array('lt',strtotime($param['end']));
        }

        if(!empty($param['start']) && !empty($param['end'])){
            $where['created_at'] = array(array('gt',strtotime($param['start'])),array('lt',strtotime($param['end'])),'and');
        }
        //订单数据查询
        $orderModel = new Order();
        $datas = $orderModel->where( $where )->select();
        $datas = Model::getResultByFild($datas);
        if(empty($datas)) $this->error('没有可以被导出的订单数据');

        //查询商品信息
        $goodsModel = new Goods();
        $designerModel = new Designer();
        foreach ($datas as $key => $order) {
            if ( !in_array($order['logs_goods_id'], $goodsIdList) ) {
                array_push($goodsIdList, $order['logs_goods_id']);
            }
            array_push($desingerIdList, $order['designer_id']);
        }
       

        //商品查询
        $goodsList = $goodsModel->field('id, name, cover, type')->where( array( 'id' => array( 'in', $goodsIdList ) ) )->select();
        //设计师查询
        $desingerList = $designerModel->field('designer_id,designer_name')->where( array('designer_id'=>array('in',$desingerIdList)) )->select();
        //商品数据拼接
        foreach ($datas as $key => $order) {
            $order['created_at'] = date('Y-m-d H:i:s', $order['created_at']);
            $order['order_status_name']= $orderModel->orderStatus( $order['order_status'] );
            $order['speed_name'] = $orderModel->SpeedStatus( $order['speed_status'] );
            $order['designer_name']='-';
            foreach ($goodsList as $tag => $goods) {
                if ( $goods->id == $order['logs_goods_id'] ) {
                    $order['goods_name'] = $goods->name;
                    $order['goods_cover']= $goods->cover;
                    $order['goods_type'] = $goods->type;
                    break 1;
                }
            }

            foreach ($desingerList as $designer) {
                if ( $designer['designer_id'] == $order['designer_id'] ) {
                    $order['designer_name'] = $designer['designer_name'];
                    break 1;
                }
            }
            $datas[$key] = $order;
        }

        $this->createExcel($datas);
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
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'商品名称');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'	下单日期');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'订单编号');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'设计师');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'预约面积');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'进度');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'交易状态');
        $excel_data[0][] = array('styleid'=>'s_title','data'=>'诚意金状态');
        //data
        foreach ((array)$data as $k=>$v){
            $tmp = array();
            $tmp[] = array('data'=>empty($v['goods_name'])?'':$v['goods_name']);
            $tmp[] = array('data'=>$v['created_at']);
            $tmp[] = array('data'=>$v['order_sn']);
            $tmp[] = array('data'=>$v['designer_name']);
            $tmp[] = array('format'=>'Number','data'=>ncPriceFormat($v['acreage']));
            $tmp[] = array('data'=>$v['order_status_name']);
            $tmp[] = array('data'=>($v['order_status']==3 || $v['order_status']==4) ? '已支付' : '待支付');
            $excel_data[] = $tmp;
        }
        $excel_data = $excel_obj->charset($excel_data,CHARSET);
        $excel_obj->addArray($excel_data);
        $excel_obj->addWorksheet($excel_obj->charset('整装订单',CHARSET));
        $excel_obj->generateXML($excel_obj->charset('整装订单',CHARSET).'-'.date('Y-m-d-H',time()));
    }

    /**
     *取消订单操作
     */
    public function orderCancel() {

        //取消页面参数
        $orderId = $this->request->param('orderId');
        $orderInfo = $this->orderModel->getOrderInfo( array('id'=>$orderId) );

        //取消页面提交表单
        $type = $this->request->param('type');
        if($type){
            $cancelReason = $this->request->param('cancelReason');

            $data['cancel_reason'] = $cancelReason;
            $data['order_status'] = Order::LOGS_ORDER_CANCEL;

            if( mb_strlen($data['cancel_reason'],'utf-8')>100 )  $this->error('请输入100字内的理由');
            //取消订单操作
            $result = $this->orderModel->updateOrderInfo( $data , array('id'=>$orderId) );

            if($result) $this->success('取消成功','',1);
            else $this->error('取消失败','',1);
        }

        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }

    /**
     * 确认量房
     */
    public function orderMeasure(){

        //页面参数
        $orderId = $this->request->param('orderId');
        $orderInfo = $this->orderModel->getOrderInfo( array('id'=>$orderId) );

        //量房页面提交表单
        $type = $this->request->param('type');
        if($type){
            //量房信息
            $measure_people = $this->request->param('measure_people');
            $measure_mob = $this->request->param('measure_mob');
            $measure_data = $this->request->param('measure_data');

            $param = [ 'measure_people' => $measure_people, 'measure_mob' => $measure_mob, 'measure_data' => $measure_data ];

            //验证条件
            $rule = array(
                'measure_people'=> 'require',
                'measure_mob' => 'require|regex:/^1[34578]\d{9}$/',
                'measure_data' => 'max:100'
            );
            $message = array(
                'measure_people.require' => '量房人不能为空',
                'measure_mob.require' => '量房人手机号不能为空',
                'measure_mob.regex' => '量房人手机号无效',
                'measure_data.max' => '量房数据不能大于100字符'
            );
            //参数验证
            $validate = new Validate($rule, $message);
            if ( !$validate->check( $param ) )
                $this->error( $validate->getError() );


            $data['measure_info'] = json_encode($param);
            //量房图片
            $imgs = $this->request->param('measure_img/a', '', 'trim');
            if( empty($imgs) ) $this->error('量房图片不能为空');
            $data['measure_image'] = json_encode($imgs);
            $data['order_id'] = $orderId;
            $res = $this->orderCommonModel->insertMeasureInfo( $data , $orderId);

            if ($res === true) $this->success('保存成功','',1);
            else $this->error( $res );
        }

        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }

    /**
     * 上传设计
     */
    public function orderDesign(){
        //页面参数
        $orderId = $this->request->param('orderId', '', 'intval');
        $orderInfo = $this->orderModel->getOrderInfo( array('id'=>$orderId) );
        $orderCommonInfo = $this->orderCommonModel->getOrderCommonInfo(['order_id'=>$orderId], 'id,design_image,is_modify');
        if( $orderCommonInfo->is_modify) {//假如是需要重新设计
            $orderCommonInfo->design_image = json_decode($orderCommonInfo->design_image, true);
        }

        //设计师信息查询
        $field = array('designer_name','designer_phone');
        $where = array( 'designer_id' => $orderInfo['designer_id'] );
        $designer = $this->orderModel->getDesignerInfo( $where , $field );

        //设计页面提交表单
        $type = $this->request->param('type');
        if($type){
            $style = $this->houseStyle;
            $styleImage = [];
            foreach( $style as $key => $val) {
                if( !empty( $this->request->param('img_url'.$key.'/a') ) ) {
                    $styleImage[$key] = $this->request->param('img_url'.$key.'/a');
                }
            }
            if ( count( $styleImage ) == 0 ) {
                $this->error( '设计图不能为空' );
            }
            $data['design_image'] = json_encode($styleImage);
            $data['id'] = $orderCommonInfo->id;
            //保存数据，并修改进度状态
            $res = $this->orderCommonModel->insertDesignImage( $data, $orderId);

            if ($res === true) {
                //发送通知短信查看设计
                $result = ( new SentTemplatesSMS() )->sent($orderInfo->phone, [$orderInfo->phone], "logs_designed");
                $this->success('保存成功','',1);
            }
            else $this->error( $res );
        }

        $this->assign('designer',$designer);
        $this->assign('order_info',$orderInfo);
        $this->assign('house_style',$this->houseStyle);
        $this->assign('common_info', $orderCommonInfo);
        return $this->fetch();
    }

    /**
     * 预约线下
     */
    public function orderOffline(){
        //页面参数,flag为1表示线下预约，为2表示签订合同
        $orderId = $this->request->param('orderId');
        $flag = $this->request->param('flag');
        $orderInfo = $this->orderModel->getOrderInfo( array('id'=>$orderId) );

        $orderCommonId = Db::name('logs_decoration_order_common')->where(array('order_id'=>$orderId))->value('id');

        //设计师信息查询
        $field = array('designer_name','designer_phone');
        $where = array( 'designer_id' => $orderInfo['designer_id'] );
        $designer = $this->orderModel->getDesignerInfo( $where , $field );

        $type = $this->request->param('type');
        if($type){
            if($flag == 1){
                //$param = $this->request->param();
                $param['offlineTime'] = $this->request->param('offlineTime');
                $param['address'] = $this->request->param('address');

                //判断条件
                if(empty($param['offlineTime']))      $this->error('预约时间不能为空');
                if(empty($param['address']))       $this->error('预约地址不能为空');
                if(mb_strlen($param['address'],'utf-8')>64)       $this->error('预约地址不能大于64字');

                $data['offline_info'] = json_encode($param);

                //保存线下预约数据
                Db::startTrans();
                $res = $this->orderCommonModel->updateOrderCommon($data,array('id'=>$orderCommonId));
                if( $res ){
                    $result = $this->orderModel->updateOrderInfo(['is_offline'=>1],['id'=>$orderId]);
                    if( !$result ){
                        Db::rollback();
                        $this->error('修改订单表失败','',1);
                    }
                }else{
                    $this->error('订单扩展数据添加失败','',1);
                }

                // 提交事务
                Db::commit();
                $this->success('保存成功','',1);
            }else{
                $param = $this->request->param();
                $res = $this->orderCommonModel->insertContractInfo($param,$orderCommonId,$orderId);

                if ($res === true) $this->success('保存成功','',1);
                else $this->error( $res );
            }

        }
        $this->assign('flag',$flag);
        $this->assign('designer',$designer);
        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }

    /**
     * 上传进度以及通知验收
     */
    public function orderSpeed(){

        //页面参数,flag为1表示上传进度，为2表示通知验收
        $orderId = $this->request->param('orderId');
        $flag = $this->request->param('flag');
        $orderInfo = $this->orderModel->getOrderInfo( array('id'=>$orderId) );
        $commonInfo = $this->orderCommonModel->getOrderCommonInfo( array('order_id'=>$orderId) );

        //如果第一次进度，则显示合同日期;若不是，则显示最新一次更新
        $speedInfo = Db::name('logs_decoration_order_speed')->where(array('order_id'=>$orderId))->select();
        if( empty($speedInfo) ){
            $this->assign('date',json_decode($commonInfo['contract_info'])->contractTime);
        }else{
            $lastspeed = json_decode($speedInfo[count($speedInfo)-1]['speed_info'],true);
            $this->assign('date',date('Y-m-d',$lastspeed['time']));
        }

        $type = $this->request->param('type');
        if($type){
            if($flag == 1){//上传进度

                $param['speedDesc'] = $this->request->param('speedDesc');
                if( empty($param['speedDesc']) ) $this->error('请填写上传进度说明');
                if(mb_strlen($param['speedDesc'],'utf-8')>140) $this->error('进度备注说明应小于140字');
                $param['time'] = time();
                $imgs = $this->request->param('speed_img/a', '', 'trim');
                if( empty($imgs) ) $this->error('请上传图片');
                $imgStr = '';
                foreach($imgs as $val) {
                    $imgStr .= $val.';';
                }
                $imgStr = substr($imgStr, 0, strlen($imgStr)-1);
                $param['speed_img'] = $imgStr;

                $data['speed_info'] = json_encode($param);

                $data['order_id'] = $orderId;
                //保存上传数据
                $res = Db::name('logs_decoration_order_speed')->insert($data);

                if(!$res)  $this->error('订单进度信息添加失败','',1);
                else {
                    $this->success('保存成功','',1);
                }
            }else{//通知验收
                $param['finishTime'] = $this->request->param('finishTime');
                $param['finishDesc'] = $this->request->param('finishDesc');

                //判断条件
                if(empty($param['finishTime']))  $this->error('施工完成日期不能为空');
                if(mb_strlen($param['finishDesc'],'utf-8')>140)  $this->error('备注说明应小于140字');

                $data['accept_info'] = json_encode($param);
                $data['is_notify'] = 1;

                //保存验收数据

                Db::startTrans();
                $res = $this->orderCommonModel->updateOrderCommon( $data , array('id'=>$commonInfo['id']) );
                if( $res ){
                    $result = $this->orderModel->updateOrderInfo(['is_verify'=>1],['id'=>$orderId]);
                    if( !$result ){
                        Db::rollback();
                        $this->error('修改订单表失败','',1);
                    }
                }else{
                    $this->error('订单扩展数据添加失败','',1);
                }

                // 提交事务
                Db::commit();
                //发送通知短信验收
                $result = ( new SentTemplatesSMS() )->sent($orderInfo->phone, [$orderInfo->phone], "logs_check");
                $this->success('保存成功','',1);
            }
        }
        $this->assign('flag',$flag);
        $this->assign('order_info',$orderInfo);
        return $this->fetch();
    }

    /**
     * 通过兑换商品
     */
    public function orderExchangeGood() {
        $orderId = $this->request->param('order_id', '', 'intval');
        //兑换页面提交表单
        $type = $this->request->param('type');
        if( $type ) {
            if( !$orderId )
                $this->error('订单数据错误','',1);
            if( !$this->orderModel->save( ['speed_status' => Order::LOGS_SPEED_EXCHANGE],['id' => $orderId] ) ) {
                $this->error('同意兑换商品失败','',1);
            }
            $this->success('同意兑换商品成功','',1);
        }
        $orderInfo = $this->orderModel->getOrderInfo( array('id'=>$orderId) );
        $designerName = ( new Designer() )->where('designer_id',$orderInfo->designer_id)->value('designer_name');
        $measure = $this->orderCommonModel->where('order_id',$orderId)->value('measure_info');
        $measureName = json_decode($measure,true)['measure_people'];
        $this->assign('order_info',$orderInfo);
        $this->assign('designerName',$designerName);
        $this->assign('measureName',$measureName);
        return $this->fetch();
    }
}
