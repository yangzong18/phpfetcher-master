<?php
/**
 * create by: PhpStorm
 * desc:楼层模板模型
 * author:yangmeng
 * create time:2016/11/21
 * modified by:修改人
 * modified time:修改时间
 * modified mark:修改备注 
 */
namespace app\admin\model;
use think\Cache;
use think\model;
class StoreyTemplate extends Model
{
    // 所有楼层模板缓存
    private $all_storey_template = 'all_storey_template';
    public function getCache() {
        $cacheData = Cache::get($this->all_storey_template);
        if (!empty($cacheData)){
            return $cacheData;
        }
        // 获取所有的分类
        $tblRows = $this->field('*')
                        ->select();
        $cacheData = array();
        foreach ($tblRows as $key => $row){
            $cacheData[$row['id']] = $row;
        }
        $result = Cache::set($this->all_storey_template, $cacheData);
        return $cacheData;
    }

    public function flushCache(){
        Cache::rm($this->all_storey_template);
        $this->getCache();
    }
}