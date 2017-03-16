<?php
/**
* Created by 长虹.
* User: 李武
* Date: 2017/1/4
* Time: 10:56
* Desc: 手机端购物车接口
*/
namespace app\mobile\controller;
use app\common\logic\BuyOne;
use app\common\model\Goods;
use app\common\model\Cart;
use app\common\model\GoodsSku;
use think\Db;

class Carts extends MobileMember
{
    protected $model;

    /**
     * 构造器
     */
    public function __construct()
    {
        parent::__construct();
        $this->model = new Cart();
    }

    /**
     * 添加购物车
     */
    public function add()
    {
        $sku    = $this->request->post('goods_sku/s', '','trim');
        $number = $this->request->post('goods_number', 0, 'intval');
        if(empty($sku) || $number<=0){
            $this->returnJson('',1,'参数错误');
        }else{
            $skuModel = new GoodsSku();
            $where    = array( 'goods_sku' => $sku );
            $goods    = $skuModel->where( $where )->find();
            if( empty($goods) ) $this->returnJson('',1,'商品已下架或不存在');
            $goodsInfo    = ( new Goods() )->where('goods_id', $goods['goods_id'])->find();
            if( empty($goodsInfo) || $goodsInfo['is_delete'] == 1 || $goodsInfo['goods_verify'] != 1) {
                $this->returnJson('',1,'商品已下架或不存在');
            } else{
                $goods = $goods->toArray();
                //如果商品存在，并且库存充足，则加入购物车
                if (array_key_exists('goods_storage',$goods) && $goods['goods_storage'] >= $number )
                {
                    //判断商品是否已经存在购物车，如果存在，则增加数量
                    $where = array( 'member_id' => $this->user['member_id'], 'goods_sku' => $goods['goods_sku'] );
                    $cart  = Db::name('cart')->where( $where )->find();
                    //如果改用户该商品已经加入购物车
                    if ( isset( $cart['cart_id'] ) ) {
                        $result = Db::name('cart')->where('cart_id', $cart['cart_id'])->setInc('goods_number', $number);
                    } else {
                        $param = array(
                            'member_id'   => $this->user['member_id'],
                            'goods_number'=> $number,
                            'goods_id'    => $goods['goods_id'],
                            'goods_sku'   => $goods['goods_sku'],
                            'created_at'  => time()
                        );
                        $result = Db::name('cart')->insert( $param );
                    }
                    if($result){
                        $this->returnJson();
                    }else{
                        $this->returnJson('',1,'商品已下架或不存在');
                    }
                }else{
                    $this->returnJson('',1,'此商品数量库存不足');
                }
            }
        }

    }


    /**
     * 更新购物车
     */
    public function updateGoodsQuantity()
    {
        $cartId	= $this->request->post('cart_id', '', 'intval');
        $quantity = $this->request->post('quantity', '', 'intval');
        if( $cartId <=0 || $quantity<=0){
            $this->returnJson('',1,'参数错误');
        }else{
            //查询购物车中商品的信息
            $cartInfo = Db::name('cart')->where( ['cart_id' => $cartId,'member_id'=>$this->user['member_id']] )->find();
            if(empty($cartInfo)){
                $this->returnJson('',1,'购物车不存在这样的商品');
            }else{
                $cartList[] = $cartInfo;
                $goodsList = ( new BuyOne() )->getGoodsCartList($cartList);

                if( empty($goodsList)){
                    $this->returnJson('',1,'商品已下架或不存在');
                }else{
                    $goodsInfo = $goodsList[0];
                    $goodsStorage = $goodsInfo['goods_storage'];
                    if($goodsStorage == 0 || $goodsStorage < $quantity) {//库存不足，修改购物车
                        $this->model->update( ['goods_number'=>$goodsStorage,'cart_id'=>$cartId] );
                        $msg = ($goodsStorage == 0) ? '当前商品断货' : '最多只能购买'.$goodsStorage.'件';
                        $this->returnJson(['goods_storage'=>$goodsStorage, 'goods_total'=>ncPriceFormat($goodsInfo['goods_price']*$goodsStorage)],1,$msg);
                    }else{
                        //库存足够
                        $result = $this->model->update( ['goods_number'=>$quantity,'cart_id'=>$cartId] );
                        if( !$result ){
                            $this->returnJson('',1,'更改数量失败');
                        }else{
                            $this->returnJson(['goods_total'=>ncPriceFormat($goodsInfo['goods_price']*$quantity)]);
                        }
                    }
                }
            }
        }
    }

    /**
     * 删除购物车
     */
    public function delete()
    {
        $cartId = $this->request->post('id', '', 'trim');
        if(empty($cartId)){
            $this->returnJson('',1,'参数错误');
        }else{
            $cartArray = explode(',', $cartId);
            $result = $this->model->deleteCart( ['cart_id'=>['in', $cartArray] ,'member_id'=>$this->user['member_id'] ]);
            if(!$result) {
                $this->returnJson('',1,'删除失败');
            }else{
                $this->returnJson();
            }
        }
    }

    /**
     * 购物车列表页
     */
    public function lists() {
        $list = Db::name('cart')->where(['member_id'=>$this->user['member_id']])->select();
        $cartList = ( new BuyOne() )->getGoodsCartList($list, 1);
        //将库存为0的购物车商品放置在最后且当库存大于0时，数量设置为1  2017.2.13 ss.wu
        if($cartList){
            foreach($cartList as $k=>$v){
                if($v['goods_number'] == 0 && $v['goods_storage'] > 0){
                    $cartList[$k]['goods_number'] = 1;
                    $this->model->update( ['goods_number'=>1,'cart_id'=>$v['cart_id']] );
                }
//                else if($v['goods_number'] == 0 && $v['goods_storage'] == 0){
//                    $temp = $v;
//                    unset($cartList[$k]);
//                    array_push($cartList,$temp);
//                }
            }
        }
        sort($cartList);
        $this->returnJson($cartList);
    }

    /**
     * 推荐商品
     */
    public function getRecommend() {
        $where = ['is_delete'=>0, 'goods_verify'=>1,'goods_type'=>1];
        $goodsList = Db::name('goods')
                     ->field('goods_id,goods_image_main,goods_name,goods_price')
                     ->where($where)
                     ->order('goods_sale_number desc,goods_created_at desc')
                     ->limit(2)
                     ->select();
        $this->returnJson($goodsList);
    }
}