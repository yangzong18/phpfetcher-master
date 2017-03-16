<?php
/**
 * 用户Token
 */

namespace app\common\model;
use think\Model;

class MobileMemberToken extends Model{

    /**
     * 获取手机用户token
     * @param $where
     * @param string $field
     * @return array
     */
    public function getMobileUserTokenInfo($where, $field = '*') {
        $info = $this->field($field)->where($where)->find();
        return (!$info) ? [] : $info->toArray();
    }

    /**
     * 删除用户token
     * @param $where
     * @return int
     * @throws \think\Exception
     */
    public function delMobileMemberToken($where) {
        return $this->where($where)->delete();
    }
}