<?php
/**
 * 商品销售库存模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang 2016-12-02 10:08
 */

namespace app\common\model;
use think\Model;
use app\common\model\Features;
use app\common\model\FeaturesValue;
class GoodsSaleFeature extends Model{

        /*
         * 销售属性
         * */
    public $FeatureName=null;
    public $FeatureValueName=null;

    public function getFName(){
        $Features_Info = Features::get($this['feature_id']);
        $this->FeatureName=$Features_Info['attribute_name'];
        $FeaturesValue_Info=FeaturesValue::get($this['feature_value_id']);
        $this->FeatureValueName = $FeaturesValue_Info['feature_value'];
    }
}