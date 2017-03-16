<?php
namespace app\index\controller;

class Index extends Common
{
    /**
     * index页面显示
     */
    public function index()
    {
        return $this->fetch();
    }

    /**
     * 上传文件，图片
     */
    public function uploadFile(){
        $this->upload();
    }

    

    /**
     * 删除图片
     */
    public function deleteFile(){
        $this->deleteImage();
    }
}
