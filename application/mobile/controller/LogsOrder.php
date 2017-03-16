<?php
/**
 * 整装免费设计订单
 */

namespace app\mobile\controller;
use app\common\model\MobileLogsDecorationOrder;
use think\Db;

class LogsOrder extends MobileMember{
    protected $model;

    /**
     * 构造器
     */
    public function __construct()
    {
        parent::__construct();
        $this->model = new MobileLogsDecorationOrder();
    }

    /**
     * 获取设计列表
     */
    public function lists() {
        $field = 'id,province,city,area,building_name,image,order_status,user_name,gender,order_sn,phone';
        $where = ['member_id' => $this->user['member_id'], 'delete_status'=>0];
        $count = Db::name('mobile_logs_decoration_order')->distinct(true)->where($where)->count();
        $list = Db::name('mobile_logs_decoration_order')->field($field)->where($where)->order('created_at desc')->paginate(2,$count);
        $page = ['currentPage'=>$list->currentPage(),'lastPage'=>$list->lastPage(),'total'=>$list->total()];
        if( empty($list) || $this->request->param('page', 1, 'intval') > $list->lastPage()) {
            $this->returnJson('', 1, '未找到数据');
        }
        $this->returnJson(['list'=>$list,'page'=>$page]);
    }

    /**
     * 查看设计详情
     */
    public function detail() {
        //判断参数
        $sn = $this->request->post('order_sn', 0, 'trim');
        if( !$sn ) $this->returnJson('', 1, '参数错误');
        //查找数据
        $field = 'id,province,city,area,building_name,image,order_status,user_name,gender';
        $where = ['member_id' => $this->user['member_id'], 'delete_status'=>0,'order_sn'=>$sn];
        $info = $this->model->getMobileOrderInfo($where, $field);
        if( !$info ) $this->returnJson('', 1, '未找到数据');
        $info = $info->toArray();
        $info['order_state'] = $this->model->orderStatus($info['order_status']);
        $this->returnJson($info);
    }
}