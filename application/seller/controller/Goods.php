<?php
namespace app\seller\controller;

use app\seller\model\GoodsCategory;
use app\common\controller\Auth;
use think\Cache;
use think\Db;
use think\Exception;
use think\Validate;
use Util\Tools;
use think\Config;
use app\common\model\Goods as GD;

class Goods extends Auth
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule = array(
        'category_id'           => 'require',
        'goods_name'            => 'require|max:192',
		'goods_name'			=> 'require|desc',
        'goods_price'           => 'require|number|gt:0',
        'goods_market_price'    => 'require|number|gt:0',
        'goods_type'            => 'require',
        'goods_storage'         => 'require|number|egt:0',
        'goods_storage_alarm'   => 'require|number|egt:0',
        'image'                 => 'require',
        'editorContent1'        => 'require',
        'editorContent2'        => 'require',
        'editorContent3'        => 'require',
        'editorContent4'        => 'require',
    );

    /**
     * 验证消息
     * @var array
     */
    protected $message = array(
        'category_id.require'          => '商品分类不能为空',
        'goods_name.require'           => '商品名称不能为空',
        'goods_name.max'               => '商品名称最多不超过64个汉字',
		'goods_name.desc'             => '商品名称含有非法字符',
        'goods_price.require'          => '商品价格不能为空',
        'goods_price.number'           => '商品价格必为数字',
        'goods_price.gt'               => '商品价格为大于0的数字',
        'goods_market_price.require'   => '商品市场价格不能为空',
        'goods_market_price.number'    => '商品市场价格必为数字',
        'goods_market_price.gt'        => '商品市场价格为大于0的数字',
        'goods_type.require'           => '商品类型不能为空',
        'goods_storage.require'        => '商品库存不能为空',
        'goods_storage.number'         => '商品库存必为整数',
        'goods_storage.egt'            => '商品库存为大于等于0整数',
        'goods_storage_alarm.require'  => '商品库存预警不能为空',
        'goods_storage_alarm.number'   => '商品库存预警必为整数',
        'goods_storage_alarm.egt'      => '商品库存预警为大于等于0整数',
        'image.require'                => '商品主图不能为空',
        'editorContent1.require'       => '商品详情 商品实拍不能为空',
//		'editorContent1.desc'         => '商品详情 商品实拍含有非法字符',
        'editorContent2.require'       => '商品详情 商品规格不能为空',
//		'editorContent2.desc'         => '商品详情 商品规格含有非法字符',
//		'editorContent3.desc'         => '商品详情 材质说明含有非法字符',
//		'editorContent4.desc'         => '商品详情 品质保证含有非法字符',
        'editorContent3.require'       => '商品详情 材质说明不能为空',
        'editorContent4.require'       => '商品详情 品质保证不能为空',
    );



    /**
     * 商品列表
     */
    public function lists()
    {

        $category_id =  $this->request->param('category_id/a');
        $category_ids =  $this->request->param('category_id/a');
        $goods_type = $this->request->param('goods_type/d',0);
        $goods_type = intval($goods_type);
        if($goods_type!=1 && $goods_type!=2) $goods_type = 0;

        $name =  $this->request->param('goods_name');   //搜索的名称或者ID
        $search_name =  $this->request->param('search_name');  //按名称为1 按编号为2
        $where = array();
        $where['is_delete'] = 0;


        if($category_id){
            foreach($category_id as $ks=>$vl) {
                if($vl == '0'){
                    unset($category_id[$ks]);
                }
            }
        }

        if($category_id){   //分类查询时的查询条件
            $keys = array_keys($category_id);
            $cid = array_pop($keys);
            if($cid == 2 && $category_id[$cid] != '0'){
                $where['category_id'] = array('=',$category_id[$cid]);
            }elseif($cid == 1 && $category_id[$cid] != '0'){
                $cate = Db::name('goods_category')->where("parent_id = '".$category_id[$cid]."'")->select();

                $cateArr = array();
                if($cate){
                   foreach($cate as $k=>$v){
                       $cateArr[] = $v['category_id'];
                   }
                    $where['category_id'] = array('IN',$cateArr);
                }else{
                    $where['category_id'] = array('IN',array('0'));
                }
            }elseif($cid == 0 && $category_id[$cid] != '0'){
                $cate = Db::name('goods_category')->where("parent_id = '".$category_id[$cid]."'")->select();

                $cateArr = array();
                if($cate){
                    foreach($cate as $k=>$v){
                        $cateArr[] = $v['category_id'];
                    }
                    $cateArrStr = implode("','",$cateArr);
                    $cates = Db::name('goods_category')->where("parent_id IN ('".$cateArrStr."')")->select();
                    $catesArr = array();
                    if($cates){
                        foreach($cates as $k=>$v){
                            $catesArr[] = $v['category_id'];
                        }
                        $where['category_id'] = array('IN',$catesArr);
                    }else{
                        $where['category_id'] = array('IN',array('0'));
                    }
                }else{
                    $where['category_id'] = array('IN',array('0'));
                }

            }

            $this->assign('nowCategory',$category_ids);
        }

        if($name){    //根据商品名称检索条件
            if($search_name == 1){
                $where['goods_name'] = array('like',"%".$name."%");
                $this->assign('name',$name);
                $this->assign('search_name',1);
            }elseif($search_name == 2){
                $where['goods_id'] = array('like',"%".$name."%");
                $this->assign('goods_id',$name);
                $this->assign('search_name',2);
            }

        }

        //添加审核状态搜索
        $param = $this->request->param();
        if ( isset( $param['verify'] ) && in_array( $param['verify'] , array(0,1,3)) ) {
            $where['goods_verify'] = $param['verify'];
        } else {
            $param['verify'] = 2;
        }

        if($goods_type==1 || $goods_type==2){
            $where['goods_type'] = $goods_type;
            $param['goods_type'] = $goods_type;
        }else{
            $param['goods_type'] = 0;
        }


        $model = new GoodsCategory();
        //获取顶级分类
        $LevelOne = $model->where("parent_id = '0'  AND status = 1 AND is_delete = 0 and category_id != '".Config::get('logs_goos_category_id')."'")->select();
        $this->assign('category',$LevelOne);
        //二级分类
        if($category_ids[0] && $category_ids[1]){
            $LevelTwo = $model->where("parent_id = '".$category_id[0]."'")->select();
            $this->assign('LevelTwo',$LevelTwo);
        }
        //三级分类
        if($category_ids[1] && $category_ids[2]){
            $LevelThree = $model->where("parent_id = '".$category_id[1]."'")->select();
            $this->assign('LevelThree',$LevelThree);
        }


        $url = $this->request->url();
       if(substr($url,-1) == '=') $url = $url.'0';
       if(strpos($url,'page')==true) $url = preg_replace("/[\/|\?]page\=\d+/i",'', $url);

        // 查询所有的用户数据 并且每页显示8条数据
        $list = Db::name('goods')->where($where)->order('goods_created_at desc')->paginate(10,'',array('path'=>$url));
        $page = $list->render();
        if(count($this->request->param())>1){
            $page = str_replace('?page=','&page=',$page);
        }


        $features = Db::name('features')->select();
        $features = $this->changKey($features,'feature_id');
        $featuresValue = Db::name('features_value')->select();
        $featuresValue = $this->changKey($featuresValue,'id');



        if($list){
            foreach($list as $k=>$v){

                $sku = Db::name('goods_sku')->where("goods_id = '".$v['goods_id']."'")->select();
                $value = Db::name("goods_sale_feature")->where("goods_id = '".$v['goods_id']."'")->order("feature_value_id asc")->select();
                $values = $value;
                //具有相同颜色的商品组
                $commonColor = array();
                if($values){
                    while($values){
                        $tempArr = array();
                        $temp = array_pop($values);
                        $tempArr[] = $temp['group_id'];
                        foreach($values as $kk=>$vv){
                            if($temp['feature_value_id'] == $vv['feature_value_id']){
                                $tempArr[] = $vv['group_id'];
                                unset($values[$kk]);
                            }
                        }
                        $commonColor[$temp['feature_value_id']] = $tempArr;
                    }
                }

                if($sku && $value){
                    foreach($sku as $ke=>$va){    //一个商品的各个规格

                        $temp = array(
                            'goods_price' => $va['goods_price'],
                            'goods_number' => $va['goods_number'],
                            'goods_storage' => $va['goods_storage'],
                            'goods_storage_alarm' => $va['goods_storage_alarm'],
                            'goods_serial' => $va['goods_serial'],
                        );
                        foreach($value as $key=>$val){
                            if($va['group_id'] == $val['group_id']){
                                $groupStr = implode("','",$commonColor[$val['feature_value_id']]);
                                $image = Db::name('attachment')->where("business_id IN ('".$groupStr."')")->select();
                                if(isset($image[0]['attachment_url']) && empty($temp['attachment_url'])){
                                    $temp['attachment_url'] = $image[0]['attachment_url'];
                                }

                                $temp['feature'][] =  array(
                                    'feature_id'=>$val['feature_id'],
                                    'feature_value_id'=>$val['feature_value_id'],
                                    'feature_name'=>$features[$val['feature_id']]['attribute_name'],
                                    'feature_value_name'=>$featuresValue[$val['feature_value_id']]['feature_value'],
                                    );
                            }
                        }
                        $v['feature'][] = $temp;
                    }
                }
                $list[$k] = $v;
            }
        }

        // 把分页数据赋值给模板变量list
        $this->assign('list', $list);
        $this->assign('page', $page);
        $this->assign('param', $param);
        // 渲染模板输出
        return $this->fetch();
    }

    /**
     * 显示添加商品页面
     */
    public function add(){

        $model = new GoodsCategory();

        //获取顶级分类
        $where = array( 'parent_id' => 0, 'is_delete'=>0, 'category_id' => array('neq', Config::get('logs_goos_category_id')) );
        $data = $model->where( $where )->select();
        $this->assign('category',$data);

        return $this->fetch();
    }


    /**
     * 执行商品添加操作
     */
    public function doAdd(){

        //对传递参数进行验证
        $param = $this->request->param();
        $validate = new Validate($this->rule, $this->message);
        if ( !$validate->check( $param ) ) {
            $this->error( $validate->getError() );
        }

        $goods_id           = Tools::guid();   //商品ID
        $category_id        = $this->request->param('category_id'); //商品分类ID
        $goods_name         = $this->request->param('goods_name'); //商品名
        $goods_desc         = $this->request->param('goods_desc','','trim'); //商品说明
        $goods_price        = $this->request->param('goods_price'); //商品价格
        $goods_market_price = $this->request->param('goods_market_price');//商品市场价
        $goods_type         = $this->request->param('goods_type');//商品类型
        $goods_serial       = $this->request->param('goods_serial');//商品卖家货号+ 、
        $goods_storage      = $this->request->param('goods_storage');//商品库存
        $goods_storage_alarm= $this->request->param('goods_storage_alarm');//商品库存+
        $image              = $this->request->param('image');//商品主图
        $editorContentOne   = $this->request->param('editorContent1');//商品详情 商品实拍
        $editorContentTwo   = $this->request->param('editorContent2');//商品详情 商品规格
        $editorContentThree = $this->request->param('editorContent3');//商品详情 材质说明
        $editorContentFour  = $this->request->param('editorContent4');//商品详情  品质保证
        $salePrice          = $this->request->param('salePrice/a');//规格：商品售价
        $featureIdName      = $this->request->param('featureIdNames/a');//规格名称
        $featureName        = $this->request->param('featureNames/a');//规格值
        $saleMarketPrice    = $this->request->param('saleMarketPrice/a');//规格：商品售价
        $saleStorage        = $this->request->param('saleStorage/a');//规格：商品库存
        $saleStorageAlarm   = $this->request->param('saleStorageAlarm/a');//规格：商品库存预警
        $saleNumber         = $this->request->param('saleNumber/a'); //规格：商品货号
        $store_id           = $this->user['store_id'];//店铺ID
        $feature            = $this->request->param('feature/a');
        $feature_ids        = $this->request->param('feature_ids/a');//规格：商品销售规格
        $feature_basic      = $this->request->param('feature_basic/a');//规格：商品基本规格
//      $editorContent      = $this->request->param('editorContent');//商品详情

        if(!empty($goods_desc) && strlen($goods_desc)>500) {
            $this->error('商品说明字符不能超过500字符');
        }


        if(trim($category_id) == ''
            || trim($goods_name) == ''
            || trim($goods_price) == ''
            || trim($goods_market_price) ==''
            || trim($goods_storage) == ''
            || trim($goods_type) == ''
            || trim($image) == ''
            || trim($editorContentOne) == ''
            || trim($editorContentTwo) == ''
            || trim($editorContentThree) == ''
            || trim($editorContentFour) == '' ){
            $this->error('添加商品数据不可为空！');
        }

        if(count($saleNumber) != count($salePrice)
            || count($salePrice) != count($salePrice)
            || count($saleStorage) != count($saleNumber)){
            $this->error('属性值输入错误');
        }

        $data = array(
            'goods_id'           =>     $goods_id,
            'goods_name'         =>     $goods_name,
            'goods_desc'         =>     $goods_desc,
            'store_id'           =>     $store_id,
            'store_name'         =>     $this->user['store_name'],
            'goods_storage'      =>     $goods_storage,
            'goods_storage_alarm'=>     $goods_storage_alarm,
            'goods_serial'       =>     $goods_serial,
            'goods_type'         =>     $goods_type,
            'goods_price'        =>     $goods_price,
            'goods_market_price' =>     $goods_market_price,
            'goods_verify'       =>     3,
            'goods_created_at'   =>     time(),
            'is_delete'          =>     0,
            'category_id'        =>     $category_id,
            'goods_image_main'   =>     $image,
        );

        $extra = array(
            'goods_id'                        =>      $goods_id,
            'goods_real_shot'                 =>      $editorContentOne,
            'goods_specifications'            =>      $editorContentTwo,
            'goods_material_description'      =>      $editorContentThree,
            'goods_quality_assurance'         =>      $editorContentFour,
        );

        $cate = new GoodsCategory();
        $level = $cate->getLevel($category_id);
        if($level != 3){
            $this->error('商品分类选择错误或未选择第三级分类');
        }

        if(mb_strlen($param['goods_name'],'utf-8') > 64){
            $this->error('商品名称最长应不超过64个汉字');
        }



        //开启事务
        Db::startTrans();

        try{

            $addGoods = Db::name('goods')->insert($data);   //添加商品
            if(!$addGoods){
                throw new Exception('商品数据添加失败');
            }

            $addGoodsExtra = Db::name('goods_extra')->insert($extra);  //添加商品扩展信息
            if(!$addGoodsExtra){
                throw new Exception('商品扩展信息添加失败');
            }


//            if($picture){                           //添加商品附图
//                $pic = trim($picture,',');
//                $picArr = explode(',',$pic);
//                if(is_array($picArr) && count($picArr) > 0){
//                    foreach($picArr as $k=>$v){
//                        $insert = array(
//                            'attachment_url'    =>  $v,
//                            'business_sn'       =>  'goods',
//                            'business_id'       =>  $goods_id,
//                            'store_id'          =>  $this->user['store_id'],
//                            'created_at'        =>  time(),
//                            'sort'              =>  0,
//                            'is_delete'         =>  0,
//                        );
//
//                        Db::name('attachment')->insert($insert);
//                    }
//
//                }
//            }

            if(is_array($saleNumber) && count($saleNumber) > 0){
            foreach($saleNumber as $k=>$v){

                if($salePrice[$k] <= 0){  //价格判断
                    throw new Exception('商品价格不能为0或负数');
                }
                if($saleMarketPrice[$k] <= 0){
                    throw new Exception('商品市场价不能为0或负数');
                }

                if($salePrice[$k] > $saleMarketPrice[$k]){
                    throw new Exception('商品价格不能高于市场价');
                }

                $groupId = Tools::guid();
                $sku = array(
                    'goods_sku'          =>      Tools::guid(),
                    'goods_price'        =>      $salePrice[$k],
                    'goods_market_price' =>      $saleMarketPrice[$k],
                    'goods_number'       =>      $saleStorage[$k],
                    'goods_storage'      =>      $saleStorage[$k],
                    'goods_storage_alarm'=>      $saleStorageAlarm[$k],
                    'goods_serial'       =>      $saleNumber[$k],
                    'goods_id'           =>      $goods_id,
                    'group_id'           =>      $groupId,
                );

                if($feature_ids[$k] && is_array($feature_ids[$k])){
                    foreach($feature_ids[$k] as $key=>$val){

                        $feature_id = $this->getFeatureId($val);
                        $sales = array(                     //组织销售属性数据
                            'feature_id'       => (int)$feature_id,
                            'feature_value_id' => (int)$val,
                            'goods_id'         => $goods_id,
                            'group_id'         => $groupId,
                        );
                        $addSale = Db::name('goods_sale_feature')->insert($sales); //添加销售属性数据
                        if(!$addSale){
                            throw new Exception('销售属性添加失败');
                        }
                    }
                }
                $skuName = array();
                if($featureIdName[$k]){
                    foreach($featureIdName[$k] as $ke=>$va){
                        $skuName[$va] = $featureName[$k][$ke];
                    }
                }
                $sku['sku_name'] = json_encode($skuName);

                $addSku = Db::name('goods_sku')->insert($sku);        //添加商品的SKU
                if(!$addSku){
                    throw new Exception('SKU添加失败');
                }
            }
            }else{  //在没有选择属性时
                $groupId = Tools::guid();

                if($goods_price <= 0){  //价格判断
                    throw new Exception('商品价格不能为0或负数');
                }
                if($goods_market_price <= 0){
                    throw new Exception('商品市场价不能为0或负数');
                }

                if($goods_price > $goods_market_price){
                    throw new Exception('商品价格不能高于市场价');
                }



                $sku = array(
                    'goods_sku'          =>      Tools::guid(),
                    'goods_price'        =>      $goods_price,
                    'goods_market_price' =>      $goods_market_price,
                    'goods_number'       =>      $goods_storage,
                    'goods_storage'      =>      $goods_storage,
                    'goods_storage_alarm'=>      $goods_storage_alarm,
                    'goods_serial'       =>      $goods_serial,
                    'goods_id'           =>      $goods_id,
                    'group_id'           =>      $groupId,
                );
                $sku['sku_name'] = json_encode(array('颜色'=>'无'));
                $addSku = Db::name('goods_sku')->insert($sku);        //添加商品的SKU

                if(!$addSku){
                    throw new Exception('SKU添加失败');
                }

                $sales = array(                     //组织销售属性数据  在没有选择属性时，添加一条默认销售属性 2017/1/6
                    'feature_id'       => 1,
                    'feature_value_id' => 1,
                    'goods_id'         => $goods_id,
                    'group_id'         => $groupId,
                );
                $addSale = Db::name('goods_sale_feature')->insert($sales); //添加销售属性数据
                if(!$addSale){
                    throw new Exception('添加销售属性失败');
                }
            }

            if(is_array($feature_basic)){
                foreach($feature_basic as $ke=>$va){
                    $basic = array(                 //基础属性数据
                        'feature_id' => $ke,
                        'feature_value_id' => $va,
                        'goods_id' => $goods_id,
                    );

                    $addBasic = Db::name('goods_basic_feature')->insert($basic);
                    if(!$addBasic){
                        throw new Exception('基础属性添加失败');
                    }
                }
            }

            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }

        //存入SKU  sku_goods_id=>sku
        $gid_key = "sku_".$goods_id;
        $goods_sku_arr = array();
        $goodsSku = Db::name('goods_sku')->where(['goods_id'=>$goods_id])->column('goods_sku');
        foreach($goodsSku as $k=>$v){
            $goods_sku_arr[] = $v;
        }
        Cache::set($gid_key,$goods_sku_arr);

        $this->success('添加商品成功',url("seller/goods/addImage",'goods_id='.$goods_id));


    }


    /**
     * 添加完商品后添加图片
     * @param $gid int
     * @return array
     */
    public function addImage($gid=0){

        if($gid){
            $goods_id = $gid;
        }else{
            $goods_id = $this->request->param('goods_id'); //商品ID
        }
        if(!$goods_id){
            $this->error('请传入商品ID',url("seller/goods/lists"));
        }

        //找到商品的sku
        $sku = Db::name('goods_sku')->where("goods_id = '".$goods_id."'")->select();

        $hasColor = array();
        $skuArr = array();  //sku中销售组的ID
        if(is_array($sku) && count($sku) > 0){
            foreach($sku as $k=>$v){
                $skuArr[] = $v['group_id'];
            }
            $skuStr = implode("','",$skuArr);

            //根据组ID找出销售属性
            $skuRes = Db::name('goods_sale_feature')->where("group_id IN ('".$skuStr."')")->select();
            if(is_array($skuRes) && count($skuRes) > 0){
                $featureId = array();
                foreach($skuRes as $k=>$v){
                    $featureId[] = $v['feature_id'];
                }

                $featureIdStr = implode("','",$featureId);
                $feature_sale = Db::name('features')->where("feature_id IN ('".$featureIdStr."')")->select();
                $color = array(); //颜色属性
                if(is_array($feature_sale) && count($feature_sale) > 0){
                    foreach($feature_sale as $k=>$v){
                        if($v['is_color'] == 1){
                            $color[] = $v['feature_id'];
                        }
                    }
                }

                if(!$color) {
                    $this->assign('group_id', $sku[0]['group_id']);
                }
                    //商品拥有具体颜色
                    foreach($skuRes as $k=>$v){
                        if(in_array($v['feature_id'],$color)){
                            $hasColor[] = $v;
                        }
                    }

                    if(is_array($hasColor) && count($hasColor) > 0){
                        $hasColor = $this->changKey($hasColor,'feature_value_id');
                        foreach($hasColor as $k=>$v){
                            $res = Db::name('features_value')->where("id ='".$v['feature_value_id']."'")->find();

                            if($res){
                                $hasColor[$k]['feature_value'] = $res['feature_value'];
                            }
                        }
                    }
            }else{ //无规格商品无颜色属性
                $this->assign('group_id',$sku[0]['group_id']);

            }
        }

        if($gid){
            return $hasColor;
        }else{
            $this->assign('color',$hasColor);
            return $this->fetch();
        }

    }


    /**
     * 执行图片添加
     */
    public function doAddImage(){
        $image            =  $this->request->param('image/a');
        $group            =  $this->request->param('group/a');


        //开启事务
        Db::startTrans();

        try{
            if(is_array($image) && is_array($group)){
                foreach($image as $k=>$v){
                if($v){                           //添加商品附图
                    $pic = trim($v,',');
                    $picArr = explode(',',$pic);
                    if(is_array($picArr) && count($picArr) > 0){
                        foreach($picArr as $ke=>$va){
                            $insert = array(
                                'attachment_url'    =>  $va,
                                'business_sn'       =>  'group_id',
                                'business_id'       =>  $group[$k],
                                'store_id'          =>  $this->user['store_id'],
                                'created_at'        =>  time(),
                                'sort'              =>  0,
                                'is_delete'         =>  0,
                            );
                            Db::name('attachment')->insert($insert);
                        }

                    }
                }
                }
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();

            $this->error('添加图片失败！');
        }
        $this->success('添加图片成功！',url('seller/goods/lists'));
    }


    /**
     * 商品图片编辑页面
     */
    public function editImage(){

        $goods_id  =  $this->request->param('goodsId');
        if($goods_id){
            $res = $this->addImage($goods_id);

            if(is_array($res) && count($res) > 0){
                foreach($res as $k=>$v){
                    $attach = Db::name('attachment')->where("business_sn = 'group_id' AND business_id = '".$v['group_id']."'")->select();
                    if(!$attach){
                        $result = Db::name('goods_sale_feature')->where("feature_value_id = '".$v['feature_value_id']."' AND goods_id = '".$goods_id."'")->select();
                        if($result){
                            foreach($result as $ke=>$va){
                                if($va['group_id'] != $v['group_id']){
                                    $result = Db::name('attachment')->where("business_sn = 'group_id' AND business_id = '".$va['group_id']."'")->select();
                                    if($result){
                                        $attach = $result;
                                    }
                                }
                            }
                        }
                    }

                    if($attach){
                        foreach($attach as $ke=>$va){
                            $res[$k]['images'][] = $va['attachment_url'];
                        }
                        $res[$k]['imageStr'] = implode(',',$res[$k]['images']);
                    }else{
                        $res[$k]['imageStr'] = '';
                    }
                }
            }else{
                //找到商品的sku
                $sku = Db::name('goods_sku')->where("goods_id = '".$goods_id."'")->find();
                $result = Db::name('attachment')->where("business_sn = 'group_id' AND business_id = '".$sku['group_id']."'")->select();

                if($result){
                    $images = array();
                    foreach($result as $ke=>$va){
                        $images[] = $va['attachment_url'];
                    }
                    $imageStr = implode(',',$images);
                }else{
                    $imageStr = '';
                }
                $this->assign('group_id',$sku['group_id']);
                $this->assign('noneColor',$result);
                $this->assign('imageStr',$imageStr);
                $this->assign('color',$res);
                return $this->fetch();
            }

            $this->assign('color',$res);
            return $this->fetch();

        }else{
            $this->error('请传入商品ID');
        }

    }


    /**
     * 执行图片编辑功能
     */
    public function doEditImage(){

        $image            =  $this->request->param('image/a');
        $group            =  $this->request->param('group/a');
        //开启事务
        Db::startTrans();

        try{

        if(is_array($image)){
           foreach($image as $key=>$picture){
               $group_id = $group[$key];
            if($picture){                           //更新商品附图
                $pic = trim($picture,',');
                $picArr = explode(',',$pic);

                if($picArr){

                    $oldPic = Db::name('attachment')->where(" business_sn = 'group_id' AND business_id='".$group_id."'")->select();

                    $oldArr = array();
                    if(is_array($oldPic)){
                        foreach($oldPic as $k=>$v){
                            if(!in_array($v['attachment_url'],$picArr)){  //删除已经被删除的图片记录
                                if(file_exists($v['attachment_url'])){
                                    unlink($v['attachment_url']);
                                }
                                Db::name('attachment')->where("business_sn = 'group_id' AND business_id='".$group_id."' AND id =".$v['id'])->delete();
                            }
                            $oldArr[] = $v['attachment_url'];  //更新前所有商品的图片
                        }
                    }
                    $needAdd = array_diff($picArr,$oldArr);      //新添加的图片

                    if(is_array($needAdd) && count($needAdd)>0){
                        foreach($needAdd as $k=>$v){
                            $insert = array(
                                'attachment_url'    =>  $v,
                                'business_sn'       =>  'group_id',
                                'business_id'       =>  $group_id,
                                'store_id'          =>  $this->user['store_id'],
                                'created_at'        =>  time(),
                                'sort'              =>  0,
                                'is_delete'         =>  0,
                            );

                            Db::name('attachment')->insert($insert);
                        }
                    }
                }
            }else{
                //删除一组图片
                $oneGroup = Db::name('attachment')->where("business_sn = 'group_id' AND business_id = '".$group_id."'")->select();
                if($oneGroup){
                    foreach($oneGroup as $k=>$v){
                        if(file_exists($v['attachment_url'])){
                            unlink($v['attachment_url']);
                        }
                    }
                }
                Db::name('attachment')->where("business_sn = 'group_id' AND business_id = '".$group_id."'")->delete();
            }
           }
        }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error('编辑失败！');
        }

        $this->success('图片编辑成功！');
    }



    /**
     * 根据属性值ID获取属性ID
     * @param $vid int 属性值ID
     * @return int
     */
    protected  function getFeatureId($vid){
        if(!$vid){
            return '';
        }
        $res = Db::name('features_value')->field('feature_id')->where("id = '".$vid."'")->find();

        if($res){
            return $res['feature_id'];
        }else{
            return '';
        }

    }


    /**
     * 编辑商品页面
     */
    public function edit(){
        $goods_id = $this->request->param('id');
        if(trim($goods_id) == ''){
            $this->error('请传入要编辑的商品ID！');
        }

        $goods = Db::name('goods')->where("goods_id = '".$goods_id."'")->find();
        $goods_extra = Db::name('goods_extra')->where("goods_id = '".$goods_id."'")->find();
        if(!$goods){
            $this->error('未找到要编辑的商品！');
        }
        $category = $this->getAllCategory($goods['category_id']);   //商品分类


        //商品所有属性
        $allCate = $this->getTypes($goods['category_id']);

        //此商品可有的销售属性
        $saleCate = isset($allCate['feature'])?$allCate['feature']:'';

        //商品基本属性
        $basic = Db::name('goods_basic_feature')->where("goods_id = '".$goods_id."'")->select();
        $basicKey = array();
        if(is_array($basic)){
            foreach($basic as $k=>$v){
                $basicKey[] = $v['feature_value_id'];
            }
        }

        //此商品销售属性
        $sale = Db::name('goods_sale_feature')->where("goods_id = '".$goods_id."'")->select();
        //判断是否是没有选择规格的商品
        if(count($sale) == 1 && $sale[0]['feature_id'] == 1 && $sale[0]['feature_value_id'] == 1 ){
            $sale = '';
        }

        $saleKey = array();
        if(is_array($sale)){
            foreach($sale as $k=>$v){
                $saleKey[] = $v['feature_value_id'];
            }
        }


        if(isset($allCate['feature']) && is_array($allCate['feature'])){
            $key = '';
            $basic = array();
            $temps = array();
            foreach($allCate['feature'] as $k=>$v){
                if($v['feature_id'] != $key){
                    $key = $v['feature_id'];
                }
                $basic[$key][] = $v;
            }
            $end = array_merge($basic,$temps);
            $allCate['feature'] = $end;
        }
        $saleFeature = Db::name('features')->select();
        $saleCate = Db::name('features_value')->select();

        $saleFeature = $this->changKey($saleFeature,'feature_id');

        if(is_array($saleCate)){
            foreach($saleCate as $k=>$v){
                $saleCate[$k]['attribute_name'] = $saleFeature[$v['feature_id']]['attribute_name'];
            }
            $saleCate = $this->changKey($saleCate,'id');
        }

        //商品的规格
        $sku = Db::name('goods_sku')->where("goods_id = '".$goods_id."'")->select();

        //商品已经存在的规格属性
        $alreadyExists = array();


        if($sku && is_array($sku)){
            foreach($sku as $k=>$v){
                if(is_array($sale)){

                    //已经存在商品的规格属性组成的键名
                    $existsKey = array();
                    foreach($sale as $key=>$val){
                        $val['attribute_name'] = isset($saleCate[$val['feature_value_id']]['attribute_name'])?$saleCate[$val['feature_value_id']]['attribute_name']:'';
                        $val['feature_value'] = isset($saleCate[$val['feature_value_id']]['feature_value'])?$saleCate[$val['feature_value_id']]['feature_value']:'';
                        if($v['group_id'] == $val['group_id']){
                            $sku[$k]['feature'][$val['feature_id']] = $val;
                            $existsKey[] = $val['feature_value_id'];
                        }
                    }

                    //已经存在的规格组成的数据，键名为规格值id，以‘_’分割
                    if($existsKey){
                        unset($v['sku_name']);
                        $alreadyExists[implode('_',$existsKey)] = $v;
                    }

                }
            }
        }

        $this->assign('already',json_encode($alreadyExists));
        $this->assign('sku',empty($sku)?array():$sku);
        $this->assign('saleKeys',empty($saleKey)?array():$saleKey);
        $this->assign('basicKeys',empty($basicKey)?array():$basicKey);
        $this->assign('allCate',empty($allCate)?array():$allCate);
        $this->assign('picture',empty($picture)?array():$picture);
        $this->assign('pictures',empty($pictures)?array():$pictures);
        $this->assign('basic',empty($basic)?array():$basic);
        $this->assign('goods',$goods);
        $this->assign('goods_extra',$goods_extra);
        $this->assign('category',$category);
        return $this->fetch();
    }

    /**
     * 执行商品编辑
     */
    public function doEdit(){
        //对传递参数进行验证
        $param = $this->request->param();
        $validate = new Validate($this->rule, $this->message);
        if ( !$validate->check( $param ) ) {
            $this->error( $validate->getError() );
        }

        $goods_id           = $this->request->param('goods_id');   //商品ID
        $category_id        = $this->request->param('category_id'); //商品分类ID
//        $picture            = $this->request->param('pic_one');//商品副图
        $goods_name         = $this->request->param('goods_name'); //商品名
        $goods_desc         = $this->request->param('goods_desc','','trim'); //商品说明
        $goods_price        = $this->request->param('goods_price'); //商品价格
        $goods_market_price = $this->request->param('goods_market_price');//商品市场价
        $goods_type         = $this->request->param('goods_type');//商品类型
        $goods_serial       = $this->request->param('goods_serial');//商品卖家货号+
        $goods_storage      = $this->request->param('goods_storage');//商品库存
        $goods_storage_alarm= $this->request->param('goods_storage_alarm');//商品库存+
        $image              = $this->request->param('image');//商品主图
//        $editorContent      = $this->request->param('editorContent');//商品详情
        $editorContentOne   = $this->request->param('editorContent1');//商品详情 商品实拍
        $editorContentTwo   = $this->request->param('editorContent2');//商品详情 商品规格
        $editorContentThree = $this->request->param('editorContent3');//商品详情 材质说明
        $editorContentFour  = $this->request->param('editorContent4');//商品详情  品质保证
        $featureIdName      = $this->request->param('featureIdNames/a');//规格名称
        $featureName        = $this->request->param('featureNames/a');//规格值
        $salePrice          = $this->request->param('salePrice/a');//规格：商品售价
        $saleMarketPrice    = $this->request->param('saleMarketPrice/a');//规格：商品售价
        $saleStorage        = $this->request->param('saleStorage/a');//规格：商品库存
        $saleStorageAlarm   = $this->request->param('saleStorageAlarm/a');//规格：商品库存预警
        $saleNumber         = $this->request->param('saleNumber/a'); //规格：商品货号
        $store_id           = $this->user['store_id'];//店铺ID
        $feature            =  $this->request->param('feature/a');
        $feature_ids        =  $this->request->param('feature_ids/a');//规格：商品销售规格
        $feature_basic      =  $this->request->param('feature_basic/a');//规格：商品基本规格
        $saleSku            =  $this->request->param('saleSku/a');//商品SKU
        $saleSkuOne         =  $this->request->param('saleSkuOne');//商品SKU 在没选择规格时使用

        if(!empty($goods_desc) && strlen($goods_desc)>500) {
            $this->error('商品说明字符不能超过500字符');
        }

        if(trim($category_id) == ''
            || trim($goods_name) == ''
            || trim($goods_price) == ''
            || trim($goods_market_price) ==''
            || trim($goods_storage) == ''
            || trim($image) == ''
            || trim($editorContentOne) == ''
            || trim($editorContentTwo) == ''
            || trim($editorContentThree) == ''
            || trim($editorContentFour) == ''){
            $this->error('添加商品数据不可为空！');
        }

        if(count($saleNumber) != count($salePrice)  && count($saleStorage) != count($saleNumber)){
            $this->error('属性值输入错误');
        }

        $cate = new GoodsCategory();
        $level = $cate->getLevel($category_id);
        if($level != 3){
            $this->error('商品分类选择错误或未选择第三级分类');
        }

        if(mb_strlen($param['goods_name'],'utf-8') > 64){
            $this->error('商品名称最长应不超过64个汉字');
        }

        $data = array(
            'goods_id'           =>     $goods_id,
            'goods_name'         =>     $goods_name,
            'goods_desc'         =>     $goods_desc,
            'store_id'           =>     $store_id,
            'goods_storage'      =>     $goods_storage,
            'goods_price'        =>     $goods_price,
            'goods_market_price' =>     $goods_market_price,
            'goods_type'         =>     $goods_type,
            'goods_storage_alarm'=>     $goods_storage_alarm,
            'goods_serial'       =>     $goods_serial,
//            'goods_describe'     =>     $editorContent,
//            'goods_state'        =>     0,
            'goods_verify'       =>     3,
//            'goods_created_at'   =>     time(),
//            'is_delete'          =>     0,
            'category_id'        =>     $category_id,
            'goods_image_main'   =>     $image,
        );

        $extra = array(
            'goods_real_shot'                 =>      $editorContentOne,
            'goods_specifications'            =>      $editorContentTwo,
            'goods_material_description'      =>      $editorContentThree,
            'goods_quality_assurance'         =>      $editorContentFour,
        );


        //开启事务
        Db::startTrans();

        try{
            Db::name('goods')->update($data);   //更新商品
            Db::name('goods_extra')->where("goods_id = '".$goods_id."'")->update($extra);  //更新商品扩展表
            $skuOld = array(); //更新前商品的sku
            $skus = Db::name('goods_sku')->field('goods_sku')->where(array('goods_id'=>$goods_id))->select();
            if($skus){
                foreach($skus as $k=>$v){
                    $skuOld[] = $v['goods_sku'];
                    $this->_dGoodsCache($v['goods_sku']);
                }
            }


            if(!empty($saleSku)){   //当有sku的情况，即 只改变了规格值但没有改变规格类的情况
                foreach($saleSku as $k=>$v){

                    if($salePrice[$k] <= 0){  //价格判断
                        throw new Exception('商品价格不能为0或负数');
                    }
                    if($saleMarketPrice[$k] <= 0){
                        throw new Exception('商品市场价不能为0或负数');
                    }
                    if($salePrice[$k] > $saleMarketPrice[$k]){
                        throw new Exception('商品价格不能高于市场价');
                    }



                    $sku = array(
                        'goods_price'        =>      $salePrice[$k],
                        'goods_number'       =>      $saleStorage[$k],
                        'goods_storage'      =>      $saleStorage[$k],
                        'goods_storage_alarm'=>      $saleStorageAlarm[$k],
                        'goods_market_price' =>      $saleMarketPrice[$k],   //规格：商品售价[$k],
                        'goods_serial'       =>      $saleNumber[$k],

                    );

                    if(in_array($saleSku[$k],$skuOld)){
                        Db::name('goods_sku')->where("goods_sku='".$saleSku[$k]."'")->update($sku);
                        foreach($skuOld as $kee=>$vaa){
                            if($vaa == $saleSku[$k]){
                                unset($skuOld[$kee]);
                            }
                        }
                    }else{
                        $groupId = Tools::guid();
                        $sku = array(
                            'goods_sku'          =>      Tools::guid(),
                            'goods_price'        =>      $salePrice[$k],
                            'goods_market_price' =>      $saleMarketPrice[$k],
                            'goods_number'       =>      $saleStorage[$k],
                            'goods_storage'      =>      $saleStorage[$k],
                            'goods_storage_alarm'=>      $saleStorageAlarm[$k],
                            'goods_serial'       =>      $saleNumber[$k],
                            'goods_id'           =>      $goods_id,
                            'group_id'           =>      $groupId,
                        );

                        if($feature_ids[$k] && is_array($feature_ids[$k])){
                            foreach($feature_ids[$k] as $key=>$val){

                                $feature_id = $this->getFeatureId($val);
                                $sales = array(                     //组织销售属性数据
                                    'feature_id'       => (int)$feature_id,
                                    'feature_value_id' => (int)$val,
                                    'goods_id'         => $goods_id,
                                    'group_id'         => $groupId,
                                );
                                $addSale = Db::name('goods_sale_feature')->insert($sales); //添加销售属性数据
                                if(!$addSale){
                                    throw new Exception('销售属性添加失败');
                                }
                            }
                        }

                        $skuName = array();
                        if($featureIdName[$k]){
                            foreach($featureIdName[$k] as $ke=>$va){
                                $skuName[$va] = $featureName[$k][$ke];
                            }
                        }
                        $sku['sku_name'] = json_encode($skuName);

                        $addSku = Db::name('goods_sku')->insert($sku);        //添加商品的SKU
                        if(!$addSku){
                            throw new Exception('SKU添加失败');
                        }



                    }

                    $this->_dGoodsCache($saleSku[$k]);

                } //sku更新完成

                //清空已删除的sku
                if($skuOld){
                    foreach($skuOld as $kk=>$vv){
                        $group_id = Db::name('goods_sku')->where(array('goods_sku'=>$vv))->find();
                        if($group_id){
                            Db::name('goods_sale_feature')->where(array('group_id'=>$group_id['group_id']))->delete();
                            Db::name('goods_sku')->where(array('group_id'=>$group_id['group_id']))->delete();
                        }
                    }
                }

                Db::name('goods_basic_feature')->where(array('goods_id'=>$goods_id))->delete();
                if(is_array($feature_basic)){
                    foreach($feature_basic as $ke=>$va){
                        $basic = array(                 //基础属性数据
                            'feature_id' => $ke,
                            'feature_value_id' => $va,
                            'goods_id' => $goods_id,
                        );

                        Db::name('goods_basic_feature')->insert($basic);

                    }

                }

            }elseif(!empty($saleSkuOne)){


                if($goods_price <= 0){  //价格判断
                    throw new Exception('商品价格不能为0或负数');
                }
                if($goods_market_price <= 0){
                    throw new Exception('商品市场价不能为0或负数');
                }

                if($goods_price > $goods_market_price){
                    throw new Exception('商品价格不能高于市场价');
                }

                    $sku = array(
                        'goods_price'        =>      $goods_price,
                        'goods_number'       =>      $goods_storage,
                        'goods_storage'      =>      $goods_storage,
                        'goods_storage_alarm'=>      $goods_storage_alarm,
                        'goods_market_price' =>      $goods_market_price,   //规格：商品售价[$k],
                        'goods_serial'       =>      $goods_serial,

                    );

                Db::name('goods_sku')->where("goods_sku='".$saleSkuOne."'")->update($sku);
                $this->_dGoodsCache($saleSkuOne);

                Db::name('goods_basic_feature')->where(array('goods_id'=>$goods_id))->delete();
                if(is_array($feature_basic)){
                    foreach($feature_basic as $ke=>$va){
                        $basic = array(                 //基础属性数据
                            'feature_id' => $ke,
                            'feature_value_id' => $va,
                            'goods_id' => $goods_id,
                        );

                        Db::name('goods_basic_feature')->insert($basic);

                    }

                }

            }else{    //当改变了规格值的情况


            if(is_array($saleNumber) && count($saleNumber) > 0){   //有规格商品更新


            //找到颜色属性的属性id
            $color = Db::name('features')->where("is_color = 1")->select();

            $colorId = array();
            if(is_array($color) && count($color) > 0){
                foreach($color as $k=>$v){
                    $colorId[] = $v['feature_id'];
                }
            }

            //清空原有商品属性前找到原商品group_id
            $oldGroup = Db::name('goods_sale_feature')->where("goods_id = '".$goods_id."'")->select();
            $oldGroupId = array();
            $oldGroupIds = '';
            $oldGroupColor = array();
            $oldGroupNoColor = array();
            if(is_array($oldGroup)){
                foreach($oldGroup as $k=>$v){
                    $oldGroupId[] = $v['group_id'];
                    if(in_array($v['feature_id'],$colorId)){
                        $oldGroupColor[$v['group_id']] = $v;
                    }else{ //无颜色属性
                        $oldGroupNoColor[$v['group_id']] = $v;
                    }
                }
            }
            if(count($oldGroupId) > 0){
                $oldGroupIds = implode("','",$oldGroupId);
            }

            //修改之前的附件
            $oldAttachment = Db::name('attachment')->where("business_id IN ('".$oldGroupIds."')")->select();

            if(is_array($oldAttachment) && count($oldAttachment) > 0){
                foreach($oldAttachment as $k=>$v){
                    if(isset($oldGroupColor[$v['business_id']])){
                        $oldAttachment[$k]['feature_value_id'] = $oldGroupColor[$v['business_id']]['feature_value_id'];
                    }else{
                        $oldAttachment[$k]['feature_value_id'] = $oldGroupNoColor[$v['business_id']]['feature_value_id'];
                    }
                }
            }
            $newKeyAttachment = $this->changKey($oldAttachment,'feature_value_id');

            //清空原有的商品属性
            $oldSku = Db::name('goods_sku')->where(array('goods_id'=>$goods_id))->select();
            if($oldSku){
                foreach($oldSku as $k=>$v){
                    $this->_dGoodsCache($v['goods_sku']);
                }
            }

            Db::name('goods_sku')->where("goods_id='".$goods_id."'")->delete();
            Db::name('goods_sale_feature')->where("goods_id='".$goods_id."'")->delete();
            Db::name('goods_basic_feature')->where("goods_id='".$goods_id."'")->delete();



            //更新新数据
            foreach($saleNumber as $k=>$v){

                if($salePrice[$k] <= 0){  //价格判断
                    throw new Exception('商品价格不能为0或负数');
                }
                if($saleMarketPrice[$k] <= 0){
                    throw new Exception('商品市场价不能为0或负数');
                }

                if($salePrice[$k] > $saleMarketPrice[$k]){
                    throw new Exception('商品价格不能高于市场价');
                }



                $groupId = Tools::guid();
                $sku = array(
                    'goods_sku'          =>      Tools::guid(),
                    'goods_price'        =>      $salePrice[$k],
                    'goods_number'       =>      $saleStorage[$k],
                    'goods_storage'      =>      $saleStorage[$k],
                    'goods_storage_alarm'=>      $saleStorageAlarm[$k],
                    'goods_market_price' =>      $saleMarketPrice[$k],   //规格：商品售价[$k],
                    'goods_serial'       =>      $saleNumber[$k],
                    'goods_id'           =>      $goods_id,
                    'group_id'           =>      $groupId,
                );

                if($feature_ids[$k] && is_array($feature_ids[$k])){
                    foreach($feature_ids[$k] as $key=>$val){

                        $feature_id = $this->getFeatureId($val);
                        $sales = array(                     //组织销售属性数据
                            'feature_id'       => (int)$feature_id,
                            'feature_value_id' => (int)$val,
                            'goods_id'         => $goods_id,
                            'group_id'         => $groupId,
                        );
                        Db::name('goods_sale_feature')->insert($sales); //添加销售属性数据


                        //更新图片
                        if(is_array($oldAttachment) && count($oldAttachment) > 0){
                            foreach($newKeyAttachment as $ke=>$va){
                                if($val == $ke){
                                    $attStr = array();
                                    foreach($oldAttachment as $Kk=>$vv){
                                        if($vv['feature_value_id'] == $va['feature_value_id']){
                                            $attStr[] = $vv['id'];
                                        }
                                    }
                                    if(count($attStr) > 0){
                                        $attStrs = implode("','",$attStr);
                                        Db::name('attachment')->where("id IN ('".$attStrs."')")->update(array('business_id'=>$groupId));
                                    }
                                    unset($newKeyAttachment[$ke]);
                                }

                            }


                        }


                    }
                }
                $skuName = array();
                if($featureIdName[$k]){
                    foreach($featureIdName[$k] as $ke=>$va){
                        $skuName[$va] = $featureName[$k][$ke];
                    }
                }
                $sku['sku_name'] = json_encode($skuName);
                $upSku = Db::name('goods_sku')->insert($sku);        //添加商品的SKU
                if(!$upSku){
                    throw new Exception('更新商品SKU失败');
                }

            }

            if(count($newKeyAttachment) > 0){
                foreach($newKeyAttachment as $ke=>$va){
                    Db::name('attachment')->where("business_id = '".$va['business_id']."'")->delete();
                }
            }

            if(is_array($feature_basic)){
                foreach($feature_basic as $ke=>$va){
                    $basic = array(                 //基础属性数据
                        'feature_id' => $ke,
                        'feature_value_id' => $va,
                        'goods_id' => $goods_id,
                    );

                   Db::name('goods_basic_feature')->insert($basic);

                }

            }

            }else{ // 无规格商品更新


                //清空原有的商品属性
                $oldSku = Db::name('goods_sku')->where('goods_id',$goods_id)->find();
                $oldAttachment = Db::name('attachment')->where("business_id",$oldSku['group_id'])->find();
                if($oldSku){
                        $this->_dGoodsCache($oldSku['goods_sku']);
                }
                Db::name('goods_sku')->where("goods_id",$goods_id)->delete();

                if($goods_price <= 0){  //价格判断
                    throw new Exception('商品价格不能为0或负数');
                }
                if($goods_market_price <= 0){
                    throw new Exception('商品市场价不能为0或负数');
                }

                if($goods_price > $goods_market_price){
                    throw new Exception('商品价格不能高于市场价');
                }

                $groupId = Tools::guid();
                $sku = array(
                    'goods_sku'          =>      Tools::guid(),
                    'goods_price'        =>      $goods_price,
                    'goods_market_price' =>      $goods_market_price,
                    'goods_number'       =>      $goods_storage,
                    'goods_storage'      =>      $goods_storage,
                    'goods_storage_alarm'=>      $goods_storage_alarm,
                    'goods_serial'       =>      $goods_serial,
                    'goods_id'           =>      $goods_id,
                    'group_id'           =>      $groupId,
                );
                $sku['sku_name'] = json_encode(array('颜色'=>'无'));
                Db::name('goods_sku')->insert($sku);        //添加商品的SKU

                Db::name('attachment')->where(array('id'=>$oldAttachment['id']))->update(array('business_id'=>$groupId));

                Db::name('goods_sale_feature')->where(array('goods_id'=>$goods_id))->delete();
                $sales = array(                     //组织销售属性数据  在没有选择属性时，添加一条默认销售属性 2017/1/6
                    'feature_id'       => 1,
                    'feature_value_id' => 1,
                    'goods_id'         => $goods_id,
                    'group_id'         => $groupId,
                );
                $addSale = Db::name('goods_sale_feature')->insert($sales); //添加销售属性数据
                if(!$addSale){
                    throw new Exception('添加销售属性失败');
                }

            }
                $goodsSku = Db::name('goods_sku')->where(['goods_id'=>$goods_id])->column('goods_sku');
                foreach($goodsSku as $value){
                    Cache::rm($value);
                }
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }

        //存入SKU  sku_goods_id=>sku
        $gid_key = "sku_".$goods_id;
        $goods_sku_arr = array();
        $goodsSku = Db::name('goods_sku')->where(['goods_id'=>$goods_id])->column('goods_sku');
        foreach($goodsSku as $k=>$v){
            $goods_sku_arr[] = $v;
        }
        Cache::set($gid_key,$goods_sku_arr);

//        $goods_m=new GD();
//        $goods_m->changeAllKCNumber(array($goods_id),0);
        $this->success('修改商品成功！',url('seller/goods/lists'));

    }


    /**
     * 将商品设置为删除状态
     */
    public function delete(){
        $goods_id = $this->request->param('id');
        if(trim($goods_id) == ''){
            $this->error('请传入要删除的商品ID！');
        }
		$goodsSku = Db::name('goods_sku')->where(['goods_id'=>$goods_id])->column('goods_sku');
		foreach($goodsSku as $value){
			Cache::rm($value);
		}
        $goods = Db::name('goods')->where("goods_id = '".$goods_id."'")->update(array('is_delete'=>1));
        if($goods){
            $this->success('删除成功！');
        }else{
            $this->error('删除失败！');
        }
    }

	/**
	 * 将商品设置为删除状态
	 */
	public function deleteChecked(){
		$goodsId  = explode(',', $this->request->param('id_list'));

		$where  = array( 'goods_id' => array( 'in', $goodsId ));
		$goodsSku = Db::name('goods_sku')->where($where)->column('goods_sku');
		foreach($goodsSku as $value){
			Cache::rm($value);
		}
		$goods = Db::name('goods')->where($where)->update(array('is_delete'=>1));
		if($goods){
			$this->success('删除成功！');
		}else{
			$this->error('删除失败！');
		}
	}



/*
    public function delete(){
        $goods_id = $this->request->param('id');
        if(trim($goods_id) == ''){
            $this->error('请传入要删除的商品ID！');
        }
        $goods = Db::name('goods')->where("goods_id = '".$goods_id."'")->find();
        if(!$goods){
            $this->error('未找到要编辑的商品！');
        }

        //开启事务
        Db::startTrans();
        try{

            //删除商品主图
            if($goods['goods_image_main']){
                if(file_exists($goods['goods_image_main'])){
                    unlink($goods['goods_image_main']);
                }
            }

            //删除商品副图
//            $slave = Db::name('attachment')->where("business_sn = 'goods' AND business_id='".$goods_id."'")->select();
//            if(is_array($slave) && count($slave)>0){
//                foreach($slave as $k=>$v){
//                    if(file_exists($v['attachment_url'])){
//                        unlink($v['attachment_url']);
//                    }
//                }
//            }

            //删除商品基本属性
            Db::name('goods_basic_feature')->where("goods_id = '".$goods_id."'")->delete();

            //删除商品的销售属性
            $goods_feature = Db::name('goods_sale_feature')->where("goods_id = '".$goods_id."'")->find();
            if($goods_feature['image_url'] && file_exists($goods_feature['image_url'])){
                unlink($goods_feature['image_url']);
            }
            $group_id = array();
            if($goods_feature){
                foreach($goods_feature as $k=>$v){
                    $group_id[] = $v['group_id'];
                }
            }
            if($group_id){
                $group_ids = implode("','",$group_id);
                Db::name('attachment')->where(" business_sn = 'group_id' AND business_id IN ('".$group_ids."')")->delete();
            }

            Db::name('goods_sale_feature')->where("goods_id = '".$goods_id."'")->delete();

            //删除商品SKU
            Db::name('goods_sku')->where("goods_id = '".$goods_id."'")->delete();

            //删除商品
            Db::name('goods')->where("goods_id = '".$goods_id."'")->delete();

            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            $this->error('商品删除失败！');
        }


        $this->success('删除商品成功！');
    }
*/

    /**
     * 更改数组的键名
     * @param $arr array 需要处理的数组
     * @param $id string 需要更改为的键名
     * @return array
     */
    public function changKey($arr,$id){
        if(is_array($arr) && $id){
            $temp = array();
            foreach($arr as $k=>$v){
                $temp[$v[$id]] = $v;
            }
            return $temp;
        }
        return $arr;
    }


    /**
     * 传入三级分类ID，返回前面的父级分类
     * 该分类主要用于商品编辑，会返回包括已删除的分类!!!!!
     * @param $cate string
     * @return array
     */
    public function getAllCategory($cate){
        if(!$cate){
            $this->error('请传入要查找的分类');
        }

        $all = Db::name('goods_category')->where([
            //'is_delete' =>0,
            'status'    =>1,
            'store_id'  =>$this->user['store_id'],
            'category_id' => array('neq', Config::get('logs_goos_category_id')),
        ])->select();

        $end = array();
        $end['three']['id'] = $cate;
        if(is_array($all)){
            foreach($all as $k=>$v){
                if($v['category_id'] == $cate){
                    $end['three']['pid'] = $v['parent_id'];     //找到三级分类的父ID
                }
            }
            foreach($all as $ke=>$va){
                if( ( $va['parent_id'] == $end['three']['pid'] && $va['is_delete'] == 0 ) || $va['category_id'] == $cate ){
                    $end['three']['data'][] = $va;
                }
            }

            $end['two']['id'] = $end['three']['pid'];
            foreach($all as $k=>$v){
                if($v['category_id'] == $end['two']['id']){
                    $end['two']['pid'] = $v['parent_id'];
                }
            }

            foreach($all as $k=>$v){
                if( ( $v['parent_id'] == $end['two']['pid'] && $va['is_delete'] == 0 ) || $end['three']['pid'] == $v['category_id'] ){
                    $end['two']['data'][] = $v;
                }
            }

            $end['one']['id'] = $end['two']['pid'];
            foreach($all as $k=>$v){
                if($v['parent_id'] == '0'){

                    $end['one']['data'][] = $v;
                }
            }

            return $end;

        }


    }


    /**
     * 获取商品分类
     */
    public function getCategory(){
        $ids = $this->request->param('ids');
        if(trim($ids) == ''){
            $this->error('请输入正确的ID');
        }
        $model = new GoodsCategory();
        $where = array('is_delete'=> 0, 'parent_id'=>$ids);
        $res = $model->where( $where )->select();
        if($res){
            $this->success('获取成功','',$res);
        }

        $this->success('','','');
    }


    /**
     * 添加属性值
     */
    public function addFeatureValue(){

        $value = $this->request->param('value');
        $fid = $this->request->param('fid');
        $cate = $this->request->param('cate');

        if(trim($value) == ''){
            $this->error('名称不能为空！');
        }

        $data = array(
            'feature_id'        => $fid,
            'feature_value'     => $value,
            'is_delete'         => 0,
            'is_self_define'    => 1,
            'sort'              => 0,
            'created_by'        => 0,
            'created_at'        => time(),
            'store_id'          => $this->user['store_id'],
            'category_id'       => $cate,
        );

        $res = Db::name('features_value')->insertGetId($data);

        if($res){
            $this->success('添加成功！','',$res);
        }else{
            $this->error('添加失败！');
        }


    }



    /**
     * 根据分类ID获取特征值
     */
    public function getTypes($ids){

        if($this->request->isAjax()){
            $ids = $this->request->param('ids');
        }
        if(empty($ids)){
            $this->error('请输入正确的ID');
        }
        $model = new GoodsCategory();
        $res = $model->where("category_id='".$ids."'")->find();    //根据分类ID取得类型ID
        $allFeature = array();


        if($res['type_id']){
            //根据类型ID取得特征ID
            $whereType = array();
            $whereType['type_id'] = $res['type_id'];
            $whereType['is_delete'] = 0;
            $type = Db::name('type')->where($whereType)->find();  //找到未删除的类型
            if($type){
            $types = Db::name('type_feature')->field('feature_id')->where('type_id',$res['type_id'])->select();
            if($types && is_array($types)){
                $temp = array();
                foreach($types as $k=>$v){
                    $temp[] = $v['feature_id'];
                }
                //根据特征ID取得特征名称
                $searchStr = implode("','",$temp);
//                $features = Db::name('features')->field('feature_id,attribute_name')->where(" sales_attribute=1 AND feature_id in ('".$searchStr."')" )->select();
                $featuresArr = Db::name('features')->field('feature_id,attribute_name,sales_attribute')->where("is_delete = 0 and feature_id in ('".$searchStr."')" )->select();

                $features = array();
                $featureBasic = array();
                if(is_array($featuresArr)){
                    foreach($featuresArr as $k=>$v){
                        if($v['sales_attribute'] == 1){
                            $features[] = $v;
                        }else{
                            $featureBasic[] = $v;
                        }
                    }
                }

                $featuresValue = '';
                if($features && is_array($features)){
                    $temp1 = array();
                    $temp2 = array();
                    foreach($features as $key=>$val){
                        $temp1[] = $val['feature_id'];
                        $temp2[$val['feature_id']] = $val['attribute_name'];
                    }


                    //根据特征ID取得特征值
                    $searchStrOne = implode("','",$temp1);
                    $featuresValue = Db::name('features_value')->field('id,feature_id,feature_value')->where(" is_delete=0 AND feature_id in ('".$searchStrOne."')" )->order(' feature_id asc,id')->select();

                    if($featuresValue && is_array($featuresValue)){
                        foreach($featuresValue as $ke=>$va){
                        $featuresValue[$ke]['attribute_name'] = $temp2[$va['feature_id']];
                        }

                    }
                }

                $endBasic = '';
                if($featureBasic && is_array($featureBasic)){
                    $temp1 = array();
                    $temp2 = array();
                    foreach($featureBasic as $key=>$val){
                        $temp1[] = $val['feature_id'];
                        $temp2[$val['feature_id']] = $val['attribute_name'];
                    }


                    //根据特征ID取得特基本属性
                    $searchStrOne = implode("','",$temp1);
                    $featuresValueBasic = Db::name('features_value')->field('id,feature_id,feature_value')->where(" is_delete=0 AND feature_id in ('".$searchStrOne."')" )->order(' feature_id asc,id')->select();

                    if($featuresValueBasic && is_array($featuresValueBasic)){
                        foreach($featuresValueBasic as $ke=>$va){
                            $featuresValueBasic[$ke]['attribute_name'] = $temp2[$va['feature_id']];
                        }
                        $key = '';
                        $basic = array();
                        $temps = array();
                        foreach($featuresValueBasic as $k=>$v){
                            if($v['feature_id'] != $key){
                              $key = $v['feature_id'];
                            }
                            $basic[$key][] = $v;
                        }
                       $endBasic = array_merge($basic,$temps);

                    }
                }

                $allFeature['feature'] = $featuresValue;
                $allFeature['feature_basic'] = $endBasic;

            }
            }
        }

        if($this->request->isAjax()){
            if($allFeature){
                $this->success('','',$allFeature);
            }else{
                $this->error('未找到相关数据');
            }
        }else{
            return $allFeature;
        }

    }

    /**
     * 删除商品的缓存信息
     * @param $goodsSku
     * @return bool
     */
    private function _dGoodsCache($goodsSku)
    {
        return Cache::rm($goodsSku);
    }


    /**
     * 商品审核
     */
    public function check(){
        $goods_id = $this->request->param('id');
        $verify = $this->request->param('verify');
        if(!$goods_id){
            $this->error('商品ID不能为空');
        }

        $res = Db::name('goods')->where("goods_id = '".$goods_id."'")->update(array('goods_verify'=>$verify,'goods_sell_time'=>time()));
        if($res){
            //清空原有的商品属性
            $oldSku = Db::name('goods_sku')->where(array('goods_id'=>$goods_id))->select();
            if($oldSku){
                foreach($oldSku as $k=>$v){
                    $this->_dGoodsCache($v['goods_sku']);
                }
            }

            $this->success('状态更改成功!');
        }else{
            $this->error('状态更改失败！');
        }

    }



}
