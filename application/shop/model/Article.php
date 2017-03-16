<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/26  15:02
 */
namespace app\shop\model;

use think\Model;

class Article extends Model
{

    /**
     * 获取一条文章信息
     */
    public function getArticle($where)
    {
        return $this->where($where)->find();
    }
}