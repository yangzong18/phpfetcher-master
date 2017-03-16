<?php
/**
 * Created by PhpStorm.
 * Project: code
 * User: Administrator
 * Date: 2016/11/14
 * Time: 16:29
 * Author: ss.wu
 */
namespace app\admin\model;
use think\Cache;
use think\Model;

class GoodsCategory extends Model {
    // 所有分类缓存索引键
    private $all_level_key = 'all_level_goods_category';
    // 一级分类缓存索引键
    private $first_level_key = 'first_level_goods_category';
    // 二级分类缓存索引键
    private $second_level_key = 'second_level_goods_category';
    // 三级分类缓存索引键
    private $third_level_key = 'third_level_goods_category';
    
    /**
     * 获取所有分类，该数据使用 id 作为索引获取分类信息
     */
    public function getAllLevel(){
        $allLevelData = Cache::get($this->all_level_key);
        if (!empty($allLevelData)){
            return $allLevelData;
        }
        // 获取所有的分类
        $tblRows = $this->field('*')->where(array('is_delete' => 0))->order('sort asc')
                        ->select();
        $allLevelData = array();
        foreach ($tblRows as $key => $row){
            $allLevelData[$row['category_id']] = $row;
        }
        $result = Cache::set($this->all_level_key, $allLevelData);
        return $allLevelData;
    }

    /**
     * 获取一级分类，该数据使用 id 作为索引获取该分类数据
     */
    public function getFirstLevel(){
        $dataArray = Cache::get($this->first_level_key);
        if (!empty($dataArray)){
            return $dataArray;
        }
        $tblRows = $this->field('*')->where(array('is_delete' => 0))
                        ->where(array('parent_id' => 0))
                        ->order('sort asc')
                        ->select();
        $dataArray = array();
        foreach ($tblRows as $key => $row){
            $dataArray[$row['category_id']] = $row;
        }
        $result = Cache::set($this->first_level_key, $dataArray);
        return $dataArray;
    }

    /**
     * 获取二级分类，该数据使用 parent_id(一级分类) 作为索引获取所有二级分类
     */
    public function getSecondLevel(){
        $secondLevelData = Cache::get($this->second_level_key);
        if (!empty($secondLevelData)){
            return $secondLevelData;
        }
        $firstLevelData = $this->getFirstLevel();
        if (empty($firstLevelData)){
            return array();
        }
        // 获取所有的一级分类的 id
        $idList = array();
        foreach ($firstLevelData as $key => $row){
            $idList[] = $row['category_id'];
        }
        // 获取所有的一级分类的所有二级分类
        $tblRows = $this->field('*')
                        ->where('parent_id', 'in', $idList)->where(array('is_delete' => 0))
                        ->order('sort asc')
                        ->select();
        $secondLevelData = array();
        foreach ($tblRows as $key => $row){
            $secondLevelData[$row['parent_id']][] = $row;
        }
        $result = Cache::set($this->second_level_key, $secondLevelData);
        return $secondLevelData;
    }

    /**
     * 获取三级分类，该数据使用 parent_id(二级分类) 作为索引获取所有三级分类
     */
    public function getThirdLevel(){
        $thirdLevelData = Cache::get($this->third_level_key);
        if (!empty($thirdLevelData)){
            return $thirdLevelData;
        }
        $secondLevelData = $this->getSecondLevel();
        if (empty($secondLevelData)){
            return array();
        }
        // 获取所有的二级分类的 id
        $idList = array();
        foreach ($secondLevelData as $first_level_key => $rows){
            foreach ($rows as $key => $row){
                $idList[] = $row['category_id'];
            }
        }
        // 获取所有的二级分类的所有三级分类
        $tblRows = $this->field('*')
                        ->where('parent_id', 'in', $idList)->where(array('is_delete' => 0))
                        ->order('sort asc')
                        ->select();
        $thirdLevelData = array();
        foreach ($tblRows as $key => $row){
            $thirdLevelData[$row['parent_id']][] = $row;
        }
        $result = Cache::set($this->third_level_key, $thirdLevelData);
        return $thirdLevelData;
    }

    /**
     * 重新加载缓存
     */
    public function flushCache(){
        Cache::rm($this->all_level_key);
        Cache::rm($this->first_level_key);
        Cache::rm($this->second_level_key);
        Cache::rm($this->third_level_key);
        // 以下顺序不要打乱
        $this->getAllLevel();
        $this->getFirstLevel();
        $this->getSecondLevel();
        $this->getThirdLevel();
    }
}