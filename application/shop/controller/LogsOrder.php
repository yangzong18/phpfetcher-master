<?php
/**
 * 原木整装订单
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-12-07 17:21
 */
namespace app\shop\controller;
use app\common\controller\Member;
use app\common\logic\LogsBuy;
use app\common\model\GoodsCategory;
use app\common\model\LogsDecorationGoods as Goods;
use app\common\model\LogsDecorationOrder as Orders;
use app\common\model\LogsDecorationOrderCommon as OrderCommon;
use app\common\model\Designer;
use app\common\model\LogsDecorationOrderSpeed;
use app\common\model\Order;
use app\common\model\Payment;
use app\shop\model\DesignerProduction;
use think\Db;
use think\Config;

class LogsOrder extends Member
{
    //设计图类型
    public $houseStyle;

    //进度提示信息
    public $speedMessage = array(
        Orders::LOGS_SPEED_MEASURE => ['', '已经进入免费量房预约专业设计！请及时关注预约信息！'],
        Orders::LOGS_SPEED_PAY => ['', '已经完成量房！请及时支付诚意金！'],
        Orders::LOGS_SPEED_DESIGN => ['', '等待设计师设计，上传效果图！'],
        Orders::LOGS_SPEED_DESIGNED => ['线下沟通是整装的重要部分，您可以提交在线预约进行线下交流',
                                          '设计师接单，已经完成设计，你可以查看设计效果图！'],
        Orders::LOGS_SPEED_SIGN => ['您的预约已经提交，我们将很快联系您！请保持手机正常状态！网上支付装修定金，可以抵扣优惠',
                                     '感谢预约，签订合同后即可进入装修流程！'],
        Orders::LOGS_SPEED_ACCEPT => ['您的合同已成功签订，请随时关注装修进度', '您的合同已成功签订，请随时关注装修进度！'],
        Orders::LOGS_SPEED_ACCEPTED => ['','您的订单已经完成，请下次光顾！'],
        Orders::LOGS_SPEED_EXCHANGE => ['线下沟通是整装的重要部分，您可以提交在线预约进行线下交流',
            '设计师接单，已经完成设计，你可以查看设计效果图！'],
        Orders::LOGS_SPEED_EXCHANGEED=> ['','您的订单已经完成兑换商品操作，请下次光顾！']
    );

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->houseStyle = ( new Goods() )->getHouseType();
    } 

    /**
     * 原木整装订单确认页面
     */
    public function index( ) {
        $goodsId    = $this->request->param('gk', 0, 'intval');
        $designerId = $this->request->param('dk', 0, 'intval');
        $goods      = null;
        $designer   = null;
        //查询商品是否存在
        $goodsModel = new Goods();
        if ( $goodsId > 0 ) {
            $where   = array( 'id' => $goodsId, 'is_delete' => 0, 'goods_verify'=>1, 'type'=> 1 );
            $goods   = $goodsModel->where( $where )->find();
        }
        //查询设计师是否存在
        $designerModel = new Designer();
        if ( $designerId > 0 ) {
            $designer      = $designerModel->where( array( 'designer_id' => $designerId, 'is_delete' => 0 ) )->find();
        }
        if ( !$goods && !$designer ) {
            $this->error('商品已下架');
        }
        //如果商品信息不存在，则查询最新发布的商品
        if ( !$goods ) {
            $where   = array( 'is_delete' => 0 , 'goods_verify'=>1, 'type'=> 1 );
            $goods   = $goodsModel->where( $where )->order('goods_sell_time desc')->find();
        } else {
            $designer      = $designerModel->where( array( 'designer_id' => $goods['designer_id'] ) )->find();
        }
        //查询设计师级别
        $level = Db::name('designer_level')->where( array( 'level_id' => $designer['level_id'] ) )->find();
        $designer['level_name'] = isset( $level['level_name'] ) ? $level['level_name'] : '';
        $this->assign('goods', $goods);
        $this->assign('designer', $designer);
        $this->assign('houseType', $this->houseStyle);
        return $this->fetch();
    }

    /**
     * 二级代理商家下单
     */
    public function vip() {
        if ( $this->login == 0 ) {
            $this->redirect(Config::get('url_domain_protocol').Config::get('url_domain_root').'/shop/login/login');
        }
        //如果不是二级代理用户，则提示没有权限
        if ( $this->user['type'] != 2 ) {
            $this->error('用户不是二级代理用户');
        }
        //查找最新发布的二级代理商品
        $goodsModel = new Goods();
        $where   = array( 'is_delete' => 0 , 'goods_verify'=>1, 'type'=> 2 );
        $goods   = $goodsModel->where( $where )->order('goods_sell_time desc')->find();
        if ( !$goods ) {
            $this->error('未发布代理商品, 请联系管理员');
        }
        //设计师查询
        $designerModel = new Designer();
        $designer      = $designerModel->where( array( 'designer_id' => $goods['designer_id'] ) )->find();
        $level = Db::name('designer_level')->where( array( 'level_id' => $designer['level_id'] ) )->find();
        $designer['level_name'] = isset( $level['level_name'] ) ? $level['level_name'] : '';
        $this->assign('goods', $goods);
        $this->assign('designer', $designer);
        $this->assign('houseType', $this->houseStyle);
        return $this->fetch();
    }

    /**
     * 原木整装下单方法
     * @param   int  logs_goods_id 整装商品ID
     * @param   string user_name 用户姓名
     * @param   int gender 用户性别，1男,2女
     * @param   string   phone 用户联系电话
     * @param   string   house_type 房屋类型
     * @param   string   province 省
     * @param   string   city 市
     * @param   string   area 区
     * @param   string   address 详细地址
     * @param   string   acreage 建筑面积
     * @param   string   building_name 楼盘名称
     * @param   int   designer_id 设计师
     * @param   string   recommend_user_phone 联系人电话，非必填
     * @param   string   message 用户留言,非必填
     * @return 标准输出
     */
    public function add() {
        $param    = $this->request->param();
        $buyLogic = new LogsBuy();
        $result   = $buyLogic->buy( $this->user, $param );
        if ( $result === true ) {
            $this->success('订单添加成功', url('shop/logsOrder/detail', array('orderId' => $buyLogic->newOrderId )) );
        } else {
            $this->error( $result );
        }
    }

    /**
     * 原木整装订单列表页
     */
    public function lists() {
        $orderModel = new Orders();
        $type = $this->request->param('type','all','trim');
        //搜索
        $logsName = $this->request->param('logs_name');
        $logsId = Db::name('logs_decoration_goods')->where('name','like','%'.$logsName.'%')->column('id');
        if( empty($logsId) ) $logsId = array(0);
        $where['logs_goods_id'] =  array('in',$logsId);

        switch ($type){
            case 'all':
                break;
            /*待免费量房*/
            case 'measure':
                $where['speed_status'] = $orderModel::LOGS_SPEED_MEASURE;
                $where['order_status'] = $orderModel::LOGS_ORDER_VERIFY;
                break;
            /*待支付诚意金*/
            case 'pay':
                $where['speed_status'] = $orderModel::LOGS_SPEED_PAY;
                $where['order_status'] = $orderModel::LOGS_ORDER_NEW;
                break;
            /*待设计*/
            case 'design':
                $where['speed_status'] = $orderModel::LOGS_SPEED_DESIGN;
                break;
            /*待确认*/
            case 'designed':
                $where['speed_status'] = $orderModel::LOGS_SPEED_DESIGNED;
                break;
            /*待签合同*/
            case 'sign':
                $where['speed_status'] = $orderModel::LOGS_SPEED_SIGN;
                break;
            /*待验收*/
            case 'accept':
                $where['speed_status'] = $orderModel::LOGS_SPEED_ACCEPT;
                break;
            /*待兑换商品*/
            case 'exchange':
                $where['speed_status'] = $orderModel::LOGS_SPEED_EXCHANGE;
                break;
            /*已完成*/
            case 'finish':
                $where['speed_status'] = $orderModel::LOGS_SPEED_ACCEPTED;
                break;
            /*已关闭*/
            case 'close':
                $where['order_status'] = $orderModel::LOGS_ORDER_CANCEL;
                break;

        }
        $where['member_id'] = $this->user['member_id'];
        $where['delete_status'] = 0;
        $count = Db::name('logs_decoration_order')->distinct(true)->field('id')->where($where)->order('id desc')->select();
        //$datas = Db::name('logs_decoration_order')->distinct(true)->field('*')->where($where)->order('id desc')->paginate(10,count($count),['query' => array('speed_status'=>$type),]);
        $datas = $orderModel->where( $where )->order('id desc')->paginate();


        $goodsIdList = array();
        $desingerIdList = array();
        foreach ($datas as $key => $order) {
            $datas[$key] = $order->toArray();
            if ( !in_array($order->logs_goods_id, $goodsIdList) ) {
                array_push($goodsIdList, $order->logs_goods_id);
            }
            array_push($desingerIdList, $order->designer_id);
        }

        $goodsModel = new Goods();
        $designerModel = new Designer();
        if ( count( $datas ) > 0 ) {
            //商品查询
            $goodsList = $goodsModel->field('id, name, cover, type')->where( array( 'id' => array( 'in', $goodsIdList ) ) )->select();
            //设计师查询
            $desingerList = $designerModel->field('designer_id,designer_name,designer_avatar')->where( array('designer_id'=>array('in',$desingerIdList)) )->select();
            //商品数据拼接
            foreach ($datas as $key => $order) {
                $order['created_at'] = date('Y-m-d H:i:s', $order['created_at']);
                $order['order_status_name']= $orderModel->orderStatus( $order['order_status'] );
                $order['speed_name'] = $orderModel->SpeedStatus( $order['speed_status'] );
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
                        $order['designer_avatar'] = $designer->designer_avatar;
                        break 1;
                    }
                }
                $datas[$key] = $order;
            }
        }

        $page = $this->dealPage($datas->render());

        //待免费量房
        $countOne 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'speed_status'=>$orderModel::LOGS_SPEED_MEASURE,'order_status'=>$orderModel::LOGS_ORDER_VERIFY,'delete_status'=>0],'id');
        //待付定金订单
        $countTwo 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'speed_status'=>$orderModel::LOGS_SPEED_PAY,'order_status'=>$orderModel::LOGS_ORDER_NEW,'delete_status'=>0],'id');
        //待设计
        $countThree 	= $orderModel->getCount(['member_id'=>$this->user['member_id'],'speed_status'=>$orderModel::LOGS_SPEED_DESIGN,'delete_status'=>0],'id');
        //待确认
        $countFour 	    = $orderModel->getCount(['member_id'=>$this->user['member_id'],'speed_status'=>$orderModel::LOGS_SPEED_DESIGNED,'delete_status'=>0],'id');
        //待签合同
        $countFive 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'speed_status'=>$orderModel::LOGS_SPEED_SIGN,'delete_status'=>0],'id');
        //待验收
        $countSix 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'speed_status'=>$orderModel::LOGS_SPEED_ACCEPT,'delete_status'=>0],'id');
        //待兑换商品
        $countSeven 	= $orderModel->getCount(['member_id'=>$this->user['member_id'],'speed_status'=>$orderModel::LOGS_SPEED_EXCHANGE,'delete_status'=>0],'id');
        //已完成
        $countEight		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'speed_status'=>$orderModel::LOGS_SPEED_ACCEPTED,'delete_status'=>0],'id');
        //已关闭
        $countNine 		= $orderModel->getCount(['member_id'=>$this->user['member_id'],'order_status'=>$orderModel::LOGS_ORDER_CANCEL ,'delete_status'=>0],'id');

        $array = ['measure'=>$countOne,'pay'=>$countTwo,'design'=>$countThree,'designed'=>$countFour,'sign'=>$countFive,'accept'=>$countSix,'exchange'=>$countSeven,'finish'=>$countEight,'close'=>$countNine];
        $this->assign('array',$array);
        $this->assign('list',$datas);
        $this->assign('page',$page);
        $this->assign('typeA',$type);
        $this->assign('logs_name',$logsName);
        return $this->fetch();
    }

    /**
     * 支付诚意金
     */
    public function pay() {
        $orderId = $this->request->param('orderId');
        $paySn = $this->request->param('paySn');
        $gotoURL = url('shop/logs_order/detail', array('orderId'=>$orderId) );

        if( $paySn == '' ) $this->error('该订单不存在', $gotoURL);

        //查询支付单信息
        $orderModel= new Orders();
        $payInfo = $orderModel->getOrderPayInfo(array('pay_sn'=>$paySn,'member_id'=>$this->user['member_id']));
        if( empty($payInfo) ) $this->error('该订单不存在',$gotoURL);
        $this->assign('pay_info',$payInfo);

        //取子订单列表
        $condition = array();
        $condition['pay_sn'] = $paySn;
        $condition['order_status'] = array('in',array(Orders::LOGS_ORDER_VERIFY,Orders::LOGS_ORDER_NEW));
        $orderInfo = $orderModel->getOrderInfo( $condition );
        if (empty($orderInfo)) $this->error('未找到需要支付的订单',$gotoURL);
        //查询整装商品名称
        $goods = new Goods();
        $orderInfo['logs_name'] = $goods->where( array('id'=>$orderInfo['logs_goods_id']) )->value('name');
        //查询设计师名字
        $designerModel = new Designer();
        $orderInfo['designer_name'] = $designerModel->where( array( 'designer_id' => $orderInfo['designer_id'] ) )->value('designer_name');

        $payAmountOnline = $orderInfo['deposit'];
        $orderInfo['payment_state'] = '在线支付';

        //如果线上线下支付金额都为0，转到支付成功页
        if ( $payAmountOnline <= 0 )
            $this->redirect('shop/logs_order/payOk', array('pay_sn'=>$paySn));

        //显示支付接口列表
        $paymentList = array();
        if( $payAmountOnline > 0 ) {
            $paymentModel = new Payment();
            $condition = array();
            $paymentList = $paymentModel->getPaymentOpenList( $condition );
            if ( !empty($paymentList) ) {
                unset($paymentList['predeposit']);
                unset($paymentList['offline']);
            }
            if (empty($paymentList))  $this->error('暂未找到合适的支付方式', $gotoURL);
        }

        //输出订单描述
        $this->assign('order_remind','请您及时付款，以便订单尽快处理！');
        $this->assign('pay_amount_online',ncPriceFormat($payAmountOnline));
        $this->assign('orderInfo',$orderInfo);
        $this->assign('payment_list',$paymentList);
        return $this->fetch();
    }


    /**
     * 支付成功
     */
    function payok()
    {
        $paySn = $this->request->param('pay_sn');
        if( empty($paySn) )
            $this->error('该订单不存在', url('shop/logs_order/lists'));
        //查询支付单信息
        $orderModel= new Orders();
        $payInfo = $orderModel->getOrderInfo(array('pay_sn'=>$paySn,'member_id'=>$this->user['member_id']));
        if( empty($payInfo) ) $this->error('该订单不存在', url('shop/logs_order/lists'));
        $payAmount = $this->request->param('pay_amount');
        $this->assign('pay_info',$payInfo);
        $this->assign('pay_amount',$payInfo->deposit);
        return $this->fetch();
    }


    /**
     * 订单详情页
     */
    public function detail(){
        //获取订单信息
        $orderId = $this->request->param('orderId', '', 'intval');
        $orderModel = new Orders();
        $where = array( 'id'=>$orderId, 'delete_status'=>0, 'member_id' => $this->user->member_id);
        $orderInfo = $orderModel->where( $where )->find();
        if(!$orderInfo){
            $this->error('wrong logs order info');
        }
        $orderInfo['order_state'] = $orderModel->orderStatus($orderInfo['order_status']);
        $orderInfo['speed_state'] = $orderModel->speedStatus($orderInfo['speed_status']);

        //查询整装商品
        $logsInfo = (new Goods())->field('id,cover,name,prize,category_id,type')
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

            //通知验收
            if( $commonIfo->accept_info != null ) {
                $commonIfo->accept_info = json_decode($commonIfo->accept_info, true);
            }

            //签订合同
            if( $commonIfo->contract_info != null ) {
                $commonIfo->contract_info = json_decode($commonIfo->contract_info, true);
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

            $this->assign('speedList', $speedList);
        }
        $orderInfo['payment_name'] = $orderModel->orderPaymentName($orderInfo['payment_code']);
        $this->assign('logsInfo',$logsInfo);
        $this->assign('orderInfo',$orderInfo);
        $this->assign('houseImage',$houseImage);
        $this->assign('designer', $designer);
        $this->assign('speedMessage', $this->speedMessage);
        $this->assign('houseStyle', $this->houseStyle);

        if( $orderInfo['order_status'] == Orders::LOGS_ORDER_SUCCESS ) return $this->fetch('finishdetail');
        else return $this->fetch();
    }

    /**
     * 用户申请修改
     */
    public function modify(){
        //订单号id
        $orderId = $this->request->param('orderId','','intval');
        $modifyInfo = $this->request->param('modifyInfo', '', 'trim');
        if( $modifyInfo == '' ) {
            $this->error('修改意见不能为空');
        }
        if( mb_strlen($modifyInfo,'utf-8') > 100 ){
            $this->error('修改意见不能超过100字');
        }
        //处理数据
        $orderCommon = new OrderCommon();
        $order = new Orders();
        $param = [];
        $param['modifyInfo'] = $modifyInfo;
        $param['time'] = time();
        $param['member_name'] = $this->user->member_name;

       //保存数据，并修改进度状态
        Db::startTrans();
        $res = $orderCommon->save( ['modify_info'=>json_encode($param), 'is_modify'=>1], ['order_id'=>$orderId] );
        if( !$res ) {
            Db::rollback();
            $this->error('订单扩展数据添加失败');
        }
        if( !$order->save( ['speed_status'=>Orders::LOGS_SPEED_DESIGN], ['id'=>$orderId] )) {
            Db::rollback();
            $this->error('订单状态修改失败','',1);
        }

        Db::commit();
        $this->success('提交成功');
    }

    /**
     * 用户确认订单，线下预约
     */
    public function confirmDesign() {
        //获取订单信息
        $orderId = $this->request->param('orderId', '', 'intval');
        $orderModel = new Orders();
        $where = [ 'id' => $orderId, 'delete_status' => 0, 'member_id' => $this->user->member_id];

        //开启事务
        Db::startTrans();
        $productionModel = new DesignerProduction();
        if( $orderModel->updateOrderInfo(['speed_status' => $orderModel::LOGS_SPEED_SIGN], $where) ) {//修改订单进度
            //修改进度成功后，将设计图保存到设计师作品中
            $production = [];
            $production['upload_time'] = time();
            $production['production_name'] = $this->request->param('production_name', '', 'trim');
            $production['designer_id'] = $this->request->param('designer_id', 0, 'intval');
            $production['order_sn'] = $this->request->param('order_sn', 0, 'trim');
            $production['house_type'] = $this->request->param('house_type', 0, 'intval');
            $production['building_area'] = $this->request->param('building_area', 0, 'intval');
            $category = $this->request->param('style', 0, 'trim');
            $production['style'] = ( new GoodsCategory() )->where('category_id', $category)->value('parent_id');
            $production['imgs'] = json_encode( explode(',',$this->request->param('imgs', 0, 'trim')) );
            $production['is_from_order'] = 1;
            if( !$productionModel->save($production) ) {
                Db::rollback();
                $this->error('上传设计作品失败');
            }
        } else {
            $this->error('预约失败');
        }
        // 提交事务
        Db::commit();
        $this->success('预约成功');
    }

    /**
     * 用户确认验收，交易成功
     */
    public function finishOrder() {
        $orderId = $this->request->param('id', 0, 'trim');

        $orderModel = new Orders();
        $where = [ 'id' => $orderId, 'delete_status' => 0, 'member_id' => $this->user->member_id];
        $data['speed_status'] = Orders::LOGS_SPEED_ACCEPTED;
        $data['order_status'] = Orders::LOGS_ORDER_SUCCESS;
        $data['finish_time'] = time();
        if( !$orderModel->save($data, $where ) ) {
            $this->error('确认验收失败');
        }
        $this->success('验收成功，订单交易完成');
    }

    /**
     * 重新设计后，用户不满意
     */
    public function unSatisfyDesign() {
        $orderId = $this->request->param('orderId', '', 'intval');
        $unsatisfyInfo = $this->request->param('phone', '', 'trim');
        if( $unsatisfyInfo == '' ) $this->error('预留电话不能为空');

        $isMob="/^1[34578]\d{9}$/";
        $isTel="/^([0-9]{3,4}-)?[0-9]{7,8}$/";
        if(!preg_match($isMob,$unsatisfyInfo) && !preg_match($isTel,$unsatisfyInfo)) {
            $this->error('手机或电话号码格式不正确。如果是固定电话，必须形如(xxxx-xxxxxxxx)!');
        }


        $orderModel = new Orders();
        $where = array( 'id'=>$orderId, 'delete_status'=>0, 'member_id' => $this->user->member_id );
        $orderInfo = $orderModel->where( $where )->find();
        if(!$orderInfo){
            $this->error('wrong logs order info');
        }
        //提交数据
        if( !$orderModel->save(['unsatisfy_info' => $unsatisfyInfo], ['id'=>$orderId]) ) {
            $this->error('提交失败');
        }
        $this->success('提交成功，请保持电话畅通');
    }

    /**
     * 用户兑换商品
     */
    public function exchangeGoods() {
        //获取订单信息
        $orderId = $this->request->param('id', '', 'intval');
        $orderModel = new Orders();
        $where = array( 'id'=>$orderId, 'delete_status'=>0 );
        $orderInfo = $orderModel->where( $where )->find();
        if(!$orderInfo){
            $this->error('wrong logs order info');
        }
        $list = [];
        //查询兑换商品列表
        $where = [];
        $where['goods_verify'] = 1;
        $where['is_delete'] = 0;
        $where['goods_type'] = 2;
        $where['gs.goods_storage'] = ['gt',0];

        $fields = 'goods_name,goods_sku,g.goods_id as goods_id,gs.goods_price as goods_price,goods_image_main';
        $join = [['mgt_goods_sku gs','g.goods_id=gs.goods_id']];
        $goodsList = Db::name('goods')->field($fields)->alias('g')->join($join)->where($where)->select();

        if( empty($goodsList) ) {//没有兑换商品
            $this->assign('goods_list', []);
        } else {
            $this->assign('goods_list', $goodsList);
        }
        $this->assign('orderInfo',$orderInfo);
        return $this->fetch();
    }
}
