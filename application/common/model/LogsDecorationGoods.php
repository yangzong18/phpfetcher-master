<?php
/**
 * 原木整装商品模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-11-23 9:16
 */

namespace app\common\model;
use think\Model;

class LogsDecorationGoods extends Model{

    //户型
    const HOUSE_TYPE_VILLA = 1;
    const HOUSE_TYPE_ONE = 2;
    const HOUSE_TYPE_TWO = 3;
    const HOUSE_TYPE_THREE = 4;
    const HOUSE_TYPE_FOUR = 5;
    const HOUSE_TYPE_SIX = 6;
    const HOUSE_TYPE_SEVEN = 7;
    const HOUSE_TYPE_EIGHT = 8;

	//商品状态

	const GOODS_STATE_ONE 	= 0; //未通过
	const GOODS_STATE_TWO 	= 1;
	const GOODS_STATE_THREE = 3; //待审核

    //户型描述
    public static  $houseTypeArr = array(
        self::HOUSE_TYPE_VILLA => '整体效果图',
        self::HOUSE_TYPE_ONE => '客厅',
        self::HOUSE_TYPE_TWO => '卧室',
        self::HOUSE_TYPE_THREE => '书房',
        self::HOUSE_TYPE_FOUR => '餐厅',
        self::HOUSE_TYPE_SIX => '儿童房',
        self::HOUSE_TYPE_SEVEN => '厨房',
        self::HOUSE_TYPE_EIGHT => '卫生间'
    );

    /**
     * 取得户型文字描述
     */
    public function getHouseType(){
        return self::$houseTypeArr;
    }

    /**
     * 取得户型文字输出
     */
    function getHouseTypeName($houseType) {
        return self::$houseTypeArr[$houseType];
    }
	/**
	 * @param $where
	 * @param string $fields
	 * @return int 统计订单数量
	 */

	public function getCount($where,$fields='*'){
		return $this->where($where)->count($fields);
	}

}
