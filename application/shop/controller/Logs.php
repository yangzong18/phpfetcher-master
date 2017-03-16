<?php
/**
 * 原木整装
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/7  9:21
 */
namespace app\shop\controller;

use app\common\controller\Shop;
use app\common\model\LogsDecorationGoods as Goods;
use app\shop\model\Features;
use app\shop\model\FeaturesValue;
use app\shop\model\GoodsCategory;
use app\shop\model\Type;
use app\shop\model\TypeFeature;
use think\Db;

class Logs extends Shop
{

    private $cid = null;  //存放分类
    private $fid = null;  //存放分类
    private $url = '';  //存放url地址
    private $clickTop;  //存放点击top分类时。存放子类的全部
    private $category;  //存放分类对象
    private $type;  //存放类型对象
    private $typeFeature;  //存放类型对象
    private $feature;  //存放特征量对象
    private $featureValue;  //存放特征量值对象
    private $cidNum = '6aa3a1e261ea54ca94225bb7756b6701';  //存放分类ID
    private $cidName = '原木整体家具';  //存放分类名称


    /**
     * 构造方法
     */
    public function __construct(){
        parent::__construct();
        $this->url = $this->request->url();
        $this->url = preg_replace("/\?page\=\d+/i",'', $this->url);   //在选择筛选条件时去掉分页参数

    }

    /**
     * 初始化
     */
    public function _initialize()
    {
        $this->cid = $this->request->param('cid/s');
        $this->fid = $this->request->param('fid/s');

        $this->category = new GoodsCategory();
        $this->type = new Type();
        $this->typeFeature = new TypeFeature();
        $this->feature = new Features();
        $this->featureValue = new FeaturesValue();
    }

    public function index()
    {
        $where = array();  //查询条件
        $cate = $this->getCategory();        //商品的分类
        list($startSelected,$feature) = $this->getType();            //商品属性

        //分类条件
        if(!empty($this->cid))
        {
            if(isset($cate['three'])){
                $where['category_id'] = $cate['three'];
            }elseif(isset($cate['two'])){
                $two = $cate['two'];
                $res = $this->category->getNextLevel($two);
                if($res){
                    $end = array();
                    foreach($res as $k=>$v){
                        $end[] = $v['category_id'];
                    }
                    $where['category_id'] = array('IN',$end);
                }else{
                    $where['category_id'] = $two;
                }
            }elseif(isset($cate['one'])){
                $three = array();
                $res = $this->category->getNextLevel($cate['one']);
                if($res){
                    foreach($res as $k=>$v){
                        $son = $this->category->getNextLevel($v['category_id']);
                        if($son){
                            foreach($son as $key=>$val){
                                $three[] = $val['category_id'];
                            }
                        }
                    }
                    if(count($three)>0){
                        $where['category_id'] = array('IN',$three);
                    }else{
                        $where['category_id'] = array('IN',$res);
                    }
                }else{
                    $where['category_id'] = $cate['one'];
                }
            }
        }
        if(array_key_exists('category_id',$where)  && count($where['category_id']) <= 0){
            unset($where['category_id']);
        }

        $list=array();
        $page='';
        if((!empty($startSelected) || !empty($feature)) && array_key_exists('category_id',$where)){
            $where['is_delete'] = 0;
            $where['goods_verify'] = 1;
            $where['type'] = 1;
            if(count($feature)>0) $where['feature_value_id'] = array('IN',$feature);
            if(count($startSelected)>0) $where['feature_id'] = array('IN',$startSelected);

            $count = Db::name('logs_decoration_goods')->distinct(true)->field('a.id')->alias('a')->join('logs_decoration_goods_feature b','a.id = b.goods_id')->where($where)->count();
            $list = Db::name('logs_decoration_goods')->distinct(true)->field('a.id,name,acreage,prize,sale_number,appointment_number,cover,goods_sell_time')->alias('a')->join('logs_decoration_goods_feature b','a.id = b.goods_id')->where($where)->order('goods_sell_time desc')->paginate(4,$count);
        }else{
            //-----2017.2.15 ss.wu 注释 -----
//            if(count($where)>0 || count($this->request->param())<=0){
                $where['is_delete'] = 0;
                $where['goods_verify'] = 1;
                $where['type'] = 1;

                $count = Db::name('logs_decoration_goods')->where($where)->count();
                $list = Db::name('logs_decoration_goods')->where($where)->order('goods_sell_time desc')->paginate(4,$count);
//            }else{
//                $list['data']=array();
//            }
            //---- 2017.2.15 ss.wu 注释 end -----

        }

        if(is_object($list)){
            $this->assign('page',$this->dealPage($list->render()));
            $list = $list->toArray();
        }else{
            $this->assign('page','');
        }
        //获取收藏状态
        if(count($list['data'])>0){
            $goods_id_arr = array_column($list['data'],'id');
            $favarit_arr = array();
            //李武修改
            if( $this->login == 1 && count($goods_id_arr)>0){
                $arr = Db::name('favorites')->where(['logs_id'=>array('in',$goods_id_arr),'member_id'=>$this->user['member_id']])->field('logs_id')->select();

                if(count($arr)>0) $favarit_arr=array_column($arr,'logs_id');
            }

            foreach( $list['data'] as $k=>$v ){
                $list['data'][$k]['fav'] = 0;
                if(in_array($v['id'],$favarit_arr)) $list['data'][$k]['fav'] = 1;
            }
        }


//        $this->assign('page',$page);
        $this->assign('list',$list['data']);
        $this->assign('url',$this->url);
        return $this->fetch();


    }


    /**
     * 商品详情页
     */
    public function detail()
    {
        //获取商品信息
        $id = $this->request->param('id', '', 'intval');
        $goodsModel = new Goods();
        $goodsInfo = $goodsModel->where(array('id' => $id, 'is_delete' => 0, 'goods_verify'=>1, 'type'=> 1))->find();
        if (!$goodsInfo) {
            $this->error('整装商品不存在');
        }

        //获取所包含的商品
        $contain = Db::name('logs_decoration_contain')
            ->where(array('logs_goods_id' => $goodsInfo['id']))
            ->select();
        //获取商品名称
		$goodsIdArr = array();
        if($contain){
			$temp_arr = array();
            foreach ($contain as  $value) {
				$temp_arr[] = $value['goods_id'];
            }
			if(!empty($temp_arr)){
				$temp_arr2 = Db::name('goods')->where(array('goods_id' => array('in', $temp_arr)))->field('goods_id,goods_name')->select();
				if($temp_arr2){
					foreach($temp_arr2 as $vt){
						$goodsIdArr[$vt['goods_id']]=$vt['goods_name'];
					}
				}
			}

        }else{
            $contain = '';
        }
		$this->assign('goodsIdArr', $goodsIdArr);

        //获取收藏状态
        $fav = 0;
        //李武修改
        if( $this->login == 1 && !empty($goodsInfo['id'])){
            if(Db::name('favorites')->where(['logs_id'=>$goodsInfo['id'],'member_id'=>$this->user['member_id']])->column('id')){
                $fav = 1;
            }
        }

        //查询商品附件
        $where = array('business_sn' => 'logs_goods', 'business_id' => $id, 'is_delete' => 0);
        $attachementList = Db::name('attachment')->field('attachment_url')->where($where)->select();
        $this->assign('attachementList', $attachementList);


        $this->assign('contain', $contain);
        $this->assign('fav',$fav);
        $this->assign('goods_info', $goodsInfo);
        return $this->fetch();
    }

    /**
     * 设置商品的分类
     */
    public function getCategory(){

        $endSelected = array();
        $category =  $this->category;
        $category_id = $category->getOneId($this->cidName);    //生活馆ID
//        $category_id = $this->cidNum;    //生活馆ID
        $topCategory = $category->getNextLevel($category_id);      //生活馆的子分类
        $url = $this->url;         //当前url地址

        if (preg_match("/\/cid\/[\d\w\-]+/i",$url))
        {
            $url = preg_replace("/\/cid\/[\d\w\-]+/i",'%s', $url);
        } else
        {
            $url .= '%s';
        }

        //当没有cid的时候
        if (is_null($this->cid)) {
            $tmpArr = array();
            $tmpArr[] = '<p><a class="cus" href="' .sprintf($url, '' ) . '">全部分类</a></p>';   //当前URL地址
            $endSelected['one'] = $category_id;
            foreach ($topCategory as $v) {

                $tmpArr[] = '<P><a href="' . sprintf($url, '/cid/' . $v->category_id) . '">' . $v->name . '</a></P>';
            }
            $this->assign('topCategory', $tmpArr);
            return $endSelected;
        }

        $tmpArr = array();
        $tmpArr[] = '<p><a  href="' . sprintf($url, '' ) . '">全部分类</a></p>';
        $pid = $category->getParentId($this->cid);
        $selectArr = array();
        $selectArrSon = array();

        foreach($topCategory as $k=>$v){
            if ($this->cid == $v->category_id || $pid == $v->category_id) {
                $tmpArr[] = '<p><a class="cus" href="' . sprintf($url, '/cid/' . $v->category_id) . '">' . $v->name . '</a></p>';
                $selectArr[] = '<p><span>分类 | '.$v->name.'</span><a href="' . sprintf($url, '').'" ><span class="close"></span></a></p>';   //选中的分类
                $endSelected['two'] = $v->category_id;
                $this->clickTop = sprintf($url, '/cid/' . $v->category_id);
            } else {
                $tmpArr[] = '<p><a href="' .sprintf($url, '/cid/' . $v->category_id) . '">' . $v->name . '</a></p>';
            }
        }

        if($selectArr){
            $this->assign('topSelect',$selectArr);
            $tmpArr = array();
        }

        $this->assign('topCategory', $tmpArr);
        $level = $category->getLevel($this->cid);

        if( $level == 3){
            $sonCategory = $category->getNextLevel($pid);
        }else{
            $sonCategory = $category->getNextLevel($this->cid);
        }

        $tmpArr = array();
        if ($level == 2) {
            $tmpArr[] = '<P><a class="cus" href="' . $this->clickTop .'">全部分类</a></p>';
        } else {
            $tmpArr[] = '<p><a  href="' . $this->clickTop .  '">全部分类</a></p>';
        }

        //组合自分类模版
        foreach ($sonCategory as $v) {
            if ($v->category_id == $this->cid) {
                $tmpArr[] = '<p><a class="cus" href="' . sprintf($url, '/cid/' . $v->category_id) . '">' . $v->name . '</a></p>';
                $endSelected['three'] = $v->category_id;
                if($level == 3){
                    $selectArrSon[] = '<p><span>分类 | '.$v->name.'</span><a href="' . sprintf($url, '/cid/' . $pid) . '"><span class="close"></span></a></p>';   //选中的分类
                }else{
                    $selectArrSon[] = '<p><span>分类 | '.$v->name.'</span><a href="' . sprintf($url, '/cid/' . $this->cid) . '"><span class="close"></span></a></p>';   //选中的分类
                }
            } else {
                $tmpArr[] = '<p><a  href="' . sprintf($url, '/cid/' . $v->category_id) . '">' . $v->name . '</a></p>';
            }
        }
        if($selectArrSon){
            $this->assign('topSelectSon',$selectArrSon);
            $tmpArr = array();
        }
        $this->assign('sonCategory', $tmpArr);

        return $endSelected;
    }


    /**
     * 根据分类设置商品的属性
     */

    public function getType()
    {

        $url = $this->url;
        $startSelected = array();
        $endSelected = array();
        $error_return=array(array(),array());
        if (preg_match("/\/fid\/[\d\w\-]+/i",$url)) {
            $url = preg_replace("/\/fid\/[\d\w\-]+/i",'%s', $url);
        } else {
            $url .= '%s';
        }

        if(!$this->cid) return $error_return;
        $cid = $this->cid;      //分类ID
        $category = $this->category;

        $res = $category->getOne($cid);
        if(!$res) return $error_return;
        $typeId = $res['type_id'];       //分类所属类型
        $type = $this->type->getOneType($typeId);
        if(!$type) return $error_return;
        $typeFeature = $this->typeFeature->getTypeFeature($type['type_id']);   //类型对应的属性
        $features = array();
        foreach($typeFeature as $k=>$v){
            if(2 == $v['type']  &&  1 == $v['visual']){
                $features[] = $v['feature_id'];
            }
        }

        if($features)
        {
            $featuresStr = implode("','",$features);
            $featuresArr = $this->feature->getFeaturesById($featuresStr);   //属性
            if($featuresArr)
            {
                $featuresValueIds = array();
                foreach($featuresArr as $k=>$v){
                    $featuresValueIds[] = $v['feature_id'];
                }
                $featuresValueIdStr = implode("','",$featuresValueIds);

                $featureValue = $this->featureValue->getFeatureValueById($featuresValueIdStr); //属性值

                $tempArr = array();
                $selectArr = array();
                if($featureValue)
                {
                    $fid = explode('_',$this->fid);   //属性可能由多个构成每个属性之间用“_”分割
                    $number = count($featuresArr);

                    //循环放入属性值
                    foreach($featuresArr as $k=>$v)
                    {
                        $tempArrStr = array();    //创建一个空数组
                        for($i=0;$i<$number;$i++){
                            $tempArrStr[$i] = 0;
                        }

                        foreach($tempArrStr as $ke=>$va){
                            $tempArrStr[$ke] = empty($fid[$ke])?0:$fid[$ke];
                        }

                        foreach($featureValue as $key=>$val){
                            //2017.2.15 ss.wu  如果显示一行用in_array判断，如果分开显示用“==”判断
//                             if(in_array($val['feature_id'],$featuresValueIds)){
                            if($v['feature_id'] == $val['feature_id']){
                                $tempArrStr[$k] = $val['id'];

                                if(isset($fid[$k]) && $fid[$k] == $val['id']){
                                    $tempArr[$v['attribute_name']][] = '<p><a class="cus" href="' . sprintf($url, '/fid/' .implode('_',$tempArrStr)) . '">'.$val["feature_value"].'</a></p>';
                                    $tempArrStr[$k] = 0;
                                    $selectArr[$k] = '<p><span>'.$v['attribute_name'].' | '.$val["feature_value"].'</span><a href="' . sprintf($url, '/fid/' .implode('_',$tempArrStr)) . '"><span class="close"></span></a></p>';  //设置选中标签
                                    $endSelected[] = $val['id'];
                                    $startSelected[]= $v['feature_id'];
                                }else{
                                    $tempArr[$v['attribute_name']][] = '<p><a href="' . sprintf($url, '/fid/' . implode('_',$tempArrStr)) . '">'.$val["feature_value"].'</a></p>';
                                }

                                if(isset($selectArr[$k])){
                                    unset($tempArr[$v['attribute_name']]);
                                }

                            }
                        }
                    }

                }
                if($selectArr){
                    $this->assign('select',$selectArr);   //选中的属性
                }
                $this->assign('feature',$tempArr);
                return array($startSelected,$endSelected);
            }else{
                return $error_return;
            }

        }else{
            return $error_return;
        }
    }

    /**
     * 切换原木整装商品，整装商品列表
     */
    public function change() {
        $where = array('is_delete' => 0, 'goods_verify'=>1, 'type'=> 1);  //查询条件
        $goodsModel = new Goods();
        $datas = $goodsModel->where( $where )->order('goods_sell_time desc')->paginate(12);
        $this->assign("datas", $datas);
        return $this->fetch();
    }
}
