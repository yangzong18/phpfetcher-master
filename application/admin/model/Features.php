<?php
/**
 * 后台特征模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-14 8:57
 */
namespace app\admin\model;

use think\Model;

class Features extends Model
{
    /**
     * 规格获取
     * @param array specificationsList 规格列表
     */
    public function specifications() {
    	$where = array( 'is_delete' => 0, 'sales_attribute' => 1 );
    	return $this->field('feature_id, attribute_name')->where( $where )->select();
    }
}