<?php
/**
 * Created by PhpStorm.
 * Project: code
 * User: Administrator
 * Date: 2016/11/14
 * Time: 10:34
 * Author: ss.wu
 */
namespace app\admin\controller;

use app\common\controller\Auth;
use app\admin\model\GoodsCategory as GoodsCategorys;
use app\common\model\Attachment;
use think\Controller;
use think\Db;
use think\Model;
use Util\Tools;

class GoodsCategory extends Auth
{




    /**
     * 分类列表页面
     */
    public function lists(){

        //实例化商品分类对象
        $model = new GoodsCategorys();
        $where = array( 'parent_id' => 0, 'is_delete' => 0 );
        //获取顶级分类
        $data = $model->where( $where )->order('sort asc')->select();
        $this->assign('category',$data);

        return $this->fetch();
    }

    /**
     * 添加分类页面
     */
    public function add()
    {
        $model = new GoodsCategorys();  //实例化商品分类对象

        $ids = $this->request->param('ids');
        //暂时使用两级分类
        $data = $model->order(array('sort' => 'asc'))->select();
        $temp = [];
        $end  = [];
        if(is_array($data)){
            foreach($data as $k=>$v){
                if($v['parent_id'] == '0'){
                    $v['level'] = '';
                    $temp[] = $v;
                    unset($data[$k]);
                }
            }
            while(count($temp) > 0){
                $one = array_pop($temp);

                array_push($end,$one);
                foreach($data as $key=>$val){
                    if($one['category_id'] == $val['parent_id']){
                        $val['level'] = '&nbsp;&nbsp;';
                        array_push($end,$val);
                    }
                }
            }
        }

        $type = Db::name('type')->where(['is_delete'=>0])->select();
        if($type){
            $this->assign('types',$type);
        }

        if($ids){
            $this->assign('ids',$ids);
        }
        $this->assign('category',$end);
       return $this->fetch();
    }

    /**
     * 执行分类添加
     */
    public function doAdd(){

        $category_id = Tools::guid();
        $name = $this->request->param('name');
        $type = $this->request->param('type');
        //$category_image = $this->request->param('image');
        $category_image = '0';
        $parent_id = $this->request->param('pid');
        $sort = $this->request->param('sort','','intval');
        $category_description = $this->request->param('description');
        $store_id = session('store_id');
        $status = 1;
        $is_delete = 0;
        $created_at = time();

        if(empty($name)){
            $this->error('分类名称不能为空');
        }
        if(empty($sort)){
            $this->error('分类排序不能为空');
        }
        if(empty($type)){
            $this->error('所属类型不能为空');
        }

        $model = new GoodsCategorys(); //实例化商品分类对象

        //组织数据
        $model->data([
            'category_id'         =>  $category_id,
            'name'                =>  $name,
            'type_id'             =>  $type,
            'category_image'      =>  $category_image,
            'parent_id'           =>  $parent_id,
            'sort'                =>  $sort,
            'category_description'=>  $category_description,
//            'store_id'          =>  $store_id,
            'store_id'            =>  1,
            'status'              =>  $status,
            'is_delete'           =>  $is_delete,
            'created_at'          =>  $created_at,

        ]);

        if($model->save()){

            //添加成功后将图片添加到附件表
            $attachment = new Attachment();
            $attachment->data([
                'attachment_url'     =>  $model->category_image,
                'business_sn'        =>  'goods_category',
                'business_id'        =>  $model->category_id,
                'created_at'         =>  time(),
                'sort'               =>  0,
                'is_delete'          =>  0
            ]);
            $attachment->save();
            //更新前端缓存
            $model->flushCache();
            $this->success('添加成功', 'lists');
        }else{
            $this->error('删除失败');
        }

    }

    /**
     *获取子分类
     */
    public function getChildCate(){

        $ids = $this->request->param('ids');
        if(empty($ids)){
            $this->error('请输入正确的ID');
        }
        $model = new GoodsCategorys();  //实例化商品分类对象
        $res = $model->where('parent_id',$ids)->where(array('is_delete'=>0))->order(array('sort' => 'asc'))->select();
        if($res){
            $this->success('成功','',$res);
        }else{
            $this->success('','','');
        }

    }


    /**
     * 编辑页面
     */
    public function edit(){

        $ids = $this->request->param('category_id');
        if(empty($ids)){
            $this->error('参数传入错误');
        }
        $model = new GoodsCategorys();   //实例化商品分类对象
        $res = $model->where('category_id',$ids)->order(array('sort' => 'asc'))->find();

        if(empty($res)){
            $this->error('未找到要编辑的数据');
        }

        $data = $model->order(array('sort' => 'asc'))->select();
        $temp = [];
        $end  = [];
        if(is_array($data)){
            foreach($data as $k=>$v){
                if($v['parent_id'] == '0'){
                    $v['level'] = '';
                    $temp[] = $v;
                    unset($data[$k]);
                }
            }
            while(count($temp) > 0){
                $one = array_pop($temp);

                array_push($end,$one);
                foreach($data as $key=>$val){
                    if($one['category_id'] == $val['parent_id']){
                        $val['level'] = '&nbsp;&nbsp;';
                        array_push($end,$val);
                    }
                }
            }
        }

        $type = Db::name('type')->where(['is_delete'=>0])->order(array('type_sort' => 'asc'))->select();
        if($type){
            $this->assign('types',$type);
        }

        $this->assign('category',$end);
        $this->assign('data',$res);
        return $this->fetch();
    }


    /**
     * 执行编辑分类
     */
    public function doEdit(){
        $category_id = $this->request->param('category_id');
        $name = $this->request->param('name');
        $type = $this->request->param('type');
        $type_associated = $this->request->param('type_associated'); //类型关联到子类
        //$category_image = $this->request->param('image');
        $category_image = '0';
//        $parent_id = $this->request->param('pid');
        $sort = $this->request->param('sort','','intval');
        $category_description = $this->request->param('description');
        $store_id = $this->user['store_id'];
        $status = 1;
        $is_delete = 0;
        $created_at = time();

        if(empty($name)){
            $this->error('分类名称不能为空');
        }
        if(empty($sort)){
            $this->error('分类排序不能为空');
        }
        if(empty($type)){
            $this->error('所属类型不能为空');
        }


        $model = new GoodsCategorys();  //实例化商品分类对象
        $old = $model->where('category_id',$category_id)->find();
        $delPic = false;  //用来判断是否需要更新附件表的图片
        if($old['category_image'] != $category_image){
            @unlink($old['category_image']);
            $delPic = true;
        }


        //组织数据
        $data = [
            'category_id'         =>  $category_id,
            'name'                =>  $name,
            'type_id'             =>  $type,
            'type_associated'     =>  empty($type_associated)?0:$type_associated,
            'category_image'      =>  $category_image,
//            'parent_id'         =>  $parent_id,
            'sort'                =>  $sort,
            'category_description'=>  $category_description,
            'store_id'            =>  $store_id,
            'status'              =>  $status,
            'is_delete'           =>  $is_delete,
            'created_at'          =>  $created_at,

        ];
        // 启动事务
        Db::startTrans();

        try{

        $res1 = Db::name('goods_category')->where("category_id",$category_id)->update($data);

        if($res1){
            if($type_associated){
                $res = $this->getSonCategoryId($category_id);
                if(is_array($res)){
                    $str = implode("','",$res);
                    Db::name('goods_category')->where(" category_id in ( '".$str."' )")->update(['type_id' => $type]);
                }
            }
            //修改成功后再附件表的图片
            if($delPic){
                Db::name('attachment')->insert(['attachment_url'=>$category_image,'business_id'=>$category_id]);
            }
        }
            // 提交事务
            Db::commit();
        }catch (\Exception $e){
            // 回滚事务
            Db::rollback();
            $this->error('分类编辑失败');
        }
        //更新前端缓存
        $model->flushCache();
     $this->success('编辑成功', 'lists');

    }


    /**
     * 根据分类ID获取此ID下的所有子类ID
     * @param $id string 分类ID
     * @return array
     */
    public function getSonCategoryId($id){

        $result = Db::name('goods_category')->where(array('is_delete'=>0))->order(array('sort' => 'asc'))->select();
        if(is_array($result)){
            $end = array();
            $temp = array();
            foreach($result as $k=>$v){
                if($v['parent_id'] == $id){
                    $temp[] = $v;
                }
            }

            while(count($temp)>0){
                $one = array_pop($temp);
                $end[] = $one['category_id'];
                foreach($result as $key=>$val){
                    if($val['parent_id'] == $one['category_id']){
                        array_push($temp,$val);
                    }
                }
            }

            return $end;
        }
    }



    /**
     * 删除操作
     */
    public function delete()
    {
        $ids = $this->request->param('category_id');
        if(empty($ids)) $this->error('参数传入错误');
        $model = new GoodsCategorys();  //实例化商品分类对象
        $rt = $model->where(array('parent_id'=>array('in',$ids),'is_delete'=>0))->select();
        $rt = Model::getResultByFild($rt);
        if(count($rt)>0){
            $this->error('该分类下存在子分类，请先删除子分类再删除此分类');
            exit;
        }

        $result = $model->where(array('category_id'=>array('in',$ids)))->select();
        $result = Model::getResultByFild($result);
        if(count($result)<=0) {
            $this->error('数据不存在');
            exit;
        }else{
            foreach($result as $rs){
                if(file_exists($rs['category_image'])) @unlink($rs['category_image']);
            }
            $res = $model->where(array('category_id'=>array('in',$ids)))->update(['is_delete'=>1]);

            if($res){
                //更新前端缓存
                $model->flushCache();
                $this->success('删除成功');
            }else{
                $this->error('删除失败');
            }
        }
    }
}
