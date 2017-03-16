<?php
/**
 * create by: PhpStorm
 * desc:广告位模型
 * author:yangmeng
 * create time:2016/11/25
 */
namespace app\shop\model;
use think\Model;
use think\Cache;
class Advertise extends Model
{
	public $advertiseList = NULL;
    /**
     * 获取所有的广告位新
     */
    public function inquire() {
    	if ( is_array( $this->advertiseList ) ) {
    		return $this->advertiseList;
    	}
    	$this->advertiseList  = array();
    	if ( ( $this->advertiseList = Cache::get('adv') ) != false ) {
    		return $this->advertiseList;
    	}
    	//从缓存中读取
    	$where = array( 'is_delete' => 0 );
        $advertiseTemp = $this->field("adv_img, adv_link, adv_type, category_id")->where( $where )->order('adv_sort asc')->select();
        foreach ($advertiseTemp as $advertise) {
        	$this->advertiseList[] = $advertise->toArray();
        }
        Cache::set('adv', $this->advertiseList);
        return $this->advertiseList;
    }

    /**
     * 通过分类ID获取广告信息
     * @param string $categoryId 分类ID
     * @param int $number 数量
     */
    public function getAdvertiseByCategoryId( $categoryId, $number = 2 ) {
    	$advertiseList  = $this->inquire();
        $advertiseList  = $advertiseList ? $advertiseList : array();
    	$calc           = 0;
    	$advertise      = array();
    	foreach ($advertiseList as $unit) {
    		if ( $calc >= $number ) {
    			break;
    		}
    		if ( $unit['category_id'] == $categoryId ) {
    			$calc++;
    			$advertise[] = $unit;
    		}
    	}
    	return $advertise;
    }
}