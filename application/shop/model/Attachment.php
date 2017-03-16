<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/13  14:47
 */
namespace app\shop\model;

use think\Model;

class Attachment extends Model
{
    /**
     * 获取附件信息
     * @param $where array
     * @return array
     */
    public function getAttachment($where){
        if(!$where){
            return array();
        }
        return $this->where($where)->select();
    }
}