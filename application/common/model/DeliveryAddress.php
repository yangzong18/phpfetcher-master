<?php
/**
 * Created by PhpStorm.
 * User: zhusunjing
 * Date: 2016/11/21
 * Time: 11:22
 */

namespace app\common\model;
use think\Model;

class DeliveryAddress extends Model{

    /**
     * @param $data
     * @return int|string
     * 获取插入的id
     */
    public function insert($data){
        if($data['is_default'] == 1){
            $this->update(['is_default'=>0],['member_id'=>$data['member_id']]);
        }
        return $this->db()->insertGetId($data);
    }

    /**
     * @param $where
     * @param string $fields
     * @return int
     * 统计数量
     */

    public function getCount($where,$fields='*'){
        return $this->where($where)->count($fields);
    }

    /**
     * @param $data
     * @param $where
     * @return int|string
     * @throws \think\Exception
     * 编辑地址表信息
     */

    public function updateData($data,$where){
        if($data['is_default'] == 1){
            $this->update(['is_default'=>0],['member_id'=>$where['member_id']]);
        }
        return $this->where($where)->update($data);
    }

    /**
     * @param $where
     * @return int
     * @throws \think\Exception
     *
     * 删除数据
     */
    public function  delAdd($where){
        return $this->where($where)->delete();
    }

    /**
     * @param $where
     * @return array|false|\PDOStatement|string|Model
     * 查找一条数据
     */
    public function getOneAdd($where){
        return $this->where($where)->find();
    }
}
