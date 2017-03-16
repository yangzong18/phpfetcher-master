<?php
/**
 * Created by PhpStorm.
 * Project: code
 * User: Administrator
 * Date: 2016/11/14
 * Time: 16:29
 * Author: ss.wu
 */
namespace app\shop\model;
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
        $tblRows = $this->field('*')->where(array('is_delete' => 0))
                        ->order(array('sort' => 'asc'))
                        ->select();
        $allLevelData = array();
        foreach ($tblRows as $key => $row){
            $allLevelData[$row['category_id']] = $row->toArray();
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
        $tblRows = $this->field('*')
                        ->where(array('parent_id' => 0))
                        ->where(array('is_delete' => 0))
                        ->order(array('sort' => 'asc'))
                        ->select();
        $dataArray = array();
        foreach ($tblRows as $key => $row){
            $dataArray[$row['category_id']] = $row->toArray();
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
                        ->where('parent_id', 'in', $idList)
                        ->where(array('is_delete' => 0))
                        ->order(array('sort' => 'asc'))
                        ->select();
        $secondLevelData = array();
        foreach ($tblRows as $key => $row){
            $secondLevelData[$row['parent_id']][] = $row->toArray();
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
                        ->where('parent_id', 'in', $idList)
                        ->where(array('is_delete' => 0))
                        ->order(array('sort' => 'asc'))
                        ->select();
        $thirdLevelData = array();
        foreach ($tblRows as $key => $row){
            $thirdLevelData[$row['parent_id']][] = $row->toArray();
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



    /**
     * 根据名称查询某个分类的ID
     * @param $name string
     * @return  string
     */
    public function getOneId($name){
        if(!$name){
            return '';
        }

        $result = $this->field('category_id')
            ->where(array('name' => $name))->where(array('is_delete' => 0))
            ->find();

        if($result){
            return $result['category_id'];
        }else{
            return '';
        }
    }


    /**
     * 获取父ID
     * @param $id string
     * @return string
     */
    public function getParentId($id){
        if(!$id){
            return '';
        }

        $result = $this->field('parent_id')
            ->where(array('category_id' => $id))->where(array('is_delete' => 0))
            ->find();

        if($result){
            return $result['parent_id'];
        }else{
            return '';
        }
    }


    /**
     * 获取某个分类下的子分类
     * @param $cid string 分类ID
     * @return array
     */
    public function getNextLevel($cid){
//        if(!$cid){
//            return array();
//        }
        $result = $this->field('*')
            ->where(array('parent_id' => $cid))
            ->where(array('is_delete' => 0))
            ->order(array('sort' => 'asc'))
            ->select();

        return $result;

    }


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
            ->where(array('category_id' => $id))
            ->where(array('is_delete' => 0))
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



    /**
     * 根据ID获取一条分类信息
     * @param $id string
     * @return string
     */
    public function getOne($id){
        if(!$id){
            return false;
        }
        $res = $this->where("category_id = '".$id."'")->where(array('is_delete' => 0))->find();

        return $res;
    }
}
