<?php
/**
 * create by: PhpStorm
 * desc:加入购物车接口
 * author:yangmeng
 * create time:2016/12/06
 */
namespace app\api\controller;
use app\common\model\GoodsSku;
use think\Controller;
use app\common\controller\Shop;
use think\Db;

class Cart extends Shop{
    /**
     * 加入购物车
     */
    public function addCart()
    {
        //参数
        $param = array();
        //$param['member_id'] = $_SESSION['member_id'];
        $param['member_id'] = 'e80c40eff0bba201f9e0efe9a581167d';
        $param['created_at'] = time();
        $param['goods_number'] = $this->request->param('goods_num', '', 'intval');
        $param['goods_id'] = $this->request->param('goods_id', '', 'trim');
        $param['goods_sku'] = $this->request->param('goods_sku', '', 'trim');

        //判断参数是否有为空的数值
        if(in_array('',$param) || $param['goods_number'] == '0'){
            $this->error('参数错误');
        }

         //检测商品是否存在,并获得库存
        $goodsStorage = Db::name('goods')
                ->where(array('goods_id'=>$param['goods_id'],'is_delete'=>0))
                ->value('goods_storage');

        $goodsSkuStorage = Db::name('goods_sku')
            ->where(array('goods_sku'=>$param['goods_sku']))
            ->value('goods_storage');

        if($goodsStorage&&$goodsSkuStorage){
            if( ($goodsStorage<$param['goods_number']) || ($goodsSkuStorage<$param['goods_number'])){
                $this->error('商品库存不足');
            }else{
                $where = array('member_id'=>$param['member_id'],'goods_id'=>$param['goods_id'],'goods_sku'=>$param['goods_sku']);
                //判断购物车中是否存在此商品
                $result = Db::name('cart')->field('cart_id,goods_number')->where($where)->find();
                if($result){
                    $goods_number = $param['goods_number']+$result['goods_number'];
                    if(Db::name('cart')->update(array('cart_id'=>$result['cart_id'],'created_at'=>time(),'goods_number'=>$goods_number))){
                        $this->success('添加购物车成功');
                    }else{
                        $this->error('添加购物车失败');
                    }
                }else{
                    if(Db::name('cart')->insert($param)){
                        $this->success('添加购物车成功');
                    }else{
                        $this->error('添加购物车失败');
                    }
                }
            }
        }else{
            $this->error('商品不存在');
        }
    }


    /**
     * 根据 goods_sku获取商品信息
     */
    public function getGoods() {
		//搜索商品名字
		$searchName = $this->request->param('search', '', 'trim');
        $input = $this->request->param('sku', '', 'trim');//sku|数量，sku|数量
        //处理数据
        $inputList = explode(',',$input);
        $skuList = [];
        foreach( $inputList as $val) {
            $info = explode('|', $val);
            $skuList[$info[0]]['goods_number'] = $info[1];//格式skuList[sku] = 数量;
        }
        unset($info);
        //获取商品信息
        $goodsSku = new GoodsSku();

        foreach( $skuList as $key => $val) {
            $goodsInfo = $goodsSku->getInfoBySku($key);//缓存读取数据
			if(!empty($searchName)){
				if (!preg_match ("/".$searchName."/i" ,  $goodsInfo['goods_name'])){
					unset($skuList[$key]);
					continue;
				}
			}
            //update by laijunliang at 2017/02/15读取规格从缓存中读取
            if ( trim( $goodsInfo['sku_name'] ) != '' ) {
                $featureList = json_decode( $goodsInfo['sku_name'], true );
                $featureInfo = array();
                foreach ($featureList as $feature_name => $feature) {
                    array_push($featureInfo, array(
                        'feature' => $feature_name,
                        'feature_value' => $feature,
                    ));
                }
                $skuList[$key]['feature'] = $featureInfo;
            } else {
                $skuList[$key]['feature'] = array();
            }
            $skuList[$key]['goods_sku'] = $goodsInfo['goods_sku'];
            $skuList[$key]['goods_id'] = $goodsInfo['goods_id'];
            $skuList[$key]['goods_price'] = $goodsInfo['goods_price'];
            $skuList[$key]['goods_storage'] = $goodsInfo['goods_storage'];
            $skuList[$key]['group_id'] = $goodsInfo['group_id'];
            $skuList[$key]['store_id'] = $goodsInfo['store_id'];
            $skuList[$key]['goods_storage_alarm'] = $goodsInfo['goods_storage_alarm'];
            $skuList[$key]['goods_name'] = $goodsInfo['goods_name'];
            $skuList[$key]['goods_verify'] = $goodsInfo['goods_verify'];
            $skuList[$key]['goods_image_main'] = $goodsInfo['goods_image_main'];
            $skuList[$key]['goods_url'] = url('shop/goods/index',['gk'=>$goodsInfo['goods_id']]);
            $skuList[$key]['goods_total'] = ncPriceFormat($goodsInfo['goods_price']*$val['goods_number']);
            if( $goodsInfo['is_delete'] == 0 && $goodsInfo['goods_verify'] == 1 && $goodsInfo['goods_storage'] > 0 )
                $skuList[$key]['state'] = 1;
            else
                $skuList[$key]['state'] = 0;
        }
        $this->success('成功', '', $skuList);
    }

    /**
     * 根据goods_sku判断库存
     */
    public function updateGoodsQuantity() {
        $goodsSku = $this->request->param('goods_sku', '', 'trim');
        $quantity = intval( abs( $this->request->param('quantity', '', 'intval') ) );
        if( $goodsSku == '' || !$quantity){
            $this->error('参数错误', '', 0);
        }
        //查询商品库存
        $skuInfo = ( new GoodsSku() )->getInfoBySku($goodsSku, true);
        if( empty($skuInfo) ) {
            $this->error('商品已下架', '', 0);
        }
        if($skuInfo['goods_storage'] == 0 || $skuInfo['goods_storage'] < $quantity) {//库存不足
            if($skuInfo['goods_storage'] == 0) {
                $msg = '当前商品断货';
            } else {
                $msg = '最多只能购买'.$skuInfo['goods_storage'].'件';
            }
            $this->error($msg, '', ['goods_storage'=>$skuInfo['goods_storage'], 'goods_total'=>ncPriceFormat($skuInfo['goods_price']*$skuInfo['goods_storage'])]);
        }
        $this->success('更改数量成功', '', ['goods_total'=>ncPriceFormat($skuInfo['goods_price']*$quantity)] );
    }

}
