<?php
/**
 * 手机端支付方式数据模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-12-02 10:08
 */

namespace app\common\model;
use think\Model;
use think\Db;

class PaymentMobile extends Model
{

    /**
     * 开启状态标识
     * @var unknown
     */
    const STATE_OPEN = 1;

    /**
     * 读取多行
     *
     * @param
     * @return array 数组格式的返回结果
     */
    public function getPaymentList($condition = array()){
        $resutl = $this->where($condition)->select();
        return self::getResultByFild($resutl);
    }

    /**
     * 读开启中的取单行信息
     *
     * @param
     * @return array 数组格式的返回结果
     */
    public function getPaymentOpenInfo($condition = array()) {
        $condition['payment_state'] = self::STATE_OPEN;
        return $this->getPaymentInfo($condition);
    }

    /**
     * 读取单行信息
     *
     * @param
     * @return array 数组格式的返回结果
     */
    public function getPaymentInfo($condition = array()) {
        return $this->where($condition)->find()->toArray();
    }

    /**
     * 读取开启中的支付方式
     *
     * @param
     * @return array 数组格式的返回结果
     */
    public function getPaymentOpenList($condition = array()){
        $condition['payment_state'] = self::STATE_OPEN;
        $resutl = $this->where($condition)->select();
        return self::getResultByFild($resutl,"*","payment_code");
    }

    /**
     * 更新信息
     *
     * @param array $param 更新数据
     * @return bool 布尔类型的返回结果
     */
    public function editPayment($data, $condition){
        return $this->where($condition)->update($data);
    }
}