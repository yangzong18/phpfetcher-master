<?php
/**
 * 设计师作品接口管理
 * User: 罗婷 17/2/24
 */

namespace app\mobile\controller;
use app\common\model\DesignerProduction;
use app\common\model\LogsDecorationGoods;
use think\Db;

class DesignerProductions extends MobileHome{
    protected $model;

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new DesignerProduction();
    }

    /**
     * 设计师作品详情页面
     */
    public function detail()
    {
        $id = $this->request->param('production_id', '', 'intval');
        if( !$id ){
            $this->returnJson('',1,'参数错误');
        } else {
            //获取作品信息
            $productionInfo = $this->model
                              ->field('production_id,production_name,house_type,building_area,remark,imgs,designer_id')
                              ->where(['production_id' => $id, 'is_delete' => 0,'is_show'=>1])
                              ->find();
            if ( !$productionInfo ) $this->returnJson('', 1, '作品不存在');
            $info = $productionInfo->toArray();
            $info['imgs'] = json_decode($info['imgs']);
            //查询设计师名称
            $info['designer_name'] = Db::name('designer')->where('designer_id', $info['designer_id'])->value('designer_name');
            //查询户型信息
            $info['type_name'] = ( new LogsDecorationGoods() )->getHouseTypeName($info['house_type']);
            //返回
            $this->returnJson($info);
        }
    }

    /**
     * 精品推荐,获取最新的4个设计作品
     */
    public function getLastProductions() {
        $where = array();
        $where['is_delete'] = 0;
        $where['is_show'] = 1;
        $field = 'production_id,designer_id,imgs,production_name,building_area';
        $res = $this->model->field($field)->where($where)->order('upload_time desc')->select();

        if($res){
            foreach ($res as $key => $val ) {
                $res[$key]['imgs'] = json_decode($res[$key]['imgs'])[0];
            }
            $this->returnJson($res,0,'获取成功');
        }else{
            $this->returnJson($res,1,'未找到数据');
        }
    }
}