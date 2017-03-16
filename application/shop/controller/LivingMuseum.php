<?php
/**
 * 木筑生活馆
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu  at: 2016/11/30  17:01
 */
namespace app\shop\controller;

use app\common\controller\Shop;
use app\shop\controller;
use app\shop\model\Features;
use app\shop\model\FeaturesValue;
use app\shop\model\Goods;
use app\shop\model\GoodsCategory;
use app\shop\model\Type;
use app\shop\model\TypeFeature;
use think\Db;
use think\Config;

class LivingMuseum extends Shop{

    private $cid = null;  //存放分类
    private $fid = null;  //存放分类
    private $name = null;  //存放搜索名称
    private $url = '';  //存放url地址
    private $clickTop;  //存放点击top分类时。存放子类的全部
    private $category;  //存放分类对象
    private $type;  //存放类型对象
    private $typeFeature;  //存放类型对象
    private $feature;  //存放特征量对象
    private $featureValue;  //存放特征量值对象
    private $goods;  //存放商品对象
    private $level;  //存放商品对象
    private $cateId;  //分类ID，需要排除的分类ID



    private $sale;  //销量排序
    private $osale;  //销量排序
    private $price;  //价格排序
    private $oprice;  //价格排序
    private $time;   //上架时间排序
    private $otime;  //上架时间排序
    private $oall;  //综合排序

    public function __construct(){
        parent::__construct();
        $this->url = $this->request->url();
        $this->url = preg_replace("/\?page\=\d+/i",'', $this->url);   //在选择筛选条件时去掉分页参数
        $this->cateId = Config::get('logs_goos_category_id');
    }


    public function _initialize(){

        $this->cid          = $this->request->param('cid/s','','trim');
        $this->fid          = $this->request->param('fid/s','','trim');
        $this->name         = $this->request->param('name/s','','trim');
        $this->sale         = $this->request->param('sale/d');
        $this->osale        = $this->request->param('osale/d');
        $this->price        = $this->request->param('price/d');
        $this->oprice       = $this->request->param('oprice/d');
        $this->time         = $this->request->param('time/d');
        $this->otime        = $this->request->param('otime/d');
        $this->category     = new GoodsCategory();
        $this->type         = new Type();
        $this->typeFeature  = new TypeFeature();
        $this->feature      = new Features();
        $this->featureValue = new FeaturesValue();
        $this->goods        = new Goods();

    }


    /**
     * 木筑生活馆前端页面展示
     */
    public function index(){

        $where = array();  //查询条件
        $order = array();   //排序条件
        $this->url = urldecode($this->url);
        $cate = $this->getCategory();        //商品的分类

        $this->getName();   //商品名称
        list($startSelected,$feature) = $this->getType();         //商品属性
        if(isset($this->osale)){
            $this->getOrderSaleNumber();     //销量排序
        }

        if(isset($this->oprice)){
            $this->getOrderPrice();          //价格排序
        }

        if(isset($this->otime)){
            $this->getOrderSellTime();       //上架时间排序
        }

        $this->getOrderAll();               //综合排序
        $oall = $this->oall;
        $this->assign('oallUrl',$oall);


        //设置商品名称
        if($this->name){
            $where['goods_name'] = array('like',"%".$this->name."%");
        }


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
                    if($three){
                        $where['category_id'] = array('IN',$three);
                    }else{
                        $where['category_id'] = array('IN',$res);
                    }

                }else{
                    $where['category_id'] = $cate['one'];
                }


            }elseif(isset($cate['top'])){
                $top = array();
                $one = array();
                foreach($cate['top'] as $k=>$v){
                    $temp = $this->category->getNextLevel($v);
                    if($temp){
                        foreach($temp as $key=>$val){
                            $top[] = $val['category_id'];
                        }
                    }
                }

                if($top){
                    foreach($top as $k=>$v){
                        $temp = $this->category->getNextLevel($v);
                        if($temp){
                            foreach($temp as $key=>$val){
                                $one[] = $val['category_id'];
                            }
                        }
                    }
                    if($one){
                        $where['category_id'] = array('IN',$one);
                    }else{
                        $where['category_id'] = array('IN',$top);
                    }
                }else{
                    $where['category_id'] = array('IN',$cate['top']);
                }
            }
        }


        if(array_key_exists('category_id',$where)  && count($where['category_id']) <= 0){
            if(isset($cate['top'])){
                unset($where['category_id']);
            }
        }


        if(isset($this->sale)){  //销量排序
            if($this->sale == 1){
                $order['goods_sale_number'] = 'asc';
            }elseif($this->sale == 2){
                $order['goods_sale_number'] = 'desc';
            }
            //前端选中判断
            $this->assign('osale',1);
        }elseif(isset($this->price)){ //价格排序
            if($this->price == 1){
                $order['goods_price'] = 'desc';
            }elseif($this->price == 2){
                $order['goods_price'] = 'asc';
            }
            //前端选中判断
            $this->assign('oprice',1);
        }elseif(isset($this->time)){ //上架时间排序
            if($this->time == 1){
                $order['goods_sell_time'] = 'desc';
            }elseif($this->time == 2){
                $order['goods_sell_time'] = 'asc';
            }
            //前端选中判断
            $this->assign('otime',1);
        }else{
            $order['goods_sell_time'] = 'desc';
            $this->assign('oall',1);
        }

        $where['is_delete'] = 0;
        $where['goods_verify'] = 1;
        $where['goods_type'] = 1;

        $list=array();
        $page='';
        if((!empty($startSelected) || !empty($feature))){

            if(count($feature)>0) $where['feature_value_id'] = array('IN',$feature);
//            if(count($startSelected)>0) $where['feature_id'] = array('IN',$startSelected);

            $count = Db::name('goods')->alias('a')->distinct(true)->field('a.goods_id,goods_name,goods_image_main,goods_sell_time,goods_sale_number,goods_price')->join('goods_basic_feature b','a.goods_id = b.goods_id')->where($where)->order($order)->count();
            $list = Db::name('goods')->alias('a')->distinct(true)->field('a.goods_id,goods_name,goods_image_main,goods_sell_time,goods_sale_number,goods_price')->join('goods_basic_feature b','a.goods_id = b.goods_id')->where($where)->order($order)->paginate(16,$count);

        }else{
                $count = Db::name('goods')->where($where)->order($order)->count();
                $list = Db::name('goods')->where($where)->order($order)->paginate(16,$count);

        }

        if(is_object($list)){
            $this->assign('page',$this->dealPage($list->render()));
            $list = $list->toArray();
        }else{
            $this->assign('page','');
        }


        //获取收藏状态
        if(count($list['data'])>0){
            $goods_id_arr = array_column($list['data'],'goods_id');
            $favarit_arr = array();
            //李武修改
            if( $this->login == 1 && count($goods_id_arr)>0){
                $arr = Db::name('favorites')->where(['goods_id'=>array('in',$goods_id_arr),'member_id'=>$this->user['member_id']])->field('goods_id')->select();
                if(count($arr)>0) $favarit_arr=array_column($arr,'goods_id');
            }

            foreach( $list['data'] as $k=>$v ){
                $list['data'][$k]['fav'] = 0;
                if(in_array($v['goods_id'],$favarit_arr)) $list['data'][$k]['fav'] = 1;
            }
        }


        $this->assign('list',$list['data']);
        $this->assign('urlLiving',$this->url);
        return $this->fetch();
    }


    /**
     * 设置搜索名称
     */
    public function getName(){
        $name = $this->name;
        $url = $this->url;

        if($name){
//            $url = preg_replace("/\?name\=[\s\S]+/i",'/name/'.$name, $url);
            $this->assign('name',$name);
            $url = preg_replace('/\/name\/[\s\S]+/','%s',$url);

            $nameUse = '<p><span>名称 | '.$name.'</span><a href="' . sprintf($url, '').'" ><span class="close"></span></a></p>';

            $this->assign('nameUse',$nameUse);
        }

    }


    /**
     *   设置销量排序规则
     */
    public function getOrderSaleNumber(){

        $url = $this->url;

        if(preg_match("/\/osale\/1/i",$url)){
            $url = preg_replace("/\/price\/[1-2]/i",'', $url);
            $url = preg_replace("/\/time\/[1-2]/i",'', $url);
            $this->price = null;
            $this->time = null;
        }

        $url = preg_replace("/\/osale\/1/i",'', $url);

        if (preg_match("/\/sale\/1/i",$url))
        {
            $url = preg_replace("/\/sale\/1/i",'', $url);
            $url .= '/sale/2';
        } elseif(preg_match("/\/sale\/2/i",$url))
        {
            $url = preg_replace("/\/sale\/2/i",'', $url);
            $url .= '/sale/1';
        }else{
            $url .= '/sale/1';
            $this->sale = 2;
        }
        $this->assign('sale',$url);

        $this->url = $url;


    }


    /**
     * 设置价格排序规则
     */
    public function getOrderPrice(){

        $url = $this->url;

        if(preg_match("/\/oprice\/1/i",$url)){
            $url = preg_replace("/\/sale\/[1-2]/i",'', $url);
            $url = preg_replace("/\/time\/[1-2]/i",'', $url);
            $this->time = null;
            $this->sale = null;
        }

        $url = preg_replace("/\/oprice\/1/i",'', $url);

        if (preg_match("/\/price\/1/i",$url))
        {
            $url = preg_replace("/\/price\/1/i",'', $url);
            $url .= '/price/2';
            $this->assign('price','price');
        } elseif(preg_match("/\/price\/2/i",$url))
        {
            $url = preg_replace("/\/price\/2/i",'', $url);
            $url .= '/price/1';
            $this->assign('price','price');
        }else{
            $url .= '/price/1';
            $this->price = 2;
        }

        $this->url = $url;

    }


    /**
     * 设置上架时间排序规则
     */
    public function getOrderSellTime(){

        $url = $this->url;

        if(preg_match("/\/otime\/1/i",$url)){
            $url = preg_replace("/\/sale\/[1-2]/i",'', $url);
            $url = preg_replace("/\/price\/[1-2]/i",'', $url);
            $this->sale = null;
            $this->price = null;
        }

        $url = preg_replace("/\/otime\/1/i",'', $url);



        if (preg_match("/\/time\/1/i",$url))
        {
            $url = preg_replace("/\/time\/1/i",'', $url);
            $url .= '/time/2';
            $this->assign('time','time');
        } elseif(preg_match("/\/time\/2/i",$url))
        {
            $url = preg_replace("/\/time\/2/i",'', $url);
            $url .= '/time/1';
            $this->assign('time','time');
        }else{
            $url .= '/time/1';
            $this->time = 2;
        }

        $this->url = $url;

    }


    /**
     * 设置综合排序
     */
    public function getOrderAll(){
        $url = $this->url;
        $url = preg_replace("/\/time\/[1-2]/i",'', $url);
        $url = preg_replace("/\/otime\/[1-2]/i",'', $url);
        $url = preg_replace("/\/price\/[1-2]/i",'', $url);
        $url = preg_replace("/\/oprice\/[1-2]/i",'', $url);
        $url = preg_replace("/\/sale\/[1-2]/i",'', $url);
        $url = preg_replace("/\/osale\/[1-2]/i",'', $url);
        $this->oall = $url;
    }

    /**
     * 设置商品的分类
     */
    public function getCategory(){

        $endSelected = array();  //检索条件
        $category =  $this->category;
        $topCategory = $category->getFirstLevel();      //生活馆的子分类

        if($topCategory){
            foreach($topCategory as $k=>$v){
                if($v['category_id'] == $this->cateId){
                    unset($topCategory[$k]);
                }else{
                    $endSelected[] = $v['category_id'];
                }
            }
        }

        $url = $this->url;         //当前url地址

        if (preg_match("/\/cid\/[\d\w\-]+/i",$url))
        {
            $url = preg_replace("/\/cid\/[\d\w\-]+/i",'%s', $url);
        } else
        {
            $url .= '%s';
        }
        $url = urldecode($url);

        //当没有cid的时候
        if (!$this->cid) {
            $tmpArr = array();
            $tmpArr[] = '<p><a class="cus" href="' .sprintf($url, '' ) . '">全部分类</a></p>';   //当前URL地址
            $endSelected['top'] = $endSelected;
            foreach ($topCategory as $v) {

                $tmpArr[] = '<P><a href="' . sprintf($url, '/cid/' . $v['category_id']) . '">' . $v['name'] . '</a></P>';
            }

            $this->assign('topCategory', $tmpArr);
            return $endSelected;
        }

        //获取当前分类cid的等级
        $this->level = $category->getLevel($this->cid);
        $level = $this->level;
        $selectArrSon = array();


        //当点击一级分类时
        if($level == 1){
            $tmpArr = array();
            $tmpArr[] = '<p><a  href="' . sprintf($url, '' ) . '">全部分类</a></p>';
            $pid = $category->getParentId($this->cid);
            $selectArr = array();

            foreach($topCategory as $k=>$v){
                if ($this->cid == $v['category_id'] || $pid == $v['category_id']) {
                    $tmpArr[] = '<p><a class="cus" href="' . sprintf($url, '/cid/' . $v['category_id']) . '">' . $v['name'] . '</a></p>';
                    $selectArr[] = '<p><span>分类 | '.$v['name'].'</span><a href="' . sprintf($url, '').'" ><span class="close"></span></a></p>';   //选中的分类
                    $endSelected['one'] = $v['category_id'];
                    $this->clickTop = sprintf($url, '/cid/' . $v['category_id']);
                } else {
                    $tmpArr[] = '<p><a href="' .sprintf($url, '/cid/' . $v['category_id']) . '">' . $v['name'] . '</a></p>';
                }
            }

            if($selectArr){
                $this->assign('topSelect',$selectArr);
                $tmpArr = array();
            }

            $this->assign('topCategory', $tmpArr);
            $sonCategory = $category->getNextLevel($this->cid);

            //组合自分类模版
            foreach ($sonCategory as $v) {
                $tmpArr[] = '<p><a  href="' . sprintf($url, '/cid/' . $v['category_id']) . '">' . $v['name'] . '</a></p>';
            }

            $this->assign('sonCategory', $tmpArr);
            return $endSelected;
        }


        //点击二级分类时
        if( $level == 2 ){
            $sonCategory = $category->getNextLevel($this->cid);
            $pid = $category->getParentId($this->cid);
            $pidCate = $category->getOne($pid);
            $nowCate = $category->getOne($this->cid);
            $tmpArr = array();
            $selectArrSon[] = '<p><span>分类 | '.$pidCate->name.'</span><a href="' . sprintf($url, '' ) . '"><span class="close"></span></a></p>';
            $selectArrSon[] = '<p><span>分类 | '.$nowCate->name.'</span><a href="' . sprintf($url, '/cid/' . $pid) . '"><span class="close"></span></a></p>';
            foreach ($sonCategory as $v) {
                    $tmpArr[] = '<p><a  href="' . sprintf($url, '/cid/' . $v['category_id']) . '">' . $v['name'] . '</a></p>';
            }


            $this->assign('topSelectSon',$selectArrSon);
            $this->assign('threeCategory', $tmpArr);
            $endSelected['two'] = $this->cid;
            return $endSelected;
        }


        //点击三级分类
        if($level == 3){
            //二级
            $pidTwo = $category->getParentId($this->cid);
            $pidTwoCate = $category->getOne($pidTwo);
            //一级
            $pidOne = $category->getParentId($pidTwoCate->category_id);
            $pidOneCate = $category->getOne($pidOne);
            //当前
            $nowCate = $category->getOne($this->cid);

            $selectArrSon[] = '<p><span>分类 | '.$pidOneCate->name.'</span><a href="' . sprintf($url, '' ) . '"><span class="close"></span></a></p>';
            $selectArrSon[] = '<p><span>分类 | '.$pidTwoCate->name.'</span><a href="' . sprintf($url, '/cid/' . $pidOne) . '"><span class="close"></span></a></p>';
            $selectArrSon[] = '<p><span>分类 | '.$nowCate->name.'</span><a href="' . sprintf($url, '/cid/' . $pidTwo) . '"><span class="close"></span></a></p>';

            $this->assign('topSelectThree',$selectArrSon);
        }
        $endSelected['three'] = $this->cid;
        return $endSelected;

    }

    /**
     * 根据分类设置商品的属性
     */

    public function getType()
    {
        $url = $this->url;
        $endSelected = array();
        $startSelected = array();
        $error_return=array(array(),array());
        if (preg_match("/\/fid\/[\d\w\-]+/i",$url)) {
            $url = preg_replace("/\/fid\/[\d\w\-]+/i",'%s', $url);
        } else {
            $url .= '%s';
        }

        if(!$this->cid || $this->level == 1) return $error_return;
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
            if(2 == $v['type']  &&  1 == $v['visual']) $features[] = $v['feature_id'];
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
                    foreach($featuresArr as $k=>$v){

                        $tempArrStr = array();    //创建一个空数组
                        for($i=0;$i<$number;$i++){
                            $tempArrStr[$i] = 0;
                        }

                        foreach($tempArrStr as $ke=>$va){
                            $tempArrStr[$ke] = empty($fid[$ke])?0:$fid[$ke];
                        }
                        foreach($featureValue as $key=>$val){

//                            if(in_array($val['feature_id'],$featuresValueIds)){
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

}
