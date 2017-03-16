<?php
/**
 * 设计师级别模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: luoTing at 2016-12-13
 */

namespace app\common\model;
use think\Model;

class DesignerLevel extends Model{

    /**
     * 获取设计师级别列表
     * @param array $where 查询条件
     * @return array
     */
    public function getDesignerLevelList( $where = [] ) {
        $list = $this->where( $where )->select();
       //数据处理
        $levelList = array();
        foreach($list as $key => $val ) {
            $levelList[$val['level_id']]['id'] = $val['level_id'];
            $levelList[$val['level_id']]['name'] = $val['level_name'];
            $levelList[$val['level_id']]['is_delete'] = $val['is_delete'];
        }

        return $levelList;
    }

    /**
     * 查询单条级别记录
     * @param array $where 查询条件
     * @return array 返回数组
     */
    public function getDesignerLevelInfo( $where = [] ) {
        return $this->where( $where )->find()->toArray();
    }
}