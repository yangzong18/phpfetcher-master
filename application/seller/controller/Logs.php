<?php
/**
 * 原木商品管理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-21 10:12
 */
namespace app\seller\controller;
use app\common\model\LogsDecorationGoods as Goods;
use app\common\model\LogsDecorationGoodsFeature as GoodsFeature;
use app\common\model\GoodsCategory as Category;
use app\common\model\Attachment;
use app\common\controller\Auth;
use app\common\model\Goods as NormalGoods;
use think\Validate;
use think\Db;
use app\common\model\Designer;
use think\Config;

class Logs extends Auth
{
    protected $model;

    protected $rule = array(
        'name'   => 'require|max:192',
        'acreage'=> 'require|between:1,1000',
        'prize'  => 'require|number|gt:0',
        'type'  => 'require',
        'cover'  => 'require',
        'attachement'  => 'require',
        'taking'  => 'require',
        'designer_id' => 'require|number|gt:0',
        'deposit' => 'require|number|gt:0',
        'goods_description'=> 'require',
    );

    protected $message = array(
        'name.require'    => '商品名称不能为空',
        'name.max'        => '商品名称最多不能超过64个汉字',
        'acreage.require' => '装修面积不能为空',
        'acreage.between' => '装修面积在1-1000之间',
        'prize.require'   => '装修价格不能为空',
        'prize.number'=> '装修价格为大于0整数',
        'prize.gt'  => '装修价格为大于0整数',
        'cover.require'   => '商品主图不能为空',
        'attachement.require'   => '商品附图不能为空',
        'type.require'=> '商品类型不能为空',
        'taking.require'  => '耗时不能为空',
        'designer_id.require'=> '请选择设计师',
        'designer_id.number'=> '请选择设计师',
        'designer_id.gt'  => '请选择设计师',
        'deposit.require'  => '请输入定金',
        'deposit.number'  => '定金必为整数',
        'deposit.gt'  => '定金为大于0整数',
        'goods_description.require' => '设计详情不能为空'
    );
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->assign( 'logs_goos_category_id', Config::get('logs_goos_category_id') );
    } 

    /**
     * 原木整装商品首页
     */
    public function index() {
        $param  = $this->request->param();
        $goodsModel   = new Goods();
        $where = array( 'is_delete' => 0 );
        $order = 'id desc';
        $search_name =  $this->request->param('search_name');  //按名称为1 按编号为2
        if ( isset( $param['goods_name'] ) && trim( $param['goods_name'] ) != '' ) {
            if(isset($search_name) && $search_name == 1){
                $where['name'] = array('like',"%".$param['goods_name']."%");
                $this->assign('name',$param['goods_name']);
                $this->assign('search_name',1);
            }elseif(isset($search_name) && $search_name == 2){
                $where['id'] = $param['goods_name'];
                $this->assign('goods_id',$param['goods_name']);
                $this->assign('search_name',2);
            }
//            $where['name'] = array('like', '%'.$param['name'].'%');
        }
        if ( isset( $param['type'] ) && in_array( $param['type'] , array(1,2)) ) {
            $where['type'] = $param['type'];
            if ( $param['type'] == 2 ) {
                $order = 'goods_sell_time desc';
            }
        } else {
            $param['type'] = 0;
        }
        if ( isset( $param['verify'] ) && in_array( $param['verify'] , array(0,1,3)) ) {
            $where['goods_verify'] = $param['verify'];
        } else {
            $param['verify'] = 2;
        }
        $datas = $goodsModel->where( $where )->order( $order )->paginate(10,'',[
            'query' => $this->request->param()
        ]);
        $this->assign("datas", $datas);
        $this->assign("param", $param);
        return $this->fetch();
    }

    /**
     * 添加原木整装商品
     */
    public function add() {
        return $this->fetch();
    }


    /**
     * 添加原木整装商品
     */
    public function addPost() {
        //参数空验证
        $param = $this->request->param();
        $validate = new Validate($this->rule, $this->message);
        if ( !$validate->check( $param ) ) {
            $this->error( $validate->getError() );
        }

        if(mb_strlen($param['name'],'utf-8') > 64){
            $this->error('商品名称最长应不超过64个汉字');
        }
        
        $deposit = intval($param['deposit']);
        if ( $deposit<=0 || $deposit !=$param['deposit'] )  $this->error( '定金是大于0的整数' );
        $param['deposit'] = $deposit;

        if(count($param['category_id'])!=3) $this->error( '类别参数错误' );
        $cate_arr=array_intersect(array(0),$param['category_id']);
        if(count($cate_arr)>0) $this->error('请选择完整的分类');
        
        $time = time();
        $param['pay_type'] = $param['type'] == 1 ? 1 : $param['pay_type'];
        $data = array(
            'category_id' => $param['category_id'][2],
            'name'    => $param['name'],
            'notes'   => $param['notes'],
            'type'    => $param['type'],
            'pay_type'=> $param['pay_type'],
            'taking'  => $param['taking'],
            'deposit'  => $param['deposit'],
            'acreage' => $param['acreage'],
            'prize'   => $param['prize'],
            'cover'   => $param['cover'],
            'designer_id'   => $param['designer_id'],
            'goods_description' => $param['goods_description'],
            'created_at'  => $time,
            'created_by'  => $this->user['member_id'],
            'goods_sell_time' => 0
        );
        $goodsModel   = new Goods();
        $featureModel = new GoodsFeature();
        $attachementModel = new Attachment();
        //开启事物
        $goodsModel->startTrans();

        if ( !$goodsModel->data( $data )->save() ) {
            $this->error( '商品添加失败' );
        }
        //如果商品添加成功，则进行商品属性的添加判断
        if ( isset( $param['attribute_id'] ) 
            && is_array( $param['attribute_id'] ) 
            && count( $param['attribute_id'] ) > 0 ) {
            //进行属性编辑添加
            $featureData = array();
            foreach ($param['attribute_id'] as $key => $attributeId) {
                array_push($featureData, array(
                    'goods_id'   => $goodsModel->id,
                    'feature_id' => $attributeId,
                    'feature_value_id' => $param['attribute_value_id'][$key]
                ));
            }
            //进行批量添加
            if ( !$featureModel->insertAll( $featureData ) ) {
                $goodsModel->rollback();
                $this->error('属性信息添加失败');
            }
            
        }
        //进行商品附图添加
        if ( isset( $param['attachement'] ) 
            && is_array( $param['attachement'] ) 
            && count( $param['attachement'] ) > 0 ) {
            //拼接附件数据
            $attachementData = array();
            foreach ($param['attachement'] as $key => $attachement) {
                array_push($attachementData, array(
                    'attachment_url' => $attachement,
                    'business_sn'    => 'logs_goods',
                    'business_id'    => $goodsModel->id,
                    'store_id'       => $this->user['store_id'],
                    'created_at'     => $time,
                    'sort'           => $key
                ));
            }
            if ( !$attachementModel->insertAll( $attachementData ) ) {
                $goodsModel->rollback();
                $this->error('附图添加失败');
            }
        }
        $goodsModel->commit();
        $this->success('原木整装商品添加成功',url('seller/logs/index'));
    }


    /**
     * 编辑原木整装商品
     * @param int $id 商品ID
     */
    public function edit( $id = 0 ) {
        $goodsModel   = new Goods();
        $featureModel = new GoodsFeature();
        $attachementModel = new Attachment();
        //查询整装商品
        $goods = $goodsModel->where( 'id', $id )->find();
        if ( isset( $goods['id'] ) ) {
            $categoryModel = new Category();
            $categoryList  = $categoryModel->getParentId( $goods['category_id'] );
            $categoryName  = array();
            foreach ($categoryList as $key => $category) {
                array_push($categoryName, $category['name']);
            }
            $goods['category_name'] = join(' > ', $categoryName);
        }
        $this->assign( 'goods', $goods );
        //查询商品附件
        $where = array( 'business_sn' => 'logs_goods', 'business_id' => $id, 'is_delete' => 0 );
        $attachementList = $attachementModel->field( 'id, attachment_url' )->where( $where )->select();
        $this->assign( 'attachementList', $attachementList );
        //属性查询
        $where = array( 'goods_id' => $id );
        $featureList = $featureModel->where( $where )->select();
        //设计师
        $desingerModel = new Designer();
        $desinger = $desingerModel->field('designer_id, designer_name')->where( array('designer_id' => $goods['designer_id']) )->find();
        $this->assign( 'desinger', $desinger );
        $this->assign( 'featureList', json_encode($featureList) );
        $this->assign( 'needInitFeature', count( $featureList ) > 0 ? 1 : 0 );

        return $this->fetch();
    }
    
    /**
     * 编辑原木整装商品
     */
    public function editPost() {
        //参数空验证
        $param = $this->request->param();

        $validate = new Validate($this->rule, $this->message);
        if ( !$validate->check( $param ) ) {
            $this->error( $validate->getError() );
        }

        if(mb_strlen($param['name'],'utf-8') > 64){
            $this->error('商品名称最长应不超过64个汉字');
        }

        $deposit = intval($param['deposit']);
        if ( $deposit<=0 || $deposit !=$param['deposit'] )  $this->error( '定金是大于0的整数' );
        $param['deposit'] = $deposit;
        
        if ( !isset( $param['id'] ) || !is_numeric( $param['id'] ) || 0 >= $param['id'] ) {
            $this->error( '产品ID不能为空' );
        }

        $where = array( 'id' => $param['id'] ); 
        $param['pay_type'] = $param['type'] == 1 ? 1 : $param['pay_type'];
        $data  = array(
            'name'    => $param['name'],
            'notes'   => $param['notes'],
            'type'    => $param['type'],
            'pay_type'=> $param['pay_type'],
            'taking'  => $param['taking'],
            'deposit' => $param['deposit'],
            'acreage' => $param['acreage'],
            'prize'   => $param['prize'],
            'cover'   => $param['cover'],
            'designer_id'   => $param['designer_id'],
            'goods_verify'  => 3,
            'goods_description' => $param['goods_description'],
        );
        //如果发现有分类的信息，说明分类也要进行设置
        if(isset($param['category_id']) && is_array($param['category_id'])){
            if(count( $param['category_id'] ) == 3 && $param['category_id'][2] != '0'){
                $data['category_id'] = $param['category_id'][2];
            }else{
                $this->error('请选择到商品的三级分类！');
            }

        }

//        if ( isset( $param['category_id'] ) && is_array( $param['category_id'] ) && count( $param['category_id'] ) == 3 && $param['category_id'][2] != '0'  ) {
//            $data['category_id'] = $param['category_id'][2];
//        }
        $goodsModel   = new Goods();
        $featureModel = new GoodsFeature();
        $attachementModel = new Attachment();
        //开启事物
        $goodsModel->startTrans();
        //进行商品信息编辑
        $result = $goodsModel->save( $data, $where );

        //如果商品编辑成功，则进行商品属性的编辑
        if ( isset( $param['attribute_id'] ) 
            && is_array( $param['attribute_id'] ) 
            && count( $param['attribute_id'] ) > 0 ) {
            //先删除之前的属性值
            $result =  $featureModel->where( 'goods_id', $param['id'] )->delete();
            //进行属性添加
            $featureData = array();
            foreach ($param['attribute_id'] as $key => $attributeId) {
                array_push($featureData, array(
                    'goods_id'   => $param['id'],
                    'feature_id' => $attributeId,
                    'feature_value_id' => $param['attribute_value_id'][$key]
                ));
            }
            //进行批量添加
            if ( !$featureModel->insertAll( $featureData ) ) {
                $goodsModel->rollback();
                $this->error('属性信息编辑失败');
            }
            
        }
        //进行商品附图添加
        if ( isset( $param['attachement'] ) 
            && is_array( $param['attachement'] ) 
            && count( $param['attachement'] ) > 0 ) {
            $time = time();
            //删除之前的附件
            $where = array( 'business_sn' => 'logs_goods', 'business_id' => $param['id'] );
            $attachementModel->where( $where )->delete();
            //拼接附件数据
            $attachementData = array();
            foreach ($param['attachement'] as $key => $attachement) {
                array_push($attachementData, array(
                    'attachment_url' => $attachement,
                    'business_sn'    => 'logs_goods',
                    'business_id'    => $param['id'],
                    'store_id'       => $this->user['store_id'],
                    'created_at'     => $time,
                    'sort'           => $key
                ));
            }
            if ( !$attachementModel->insertAll( $attachementData ) ) {
                $goodsModel->rollback();
                $this->error('附图添加失败');
            }
        }
        $goodsModel->commit();
        $this->success('原木整装商品编辑成功',url('seller/logs/index'));
    }

    /**
     * 删除原木整装商品
     */
    public function delete() {
        $id = $this->request->param( 'id', 'intval', 0 );
        $where = array( 'id' => $id );
        $goodsModel   = new Goods();
        //进行商品信息编辑
        if ( $goodsModel->save( array( 'is_delete' => 1 ), $where ) ) {
            $this->success( '删除成功' );
        } else {
            $this->success( '删除失败' );
        }
    }

    /**
     * 删除选中的文章分类方法
     */
    public function deleteChecked() {
        $id_list = $this->request->param('id_list');
        $id_list = trim($id_list);
        if (empty($id_list)){
            $this->error('参数醋哟无');
        }
        $goodsModel   = new Goods();
        if ( $goodsModel->where('id', 'in', $id_list)->delete() ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 设置关联商品页面
     * @param int $id 商品ID
     */
    public function relation( $id = 0 ) {
        //查询商品信息
        $goodsModel   = new Goods();
        //查询整装商品
        $goods = $goodsModel->where( 'id', $id )->find();
        $this->assign( 'goods', $goods );
        //查询商品关联信息
        $linkList  = Db::name('logs_decoration_contain')->where( 'logs_goods_id', $id )->select();
        //进行商品查询
        if ( count( $linkList ) > 0 ) {
            //获取到商品ID
            $goodsId = array();
            foreach ($linkList as $link) {
               array_push($goodsId, $link['goods_id']);
            }
            //商品名称查询
            $normalGoodsModel = new NormalGoods();
            $where            = array( 'goods_id' => array( 'in', $goodsId ) );
            $goodsList        = $normalGoodsModel->field('goods_id, goods_name')->where( $where )->select();
            //数据拼接
            foreach ($linkList as $key => $link) {
                foreach ($goodsList as $tag => $goods) {
                    if ( $link['goods_id'] == $goods['goods_id'] ) {
                        $linkList[$key]['goods_name'] = $goods['goods_name'];
                        unset($goodsList[$tag]);
                        break 1;
                    }
                }
            }
        }
        $this->assign( 'linkList', $linkList );
        return $this->fetch();
    }


    /**
     * 分页查询普通商品
     * TODO，移动到商品控制器
     */
    public function inquire() {
        $param = $this->request->param();
        $where = array();
        if ( isset( $param['name'] ) && trim( $param['name'] ) != '' ) {
            $where['goods_name'] = array('like', '%'.$param['name'].'%');
        }
        $where['goods_verify'] = 1;
        $where['goods_type'] = 1;
        $number = 14; //默认每页14个商品
        $page   = isset( $param['page'] ) && is_numeric( $param['page'] ) && $param['page'] > 0 ? $param['page'] : 0;
        $goodsModel = new NormalGoods();
        $this->success( '', '', $goodsModel->inquire( 'goods_id, goods_name, goods_image_main', $where, $page, $number ) );
    }
    
    /**
     * 原木整装商品与普通商品建立关系
     */
    public function link() {
        $param = $this->request->param();
        if ( !isset( $param['logs_goods_id'] ) || !isset( $param['goods_id'] )|| !isset( $param['number'] ) ) {
            $this->error( '错误的参数提交' );
        }
        if ( trim( $param['goods_id'] ) == '' ) {
            $this->error( '商品ID不能为空' );
        }
        if ( !is_numeric( $param['number'] ) || 0 >= $param['number'] ) {
            $this->error( '数量为大于0整数' );
        }
        //先查寻是否存在
        $where = array( 'logs_goods_id' => $param['logs_goods_id'], 'goods_id' => $param['goods_id'] );
        if ( Db::name('logs_decoration_contain')->where( $where )->count() > 0 ) {
            $this->error( '商品已存在,请先删除' );
        }
        $linkId = Db::name('logs_decoration_contain')->insertGetId( $param );
        if ( $linkId ) {
            $this->success( '添加成功', '', $linkId );
        } else {
            $this->error( '添加失败' );
        }
    }

    /**
     * 原木整装商品与普通商品删除关系
     */
    public function deleteLink() {
        $linkId = $this->request->param( 'link_id', 'intval', 0 );
        if ( Db::name('logs_decoration_contain')->where( 'id', $linkId )->delete() ) {
            $this->success( '删除成功' );
        } else {
            $this->error( '删除失败' );
        }
    }

    /**
     * 通过设计师名称获取设计师信息
     */
    public function desinger() {
        $name = $this->request->param('name');
        $desingerList = array();
        if ( trim( $name ) != '' ) {
            $desingerModel = new Designer();
            $where         = array('designer_name'=> array('like', '%'.$name.'%'));
            $desingerList  = $desingerModel->field('designer_id, designer_name, company')->where( $where )->select();
        }
        $this->success('', '', $desingerList);
    }

    /**
     * 商品审核
     */
    public function check(){
        $id     = $this->request->param('id', 0, 'intval');
        $verify = $this->request->param('verify', 0, 'intval');
        if( !$id ){
            $this->error('商品ID有误');
        }
        $result = Db::name('logs_decoration_goods')->where('id', $id)->update(array('goods_verify'=>$verify,'goods_sell_time'=>time()));
        if( $result ){
            $this->success('状态更改成功!');
        }else{
            $this->error('状态更改失败！');
        }
    }

    /**
     * 批量设置诚意金
     */
    public function deposit() {
        $deposit = $this->request->param('deposit');
        $goodsId = $this->request->param('goods_id');

        $depositTemp = intval($deposit);
        if ( $depositTemp<=0 || $depositTemp !=$deposit )  $this->error( '定金是大于0的整数' );

        $goodsId = explode(',', $goodsId);
        $where   = array( 'id' => array( 'in', $goodsId ) );
        Db::name('logs_decoration_goods')->where( $where )->update( array( 'goods_verify'=>3, 'deposit'=>$deposit ) );
        $this->success( '定金设置成功' );
    }
}
