<?php
/**
 * Created by PhpStorm.
 * 罗婷 12/22 原木整装进度
 */

namespace app\common\model;
use think\Model;

class LogsDecorationOrderSpeed extends Model{

    /**
     * 查询单个订单进度列表
     * @param $where
     */
    public function getOrderSpeedInfo( $where ) {
       $list =  $this->where( $where )->select();
        if( !$list )
           return [];

        $speedInfo = $speedList = [];
        foreach( $list as $key => $val ) {
            $speedInfo = json_decode($val->speed_info, true);
            $speedInfo['speed_img'] = explode(';', $speedInfo['speed_img']);
            $speedList[] = $speedInfo;
        }
        return $speedList;
    }
}