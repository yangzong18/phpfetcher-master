<?php
/**
 * 众筹商品
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/13  10:23
 */
namespace app\shop\controller;

use app\common\controller\Shop;
use app\shop\model\Attachment;
use app\shop\model\CrowdfundingGoods;
use app\shop\model\CrowdfundingGoodsExtra;

class Crowdfunding extends Shop
{
    public function index(){

        $goods = new CrowdfundingGoods();
        $where = array();
        $where['is_delete'] = 0;
        $where['verify'] = 1;
        $list = $goods->where($where)->order('sell_time desc')->paginate(12);
        $page = $this->dealPage($list->render());

        $this->assign('page',$page);
        $this->assign('list',$list);
        return $this->fetch();
    }


    /**
     * 众筹商品详情页
     */
    public function detail(){
		//李武修改
        $goodsId = $this->request->param('goodsId/d');
        if(!$goodsId){
            $this->error('请传入商品ID！');
        }
        $goods = new CrowdfundingGoods();
        //众筹商品信息
        $goodsRes = $goods->getCrowdfundingGoods($goodsId);
        if(!$goodsRes){
            $this->error('未找到商品信息！');
        }

        $goodsExtra = new CrowdfundingGoodsExtra();
        //众筹商品扩展信息
        $whereExtra = array();
        $whereExtra['crowdfunding_goods_id'] = $goodsId;
        $goodsExtraRes = $goodsExtra->getCrowdfundingGoodsExtra($whereExtra);

        //众筹商品附件信息
        $attachment = new Attachment();
        $attachWhere = array();
        $attachWhere['business_sn'] = 'crowdfunding_goods';
        $attachWhere['business_id'] = $goodsId;
        $attachRes = $attachment->getAttachment($attachWhere);

        //众筹进度
        if($goodsRes['quotient'] == 0){
            $progress = '<span style="width: 0;">0%</span>';
        }else{
            $progress = '<span style="width: '.(round($goodsRes['sale_number']/$goodsRes['quotient'],3)*100).'%;">'.(round($goodsRes['sale_number']/$goodsRes['quotient'],3)*100).'%</span>';
        }


        $this->assign('progress',$progress);
        $this->assign('goods',$goodsRes);
        $this->assign('goodsExtra',$goodsExtraRes);
        $this->assign('attach',$attachRes);

        return $this->fetch();
    }
}