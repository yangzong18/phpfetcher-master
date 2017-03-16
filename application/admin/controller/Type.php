<?php
/**
 * 商品类型管理
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang <ljl6907603@sina.cn> at 2016-11-14 9:57
 */
namespace app\admin\controller;
use app\common\controller\Auth;
use app\admin\model\Features;
use app\admin\model\FeaturesValue;
use think\Db;

class Type extends Auth
{
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
    } 

    /**
     * 菜单显示页
     */
	public function index() {
        $where = array( 'is_delete' => 0 );
        $datas = Db::name('type')->where( $where )->paginate();
        $this->assign("datas", $datas);
        return $this->fetch();
	}

	/**
     * 添加菜单页
     */
    public function add() {
        //规格查询
        $featuresModel = new Features();
        $this->assign('specificationsList', $featuresModel->specifications());
        return $this->fetch();
    }
    
    /**
     * 添加菜单方法
     */
    public function addPost() {
        $param = $this->request->param();
        if ( !isset( $param['type_name'] ) || trim( $param['type_name'] ) == '' ) {
            $this->error('类型名称不能为空');
        }
        $featuresModel      = new Features();
        $featuresValueModel = new FeaturesValue();
        //时间搓
        $time = time();
        //开启事务
        Db::startTrans();
        //类型添加
        $data   = array(
            'type_name' => $param['type_name'],
            'type_sort' => is_numeric($param['sort']) ? $param['sort'] : 0,
        );
        $typeId = Db::name('type')->insertGetId( $data );
        if ( !$typeId ) {
            Db::rollback();
            $this->error('类型插入失败');
        }
        //记录属性是否显示
        $showAttribute = array();
        //判断规格是否存在
        if ( isset( $param['type_specifications'] ) && count( $param['type_specifications'] ) > 0 ) {
            foreach ($param['type_specifications'] as $key => $specifications) {
                array_push($showAttribute, array(
                    'type_id'    => $typeId,
                    'feature_id' => $specifications,
                    'type'       => 1,
                    'visual'     => 0
                ));
            }
        }
        //如果有设置属性,则进行属性记录
        if ( isset( $param['attribute_name'] ) && isset( $param['attribute_value'] ) && count( $param['attribute_name'] ) > 0 ) {
            $attributeData = array();
            foreach ($param['attribute_name'] as $key => $name) {

                $data = array(
                    'attribute_name' => $name,
                    'sort'           => $param['attribute_sort'][$key],
                    'feature_created_at' => $time
                );
                //进行属性插入
                $result = $featuresModel->isUpdate(false)->data( $data )->save();

                //如果插入失败
                if ( !$result ) {
                    Db::rollback();
                    $this->error('属性插入失败');
                }

                //进行属性值的添加
                $attributeValueList = explode(',', $param['attribute_value'][$key]);
                foreach ($attributeValueList as $tag => $attributeValue) {
                    array_push($attributeData, array(
                        'feature_id'    => $featuresModel->feature_id,
                        'feature_value' => $attributeValue,
                        'sort'          => $tag,
                        'created_by'    => $this->user['member_id'],
                        'created_at'    => $time
                    ));
                }
                
                //将改属性是否显示记录下来
                array_push($showAttribute, array(
                    'type_id'    => $typeId,
                    'feature_id' => $featuresModel->feature_id,
                    'type'       => 2,
                    'visual'       => $param['attribute_show'][$key]
                ));
            }
            //进行属性值的添加
            $result = $featuresValueModel->saveAll( $attributeData );
            //如果属性值插入失败，则回滚
            if ( !$result ) {
                Db::rollback();
                $this->error('属性值插入失败');
            }
        }


        //如果有属性规格和类型的关联，则插入
        if ( count( $showAttribute ) > 0  ) {
            $result = Db::name('type_feature')->insertAll( $showAttribute );
            if ( !$result ) {
                 Db::rollback();
                $this->error('规格属性与类型关联失败');
            }
        }
        // 提交事务
        Db::commit();
        $this->success('添加成功', 'index');
    }


    /**
     *  编辑菜单
     */
    public function edit() {
        $typeId = $this->request->param('id');
        //类型查询
        $type   = Db::name('type')->where('type_id', $typeId)->find();
        //查询类型与特征的关联
        $typeFeature   = Db::name('type_feature')->where('type_id', $typeId)->select();
        //规格查询
        $featuresModel      = new Features();
        $featuresValueModel = new FeaturesValue();
        $specificationsList = $featuresModel->specifications();
        //剔除属性
        $attributeIdList = array();
        foreach ($typeFeature as $key => $feature) {
            //如果是属性的话
            if ( $feature['type'] == 2 ) {
                array_push($attributeIdList, $feature['feature_id']);
                //unset($typeFeature[$key]);
            }
        }
        foreach ($specificationsList as $key => $specifications) {
            $specifications['select'] = 0;
            foreach ($typeFeature as $tag => $feature) {
                if ( $specifications['feature_id'] == $feature['feature_id'] ) {
                    $specifications['select'] = 1;
                    unset($typeFeature[$tag]);
                }
            }
            $specificationsList[$key] = $specifications;
        }
        //属性和属性值查询
        $attributeList = array();
        if ( count( $attributeIdList ) > 0 ) {
            //属性查询
            $where = array( 'feature_id'=> array('in', $attributeIdList) );
            $attributeList = $featuresModel->field('feature_id, attribute_name, sort')->where( $where )->order('sort asc')->select();
            //属性值查询
            $attributeValueList = $featuresValueModel->field('feature_id, feature_value, sort')->where( $where )->order('sort asc')->select();
            //进行数据拼接
            foreach ($attributeList as $key => $attribute) {
                $valueList = array();
                foreach ($attributeValueList as $tag => $attributeValue) {
                    if ( $attribute['feature_id'] == $attributeValue['feature_id'] ) {
                        array_push($valueList, $attributeValue['feature_value']);
                        unset($attributeValueList[$tag]);
                    }
                }
                $attribute['attribute_value'] = join( ',',  $valueList); 
                //拼接改属性是显示还是隐藏
                $attribute['show'] = 0;
                foreach ($typeFeature as $tag => $feature) {
                    if ( $attribute['feature_id'] == $feature['feature_id'] && $feature['visual'] == 1 ) {
                        $attribute['show'] = 1;
                        unset($typeFeature[$tag]);
                        break 1;
                    }
                }
                $attributeList[$key] = $attribute;
            }
        }
        //标记规格的选定状态
        $this->assign('data', $type);
        $this->assign('attributeList', $attributeList);
        $this->assign('specificationsList', $specificationsList);
        return $this->fetch();
    }

   

    /**
     * 编辑菜单方法
     */
    public function editPost() {
        $param  = $this->request->param();
        if ( !isset( $param['type_id'] ) || !is_numeric( $param['type_id'] ) || 0>=$param['type_id'] ) {
            $this->error('错误的类型ID');
        }
        if ( !isset( $param['type_name'] ) || trim( $param['type_name'] ) == '' ) {
            $this->error('类型名称不能为空');
        }
        if ( !isset( $param['sort'] ) || !is_numeric( $param['sort'] ) ) {
            $param['sort'] = 0;
        }
        $time = time();
        //开启事务
        Db::startTrans();
        //类型编辑
        $data   = array(
            'type_name' => $param['type_name'],
            'type_sort' => $param['sort'],
        );
        $type   = Db::name('type')->where('type_id', $param['type_id'])->update( $data );

        $featuresModel      = new Features();
        $featuresValueModel = new FeaturesValue();
        //属性规格与类型的关联
        $showAttribute = array();
        //判断规格是否存在
        if ( isset( $param['type_specifications'] ) && count( $param['type_specifications'] ) > 0 ) {
            //进行规格关联
            foreach ($param['type_specifications'] as $key => $specifications) {
                array_push($showAttribute, array(
                    'type_id'    => $param['type_id'],
                    'feature_id' => $specifications,
                    'type'       => 1,
                    'visual'     => 0
                ));
            }
            //删除之前的规格关联
            $where = array('type_id' => $param['type_id'], 'type'=>1);
            Db::name('type_feature')->where( $where )->delete();
        }

        //如果有设置属性,则进行属性记录
        if ( isset( $param['attribute_name'] ) && isset( $param['attribute_value'] ) && count( $param['attribute_name'] ) > 0 ) {
            $attributeData = array();
            foreach ($param['attribute_name'] as $key => $name) {
                $data = array(
                    'attribute_name' => $name,
                    'sort'           => $param['attribute_sort'][$key],
                    'feature_created_at' => $time
                );
                //进行属性插入
                $result = $featuresModel->isUpdate(false)->data( $data )->save();

                //如果插入失败
                if ( !$result ) {
                    Db::rollback();
                    $this->error('属性插入失败');
                }

                //进行属性值的添加
                $attributeValueList = explode(',', $param['attribute_value'][$key]);
                foreach ($attributeValueList as $tag => $attributeValue) {
                    array_push($attributeData, array(
                        'feature_id'    => $featuresModel->feature_id,
                        'feature_value' => $attributeValue,
                        'sort'          => $tag,
                        'created_by'    => $this->user['member_id'],
                        'created_at'    => $time
                    ));
                }
                
                //将改属性是否显示记录下来
                array_push($showAttribute, array(
                    'type_id'    => $param['type_id'],
                    'feature_id' => $featuresModel->feature_id,
                    'type'       => 2,
                    'visual'       => $param['attribute_show'][$key]
                ));
            }
            //进行属性值的添加
            $result = $featuresValueModel->saveAll( $attributeData );
            //如果属性值插入失败，则回滚
            if ( !$result ) {
                Db::rollback();
                $this->error('属性值插入失败');
            }
        }

        //如果有属性规格和类型的关联，则插入
        if ( count( $showAttribute ) > 0  ) {
            $result = Db::name('type_feature')->insertAll( $showAttribute );
            if ( !$result ) {
                Db::rollback();
                $this->error('规格属性与类型关联失败');
            }
        }
        // 提交事务
        Db::commit();
        $this->success('编辑成功', 'index');
    }

    /**
     * 删除菜单方法
     */
    public function delete() {
        $typeId  = $this->request->param('id');
        $where   = array( 'type_id' => array( 'in', $typeId ) );
        if ( count( $typeId ) > 0 && Db::name('type')->where($where)->update(array( 'is_delete' =>1 ) ) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
    
    /**
     * 批量删除规格方法
     */
    public function deleteChecked() {
        $typeId  = explode(',', $this->request->param('id_list'));
        $where      = array( 'type_id' => array( 'in', $typeId ) );
        if ( count( $typeId ) > 0 && Db::name('type')->where($where)->update(array( 'is_delete' =>1 ) ) ) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }


}
