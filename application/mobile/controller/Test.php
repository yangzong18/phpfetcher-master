<?php
namespace app\mobile\controller;
use think\Cache;
use think\Model;



class Test extends Mobile{

    public function index()
    {
        print_r('OK');
    }

    public function upload(){
        return $this->fetch();
    }
}