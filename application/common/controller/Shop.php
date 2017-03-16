<?php
namespace app\common\controller;
use app\shop\model\GoodsCategory;
use app\shop\model\Navigation;
use app\shop\model\Advertise;
use app\common\model\Setting;
use app\common\logic\Feature;
use think\Controller;
use think\Cache;
use think\Session;
use think\Config;
use think\Debug;
use think\Log;

class Shop extends Controller {

    //用户信息
    public $user;
    //验证用户是否登陆
    public $login;
    /**
     * 初始化构造器
     */
    public function __construct() {
        parent::__construct();
        //初始化用户登陆信息
        $this->checkLogin();
        //初始化分类信息
        $this->category();
        $search_arr=array();
        $search_str=Cache::get('search_ctrl');
        if(!empty($search_str)){
            $search_arr=explode(',',$search_str);
        }
        $this->assign("search_arr", $search_arr);
    }

    /**
     * 初始化登陆信息
     */
    public function checkLogin() {
        $this->login = Session::has('user') ? 1 : 0 ;
        //如果用户已经登陆，则将用户的信息解析出来
        if ( $this->login == 1 ) {
            $this->user = unserialize( Session::get('user') );
            $this->user['member_name'] = trim( $this->user['member_name'] ) == '' ? $this->user['phone'] : $this->user['member_name'];
        }
        $this->assign('isLogin', $this->login);
        $this->assign('user', $this->user);
    }

    /**
     * 初始化分类信息
     */
    public function category() {
        // 获取友情链接信息
        $friendlyLink = Cache::get('friendly_link_ctrl');
        $this->assign("friendlyLink", $friendlyLink);
        $this->assign("friendly_link_num", count($friendlyLink));

        $myAdvertise = new Advertise();
        $myNavigation = new Navigation();
        $myGoodsCategory = new GoodsCategory();
        $featureModel = new Feature();
        // 获取首页导航条信息
        $navigationRows = $myNavigation->field("*")
                                       ->where(array('location' => 0,'type'=>['not in',[4,5,6]]))
                                       ->order(array('sort' => 'asc'))
                                       ->select();
//        foreach ($navigationRows as $key => $navigation){
//            $navigation['url'] = Config::get('url_domain_protocol') . Config::get('url_domain_root') ."/". $navigation['url'];
//            $navigationRows[$key] = $navigation;
//        }
        $this->assign("navigationRows", $navigationRows);

        //招商加盟
        $alliance = $myNavigation->field('url,title,new_open')->where(array('type'=>4))->order('id desc')->find();
        $this->assign('alliance',$alliance);

        //客户服务
        $cusService = $myNavigation->field('url,title,new_open')->where(array('type'=>5))->order('id desc')->find();
        $this->assign('cusService',$cusService);

        // 获取售后服务信息
        $service = Cache::get('service_ctrl');
        $service = empty($service) ? array() : $service;
        $this->assign('service', $service);

        // 获取商品一级商品分类导航
        $categoryNavigate = Cache::get('category_navigate_ctrl');

        // 刷新缓存
        //$myGoodsCategory->flushCache();
        // 获取商品一级分类数据，该数据的索引为自身 id
        $firstGoodsCategoryData = $myGoodsCategory->getFirstLevel();
        // 获取商品二级分类数据，该数据的索引为一级商品分类id
        $secondGoodsCategoryData = $myGoodsCategory->getSecondLevel();
        //查询分类下面类型和属性
        $typeIdList = array();
        $categoryId = array();
        foreach ($secondGoodsCategoryData as $categoryList) {
            foreach ($categoryList as $category) {
                if ( !in_array($category['type_id'], $typeIdList) ) {
                    array_push($typeIdList, $category['type_id']);
                }
                array_push($categoryId, $category['category_id']);
            }
        }
        //查询分类下面的属性和属性值
        $featureList = $featureModel->getFeatureByType( $typeIdList );
        // 获取商品三级分类数据，该数据的索引为二级商品分类id
        $thirdGoodsCategoryData = $myGoodsCategory->getThirdLevel();
        // 组装数据
        $firstGoodsCategoryTemp = array();
        //获取整装的分类ID
        $logCategoryId = Config::get('logs_goos_category_id');
        $this->assign("logCategoryId", $logCategoryId);
        foreach ($firstGoodsCategoryData as $key => $firstGoodsCategoryOpt){
            $first_goods_category_id = $firstGoodsCategoryOpt['category_id'];
            $secondGoodsCategoryList = isset($secondGoodsCategoryData[$first_goods_category_id]) ?
                                       $secondGoodsCategoryData[$first_goods_category_id] : array();
            $secondGoodsCategoryTemp = array();
            foreach ($secondGoodsCategoryList as $secondGoodsCategoryOpt){
                // 组装二级分类子分类 => 三级分类
                $second_goods_category_id = $secondGoodsCategoryOpt['category_id'];
                $thirdGoodsCategoryList = isset($thirdGoodsCategoryData[$second_goods_category_id]) ?
                                          $thirdGoodsCategoryData[$second_goods_category_id] : array();
                $secondGoodsCategoryOpt['sub_goods_category'] = $thirdGoodsCategoryList;
                // 获取该商品二级分类的首页广告图
                $secondGoodsCategoryOpt['goods_category_img'] = $myAdvertise->getAdvertiseByCategoryId( $secondGoodsCategoryOpt['category_id'] );
                $secondGoodsCategoryOpt['type_list'] = isset( $featureList[ $secondGoodsCategoryOpt['type_id'] ] ) ? $featureList[ $secondGoodsCategoryOpt['type_id'] ] : array();
                $secondGoodsCategoryTemp[] = $secondGoodsCategoryOpt;
            }
            // 组装一级分类子分类 => 二级分类
            $firstGoodsCategoryOpt['sub_goods_category'] = $secondGoodsCategoryTemp;
            if( is_array($categoryNavigate) && array_key_exists($first_goods_category_id,$categoryNavigate) ){
                $firstGoodsCategoryOpt['controller'] = isset( $categoryNavigate[$first_goods_category_id]['controller'] ) ? $categoryNavigate[$first_goods_category_id]['controller'] : HTTP_SITE_HOST;
            }else{
                $firstGoodsCategoryOpt['controller'] = HTTP_SITE_HOST;
            }
            $firstGoodsCategoryTemp[] = $firstGoodsCategoryOpt;
        }
        $this->assign("firstGoodsCategoryTemp", $firstGoodsCategoryTemp);
        // 是否是首页标记，默认不是首页
        $this->assign("is_index_page", false);
        //加载基础配置
        $this->assign( 'setting', ( new Setting() )->inquire() );
    }


    /**
     * 将分页按钮符号转换为文字
     * @param $str string
     * @return string
     */
    public function dealPage($str){
        if(!$str){
            return '';
        }
        $res = preg_replace('/&lt;&lt;/','首页',$str);
        $res = preg_replace('/&lt;/','上一页',$res);
        $res = preg_replace('/&gt;&gt;/','尾页',$res);
        $res = preg_replace('/&gt;/','下一页',$res);

        return $res;
    }

}
