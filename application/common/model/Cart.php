<?php
/**
 * Created by PhpStorm.
 * User: 罗婷
 * Date: 2016/12/8
 * Time: 9:27
 */
namespace app\common\model;
use think\Db;
use think\Model;

class Cart extends Model{

    protected $insert = ['created_at'];

    /**
     * 自动完成创建时间
     * @return int 时间戳
     */
    protected function setCreatedAtAttr() {
        return time();
    }

    /**
     * 删除购物车
     * @param array $condition 条件
     */
    public function deleteCart( $condition = [],$type='db') {
        return $this->where( $condition )->delete();
    }

    /**
     * 将浏览器本地购物车数据加入数据库
     * @param $localCart 浏览器本地购物车数据 格式 =>sku|数量,sku|数量拼接串
     * @param $memberId 用户id
     */
    public function mergeCart($localCart, $memberId) {
        $list = explode(',',$localCart);
        $cart = [];
        foreach( $list as $key => $val ) {
            $info = explode('|', $val);
            $cart[$info[0]] = ['goods_sku'=> $info[0], 'goods_number'=> $info[1]];
        }
        //获取商品信息
        $goodsSku = new GoodsSku();
        foreach ( $cart as $key => $val ) {
            $goodsInfo = $goodsSku->getInfoBySku($val['goods_sku'], true);//缓存读取数据
            if( empty($goodsInfo) ) {//若商品不正常
                unset($cart[$key]);
            } else {
                $cart[$key]['goods_id'] = $goodsInfo['goods_id'];
                $cart[$key]['goods_storage'] = $goodsInfo['goods_storage'];
            }
        }
        //判断是否是新增还是增加数据
        $where = [];
        foreach($cart as $key => $val) {
            $where['member_id'] = $memberId;
            $where['goods_id'] = $val['goods_id'];
            $where['goods_sku'] = $val['goods_sku'];
            if( ($info = Db::name('cart')->where( $where )->find()) == null) {//不存在则添加
                unset($cart[$key]['goods_storage']);
                $cart[$key]['member_id'] = $memberId;
                $cart[$key]['created_at'] = time();
                Db::name('cart')->insert($cart[$key]);
            } else {//判断合并数据和剩余库存比较
                if($val['goods_number'] + $info['goods_number'] >= $val['goods_storage']) {//超过库存
                    Db::name('cart')->where( $where )->setField('goods_number', $val['goods_storage']);
                } else {
                    Db::name('cart')->where( $where )->setInc('goods_number', $val['goods_number']);
                }
            }
        }

    }
}
