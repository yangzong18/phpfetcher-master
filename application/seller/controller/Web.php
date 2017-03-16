<?php
namespace app\seller\controller;
use app\common\controller\Auth;

//这里是模板开发区域
class Web extends Auth
{
    public function _initialize(){
        parent::_initialize();
    }

    //出售中的商品
    public function goods_Of_Sale()
    {
        return $this->fetch();
    }
    //demo
    public function demo()
    {
        return $this->fetch();
    }

    public function web2()
    {

        return $this->fetch();
    }

    public function photos()
    {

        return $this->fetch();
    }
}
