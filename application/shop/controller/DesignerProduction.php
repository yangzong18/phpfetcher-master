<?php
/**
 * create by: PhpStorm
 * desc:实例展示
 * author:yangmeng
 */
namespace app\shop\controller;
use app\common\controller\Shop;
use app\shop\model\DesignerProduction as production;
use app\common\model\LogsDecorationGoods;
use think\Db;

class DesignerProduction extends Shop{

    protected $style;
    /**
     * 构造器
     */
    public function __construct() {
        $this->production = new production();
        $this->style = $this->production->getStyle();
        parent::__construct();

    }


    /**
     * 实例展示列表
     */
    public function index() {
        //查询作品
        $condition = array();
        $condition['is_delete'] = 0;
        $condition['is_show'] = 1;

        //获取风格
        $style = $this->request->param('style', '', 'trim');
        if( !empty($style) ){
            $condition['style'] = $style;
            $this->assign('category_id', $condition['style']);
        }

        $field = 'production_id,production_name,building_area,remark,imgs';
        $list = $this->production->productionList( $condition, $field , '' ,true ,8 );

		if(is_object($list)){
			$this->assign('page',$this->dealPage($list->render()));
			$list = $list->toArray();
		}else{
			$this->assign('page','');
		}

		//获取收藏状态  李武添加
		if(count($list['data'])>0){
			$goods_id_arr = array_column($list['data'],'production_id');
			$favarit_arr = array();
			if( $this->login == 1 && count($goods_id_arr)>0){
				$arr = Db::name('favorites')->where(['production_id'=>array('in',$goods_id_arr),'member_id'=>$this->user['member_id']])->field('production_id')->select();
				if(count($arr)>0) $favarit_arr=array_column($arr,'production_id');
			}

			foreach( $list['data'] as $k=>$v ){
				$list['data'][$k]['fav'] = 0;
				if(in_array($v['production_id'],$favarit_arr)) $list['data'][$k]['fav'] = 1;
			}
		}

        //风格分类
        $category = $this->style;

        //默认第一张为主图
        foreach( $list['data'] as $k=>$v ){
			$list['data'][$k]['cover'] = json_decode($v['imgs'])[0];
        }


        $this->assign( 'list' , $list['data']);
        $this->assign('category',$category);
        return $this->fetch();
    }

    /**
     * 查看详情
     */
    public function detail() {
        $id = $this->request->param('id','','intval');

        $where = array('production_id'=>$id,'is_delete'=>0);
        $info = $this->production->getProductionInfo( $where );
        if ( !isset( $info['designer_id'] ) ) {
            $this->error('该作品不存在');
        }
        $info['designer_name'] = Db::name('designer')->where( array('designer_id'=>$info['designer_id']) )->value('designer_name');
        $info['category_name'] = Db::name('goods_category')->where( array('category_id'=>$info['style']) )->value('name');
        $info['type_name'] = ( new LogsDecorationGoods() )->getHouseTypeName($info['house_type']);
        $info['logs_id'] = Db::name('logs_decoration_order')->where( array('order_sn'=>$info['order_sn']) )->value('logs_goods_id');

        //判断实例展示是否被收藏
        $info['fav'] = 0;
        //李武修改
        if( $this->login == 1 && !empty($id)) {
            if(Db::name('favorites')->where(['production_id' => $id, 'member_id' => $this->user['member_id']])->value('id')){
                $info['fav'] = 1;
            }
        }

        $ImageArr = json_decode($info['imgs']);
        $info['cover'] = $ImageArr[0];
        $info['image'] = array_splice( $ImageArr,1 );

        $image = array();
        $count = count($info['image']);

        if( $count%2 == 0 ){
            foreach($info['image'] as $k=>$v){
                for($i=0;$i<($count/2);$i++){
                    $image[$i][0] = $info['image'][$k];
                    $image[$i][1] = $info['image'][$k+1];
                    $k = $k+2;
                }
                if($i*2 == $count) break;
            }
        }else{
            $number = ceil( $count / 2);
            foreach($info['image'] as $k=>$v){
                for( $i=0;$i< $number ; $i++){
                    $image[$i][0] = $info['image'][$k];
                    if ( isset( $info['image'][$k+1] ) ) {
                        $image[$i][1] = $info['image'][$k+1];
                    }
                    $k = $k+2;
                }
                if( $i*2 >= $count )break;
            }
            // $image[floor($count/2)][0] = $info['image'][$count-1];
            // $image[floor($count/2)][1] = '';
        }
        $this->assign('image',$image);
        $this->assign( 'info' , $info );
        return $this->fetch();
    }

}
