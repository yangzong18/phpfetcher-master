<?php
/**
 * create by: PhpStorm
 * desc:整装联盟
 * author:yangmeng
 */
namespace app\shop\controller;
use app\common\controller\Shop;
use think\Db;

class Union extends Shop{
    /**
     * 整装联盟列表
     */
    public function index(){
        //取联盟分类
        $category = Db::name('union_cate')->where(array('is_delete'=>0))->order('px asc')->select();
        $unionInfo = array();
        foreach($category as $k=>$v){
            $where = array('is_delete'=>0,'cate_id'=>$v['cate_id']);
            $unionInfo[$k]['cate_name'] = $v['cate_name'];
            $unionInfo[$k]['info'] = Db::name('union')->field('id,union_name,log_pic,brand')->where($where)->order('px asc')->select();
        }

        $this->assign('unionInfo',$unionInfo);
        return $this->fetch();
    }

    /**
     * 整装联盟详情
     */
    public function detail(){
        //联盟详情id
        $id = $this->request->param('id', '', 'intval');
        if(!$id) $this->error('参数错误');

        //联盟详情信息
        $where = array('id'=>$id,'is_delete'=>0);
        $unionInfo = Db::name('union')->where($where)->find();
        if(!$unionInfo) $this->error('联盟详情信息错误');

        $this->assign('unionInfo',$unionInfo);
        return $this->fetch();
    }
}

