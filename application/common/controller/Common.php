<?php
/**
 * Created by PhpStorm.
 * Project: code
 * User: Administrator
 * Date: 2016/11/9
 * Time: 9:50
 * Author: ss.wu
 */
namespace app\common\controller;

use think\Controller;
use think\Db;

class Common extends Controller
{

    public function upload()
    {
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit; // finish preflight CORS requests here
        }
        if ( !empty($_REQUEST[ 'debug' ]) ) {
            $random = rand(0, intval($_REQUEST[ 'debug' ]) );
            if ( $random === 0 ) {
                header("HTTP/1.0 500 Internal Server Error");
                exit;
            }
        }


        // 5 minutes execution time
        @set_time_limit(5 * 60);
        // Uncomment this one to fake upload time
        usleep(5000);
        // Settings

        $targetDir = './Upload'.DIRECTORY_SEPARATOR.'file_material_tmp';
        $uploadDir = './Upload'.DIRECTORY_SEPARATOR.'file_material'.DIRECTORY_SEPARATOR.date('Ymd');
        $cleanupTargetDir = true; // Remove old files
        $maxFileAge = 5 * 3600; // Temp file age in seconds

        // Create target dir
        if (!file_exists($targetDir)) {
            @mkdir($targetDir,0777,true);
        }
        // Create target dir
        if (!file_exists($uploadDir)) {
            @mkdir($uploadDir,0777,true);
        }
        // Get a file name
        if (isset($_REQUEST["name"])) {
            $fileName = $_REQUEST["name"];
        } elseif (!empty($_FILES)) {
            $fileName = $_FILES["file"]["name"];
        } else {
            $fileName = uniqid("file_");
        }

        $fileType = pathinfo($fileName);
        //判断文件类型
        $result = false;
        $sizeLimit = 0;
        $res = Db::name('upload_config')->select();
        if($res && is_array($res)){
            foreach($res as $k=>$v){
                if(in_array($fileType['extension'],explode(',',$v['format']))){
                    $result = true;
                    $sizeLimit = $v['size'];
                    break;
                }
            }
        }
        if(!$result){
            die(json_encode(array('success'=>false,'error'=>array('code'=>'106','message'=>'错误的文件类型'))));
        }

        $oldName = $fileName;
        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        // Chunking might be enabled
        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 1;
        // Remove old temp files
        if ($cleanupTargetDir) {
            if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory111."}, "id" : "id"}');
            }
            while (($file = readdir($dir)) !== false) {
                $tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;
                // If temp file is current file proceed to the next
                if ($tmpfilePath == "{$filePath}_{$chunk}.part" || $tmpfilePath == "{$filePath}_{$chunk}.parttmp") {
                    continue;
                }
                // Remove temp file if it is older than the max age and is not the current file
                if (preg_match('/\.(part|parttmp)$/', $file) && (@filemtime($tmpfilePath) < time() - $maxFileAge)) {
                    @unlink($tmpfilePath);
                }
            }
            closedir($dir);
        }

        // Open temp file
        if (!$out = @fopen("{$filePath}_{$chunk}.parttmp", "wb")) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream222."}, "id" : "id"}');
        }

        if (!empty($_FILES)) {
            if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file333."}, "id" : "id"}');
            }
            // Read binary input stream and append it to temp file
            if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream444."}, "id" : "id"}');
            }
        } else {
            if (!$in = @fopen("php://input", "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream555."}, "id" : "id"}');
            }
        }
        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }
        @fclose($out);
        @fclose($in);
        rename("{$filePath}_{$chunk}.parttmp", "{$filePath}_{$chunk}.part");
        $index = 0;
        $done = true;
        for( $index = 0; $index < $chunks; $index++ ) {
            if ( !file_exists("{$filePath}_{$index}.part") ) {
                $done = false;
                break;
            }
        }

        if ( $done ) {
            $pathInfo = pathinfo($fileName);
            $hashStr = substr(md5($pathInfo['basename']),8,16);
            $hashName = '_'.time() . $hashStr . '.' .$pathInfo['extension'];
            $uploadPath = $uploadDir . DIRECTORY_SEPARATOR .$hashName;

            if (!$out = @fopen($uploadPath, "wb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream666."}, "id" : "id"}');
            }
            if ( flock($out, LOCK_EX) ) {
                for( $index = 0; $index < $chunks; $index++ ) {
                    if (!$in = @fopen("{$filePath}_{$index}.part", "rb")) {
                        break;
                    }
                    while ($buff = fread($in, 4096)) {
                        fwrite($out, $buff);
                    }
                    @fclose($in);
                    @unlink("{$filePath}_{$index}.part");
                }
                flock($out, LOCK_UN);
            }
            @fclose($out);

            //判断上传文件的大小
            $fileSize = intval(filesize($uploadPath)/1024);
            if($fileSize > $sizeLimit){
                @unlink($uploadPath);
                die(json_encode(array('success'=>false,'error'=>array('code'=>'107','message'=>'上传文件大小超过限制','size'=>$fileSize))));

            }

            $response = [
                'success'=>true,
                'oldName'=>$oldName,
                'filePath'=>$uploadPath,
                'fileSize'=>$fileSize,
                'fileSuffixes'=>$pathInfo['extension'],
            ];

            die(json_encode($response));
        }

        // Return Success JSON-RPC response
        die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');


    }
}
