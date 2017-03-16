<?php
/**
 * Created by PhpStorm.
 * Project: code
 * User: Administrator
 * Date: 2016/11/9
 * Time: 9:50
 * Author: ss.wu
 */
namespace app\index\controller;

use think\Controller;
use think\Db;
use think\Image;
use think\Config;

use think\exception\HttpResponseException;
use think\Response;

class Common extends Controller
{

    /**
     * 访问图片缩略图
     * url 为上传图片后返回的url
     * type 格式为 xx_xx  为缩略图的长度和宽度
     * eg:index/index/thumbImage?url=./Upload/file_material/20161111/_xxx.jpg&type=66_88
     */
    public function thumbImage(){
        $url = isset($_GET['url'])?$_GET['url']:'';
        $type = isset($_GET['type'])?$_GET['type']:'';

        if(is_file($url) && !empty($type)){
            $path = pathinfo($url);
            $urlNew = $path['dirname'].'/'.$path['filename'].'_'.$type.'.'.$path['extension'];
            if(!is_file($urlNew)){
                $typeNew = explode('_',$type);
                $open = Image::open($url);
                $open->thumb($typeNew[0], $typeNew[1],Image::THUMB_FILLED)->save($urlNew);
            }
            return $urlNew;
        }

        return $url;
    }

    /**
     * 图片上传
     */
    public function upload()
    {
        $message = [
            'success' => false,
            'error'   => ['code'=>106, 'message'=> '上传失败' ],
        ];
        //获取配置限制
        $config   = Db::name('upload_config')->where('type = 1')->find();
        $file = request()->file('file');
        $uploadPath = ROOT_PATH . 'entry'. DS . 'public' . DS . 'uploads';

        $result = $file->validate(['size' => intval( $config['size'] )*1024, 'ext'=> $config['format'] ])->move( $uploadPath );
        if($result){
            $message = [
                'success' => true,
                'oldName' => $result->getFilename(),
                'filePath'=> HTTP_SITE_HOST.'/public/uploads/'.$result->getSaveName(),
                'fileSize'=> $result->getSize(),
                'fileSuffixes' => $result->getExtension(),
            ];
        }else{
            $message['error']['message'] = $file->getError();
        }
        $response = Response::create($message, 'json')->header([]);
        throw new HttpResponseException($response);
    }

    /**
     * 图片缩放, 由index.php行转发，发现文件不存在，则跳转到该地址
     */
    public function thumb() {
        //获取到文件的路径, 如 /public/uploads/20170117/aaa.jpg@w100_h100.png;
        $filePath   = $this->request->param('fid');
        //如果不存在，则生成
        $docmentList = explode('@', $filePath);
        //定义图片根目录
        $root = ROOT_PATH . 'entry';
        //默认图片
        $defaultImage = $root.DS.'static'.DS.'shop'.DS.'images'.DS.'loading.jpg';
        //如果@后面的东西不存在，则显示默认图片
        if ( !isset( $docmentList[1] ) || !file_exists( $root.$docmentList[0] ) ) {
            $this->showThumb( $defaultImage, 'image/jpeg' );
        }
        $docmentList[1] = str_replace('.png', '', $docmentList[1]);
        //读取原文件
        $image = Image::open( $root.$docmentList[0] );
        //重新定义高和宽
        $height = 0;
        $width  = 0;
        $remark = explode('_', $docmentList[1]);
        foreach ($remark as $size) {
            //如果包含有宽度
            if ( strpos($size, 'w') === 0 ) {
                $width = intval( substr($size, 1) );
                continue;
            }
            //如果包含有高度
            if ( strpos($size, 'h') === 0 ) {
                $height = intval( substr($size, 1) );
                continue;
            }
        }
        //如果高和宽都没有，则显示原图
        if ( $height == 0 && $width == 0 ) {
            $this->showThumb( $defaultImage, 'image/jpeg' );
        }
        $type = Image::THUMB_FIXED;
        //如果高为0
        if ( $height == 0 ) {
            $height = ( $width/$image->width() )*$image->height();
            //$type = Image::THUMB_SCALING;
        }
        //如果宽度为0
        if ( $width == 0 ) {
            $width = ( $height/$image->height() )*$image->width();
            //$type = Image::THUMB_SCALING;
        }
        $image->thumb(intval($width), intval($height), $type)->save($root.$filePath, 'png');
        $this->showThumb( $root.$filePath, 'image/png' );
    }

    /**
     * 显示图片png
     */
    public function showThumb( $path, $type ) {
        $im = file_get_contents($path);
        $response = Response::create($im)->header([ 'Content-type' => $type ]);
        throw new HttpResponseException($response);
    }


    /**
     * 文件上传
     */
    public function file()
    {
        $message = [
            'success' => false,
            'error'   => ['code'=>106, 'message'=> '上传失败' ],
        ];
        //获取配置限制
        $config   = Db::name('upload_config')->where('type = 2')->find();
        $file = request()->file('file');
        $uploadPath = ROOT_PATH . 'entry'. DS . 'public' . DS . 'uploads';

        $result = $file->validate(['size' => intval( $config['size'] )*1024, 'ext'=> $config['format'] ])->move( $uploadPath );
        if($result){
            $message = [
                'success' => true,
                'oldName' => $result->getFilename(),
                'filePath'=> HTTP_SITE_HOST.'/public/uploads/'.$result->getSaveName(),
                'fileSize'=> $result->getSize(),
                'fileSuffixes' => $result->getExtension(),
            ];
        }else{
            $message['error']['message'] = $file->getError();
        }
        $response = Response::create($message, 'json')->header([]);
        throw new HttpResponseException($response);
    }
    
    
    /**
     * 删除图片
     */
    public function deleteImage(){
        $url = $this->request->param('delUrl');
        if($url){
            $res = parse_url($url);
            if($res['path']){
                $delPath = ".".ltrim($res['path'],".");
                if(file_exists($delPath)){
                    if(unlink($delPath)){
                        $this->success('删除成功！');
                    }else{
                        $this->error("删除失败！");
                    }
                }else{
                    $this->error('文件不存在！');
                }

            }
        }
    }
}
