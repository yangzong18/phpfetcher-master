<?php
namespace app\seller\controller;
use app\common\controller\Auth;
use app\common\logic\Feature;

class GoodsCategory extends Auth
{
    public function _initialize(){
        parent::_initialize();
    }

    public function lists()
    {

        return $this->fetch();
    }

    public function add()
    {

        return $this->fetch();
    }

    /**
     * 通过分类ID，获取所关联的规格
     */
    public function specifications() {
        $categoryId = $this->request->param('category_id');
        $featureModel = new Feature();
        $datas        = $featureModel->specifications($categoryId, $this->user['store_id']);
        $this->success('成功', '', $datas);
    }

    /**
     * 通过分类ID，获取所关联的属性和属性值
     */
    public function attribute() {
        $categoryId = $this->request->param('category_id');
        $featureModel = new Feature();
        $datas        = $featureModel->attribute( $categoryId );
        $this->success('成功', '', $datas);
    }
}
