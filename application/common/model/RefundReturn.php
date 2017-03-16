<?php
/**
 * 订单模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-12-02 10:08
 */

namespace app\common\model;
use think\Model;
use think\Db;
use think\View;
use think\Config;

class RefundReturn extends Model
{
    /*
     * 视图实例
     * */
    private $view=null;

    /**
     * 架构函数
     * @access public
     * @param array|object $data 数据
     */
    public function __construct($data = [])
    {
        parent::__construct($data);
        $this->view = View::instance(Config::get('template'), Config::get('view_replace_str'));
    }
    /**
	 * 增加退款添加记录
	 * @param
	 * @return int
	 */
	public function addRefundReturn($refund_array, $order = array()) {
		if (!empty($order) && is_array($order)) {
			$refund_array['order_id'] = $order['order_id'];
			$refund_array['order_sn'] = $order['order_sn'];
			$refund_array['store_id'] = $order['store_id'];
			$refund_array['store_name'] = $order['store_name'];
			$refund_array['member_id'] = $order['member_id'];
			$refund_array['member_name'] = $order['member_name'];
			if($order['order_state'] >= 30){
				$refund_array['refund_type'] = 2;//退货
			}
			$refund_array['order_lock'] = 2;
			$refund_array['add_time'] = time();
		}
		$refund_array['refund_sn'] = $this->getRefundsn($refund_array['store_id']);
		$where = ['order_id'=>$order['order_id'],'member_id'=>$order['member_id']];
		$data  = ['refund_state'=>1,'lock_state'=>1];
		try{
			// 启动事务
			Db::startTrans();

			$this->db()->insert($refund_array);
			$this->db()->name('order')->where($where)->update($data);
			// 提交事务
			Db::commit();
			return true;
		} catch (\PDOException $e) {
			// 回滚事务
			Db::rollback();
			return false;
		}
	}

    /**
     * 取退款退货记录
     *
     * @param
     * @return array
     */
    public function getRefundReturnList($condition = array(), $page = '', $fields = '*', $limit = '') {
        $result = $this->where($condition)->field($fields)->page($page);
			if(!empty($limit)) $result = $result->limit($limit);
		$result = $result->order('refund_id desc')->select();
		$result = Model::getResultByFild($result);
        return $result;
    }
    
    /**
	 * 退款退货申请编号
	 * @param
	 * @return array
	 */
	public function getRefundsn($store_id) {
		$result = mt_rand(100,999).substr(100+$store_id,-3).date('ymdHis');
		return $result;
	}


}
