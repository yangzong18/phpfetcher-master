<?php
/**
 * Created by PhpStorm.
 * User: zhusunjing
 * Date: 2016/11/21
 * Time: 10:50
 */
namespace app\api\controller;
use app\common\model\Area;
use think\Controller;
use think\Validate;
use think\Request;
class Address extends Controller{
	/**
	 * 获取省市区三级联动数据
	 */

	public function areaList(){
		$parentId = $this->request->param('parent_id','1','intval');
		$deep = $this->request->param('deep','0','intval');
		$modelArea = new Area();
		$areaList = $modelArea->getAreaArrayForJson($parentId,$deep+1);
		$this->success('', '', $areaList[$parentId]);
	}

	/**
	 * 通过传过来的省市id
	 *
	 * 查询省市区详细信息
	 */
	public function areaInfo(){
		$provinceId = $this->request->param('province_id','','intval');
		$cityId = $this->request->param('city_id','','intval');
		$areaId = $this->request->param('area_id','','intval');
		$modelArea = new Area();
		$areaList = $modelArea->getAreaNames();
		$data = array(
			'province'=>$areaList[$provinceId],
			'city' =>$areaList[$cityId],
			'area' =>$areaList[$areaId],
		);
		echo json_encode($data);
	}
	/**
	 * 通过传过来的省id，市id
	 *
	 * 查询省市的名称
	 */
	public function areaName(){
		$provinceId = $this->request->param('province_id','','intval');
		$cityId = $this->request->param('city_id','','intval');
		$modelArea = new Area();
		$province_name = $modelArea->getAreaList(array('area_id'=>$provinceId),'area_name');
		$city_name= $modelArea->getAreaList(array('area_id'=>$cityId),'area_name');
		$data=array(
			'province_name'=>$province_name[0]['area_name'],
			'city_name'=>$city_name[0]['area_name']
		);
		$this->success('','',$data);
	}
}
