<?php
/**
 * Created by 长虹.
 * User: 李武
 * Date: 2017/1/4
 * Time: 10:56
 * Desc: 手机端木筑生活馆商品接口
 */
namespace app\mobile\controller;
use think\Cache;
use think\Config;
use think\Db;
use think\Model;
use app\common\model\Features;
use app\common\model\FeaturesValue;
use app\common\model\Goods as NormalGoods;
use app\shop\model\GoodsCategory;
use app\common\model\GoodsSku;
use app\common\model\Attachment;


class LivingGoods extends MobileHome
{

    //需要排除的分类ID
    private $cateId = null;

    //当前选择分类ID
    private $cid = null;

    //存放搜索名称
    private $name = null;


    public function _initialize(){
        $this->cateId = Config::get('logs_goos_category_id');
        $this->cid = $this->request->param('cid/s', '', 'trim');
        $this->name         = $this->request->param('name/s','','trim');
    }

    /**
     * 常规商品列表接口
     */
    public function index(){

        $where = array();  //查询条件
        $cate_arr = $this->_getCate();        //商品的分类

        //分类条件
        if(!empty($this->cid))
        {
//            //1.假设在三级分类
//            $cate_3_arr = array_get_by_key($cate_arr['thirdLevel'],'category_id');
//            if(in_array($this->cid,$cate_3_arr)){
//                $where['category_id'] = $this->cid;
//            }else{//2.假设在二级分类
//                $cate_2_arr = array_get_by_key($cate_arr['secondCategory'],'category_id');
//                if(in_array($this->cid,$cate_2_arr) && array_key_exists($this->cid,$cate_arr['thirdLevel'])){
//                    $cate_3_arr = array_column($cate_arr['thirdLevel'][$this->cid],'category_id');
//                    $where['category_id'] = array('IN',$cate_3_arr);
//                }else{
                //3.假设在一级分类
                    if(array_key_exists($this->cid,$cate_arr['secondCategory']) && count($cate_arr['thirdLevel'])>0){
                       //----ss.wu 2017.2.17 ------
                        $cate_2_arr = array();
                        if(is_array($cate_arr['secondCategory'][$this->cid])){
                            foreach($cate_arr['secondCategory'][$this->cid] as $key=>$val){
                                if(is_array($val)){
                                    $cate_2_arr[] = $val['category_id'];
                                }elseif(is_object($val)){
                                    $cate_2_arr[] = $val->category_id;
                                }
                            }
                        }
//                        $cate_2_arr = array_column($cate_arr['secondCategory'][$this->cid],'category_id');
                        // -------  end  ---------
                        $cate_3_arr=array();
                        foreach($cate_arr['thirdLevel'] as $key2=>$v_arr){
                            if(in_array($key2,$cate_2_arr)){
                                //-------ss.wu 2017.2.17 --------
                                if(is_array($v_arr)){
                                    foreach($v_arr as $key=>$val){
                                        if(is_array($val)){
                                            $cate_3_arr[] = $val['category_id'];
                                        }elseif(is_object($val)){
                                            $cate_3_arr[] = $val->category_id;
                                        }
                                    }
                                }
//                                $cate_3_arr=array_merge($cate_3_arr,array_column($v_arr,'category_id'));
                                //-------  end ---------
                            }
                        }
                        //ss.wu 添加if判断
                        if($cate_3_arr){
                            $where['category_id'] = array('IN',$cate_3_arr);
                        }elseif($cate_2_arr){
                            $where['category_id'] = array('IN',$cate_2_arr);
                        }else{
                            $where['category_id'] = $this->cid;
                        }

                    }elseif(array_key_exists($this->cid,$cate_arr['thirdLevel'])){   //二级分类时

                        $cate_3_arr=array();
                        if(is_array($cate_arr['thirdLevel'][$this->cid])){
                            foreach($cate_arr['thirdLevel'][$this->cid] as $key2=>$v_arr){
                                if(is_array($v_arr)){
                                    $cate_3_arr[] = $v_arr['category_id'];
                                }elseif(is_object($v_arr)){
                                    $cate_3_arr[] = $v_arr->category_id;
                                }

                            }
                        }

                        if($cate_3_arr){
                            $where['category_id'] = array("IN",$cate_3_arr);
                        }else{
                            $where['category_id'] = $this->cid;
                        }
                    }else{  //三级分类时
                        $where['category_id'] = $this->cid;
                    }
                //}
            //}
        }

        //设置商品名称
        if($this->name){
            $where['goods_name'] = array('like',"%".$this->name."%");
        }

        $where['is_delete'] = 0;
        $where['goods_verify'] = 1;
        $where['goods_type'] = 1;
        $count = Db::name('goods')->alias('a')->distinct(true)->field('a.goods_id,goods_name,goods_image_main,goods_sell_time,goods_sale_number,goods_price')->join('goods_basic_feature b','a.goods_id = b.goods_id','LEFT')->where($where)->count();
        $list = Db::name('goods')->alias('a')->distinct(true)->field('a.goods_id,goods_name,goods_image_main,goods_sell_time,goods_sale_number,goods_price')->join('goods_basic_feature b','a.goods_id = b.goods_id','LEFT')->where($where)->order('goods_sell_time desc')->paginate(20,$count);
        if(is_object($list)) $list=$list->toArray();

        if(count($list)>0){
            //获取收藏状态
            $goods_id_arr = array_column($list['data'],'goods_id');
            $favarit_arr = array();
            //李武修改，罗婷后改
            if(count($this->user)>0 && count($goods_id_arr)>0){
                $arr = Db::name('favorites')->where(['goods_id'=>array('in',$goods_id_arr),'member_id'=>$this->user['member_id']])->field('goods_id')->select();
                if(count($arr)>0) $favarit_arr=array_column($arr,'goods_id');
            }
            foreach( $list['data'] as $k=>$v ){
                $list['data'][$k]['fav'] = 0;
                if(in_array($v['goods_id'],$favarit_arr)) $list['data'][$k]['fav'] = 1;
            }
        }
        
        $this->returnJson($list);
    }

    /*
     * 商品详情
     * */
    public function detail()
    {
        $goodsId = $this->request->param('gk/s', '', 'trim');
        if(empty($goodsId)) {
            $this->returnJson('',1,'参数错误');
        }else{
            //查询商品信息
            $where      = array( 'goods_id' => $goodsId, 'is_delete' => 0 );
            $goodsModel = new NormalGoods();
            $goods      = $goodsModel->where( $where  )->find();
            //如果商品不存在，则提示错误
            if ( !$goods ) {
                $this->returnJson('',1,'商品不存在');
            }else{
                unset($where['is_delete']);
                //查询商品额外信息
                $extra = Db::name('goods_extra')->where( $where )->find();
                $extra["goods_real_shot"] = str_replace('src="/ueditor/php/upload/image/', 'src="'.HTTP_SITE_HOST.'/ueditor/php/upload/image/', $extra["goods_real_shot"]);
                $extra["goods_specifications"] = str_replace('src="/ueditor/php/upload/image/', 'src="'.HTTP_SITE_HOST.'/ueditor/php/upload/image/', $extra["goods_specifications"]);
                $extra["goods_material_description"] = str_replace('src="/ueditor/php/upload/image/', 'src="'.HTTP_SITE_HOST.'/ueditor/php/upload/image/', $extra["goods_material_description"]); 
                $extra["goods_quality_assurance"] = str_replace('src="/ueditor/php/upload/image/', 'src="'.HTTP_SITE_HOST.'/ueditor/php/upload/image/', $extra["goods_quality_assurance"]); 
                //查询商品所有的sku
                $skuModel = new GoodsSku();
                $goodsList= $skuModel->where( $where )->order('goods_price asc')->select();
                
                //查询商品所有的规格和规格值
                $saleFeatureList = Db::name('goods_sale_feature')->where( $where )->select();
                $featureList     = $this->formatFeature( $saleFeatureList );

                //-------颜色 对应 图片-------- 罗婷从PC端复制过来
                $featureColor = null;
                $featureColorGroup = array();
                $featureColorGroupUrl = array();
                if($featureList){
                    foreach($featureList as $k=>$v){
                        if($v['is_color'] == 1){
                            $featureColor = $v;
                        }
                    }
                }
                if($featureColor){
                    foreach($featureColor['feature_value'] as $k=>$v){
                        foreach($saleFeatureList as $key=>$val){
                            if($v['id'] == $val['feature_value_id']){
                                $featureColorGroup[$v['id']][] = $val['group_id'];
                            }
                        }
                    }
                }
                //查询商品附图
                $attachmentModel = new Attachment();
                if($featureColorGroup){
                    foreach($featureColorGroup as $k=>$v){
                        if($v){
                            $where           = array('is_delete' => 0, 'business_sn'=>'group_id', 'business_id'=> array('in', $v) );
                            $colorUrl  = $attachmentModel->field('attachment_url ')->where( $where )->order('sort asc')->select();
                            if($colorUrl){
                                foreach($colorUrl as $key=>$val){
                                    $featureColorGroupUrl[$k][] = $val['attachment_url'];
                                }

                            }
                        }
                    }
                }
                //--------  颜色对应图片  end ---------

                //验证是否无规格商品,罗婷
                if ( count( $featureList ) == 1 && $featureList[0]['feature_id'] == 1
                    && count( $featureList[0]['feature_value'] ) == 1 && $featureList[0]['feature_value'][0]['id'] == 1 ) {
                    $featureList = [];
                }
                //假如包含颜色规格，且规格有值，将附图和颜色规格绑定 罗婷 start
                if ( count( $featureList ) >0 && array_key_exists('feature_id',$featureList[0])&&$featureList[0]['feature_id'] == 1) {
                    foreach($featureList[0]['feature_value'] as $key => $val) {
                        if(array_key_exists($val['id'],$featureColorGroupUrl)) {
                            $featureList[0]['feature_value'][$key]['pic_url'] = $featureColorGroupUrl[$val['id']];
                        }
                    }
                }
                //假如包含颜色规格，且规格有值，将附图和颜色规格绑定 罗婷 end
                //将分组查询出来，以便于查询附图
                $groupIdList = array();
                foreach ($saleFeatureList as $saleFeature) {
                    $key = $saleFeature['group_id'];
                    if ( !in_array($saleFeature['group_id'], $groupIdList) ) {
                        array_push($groupIdList, $key);
                    }
                }

                //查询商品附图
                $where           = array('is_delete' => 0, 'business_sn'=>'group_id', 'business_id'=> array('in', $groupIdList) );
                $attachmentModel = new Attachment();
                $attachmentList  = $attachmentModel->field('business_id, attachment_url as address')->where( $where )->order('sort asc')->select();
                //将附图分类, 拼接规格和规格指
                foreach ($goodsList as $key => $unit) {
                    $unit = $unit->toArray();
                    $unit['attachment_list'] = array();
                    foreach ($attachmentList as $tag => $attachment) {
                        if ( $unit['group_id'] == $attachment['business_id'] ) {
                            array_push( $unit['attachment_list'], $attachment->toArray() );
                            unset( $attachmentList[$tag] );
                        }
                    }
                    $unit['value_id_list'] = array();
                    foreach ($saleFeatureList as $tag => $saleFeature) {
                        if ( $saleFeature['group_id'] == $unit['group_id'] ) {
                            array_push($unit['value_id_list'], $saleFeature['feature_value_id']);
                            unset($saleFeatureList[$tag]);
                        }
                    }
                    unset($unit['sku_name']);
                    sort($unit['value_id_list']);
                    $goodsList[$key] = $unit;
                }
                //将价格最低的的sku附图作为默认的商品

                foreach($goodsList as $k=>$v){
                    if($v['attachment_list']){
                        $goods['attachment_list'] = $v['attachment_list'];
                    }
                }
                if(empty($goods['attachment_list'])){
                    $goods['attachment_list'] = $goodsList[0]['attachment_list'];
                }

                //查询商品是否被收藏
                $goods['fav'] = 0;
                //李武修改,罗婷后改
                if(count($this->user)>0  && !empty($goodsId)){
                    if(Db::name('favorites')->where(['goods_id'=>$goodsId,'member_id'=>$this->user['member_id']])->value('id')){
                        $goods['fav'] = 1;
                    }
                }
                //$goods['default_goods'] = $goodsList[0];

                $data = array();
                $data['goods']=$goods;
                $data['extra']=$extra;
                $data['featureList']=$featureList;
                $data['featureLength']=count( $featureList );
                $data['goods_group']=$goodsList;
                $this->returnJson($data);
            }
        }
    }

    /**
     * 格式化特征和特征值
     * @param array $data 传入的数组，里面含有feature_id和featur_value_id一对一关系
     * @return array $featureList 格式化的结果
     */
    private function formatFeature( $data ) {
        $featureModel      = new Features();
        $featureValueModel = new FeaturesValue();
        $featureList   = array();
        $featureIdList = array();
        $featureValueIdList = array();
        foreach ($data as $key => $feature) {
            //录入id
            if ( !in_array( $feature['feature_id'] , $featureIdList) ) {
                array_push($featureIdList, $feature['feature_id']);
            }
            //录入特征值id
            array_push($featureValueIdList, $feature['feature_value_id']);
        }
        //进行特征和特征值的查询
        if ( count( $featureIdList ) > 0 ) {
            //特征查询
            $where       = array( 'feature_id' => array( 'in',  $featureIdList));
            $featureList = $featureModel->field('feature_id,attribute_name,is_color')->where( $where )->order('sort asc')->select();
            //特征值查询
            $where       = array( 'id' => array( 'in', $featureValueIdList ));
            $featureValueList = $featureValueModel->field('id,feature_id,feature_value')->where( $where )->order('sort asc')->select();
            //拼接特征和特征值
            foreach ($featureList as $key => $feature) {
                $feature = $feature->toArray();
                $feature['feature_value'] = array();
                foreach ($featureValueList as $tag => $featureValue) {
                    if ( $feature['feature_id'] == $featureValue['feature_id'] ) {
                        array_push($feature['feature_value'], $featureValue->toArray());
                        unset( $featureValueList[$tag] );
                    }
                }
                $featureList[$key] = $feature;
            }
        }

        return $featureList;
    }

    /*
     * 获取分类
     * */
    private function _getCate()
    {
        $return_arr = array('topCategory'=>array(),'secondCategory'=>array(),'thirdLevel'=>array());
        $category = new GoodsCategory();
        $topCategory = $category->getFirstLevel();//生活馆的一级分类

        if(!empty($topCategory))
        {
            $top_cat_arr = array();
            foreach($topCategory as $k=>$v) {
                if($v['category_id'] == $this->cateId){
                    unset($topCategory[$k]);
                }else{
                    $top_cat_arr[]=$v['category_id'];
                }
            }

            //生活馆的二级分类
            $secondCategory = array();
            $thirdCategory = array();
            if(!empty($top_cat_arr))
            {
                $arr2 = $category->getSecondLevel();//生活馆的三级分类

                if(!empty($arr2))
                {
                    if(array_key_exists($this->cateId,$arr2)) unset($arr2[$this->cateId]);

                    $secondCategory = $arr2;
                    $second_cat_arr = array();
                    foreach($secondCategory as $v_arr2){
                        //-------2017.2.17 ss.wu 修改----------
                        if(is_array($v_arr2)){
                           foreach($v_arr2 as $key=>$val){
                               if(is_object($val)){
                                   $second_cat_arr[] = $val->category_id;
                               }elseif(is_array($val)){
                                   $second_cat_arr[] = array_column($val,'category_id');
                               }

                           }
                       }
                        //----------end  ----------
//                        $second_cat_arr = array_merge($second_cat_arr,array_column($v_arr2,'category_id'));
                    }

                    if(!empty($second_cat_arr))
                    {
                        $arr3 = $category->getThirdLevel();//生活馆的三级分类
                        if(!empty($arr3)) {
                            foreach ($arr3 as $key2=>$v_arr3) {
                                if(in_array($key2,$second_cat_arr)) $thirdCategory[$key2]=$v_arr3;
                            }
                            if(empty($thirdCategory)) $thirdCategory = array();
                        }

                    }else{
                        $secondCategory = array();
                    }
                }else{
                    $secondCategory = array();
                }
                $return_arr['topCategory'] = $topCategory;
                $return_arr['secondCategory'] = $secondCategory;
                $return_arr['thirdLevel'] = $thirdCategory;
                return $return_arr;
            }else{
                return $return_arr;
            }

        }else{
            return $return_arr;
        }
    }
    /*
     * 获取商品分类对外接口,只需要返回一级分类
     *
     * */
    function cate(){
        $cate_arr = $this->_getCate();
        $list = [];
        if( !empty($cate_arr['topCategory']) ) {
            foreach($cate_arr['topCategory'] as $val) {
                $list[] = ['id'=>$val['category_id'],'name'=>$val['name']];
            }
        }
        $this->returnJson($list);
    }
}
