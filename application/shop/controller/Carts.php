<?php
/**
 * 购物车页面
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷 at 2016/12/5
 */

namespace app\shop\controller;
use app\common\controller\Shop;
use app\common\logic\BuyOne;
use app\common\model\Goods;
use app\common\model\Member;
use app\shop\model\Cart;
use app\common\model\GoodsSku;
use think\Db;
use think\Session;
use think\Validate;

class Carts extends Shop{
    protected $model;

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Cart();
    }

    /**
     * 购物车列表页
     */
    public function index() {
		//搜索商品名字
		$searchName = $this->request->param('search', '', 'trim');
        //假如用户登录了
        if( $this->login ) { //读取购物车数据

			$cartList = array();
			$condtion=array();
			$condtion['member_id']=$this->user->member_id;
			if(!empty($searchName)){
				$goodsId = Db::name('goods')->where('goods_name','like','%'.$searchName.'%')->column('goods_id');
				if(!empty($goodsId)){
					$condtion['goods_id'] = array('in',$goodsId);
					$list = Db::name('cart')->where($condtion)->select();
					$cartList = ( new BuyOne() )->getGoodsCartList($list, 1);
				}
			}else{
				$list = Db::name('cart')->where($condtion)->select();
				$cartList = ( new BuyOne() )->getGoodsCartList($list, 1);
			}


            //将库存为0的购物车商品放置在最后且当库存大于0时，数量设置为1  2017.2.13 ss.wu
            if($cartList){
                foreach($cartList as $k=>$v){
                    if($v['goods_number'] == 0 && $v['goods_storage'] > 0){
                        $cartList[$k]['goods_number'] = 1;
                        $this->model->update( ['goods_number'=>1,'cart_id'=>$v['cart_id']] );
                    }elseif($v['goods_number'] == 0 && $v['goods_storage'] == 0){
                        $temp = $v;
                        unset($cartList[$k]);
                        array_push($cartList,$temp);
                    }elseif($v['goods_verify'] != 1){
                        unset($cartList[$k]);
                        array_push($cartList,$v);
                    }
                }
            }

            $this->assign('cartList', $cartList);
            $this->assign('search_name', $searchName);
            return $this->fetch();
        } else { //用户没登陆
			$this->assign('search_name', $searchName);
            return $this->fetch('nologin');
        }
    }

    /**
     * 加入购物车页面
     */
    public function add() {
        $number = $this->request->param('nr', 0, 'intval');
        $sku    = $this->request->param('rid', '');
        $storage= 0;
        //验证商品以及库存
        if ( is_numeric( $number ) && $number > 0 && trim( $sku ) != '' ) {
            $skuModel = new GoodsSku();
            $where    = array( 'goods_sku' => $sku );
            $goods    = $skuModel->where( $where )->find();
            if( !$goods )  $this->error('商品已下架或不存在');
            $goodsInfo = ( new Goods() )->where('goods_id', $goods['goods_id'])->find();
            if( !$goodsInfo || $goodsInfo['is_delete'] == 1 || $goodsInfo['goods_verify'] != 1) $this->error('商品已下架或不存在');
            //如果商品存在，并且库存充足，则加入购物车
            if ( isset( $goods['goods_storage'] ) && $goods['goods_storage'] >= $number ) {
                //判断用户是否登录，如果登录，则直接计入购物车，否则的话计入cookie
                if ( $this->login ) {
                    //判断商品是否已经存在购物车，如果存在，则增加数量
                    $where = array( 'member_id' => $this->user['member_id'], 'goods_sku' => $goods['goods_sku'] );
                    $cart  = Db::name('cart')->where( $where )->find();
                    //如果改用户该商品已经加入购物车
                    if ( isset( $cart['cart_id'] ) ) {
                        $storage = Db::name('cart')->where('cart_id', $cart['cart_id'])->setInc('goods_number', $number) ? 1 : 0;
                    } else {
                        $param = array(
                            'member_id'   => $this->user['member_id'],
                            'goods_number'=> $number,
                            'goods_id'    => $goods['goods_id'],
                            'goods_sku'   => $goods['goods_sku'],
                            'created_at'  => time()
                        );
                        $storage = Db::name('cart')->insert( $param ) ? 1 : 0;
                    }
                    
                }
                //如果用户没有登陆，则存入localstorage
                $storage = 2;
            }else{
                $this->error('商品库存不足');
            }
        }
        //如果storage为0，则代表库存不足或者商品不存在，则直接报错
        //通过sku获取商品名称等信息
        $goodsInfo = ( new GoodsSku() )->getInfoBySku($sku, false);
        //猜你喜欢
        $goodsList = (new Goods() )->where(['is_delete'=>0, 'goods_verify'=>1])
                    ->field('goods_id,goods_image_main,goods_name,goods_price,goods_sale_number')
                    ->limit(3)->select();

        $this->assign('storage', $storage);
        $this->assign('number', $number);
        $this->assign('sku', $sku);
        $this->assign('goodsInfo', $goodsInfo);
        $this->assign('goodsList', $goodsList);
        return $this->fetch();
    }

    /**
     * 删除购物车商品
     */
   public function delete() {
       $cartId = $this->request->param('id', '', 'trim');
       if(empty($cartId)) $this->error('删除失败');
       $cartArray = explode(',', $cartId);
       $result = $this->model->deleteCart( ['cart_id'=>['in', $cartArray] ,'member_id'=>$this->user['member_id'] ]);
       if( !$result )  $this->error('删除失败');
       $this->success('删除成功');
   }

    /**
     * 更新购物车商品数量
     */
   public function updateGoodsQuantity() {
       $cartId	= $this->request->param('cart_id', '', 'intval');
       $quantity = $this->request->param('quantity', '', 'intval');
       if( $cartId <=0 || $quantity<=0) $this->error('参数错误', '', 0);

       //查询购物车中商品的信息
       $cartInfo = Db::name('cart')->where( ['cart_id' => $cartId,'member_id'=>$this->user['member_id']] )->find();
       if(empty($cartInfo)) $this->error('参数错误', '', 0);

       $cartList[] = $cartInfo;
       $goodsList = ( new BuyOne() )->getGoodsCartList($cartList);
       if(empty($goodsList)) $this->error('商品不存在或已下架', '', 0);
       $goodsInfo = $goodsList[0];
       if( !$goodsInfo['state'] ) $this->error('商品不存在或已下架', '', 0);

       $goodsStorage = $goodsInfo['goods_storage'];
       if($goodsStorage == 0 || $goodsStorage < $quantity){//库存不足，修改购物车
           $this->model->update( ['goods_number'=>$goodsStorage,'cart_id'=>$cartId] );
           if($goodsStorage == 0){
               $msg = '当前商品断货';
           }else{
               $msg = '最多只能购买'.$goodsStorage.'件';
           }
           $this->error($msg, '', ['goods_storage'=>$goodsStorage, 'goods_total'=>ncPriceFormat($goodsInfo['goods_price']*$goodsStorage)]);
       }
       //库存足够
       $result = $this->model->update( ['goods_number'=>$quantity,'cart_id'=>$cartId] );
       if( !$result ){
           $this->error('更改数量失败', '', 0);
       }
       $this->success('更改数量成功', '', ['goods_total'=>ncPriceFormat($goodsInfo['goods_price']*$quantity)]);
   }

    /**
     * 检测用户是否登录
     */
    public function isLogin() {
        if( $this->login == 1 )
            $this->success('用户已登录');
        else
            $this->error('用户未登录');
    }

    /**
     * 登录处理
     */
    public function loginDo() {

        $account = $this->request->param('account', '', 'trim');
        $password  = $this->request->param('password', '', 'trim');
        $localCart  = $this->request->param('localCart', '', 'trim');
        $memberInfo = [ 'member_name' => $account, 'password' => $password ];

        $rule = array(
            'member_name' => 'require',
            'password'=> 'require'
        );
        $message = array(
            'member_name.require' => '账号不能为空',
            'password.require' => '密码不能为空'
        );
        //参数空验证
        $validate = new Validate($rule, $message);
        if ( !$validate->check( $memberInfo ) )
            $this->error( $validate->getError() );
        //账户验证
        $selectInfo = ( new Member() )->where('phone', $memberInfo['member_name'])->find();
        if( !$selectInfo )
            $this->error( '该账户不存在' );
        if( $selectInfo->password != $memberInfo['password'] )
            $this->error( '密码错误' );

        //登陆成功后，存储session
        Session::set('user', serialize( $selectInfo ));
        //登录成功后，若localStorage有购物车数据，将其加到数据库中
        if( $localCart !='' ) {
            $this->model->mergeCart($localCart, $selectInfo->member_id);
        }
        $this->success('登录成功');
    }

    /**
     * 用户登录
     */
    public function memberLogin() {
        $this->view->engine->layout(false);
        return $this->fetch();
    }

}
