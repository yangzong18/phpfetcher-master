<?php
/**
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2017/1/3  15:51
 */
namespace app\seller\model;


use think\Model;

class GoodsCategory extends Model
{

    /**
     * 根据id判断数据第几层级
     * @param $id int 分类id
     * @return number
     */
    public function getLevel($id){
        if(!$id){
            return false;
        }
        $result = $this->field('*')
            ->where(array('category_id' => $id,'is_delete' => 0))
            ->find();

        if(isset($result['parent_id'])){
            if($result['parent_id'] == '0'){
                return 1;
            }
            $res = $this->where(array("category_id"=>$result['parent_id'],'is_delete' => 0))->find();
            if(isset($res['parent_id'])){
                if($res['parent_id'] == '0'){
                    return 2;
                }

                $ress = $this->where(array("category_id"=>$res['parent_id'],'is_delete' => 0))->find();
                if(isset($ress['parent_id'])){
                    if($ress['parent_id'] == '0'){
                        return 3;
                    }
                }

            }

        }


        return 0;
    }

}