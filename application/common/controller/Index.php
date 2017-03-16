<?php
namespace app\common\controller;

class Index extends Common
{
    public function index()
    {
        return $this->fetch();
    }

    public function uploadFile(){
        $this->upload();
    }
}
