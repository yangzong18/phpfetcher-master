<?php
namespace app\shop\controller;
use app\common\controller\Shop;
use app\shop\model\StoreyConfig;
use app\shop\model\GoodsCategory;
use app\shop\model\StoreyTemplate;
use app\shop\model\ProcessSetting;
use app\shop\model\ProcessCategory;
use app\shop\model\Advertise;
use think\Cache;
use think\Db;
use think\Model;

class Index extends Shop {
	// 验证规则默认提示信息
	protected $wh = [
		'5'     => ['path_2'=>'@w341_h233.png','path_3'=>'@w341_h233.png','path_4'=>'@278_h191.png','path_5'=>'@278_h191.png'],
		'6'     => ['path_2'=>'@w341_h233.png','path_3'=>'@w341_h233.png','path_4'=>'@278_h191.png','path_5'=>'@278_h191.png','path_6'=>'@278_h191.png'],
		'7'     => ['path_2'=>'@w341_h233.png','path_3'=>'@w341_h233.png','path_4'=>'@278_h191.png','path_5'=>'@278_h191.png','path_6'=>'@278_h191.png','path_7'=>'@278_h191.png'],
	];
    /**
     * 首页加载
     */
    public function index()
    {
        $myAdvertise = new Advertise();
        $myStoreyConfig = new StoreyConfig();
        $myGoodsCategory = new GoodsCategory();
        $myStoreyTemplate = new StoreyTemplate();
        $myProcessSetting = new ProcessSetting();
        $myProcessCategory = new ProcessCategory();
        // 获取楼层模板数据
        $storeyTemplateData = $myStoreyTemplate->getCache();
        // 获取楼层配置数据
        //$storeyConfigData = $myStoreyConfig->flushCache();
        $storeyConfigData = $myStoreyConfig->getAllCache();
        $myStoreyConfig->makeIndexIncludeFile();
        // 获取商品一级分类数据
        //$myGoodsCategory->flushCache();
        $firstGoodsCategoryData = $myGoodsCategory->getFirstLevel();
        $this->assign('firstGoodsCategoryData', $firstGoodsCategoryData);
        // 获取整装定制流程数据
        $processCategoryRow = $myProcessCategory->field('cate_id')
                                                ->where(array('name' => '整装定制流程'))
                                                ->find();
        $cate_id = $processCategoryRow['cate_id'];
        $processSettingRows = $myProcessSetting->field('*')
                                               ->order(array('sort' => 'asc'))
                                               ->where(['cate_id'=>$cate_id,'is_delete'=>0])
                                               ->select();
        $this->assign("packagedProcessSetting", $processSettingRows);
        // 整理楼层配置数据，并使用 goods_category_id 作为索引
		$array = array();
        foreach ($storeyConfigData as $storey_config_id => $configData){
			$array = json_decode($configData['parameter'],true);
			/*2017-3-9  yp  添加图片的缩略图**/
			foreach($array as $k => $v){
				$wh = $this->wh[count($v['forEdit'])];
				for($i = 2; $i <=count($v['forEdit']);$i++){
					$array[$k]['path_'.$i] = $v['path_'.$i].$wh['path_'.$i];
				}
			}
			$configData['parameter'] = json_encode($array);
            // storeyConfigData 里面柔和了以 goods_category_id 作为索引的商品一级分类数据，
            // 以及使用 storey_config_id 作为索引的商品一级分类数据
            $storeyConfigData[$configData['goods_category_id']] = $configData;
        }
        // 遍历商品分类楼层并获取楼层配置
        $include_string = '';
        $i=1;
		//李武修改
		$dw_arr = array();
        foreach ($firstGoodsCategoryData as $first_goods_category_id => $categorydData){
            // 模板过滤
            // 商品是否配置相应的模板
            if (!isset($storeyConfigData[$first_goods_category_id])){
                continue;
            }
            // 模板失效
            $storeyConfig = $storeyConfigData[$first_goods_category_id];
            if ($storeyConfig['is_disable']){
                continue;
            }
            $unique_name  = $storeyConfig['unique_name'];

			//李武修改
			$dw_arr[]=array('name'=>$categorydData['name'],'index'=>$i);


            // 获取一级分类商品名称
            $this->assign($unique_name ."_name", $categorydData['name']);
            $this->assign($unique_name."_index",$i);
			$this->assign($unique_name ."_url", $storeyConfigData[$first_goods_category_id]['url']);
            // 获取楼层配置数据
            $storeyConfig = json_decode($storeyConfig['parameter'], true);
            $i++;
            // 配置模板数据
            $this->assign($unique_name, $storeyConfig);
        }

		$this->assign('dw_arr',$dw_arr);
        // 获取新手指南信息
        $newbieGuide = Cache::get('newbie_guide_ctrl');
        $newbieGuide = empty($newbieGuide) ? array() : $newbieGuide;
        $this->assign('newbieGuide', $newbieGuide);
        // 获取支付方式信息
        //$payMethod = $dataList = Cache::get('pay_method_ctrl');

        $payMethod = Db::name('payment')->where(['payment_state'=>1])->column('payment_name');
        $payMethod = empty($payMethod) ? array() : $payMethod;
        $this->assign('payMethod', $payMethod);

        // 获取售后服务信息
        $service = $dataList = Cache::get('service_ctrl');
        $service= empty($service) ? array() : $service;
        $this->assign('service', $service);
        $setting= $dataList = Cache::get('setting');
        $setting= empty($setting) ? array() : $setting;
        $this->assign('setting',$setting);
        // 获取服务站点信息
        $servicePoint = $dataList = Cache::get('service_point_ctrl');
        $servicePoint = empty($servicePoint) ? array() : $servicePoint;
        $this->assign('servicePoint', $servicePoint);
        // 获取安装方式信息
        $installMethod = $dataList = Cache::get('install_method_ctrl');
        $installMethod = empty($installMethod) ? array() : $installMethod;
        //去除host,这个插件有些诡异
        foreach ($installMethod as $key => $install) {
            $installMethod[$key]['video'] = str_replace(HTTP_SITE_HOST, '', $install['video']);
        }
        $this->assign('installMethod', $installMethod);
        // 获取配送方式信息
        $distributeMethod = $dataList = Cache::get('distribute_method_ctrl');
        $distributeMethod = empty($distributeMethod) ? array() : $distributeMethod;
        $this->assign('distributeMethod', $distributeMethod);
        // 原木介绍及家具生产过程
        $processCategoryRow = $myProcessCategory->field('cate_id')
                                                ->where(array('name' => '原木介绍及家具生产过程'))
                                                ->find();
        $cate_id = $processCategoryRow['cate_id'];
        $processSettingRows = $myProcessSetting->field('*')
                                               ->order(array('sort' => 'asc'))
                                               ->where(['cate_id'=>$cate_id,'is_delete'=>0])
                                               ->select();
        $this->assign("productiveProcess", $processSettingRows);
        // 是否是首页标记
        $this->assign("is_index_page", true);
        // 首页滚动广告位信息
        $where = array();
        $where['is_delete'] = array('=', 0);
        $where['adv_type'] = array('in','0,1');
        $advertiseRows = $myAdvertise->field('*')->order(array('adv_sort' => 'asc', 'is_delete'=>0))->where($where)->select();
        $advertiseRows = Model::getResultByFild($advertiseRows);
        $adverRows = array();
        $adcolose_str='';
        $bigAdvertise = array();
        foreach ($advertiseRows as $key => $advertise) {
            if(empty($advertise['adv_img'])) continue;
            $advertise['adv_img'] = str_replace("\\", '/', $advertise['adv_img']);
            if($advertise['adv_type']==1){
                $adverRows[]=$advertise;
            }elseif($advertise['adv_type']==0){
                if($adcolose_str=='') {
                    $adcolose_str=$advertise['adv_img'];
                    $bigAdvertise = $advertise;
                }
            }
        }
        $this->assign("bigAdvertise", $bigAdvertise);
        $this->assign("advertiseRows", $adverRows);
        $this->assign("adcolose_str", $adcolose_str);
        return $this->fetch();
    }
}
