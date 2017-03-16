<?php
/**
 * 日志管理
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 罗婷 at 2017/1/19
 */

namespace app\admin\controller;
use app\common\controller\Auth;

class FileLog extends Auth{

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * 日志列表
     *
     */
    public function index(){
        return $this->fetch();
    }


    //读取log下面的所有文件
    public function loopFile() {
        $rootPath = RUNTIME_PATH.'/log';
        $findPath = $this->request->post('path', '', 'trim');
        $path     = $rootPath.$findPath;
        $fileList = array();
        $packageList = array();
        if ($dh = opendir($path)) {
            while (($file = readdir($dh)) !== false) {
                if((is_dir($path."/".$file)) && $file!="." && $file!="..") {
                    array_push($packageList, array(
                            'fileName' => $file,
                            'filePath' => $findPath.'/'.$file,
                            'isDir'    => 1,
                            'icon'     => IMG_PATH.'package_icon.png'
                        )
                    );
                } else {
                    if ( $file!="." && $file!=".." ) {
                        array_push($fileList, array(
                                'fileName' => $file,
                                'filePath' => $findPath.'/'.$file,
                                'isDir'   => 0,
                                'icon'     => IMG_PATH.'file_icon.png'
                            )
                        );
                    }
                }
            }
            closedir($dh);
        }
        //将文件列表按照文件夹在前排序
        $logFileTempList = array_merge($packageList, $fileList);
        $unit = 4;
        $unitFile = array();
        $logFileList = array();
        $total = count( $logFileTempList );
        //将日志文件数据n个一组，方便前端显示
        foreach ($logFileTempList as $key => $logFile) {
            array_push($unitFile, $logFile);
            if ( ($key+1)%$unit == 0 ) {
                array_push($logFileList, $unitFile);
                $unitFile = array();
            }
            if ( ( $key+1 ) == $total && !empty($unitFile)) {
                array_push($logFileList, $unitFile);
            }
        }
        $this->success('成功','', $logFileList);
    }

    /**
     * 下载文件
     * @param 文件路径
     */
    function downloadFile( $file ){
        $rootPath = RUNTIME_PATH.'/log';
        $path     = $rootPath.$file;
        if( is_file($path) ){
            $length = filesize($path);
            $type = mime_content_type($path);
            $showName =  ltrim(strrchr($path,'/'),'/');
            header("Content-Description: File Transfer");
            header('Content-type: ' . $type);
            header('Content-Length:' . $length);
            if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) { //for IE
                header('Content-Disposition: attachment; filename="' . rawurlencode($showName) . '"');
            } else {
                header('Content-Disposition: attachment; filename="' . $showName . '"');
            }
            readfile($path);
            exit;
        } else {
            exit('文件不存在！');
        }
    }

}