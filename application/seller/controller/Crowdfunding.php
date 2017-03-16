<?php
/**
 * 众筹商品
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/8  16:41
 */
namespace app\seller\controller;

use app\common\controller\Auth;
use app\common\logic\Task;
use app\seller\model\Attachment;
use app\seller\model\CrowdfundingGoods;
use app\seller\model\CrowdfundingGoodsExtra;
use think\Db;
use think\Exception;
use think\Validate;
use Util\Tools;

class Crowdfunding extends Auth
{

    /**
     * 验证规则
     * @var array
     */
    protected $rule = array(
        'name'          => 'require',
        'serialNumber'  => 'require',
        'totalPrice'    => 'require',
        'quotient'      => 'require|number|gt:0',
        'onePrice'      => 'require',
        'limit'         => 'require|number|gt:0',
        'startTime'     => 'require',
        'endTime'       => 'require',
    );

    /**
     * 验证消息
     * @var array
     */
    protected $message = array(
        'name.require'          => '商品名称不能为空',
        'serialNumber.require'  => '项目编号不能为空',
        'totalPrice.require'    => '众筹总价不能为空',
        'quotient.require'      => '众筹份额不能为空',
        'quotient.number'       => '众筹份额必为整数',
        'quotient.gt'           => '众筹份额为大于0整数',
        'onePrice.require'      => '每份价格不能为空',
        'limit.require'         => '最多可购买份额不能为空',
        'limit.number'          => '份额必为整数',
        'limit.gt'              => '份额为大于0整数',
        'startTime.require'     => '开始时间不能为空',
        'endTime.require'       => '结束时间不能为空',
    );

    /**
     * 添加商品页面
     */
    public function add(){

        return $this->fetch();
    }


    /**
     * 执行众筹商品添加
     */
    public function doAdd(){

        //对传递参数进行验证
        $param = $this->request->param();
        $validate = new Validate($this->rule, $this->message);
        try{
        if ( !$validate->check( $param ) ) {
            throw new Exception( $validate->getError() );
        }

        if(!isset($param['mainImage'])){
            throw new Exception('主图不能为空');
        }
        if(!isset($param['priceImage'])){
            throw new Exception('价格走势图不能为空');
        }
        if(!isset($param['slaveImage'])){
            throw new Exception('附图不能为空');
        }


        //项目时间验证
        $startTime = strtotime($param['startTime']);
        $endTime = strtotime($param['endTime']);
        $oneDay = 60*60*24;
        $sevenDay = 60*60*24*7;
        if($startTime < time()){
            throw new Exception('开始时间应该大于当前时间');
        }

        if($startTime > $endTime){
            throw new Exception('开始时间应该小于结束时间');
        }
        if($startTime-time() < $oneDay){
            throw new Exception('项目发布时间必须提前24小时');
        }
        if($startTime-time() > $sevenDay){
            throw new Exception('项目发布时间不能提前超过7天');
        }


        if(mb_strlen($param['name'],'utf-8') > 64){
            throw new Exception('商品名称最长应不超过64个汉字');
        }

        if(!isset($param['editorContent0'])){
            throw new Exception('商品说明不能为空');
        }
        if(!isset($param['editorContent1'])){
            throw new Exception('商品实拍不能为空');
        }
        if(!isset($param['editorContent2'])){
            throw new Exception('商品规格不能为空');
        }
        if(!isset($param['editorContent3'])){
            throw new Exception('材质说明不能为空');
        }
        if(!isset($param['editorContent4'])){
            throw new Exception('品质保证不能为空');
        }





            //组装商品数据
        $data = array(
            'name'                  =>      $param['name'],
            'serial_number'         =>      $param['serialNumber'],
            'specification'         =>      $param['specification'],
            'type'                  =>      $param['type'],
            'description'           =>      $param['editorContent0'],
            'total_price'           =>      $param['totalPrice'],
            'quotient'              =>      $param['quotient'],
            'one_price'             =>      $param['onePrice'],
            'limit'                 =>      $param['limit'],
            'start_at'              =>      $startTime,
            'end_at'                =>      $endTime,
            'image_main'            =>      $param['mainImage'],
            'image_price'           =>      $param['priceImage'],
            'is_self'               =>      $param['isSelf'],
            'freight'               =>      $param['freight'],
            'store_id'              =>      $this->user['store_id'],
            'store_name'            =>      $this->user['store_name'],
            'created_at'            =>      time(),
        );


        // 启动事务
        Db::startTrans();


            //实例化众筹商品模型
            $goodsModel = new CrowdfundingGoods();
            //添加众筹商品
            $goodsId = $goodsModel->addCrowdfundingGoods($data);

            //组装众筹商品扩展数据
            $extra = array(
                'crowdfunding_goods_id'         =>      $goodsId,
                'goods_real_shot'               =>      $param['editorContent1'],
                'goods_specifications'          =>      $param['editorContent2'],
                'goods_material_description'    =>      $param['editorContent3'],
                'goods_quality_assurance'       =>      $param['editorContent4'],
            );

            //实例化众筹商品扩展模型
            $goodsExtraModel = new CrowdfundingGoodsExtra();
            //添加实例化商品扩展
            $goodsExtraModel->addCrowdfundingGoodsExtra($extra);

            //实例化扩展模型
            $attachment = new Attachment();
            if($param['slaveImage']){
                foreach($param['slaveImage'] as $k=>$v){
                    $attach = array(
                        'attachment_url'        =>  $v,
                        'business_sn'           =>  'crowdfunding_goods',
                        'business_id'           =>  $goodsId,
                        'store_id'              =>  $this->user['store_id'],
                        'created_at'            =>  time(),
                    );

                    //循环添加
                    $attachment->addAttachment($attach);
                }
            }

            //提交事务
            Db::commit();

            //众筹到开始时间执行
            $key_start = 'crowdfunding_start_'.$goodsId;
            $method_start = '/crontab/task/dealCrowdfundingGoodsStart';
            $param_start = array('id'=>$goodsId);
            $time = $startTime-time();

            $task = new Task();
            $result_start = $task->addTask($key_start,$method_start,$time,$param_start);
            if(!$result_start){
                Db::rollback();
                throw new Exception('添加定时任务失败');
            }


        }catch (\Exception $e){
            //回滚事务
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('添加成功',url('seller/crowdfunding/lists'));

    }


    /**
     * 众筹商品列表
     */
    public function lists(){

        //实例化商品对象
        $goods = new CrowdfundingGoods();
        //组装搜索条件
        $where = array();
        $where['is_delete'] = 0;
        $name = $this->request->param('goods_name');
        $search_name =  $this->request->param('search_name');  //按名称为1 按编号为2
        if($name){
            if($search_name == 1){
                $where['name'] = array('like',"%".$name."%");
                $this->assign('name',$name);
                $this->assign('search_name',1);
            }elseif($search_name == 2){
                $where['serial_number'] =array('like',"%".$name."%");
                $this->assign('goods_id',$name);
                $this->assign('search_name',2);
            }
//            $where['name'] = array('like','%'.$name.'%');
        }
        //添加审核状态搜索
        $param = $this->request->param();
        if ( isset( $param['verify'] ) && in_array( $param['verify'] , array(0,1,3)) ) {
            $where['verify'] = $param['verify'];
        } else {
            $param['verify'] = 2;
        }

        //进行分页查询
        $list = $goods->where($where)->order('id desc')->paginate(10,'',[
            'query' => $this->request->param()
        ]);

        $name = $goods::$goodsStatus;
        if($list){
            foreach($list as $k=>$v){
                $list[$k]['state_name'] = $name[$v['state']];
            }
        }

        //指派数据到模板
        $this->assign('param',$where);
        $this->assign('datas',$list);
        $this->assign('param',$param);
        return $this->fetch();
    }


    /**
     * 编辑商品页面
     */
    public function edit(){
        $id = $this->request->param('id','','intval');
        if(!$id){
            $this->error('请传入要编辑商品ID');
        }

        //实例化众筹商品模型
        $goods = new CrowdfundingGoods();
        $res = $goods->getCrowdfundingGoods($id);
        if($res['state'] != 1){
            $this->error('众筹商品不是预热状态，不可编辑！');
        }

        //实例化众筹商品扩展模型
        $goodsExtra = new CrowdfundingGoodsExtra();
        //实例化附件模型
        $attachment = new Attachment();
        //获取商品附件
        $resAttach = $attachment->getAttachment(array('business_sn'=>'crowdfunding_goods','business_id'=>$id));
        //获取要编辑的商品
        $resGoods = $goods->getCrowdfundingGoods($id);
        //获取商品的扩展信息
        $resGoodsExtra = $goodsExtra->getCrowdfundingGoodsExtra($id);

        //将数据指派到模板
        $this->assign('goods',$resGoods);
        $this->assign('goodsExtra',$resGoodsExtra);
        $this->assign('attachment',$resAttach);

        //显示模板
        return $this->fetch();
    }


    /**
     * 执行编辑商品
     */
    public function doEdit(){

        //对传递参数进行验证
        $param = $this->request->param();
        $validate = new Validate($this->rule, $this->message);
        try{
        if ( !$validate->check( $param ) ) {
            throw new Exception( $validate->getError() );
        }

        //项目时间验证
        $startTime = strtotime($param['startTime']);
        $endTime = strtotime($param['endTime']);
        $oneDay = 60*60*24;
        $sevenDay = 60*60*24*7;

        if($startTime < time()){
            throw new Exception('开始时间应该大于当前时间');
        }

        if($startTime > $endTime){
            throw new Exception('开始时间应该小于结束时间');
        }
        if($startTime-time() < $oneDay){
            throw new Exception('项目发布时间必须提前24小时');
        }
        if($startTime-time() > $sevenDay){
            throw new Exception('项目发布时间不能提前超过7天');
        }

        //验证ID
        if(!$param['id']){
            throw new Exception('请传入编辑商品ID');
        }

        if(mb_strlen($param['name'],'utf-8') > 64){
            throw new Exception('商品名称最长应不超过64个汉字');
        }

        if(!isset($param['editorContent0'])){
            throw new Exception('商品说明不能为空');
        }
        if(!isset($param['editorContent1'])){
            throw new Exception('商品实拍不能为空');
        }
        if(!isset($param['editorContent2'])){
            throw new Exception('商品规格不能为空');
        }
        if(!isset($param['editorContent3'])){
            throw new Exception('材质说明不能为空');
        }
        if(!isset($param['editorContent4'])){
            throw new Exception('品质保证不能为空');
        }


            $goodsId = $param['id'];

        //组装商品数据
        $data = array(
            'id'                    =>      $param['id'],
            'name'                  =>      $param['name'],
            'serial_number'         =>      $param['serialNumber'],
            'specification'         =>      $param['specification'],
            'type'                  =>      $param['type'],
            'description'           =>      $param['editorContent0'],
            'total_price'           =>      $param['totalPrice'],
            'quotient'              =>      $param['quotient'],
            'one_price'             =>      $param['onePrice'],
            'limit'                 =>      $param['limit'],
            'start_at'              =>      $startTime,
            'end_at'                =>      $endTime,
            'image_main'            =>      $param['mainImage'],
            'image_price'           =>      $param['priceImage'],
            'is_self'               =>      $param['isSelf'],
            'freight'               =>      $param['freight'],
            'verify'                =>      3,  //编辑后将商品改为待审核状态
	        'state'		            =>      1,  //编辑后将商品状态改为预热中
        );


        // 启动事务
        Db::startTrans();


            //实例化众筹商品模型
            $goodsModel = new CrowdfundingGoods();
            //获取修改之前商品信息
            $oldGoods = $goodsModel->getCrowdfundingGoods($goodsId);
            if($oldGoods){
                if($oldGoods['image_main'] != $param['mainImage']){
                    if(file_exists($oldGoods['image_main'])){
                        unlink($oldGoods['image_main']);
                    }
                }
            }


            //修改众筹商品
            $where = array();
            $where['id'] = $goodsId;
            $goodsModel->saveCrowdfundingGoods($data,$where);

            //组装众筹商品扩展数据
            $extra = array(
                'goods_real_shot'               =>      $param['editorContent1'],
                'goods_specifications'          =>      $param['editorContent2'],
                'goods_material_description'    =>      $param['editorContent3'],
                'goods_quality_assurance'       =>      $param['editorContent4'],
            );

            //实例化众筹商品扩展模型
            $goodsExtraModel = new CrowdfundingGoodsExtra();
            //添加实例化商品扩展
            $whereExtra = array();
            $whereExtra['crowdfunding_goods_id'] = $goodsId;
            $goodsExtraModel->saveCrowdfundingGoodsExtra($extra,$whereExtra);

            //实例化扩展模型
            $attachment = new Attachment();
            $whereAttach = array();
            $whereAttach['business_sn'] = 'crowdfunding_goods';
            $whereAttach['business_id'] = $goodsId;
            $oldAttach = $attachment->getAttachment($whereAttach);
            $newAttachArr = array();
            $oldAttachArr = array();
            if($param['slaveImage']){
                foreach($param['slaveImage'] as $k=>$v){
                    $newAttachArr[] = $v;
                }
            }

            if($oldAttach){
                foreach($oldAttach as $k=>$v) {
                    $oldAttachArr[] = $v;
                }
            }

            //如果新修改的附件不在已存在的附件内，则添加
            if(count($newAttachArr) > 0){
                foreach($newAttachArr as $k=>$v){
                    if(!in_array($v,$oldAttachArr)){
                        $attach = array(
                            'attachment_url'        =>  $v,
                            'business_sn'           =>  'crowdfunding_goods',
                            'business_id'           =>  $goodsId,
                            'store_id'              =>  $this->user['store_id'],
                            'created_at'            =>  time(),
                        );

                        //循环添加
                        $attachment->addAttachment($attach);
                    }
                }
            }

            //如果已经存在的附件不在新修改的附件内，则删除
            if(count($oldAttach)){
                foreach($oldAttach as $k=>$v){
                    if(!in_array($v,$newAttachArr)){
                        $deleteaAttach = array();
                        $deleteaAttach['id'] = $v['id'];
                        $attachment->deleteAttachment($deleteaAttach);
                        if(file_exists($v)){
                            unlink($v);
                        }
                    }
                }
            }

            //提交事务
            Db::commit();

            //众筹到开始时间执行
            $key_start = 'crowdfunding_start_'.$goodsId;
            $method_start = '/crontab/task/dealCrowdfundingGoodsStart';
            $param_start = array('id'=>$goodsId);
            $time = $startTime-time();


            $task = new Task();
            $result_start = $task->addTask($key_start,$method_start,$time,$param_start);
            if(!$result_start){
                Db::rollback();
                throw new Exception('添加定时任务失败');
            }

        }catch (\Exception $e){
            //回滚事务
            Db::rollback();
            $this->error($e->getMessage());
        }



        $this->success('修改成功',url('seller/crowdfunding/lists'));
    }



    /**
     * 删除众筹商品
     */
    public function delete(){
        $id = $this->request->param('id','','intval');
        if(!$id){
            $this->error('请传入要删除商品ID');
        }

        $goods = new CrowdfundingGoods();
        $deleteWhere = array();
        $deleteWhere['id'] = $id;
        $res = $goods->deleteCrowdfundingGoods($deleteWhere);

        if($res){
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }

    }
	/**
	 * 删除众筹商品
	 */
	public function deleteChecked(){
		$typeId  = explode(',', $this->request->param('id_list'));
		$where      = array( 'id' => array( 'in', $typeId ) );
		$goods = new CrowdfundingGoods();
		$res = $goods->where($where)->update(array( 'is_delete' =>1 ));
		if($res){
			$this->success('删除成功');
		}else{
			$this->error('删除失败');
		}

	}


    /**
     * 商品审核
     */
    public function check(){
        $id = $this->request->param('id','','intval');
        $verify = $this->request->param('verify','','intval');
        if(!$id){
            $this->error('商品ID不能为空');
        }

        //实例化商品对象
        $goods = new CrowdfundingGoods();
        //组装查询条件
        $checkWhere = array();
        $checkWhere['id'] = $id;
        $data = array();
        $data['verify'] = intval($verify);
        //更改商品审核状态
        $res = $goods->checkCrowdfundingGoods($checkWhere,$data);
        if($res){
            Db::startTrans();

            try{

                //查询一条商品数据
                $goodsOne = $goods->getCrowdfundingGoods($id);
                if(!$goodsOne){
                    throw new Exception ('未找到商品数据');
                }
                $time_end = $goodsOne['end_at']-time()+15*60;   //等待多少秒后执行(众筹结束15分钟后执行)
                $time_start = $goodsOne['start_at'];
                //如果审核通过添加定时任务

                if( $verify == 1 ){

                    //众筹结束15分钟执行
                    $key_end = 'crowdfunding_end_'.$id;
                    $method_end = '/crontab/task/dealCrowdfundingGoods';
                    $param_end = array('id'=>$id);
                    $task = new Task();
                    $result_end = $task->addTask($key_end,$method_end,$time_end,$param_end);

                    if(!$result_end ){
                        Db::rollback();
                        throw new Exception('定时任务添加失败，状态更改失败！');
                    }
                }
                Db::commit();
            }catch (\Exception $e){
                Db::rollback();
                $this->error($e->getMessage());
            }
            $this->success('状态更改成功!');

        }else{
            $this->error('状态更新失败');
        }
    }


    /**
     * 更改众筹商品状态
     */
    public function changeLock(){
        $gid = $this->request->param('gid','','intval');
        $val = $this->request->param('val','','intval');
        $goods = new CrowdfundingGoods();
        $where = array();
        $where['id'] = $gid;
        $data = array();
        $data['is_pause'] = $val;

        $res = $goods->saveCrowdfundingGoods($data,$where);
        if($res){
            $this->success('更改锁定状态成功');
        }else{
            $this->error('更改锁定状态失败');
        }
    }





}
