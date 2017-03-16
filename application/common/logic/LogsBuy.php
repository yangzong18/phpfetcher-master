<?php
/**
 * 原木整装商品购买逻辑
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang at 2016/12/07
 */
namespace app\common\logic;
use think\Validate;
use app\shop\model\Designer;
use Util\Tools;
use think\Log;
use think\Db;

class LogsBuy {
    //用户信息
    private $member;
    //订单页面提交的订单信息
    private $data;
    //新生产的订单ID
    public $newOrderId;
    
    /**
     * 生成订单的逻辑
     * @param array $member 用户信息
     * @param array $data 前端传递的参数
     * @return int $result 购买的最终状态
     */
    public function buy( $member, $data ) {
        $verification = $this->verification( $data );
        //如果参数有误
        if ( $verification !== true ) {
            return $verification;
        }
        //进行商品验证
        $where = array( 'id' => $data['logs_goods_id'], 'is_delete' => 0, 'goods_verify' => 1 );
        $goods = Db::name('logs_decoration_goods')->where( $where )->find();
        if ( !$goods ) {
            return '商品已下架';
        }
        //进行设计师验证
        $designerModel = new Designer();
        $where         = array( 'designer_id' => $data['designer_id'], 'is_delete' => 0 );
        $designer      = $designerModel->where( $where )->find();
        if ( !$designer ) {
            return '设计师不存在';
        }
        $deposit = $goods['deposit'];
        //判断商品类型，普通类型的商品附图是必填
        if ( $goods['type'] == 1 ) {
            if ( !isset( $data['attachment'] ) || !is_array( $data['attachment'] ) || count( $data['attachment'] ) == 0 ) {
                return '用户户型图必填';
            }
            $data['contract_price'] = 0;
            //判断用户的电话号码是否和上传的电话号码一致
            if ( $this->member['type'] == 1 && $this->member['phone'] != $data['phone'] ) {
                return '不能代提交意向';
            }
        } else {
            //如果是代理商品,则计算合同价
            if ( !isset( $data['contract_price'] ) || !is_numeric( $data['contract_price'] ) || 0 >= $data['contract_price'] ) {
                return '合同价格为大于0整数';
            }
            //判断诚意金支付方式,如果是按照合同比例
            if ( $goods['pay_type'] == 2 ) {
                $deposit = round( floatval( $data['contract_price'] )*( $deposit / 100), 2); 
            }
        }
        //用户订单插入
        $param = array(
            'member_id' => $member['member_id'],
            'order_sn'  => Tools::guid(),
            'pay_sn' => Tools::guid(),
            'order_type'=> $goods['type'],
            'logs_goods_id' => $data['logs_goods_id'],
            'user_name' => $data['user_name'],
            'gender'    => $data['gender'],
            'phone'     => $data['phone'],
            'house_type'  => $data['house_type'],
            'province'    => $data['province'],
            'deposit'    => $deposit,
            'contract_price' => $data['contract_price'],
            'city' => $data['city'],
            'area' => $data['area'],
            'address' => $data['address'],
            'acreage' => $data['acreage'],
            'building_name' => $data['building_name'],
            'designer_id'   => $data['designer_id'],
            'recommend_user_phone' => isset( $data['recommend_user_phone'] ) ? $data['recommend_user_phone'] : '',
            'message'    => isset( $data['message'] ) ? $data['message']: '',
            'created_at' => time()
        );
        //update by laijunilang at 2017/02/21 修改留言不能超过500个字符
        if ( mb_strlen( $param['message'],'utf8' ) > 500 ) {
            return '留言或说明不能大于500个字符';
        }
        Db::startTrans();
        $orderId = Db::name('logs_decoration_order')->insertGetId( $param );
        //插入订单
        if ( !$orderId ) {
            Db::rollback();
            Log::write('原木整装商品订单添加失败,商品信息:'.json_encode( $data ), 'error');
            return '订单插入失败';
        }
        //如果有附图，则插入附图
        if ( isset( $data['attachment'] ) && is_array( $data['attachment'] ) && count( $data['attachment'] ) ) {
            $attachmentList = array();
            foreach ($data['attachment'] as $attachment) {
                array_push($attachmentList, array(
                    'logs_order_id' => $orderId,
                    'image_url'     => $attachment
                ));
            }
            if ( !Db::name('logs_decoration_order_diagram')->insertAll( $attachmentList ) ) {
                Db::rollback();
                Log::write('原木整装商品附图添加失败,商品信息:'.json_encode( $data ), 'error');
                return '商品附图插入失败';
            }
        }
        //增加预约数量
        $where = array( 'id' => $data['logs_goods_id'] );
        if ( !Db::name('logs_decoration_goods')->where( $where )->setInc( 'appointment_number', 1 ) ) {
            Db::rollback();
            Log::write('原木整装商品附图添加失败,商品信息:'.json_encode( $data ), 'error');
            return '商品数量更新失败';
        }
        //插入order_pay表
        $orderPay = array();
        $orderPay['pay_sn'] = $param['pay_sn'];
        $orderPay['member_id'] = $member['member_id'];
        $orderPayId = Db::name('logs_decoration_order_pay')->insertGetId( $orderPay );
        if ( !$orderPayId ) {
            Db::rollback();
            Log::write('原木整装商品订单添加失败,商品信息:'.json_encode( $orderPay ), 'error');
            return '订单支付表添加失败';
        }

        $this->newOrderId = $orderId;
        Db::commit();
        return true;
    }

    /**
     * 参数验证，并将数据格式化
     */
    public function verification( $data ) {
        //参数验证
        $rule = array(
            'logs_goods_id' => 'number|gt:0',
            'user_name' => 'require',
            'gender'    => 'in:1,2',
            'acreage' => 'require|number|gt:0',
            'phone'     => 'require|length:11',
            'house_type'  => 'require',
            'province'    => 'require',
            'city' => 'require',
            'area' => 'require',
            'address' => 'require',
            'building_name' => 'require',
            'designer_id'   => 'number|gt:0',
        );
        $message = array(
            'logs_goods_id.number'    => '整装商品ID有误',
            'logs_goods_id.gt' => '整装商品ID有误',
            'user_name.require'=> '用户姓名不能为空',
            'gender.in'     => '性别不能为空',
            'acreage.require'  => '装修面积不能为空',
            'acreage.number'  => '装修面积信息有误',
            'acreage.gt' => '装修面积必须大于0',
            'phone.length'  => '错误的电话号码格式',
            'phone.phone'  => '电话号码不能为空',
            'house_type.require'=> '户型不能为空',
            'province.require'  => '省不能为空',
            'city.require'  => '市不能为空',
            'area.require'  => '区不能为空',
            'address.require'  => '详细地址不能为空',
            'building_name.require' => '楼盘名称不能为空',
            'designer_id.number'    => '设计师信息有误',
            'designer_id.gt' => '设计师信息有误',
        );

        $validate = new Validate($rule, $message);
        if ( !$validate->check( $data ) ) {
            return $validate->getError();
        }
        return true;
    }
    
}