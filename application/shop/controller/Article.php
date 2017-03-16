<?php
/**
 * 文章页面
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: laijunliang at 2016/12/19
 */
namespace app\shop\controller;

use app\common\controller\Shop;
use app\shop\model\Article as Articles;
use think\Db;

class Article extends Shop {

    /**
     * 文章
     */
	public function index(){
		die('文章详情页');
		return $this->fetch();
	}

    /**
     * 通过ID获取一条文章信息
     */
    public function detail(){
        $id = $this->request->param('id');
        if(!$id){
            $this->error('请传入文章ID');
        }
        $province= $this->request->param('province');
        $city= $this->request->param('city');
        if(!empty($province)&&!empty($city)){
            $this->assign('province',$province);
            $this->assign('city',$city);
        }
        $article = new Articles();
        $where = array();
        $where['id'] = $id;
        $result = $article->getArticle($where);
        if(!$result){
            $this->error('未找到该条文章信息');
        }
        $cate = Db::name('article_category')->where(array('id'=>$result['article_category_id']))->find();
        if($cate){
            $this->assign('cate',$cate['name']);
            $cateParent = Db::name('article_category')->where(array('id'=>$cate['parent_id']))->find();
            if($cateParent){
                $this->assign('cateParent',$cateParent['name']);
            }
        }
        $result['content'] = htmlspecialchars_decode($result['content']);
        $this->assign('article',$result);
        return $this->fetch();
    }
}
