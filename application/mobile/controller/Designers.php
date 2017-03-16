<?php
namespace app\mobile\controller;
use think\Cache;
use app\common\model\Designer;
use app\common\model\DesignerLevel;
use app\common\model\DesignerProduction;
use think\Db;
use think\Model;

class Designers extends MobileHome{

    protected $model;
    protected $designerLevel;

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Designer();
        $this->designerLevel = new DesignerLevel();
    }

    /*
     *  设计师分类
     * */
    public function levelList()
    {
        $arr = $this->designerLevel->getDesignerLevelList(['is_delete' => 0]);
        $list = [];
        foreach( $arr as $val) {
            $list[] = $val;
        }
        $this->returnJson($list);
    }

    /**
     * 设计之家列表
     */
    public function index()
    {
        $levelId = $this->request->param('level', '', 'intval');
        $where = [];
        $where['is_delete'] = 0;
        if( $levelId ) {
            $where['level_id'] = $levelId;
            $this->assign('level_id', $levelId);
        }
        $designerList = $this->model->getDesignerList( $where ,'designer_id,level_id,designer_year,designer_avatar,designer_name','','',true);
        $levelList = $this->designerLevel->getDesignerLevelList();
        $list = [];
        foreach( $levelList as $val) {
            $list[] = $val;
        }
        $data = array();
        $data['designerList'] = $designerList;
        $data['levelList'] = $list;
        $this->returnJson($data);
    }

    /**
     * 设计师详情页面
     */
    public function detail()
    {
        $id = $this->request->param('designer_id', '', 'intval');
        if( !$id ){
            $this->returnJson('',1,'参数错误');
        }else{
            //获取设计师信息
            $info = $this->model->getDesignerInfo( ['designer_id' => $id] );
            if(empty($info)){
                $this->returnJson('',1,'没有这样的设计师');
            }else{
                //获取设计师作品信息
                $productionModel = new DesignerProduction();
                $field = 'production_id, production_name, designer_id, building_area, remark, imgs';
                $production = $productionModel->productionList( ['designer_id' => $id,'is_delete'=>0,'is_show'=>1] ,$field, 0, true,4);
                foreach($production as $key => $val) {//处理图片
                    $production[$key]['imgs'] = explode(';', $production[$key]['imgs'])[0];
                }

                $data = array();
                $data['info'] = $info;
                $data['production'] = $production;
                $this->returnJson($data);
            }
        }
    }

}