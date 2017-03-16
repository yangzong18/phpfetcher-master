<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
//error_reporting(0);

//定义站点域名
define('SITE_HOST',$_SERVER['HTTP_HOST']);
//带HTTP的域名
define('HTTP_SITE_NOPATH_HOST',"http://".SITE_HOST);
//如果是代理过来的，如；http://localhost.cacke.com/ec,则PATH_PRE是/ec
define('PATH_PRE','');
define('HTTP_SITE_HOST',"http://".SITE_HOST.PATH_PRE);
//因为ngix的图片请求，当图片请求不存在的时候也会转发到index.php
//所以在这里判断处理, 这个转发本来是应该由nginx处理的，但是考虑到
//本地环境，所以在这里加判断，进行转发，后期一定要靠nginx
if ( isset( $_SERVER['REQUEST_URI'] ) ) {
	$imageUri = $_SERVER['REQUEST_URI'];
	if ( strpos($imageUri, '/public/uploads/') === 0 ) {
		$_SERVER['PATH_INFO']   = '/index/index/thumb';
		$_GET['fid'] = $imageUri;
	}
}
// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');
//定义配置文件目录
define('CONF_PATH',__DIR__.'/../config/');
// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';
