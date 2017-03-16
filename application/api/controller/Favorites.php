<?php
/**
 * create by: PhpStorm
 * desc:收藏接口
 * author:yangmeng
 */
namespace app\api\controller;
use think\Controller;
use app\common\controller\Member;
use think\Db;

class Favorites extends Member{

    public function addFav(){
        $dataId = $this->request->param('data');
        $type = $this->request->param('type');
        $param['member_id'] = $this->user->member_id;
        if(is_numeric($dataId)){
            //实例展示收藏
            if($type){
                $param['production_id'] = $dataId;
                $param['type'] = 2;
                $where = array('member_id'=>$param['member_id'],'production_id'=>$param['production_id']);
            }else{
                $param['logs_id'] = $dataId;
                $goods = Db::name('logs_decoration_goods')->field('id,is_delete,goods_verify')->where('id',$dataId)->find();
                if( empty($goods) || $goods['is_delete']== 1 || $goods['goods_verify'] != 1)
                    $this->error('整装商品已下架或不存在');
                $param['type'] = 1;
                $where = array('member_id'=>$param['member_id'],'logs_id'=>$param['logs_id']);
            }
        }else{
            $param['goods_id'] = $dataId;
            $goods = Db::name('goods')->field('goods_id,goods_price,is_delete,goods_verify')->where('goods_id',$dataId)->find();
            if( empty($goods) || $goods['is_delete']== 1 || $goods['goods_verify'] != 1)
                $this->error('商品已下架或不存在');
            $param['price'] = $goods['goods_price'];
            $param['type'] = 0;
            $where = array('member_id'=>$param['member_id'],'goods_id'=>$param['goods_id']);
        }

        if( Db::name('favorites')->where($where)->find() ){
            $this->success('收藏成功');
        }
        $result = Db::name('favorites')->insert($param);
        if($result) $this->success('收藏成功');
        else $this->error('收藏失败');

    }

    //取消收藏
    public function delFav(){
        //收藏参数
        $dataId = $this->request->param('data','');
        $type = $this->request->param('type');
        $param = array();
        $param['member_id'] = $this->user->member_id;

        if(is_numeric($dataId)){
            if($type) {
                $where = array('member_id' =>$param['member_id'], 'production_id' =>$dataId);
            }else{
                $goods = Db::name('logs_decoration_goods')->field('id,is_delete,goods_verify')->where('id',$dataId)->find();
                if( empty($goods) || $goods['is_delete']== 1 || $goods['goods_verify'] != 1)
                    $this->error('整装商品已下架或不存在');
                $where = array('member_id' =>$param['member_id'], 'logs_id' =>$dataId);
            }
        }else{
            $goods = Db::name('goods')->field('goods_id,is_delete,goods_verify')->where('goods_id',$dataId)->find();
            if( empty($goods) || $goods['is_delete']== 1 || $goods['goods_verify'] != 1)
                $this->error('商品已下架或不存在');
            $where = array('member_id' =>$param['member_id'], 'goods_id' =>$dataId);
        }

        $res = Db::name('favorites')->where($where)->value('id');

        if($res){
            if(Db::name('favorites')->where(array('id'=>$res))->delete())
                $this->success('已取消收藏');
            else  $this->success('取消失败');
        }else $this->success('取消失败');

    }

    /**
     * 购物车商品移入我的收藏
     */
    public function cartAddFav() {
        if( $this->login !=1 )
            $this->error('请先登录');
        $cartId = $this->request->param('cart_id', '', '');
        $goodsId = $this->request->param('goods_id', '', '');
        if( $cartId == '' || $goodsId == '')
            $this->error('操作失败');

        //数据处理
        $cartArray = explode(',', $cartId);
        $goodsArray = array_unique( explode(',', $goodsId) );
        $where = ['member_id' => $this->user->member_id, 'goods_id' => ['in', $goodsArray] ];
        $isFav = Db::name('favorites')->where($where)->column('goods_id');
        //查询商品价格
        $price = Db::name('goods')->where( ['goods_id'=>['in', $goodsArray]] )->field('goods_id,goods_price')->select();
        $priceList = [];
        foreach($price as $val) {
            $priceList[$val['goods_id']] = $val['goods_price'];
        }
        //选择没有收藏的商品，去除已经收藏的商品
        $list = [];
        foreach( $goodsArray as $val ) {
            if( !in_array($val, $isFav) ) {//若商品还未收藏
                $list[] = ['member_id' => $this->user->member_id, 'goods_id' => $val, 'price' => $priceList[$val]];
            }
        }

        Db::name('favorites')->insertAll($list);
        //删除购物车
        Db::name('cart')->where([ 'cart_id'=>['in', $cartArray] ])->delete();

        $this->success('成功移入我的收藏');
    }

}
