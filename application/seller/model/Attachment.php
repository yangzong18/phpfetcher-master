<?php
/**
 * 附件模型
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/9  16:09
 */
namespace app\seller\model;

use think\Model;

class Attachment extends Model
{

    /**
     * 添加一条附件信息
     * @param $data array
     * @return int
     */
    public function addAttachment($data){
        return $this->insertGetId($data);
    }


    /**
     * 获取附件信息
     * @param $where array 查找条件
     * @return array
     */
    public function getAttachment($where){
        if(!$where){
            return array();
        }

        return $this->where($where)->select();
    }


    /**
     * 删除一条附件信息
     * @param $where array
     * @return bool
     */
    public function deleteAttachment($where){
        if(!$where){
            return false;
        }

        return $this->where($where)->delete();
    }
}