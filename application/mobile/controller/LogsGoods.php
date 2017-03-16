<?php
/**
 * 原木整装商品
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷
 */
namespace app\mobile\controller;
use app\common\controller\SentTemplatesSMS;
use app\common\logic\Auth;
use app\common\model\Designer;
use app\common\model\GoodsCategory;
use app\common\model\LogsDecorationGoods;
use app\common\model\Member;
use app\common\model\MobileLogsDecorationOrder;
use app\common\model\MobileMemberToken;
use think\Config;
use think\Db;
use think\Exception;
use think\Validate;
use Util\Tools;

class LogsGoods extends MobileHome
{
    protected $model;
    protected $category;
    private $cidNum ;  //存放分类ID
    /**
     * 构造方法
     */
    public function __construct(){
        parent::__construct();
        $this->model = new LogsDecorationGoods();
        $this->category = new GoodsCategory();
        $this->cidNum = Config::get('logs_goos_category_id');
    }

    /**
     * 获取整装商品分类列表
     */
    public function cate() {
        //读取原木整体家具的二级分类
//        $category_id = $this->category->getOneId('原木整体家具');
//        if( $category_id == '')
//            $this->returnJson('', 1, '未找到数据');
        $topCategory = $this->category->getNextLevel($this->cidNum);
        if( empty($topCategory) )
            $this->returnJson('', 1, '未找到数据');

        $list= [];
        foreach( $topCategory as $val ) {
            if( $val['is_delete'] == 0 &&  $val['status'] == 1) {
                $list[] = $val->toArray();
            }
        }
        $this->returnJson($list);
    }

    /**
     * 获取商品
     */
    public function lists() {
        $where = [];
        $where['is_delete'] = 0;
        $where['goods_verify'] = 1;
        $where['type'] = 1;
        if( $this->request->has('cate') ) {//假如有分类查询
            $cateId = $this->request->param('cate', '', 'trim');
            $cateList = $this->category->getNextLevel($cateId);
            if( empty( $cateList ) )
                $this->returnJson('', 1, '未找到数据');
            $end = [];
            foreach( $cateList as $k=>$v ){
                $end[] = $v['category_id'];
            }
            $where['category_id'] = array('IN', $end);
        }
        $count = Db::name('logs_decoration_goods')->distinct(true)->where($where)->count();
        $list = Db::name('logs_decoration_goods')->field('id,name,prize,cover')->where($where)->order('goods_sell_time desc')->paginate(20,$count);
        $page = ['currentPage'=>$list->currentPage(),'lastPage'=>$list->lastPage(),'total'=>$list->total()];
        if( empty($list) || $this->request->param('page', 1, 'intval') > $list->lastPage())
            $this->returnJson('', 1, '未找到数据');
        else
            $this->returnJson(['list'=>$list,'page'=>$page]);
    }

    /**
     * 商品详情
     */
    public function detail()
    {
        //获取商品信息
        $id = $this->request->param('id', 0, 'intval');
        if( !$id ) $this->returnJson('', 1, '参数不正确');
        $goodsInfo = $this->model->field('id,cover,name,prize,category_id,designer_id,acreage')
                                   ->where(['id' => $id, 'is_delete' => 0, 'goods_verify'=>1, 'type'=> 1])
                                   ->find();
        if (!$goodsInfo) $this->returnJson('', 1, '整装商品不存在');
        $info = $goodsInfo->toArray();
        //查询商品附件
        $where = ['business_sn' => 'logs_goods', 'business_id' => $id, 'is_delete' => 0];
        $attachmentList = Db::name('attachment')->field('attachment_url')->where($where)->select();
        $info['attachmentList'] = $attachmentList;
        //查询设计师
        $designer = Db::name('designer')->where(['designer_id'=>$info['designer_id'],'is_delete'=>0])->find();
        if( !empty($designer) )
            $info['designer_name'] = $designer['designer_name'];
        //查询风格名称
        $parentId = $this->category->where('category_id', $info['category_id'])->value('parent_id');
        if($parentId != null) {
            $name = $this->category->where('category_id', $parentId)->value('name');
            if( $name != null) {
                $info['category_name'] = $name;
            }
        }
        //返回
        $this->returnJson($info);
    }

    /**
     * 获取最近发布的4个整装商品
     */
    public function getLastLogs(){

        $where = array();
        $where['is_delete'] = 0;
        $where['goods_verify'] = 1;
        $field = 'id,name,prize,cover';
        $res = Db::name('logs_decoration_goods')->field($field)->where($where)->order('goods_sell_time desc')->limit(4)->select();

        if($res){
            $this->returnJson($res,0,'获取成功');
        }else{
            $this->returnJson($res,1,'未找到数据');
        }
    }

    /**
     * 免费设计
     */
    public function addLogs() {
        $data = $this->request->post();
        //1、校验参数
        if( !array_key_exists('type', $data) ) $this->returnJson('',1,'类型参数不能为空');
        if ( !array_key_exists('orderInfo', $data) ) $this->returnJson('',1,'用户数据不能为空');
        $orderArr = json_decode($data['orderInfo'], true);
        if(!is_array($orderArr) || empty($orderArr)) $this->returnJson('',1,'用户数据参数错误');
        $verification = $this->verification( $orderArr );
        if ( $verification !== true )
           $this->returnJson('', 1, $verification);
        if( $data['type'] == 2 && !array_key_exists('designer_id', $orderArr))
            $this->returnJson('', 1, '设计师不能为空');
        if( $data['type'] == 3 && !array_key_exists('logs_goods_id', $orderArr))
            $this->returnJson('', 1, '整装商品不能为空');
        //3、检测账户信息
        $this->checkMember($data);
        if ( $this->user['type'] == 1 && $this->user['phone'] != $orderArr['phone'] )
            $this->returnJson('', 1, '不能代提交意向');
        //4、下单
        try {
            // 启动事务
            Db::startTrans();
            //第1步 生成订单
            $this->buy($this->user['member_id'], $orderArr, $data['type']);
            if( !array_key_exists('key', $data) ) {
                //第2步 生成token
                $token = $this->getToken($this->user['member_id'], $this->user['phone']);
            }
            // 提交事务
            Db::commit();
            //未登录、未注册的还要返回token
            if( !array_key_exists('key', $data) )
                $this->returnJson(['account' => $this->user['account'], 'key' => $token], 0, '提交成功');
            else
                $this->returnJson('', 0, '提交成功');
        }catch (Exception $e){
            // 回滚事务
            Db::rollback();
            $this->returnJson('', 1, '提交失败');
        }
    }

    /**
     * 参数验证，并将数据格式化
     * @param $data
     * @return bool
     */
    private function verification( $data ) {
        //参数验证
        $rule = array(
            'user_name' => 'require',
            'gender'    => 'in:1,2',
            'phone'     => 'require|length:11',
            'province'    => 'require',
            'city' => 'require',
            'area' => 'require',
            'building_name' => 'require',
            'attachment' => 'require',
        );
        $message = array(
            'user_name.require'=> '用户姓名不能为空',
            'gender.in'     => '性别不能为空',
            'phone.length'  => '错误的电话号码格式',
            'phone.require'  => '电话号码不能为空',
            'province.require'  => '省不能为空',
            'city.require'  => '市不能为空',
            'area.require'  => '区不能为空',
            'building_name.require' => '楼盘名称不能为空',
            'attachment.require' => '户型图不能为空',
        );

        $validate = new Validate($rule, $message);
        if ( !$validate->check( $data ) ) {
            return $validate->getError();
        }
        return true;
    }

    /**
     * 检测用户帐号，是否需要新增或登录
     * @param $data 提交数据
     */
    private function checkMember( $data ) {
        $orderArr = json_decode($data['orderInfo'], true);
        $key = !array_key_exists('key', $data) ? '' : $data['key'];
        $phone = $orderArr['phone'];
        $memberModel = new Member();
        if( empty($key) ) {//假如没有token时，需要登录或是注册
            if( !array_key_exists('authCode', $orderArr)) $this->returnJson('', 1, '验证码不能为空' );
            //判断手机验证码
            $authLogic = new Auth();
            $result = $this->authCode( $phone, $orderArr['authCode'] );
            if ( $result['code'] != 1 )
                $this->returnJson('', 1,$result['msg'] );
            //判断帐号
            $info = $memberModel->getMemberInfo( ['phone'=> $phone] );
            if( !empty($info) ) {//假如帐号存在,直接登录
               $this->user = $info;
            } else {//帐号不存在，进行注册
                $password = Tools::getRandChar(6);
                $result = $authLogic->regist( $phone, md5($password) );
                if ( $result['code'] != 1 ) $this->returnJson('', 1, $result['msg']);
                //注册成功后，给用户发送密码到手机
                $result = ( new SentTemplatesSMS() )->sent($phone, [$phone,$phone,$password], "logs_new_account");
                //注册成功后进行登录
                $this->user = $memberModel->getMemberInfo( ['phone' => $phone, 'password'=>md5($password)] );
                if( empty($this->user) )  $this->returnJson('', 1, '帐号错误');
            }
        } else {//传入token
            $tokenModel = new MobileMemberToken();
            $mobileUserTokenInfo =  $tokenModel->getMobileUserTokenInfo( ['token'=>$key] );
            if( empty($mobileUserTokenInfo) )  $this->returnJson('', 1, '帐号错误');
            $this->user = $memberModel->getMemberInfo( ['member_id' => $mobileUserTokenInfo['member_id']] );
            if( empty($this->user) )  $this->returnJson('', 1, '帐号错误');
        }
    }


    /**
     * 生成免费设计订单
     * @param array $member 用户信息
     * @param array $data 前端传递的参数
     * @param array $type 前端传递的参数
     * @return int $result 购买的最终状态
     */
    public function buy( $member, $data ,$type) {
        //用户订单插入
        $param = [ 'member_id' => $this->user['member_id'],
                   'user_name' => $data['user_name'],
                   'gender'    => $data['gender'],
                   'phone'     => $data['phone'],
                   'province'    => $data['province'],
                   'city' => $data['city'],
                   'area' => $data['area'],
                   'building_name' => $data['building_name'],
                   'image' => $data['attachment'],
                   'order_sn' =>  Tools::guid(),
                   'created_at' => time()
                 ];
        switch($type){
            case 1://来自首页免费设计,设计师和整装商品都为空，罗婷
//                $whereGoods   = ['is_delete' => 0 , 'goods_verify'=>1, 'type'=> 1];
//                $goods   = $this->model->where( $whereGoods )->order('goods_sell_time desc,created_at desc')->find();
//                $param['logs_goods_id'] = $goods['id'];
//                $param['designer_id'] = $goods['designer_id'];
                break;
            case 2://来自设计师
                $designerModel = new Designer();
                $where         = ['designer_id' => $data['designer_id'], 'is_delete' => 0];
                $designer      = $designerModel->where( $where )->find();
                if ( !$designer ) throw new Exception('设计师不存在');
                $param['designer_id'] = $designer['designer_id'];
                $whereGoods   = ['is_delete' => 0 , 'goods_verify'=>1, 'type'=> 1];
                $goods   = $this->model->where( $whereGoods )->order('goods_sell_time desc,created_at desc')->find();
                $param['logs_goods_id'] = $goods['id'];
                break;
            case 3://来自整装商品
                $where = array( 'id' => $data['logs_goods_id'], 'is_delete' => 0, 'goods_verify' => 1 );
                $goods = Db::name('logs_decoration_goods')->where( $where )->find();
                if ( !$goods ) throw new Exception('商品已下架');
                $param['designer_id'] = $goods['designer_id'];
                $param['logs_goods_id'] = $data['logs_goods_id'];
                break;
        }
        $mobileOrder = new MobileLogsDecorationOrder();
        $orderId = $mobileOrder->save( $param );
        if (!intval($orderId))  throw new Exception('生成设计失败');
    }

    /**
     * 返回获取设计的业主数量
     */
    public function getFreeDesignCount() {
        $number = Db::name('mobile_logs_decoration_order')->where('order_status', MobileLogsDecorationOrder::LOGS_ORDER_DESIGNED)->count('distinct member_id');
        $this->returnJson(['number'=>$number]);

    }
}