<?php
/**
 * Created by PhpStorm.
 * Project: code
 * User: Administrator
 * Date: 2016/11/9
 * Time: 14:33
 * Author: ss.wu
 */
define('STATIC_COMMON_PATH','/static/common/');
define('CSS_PATH','/static/shop/css/');
define('JS_PATH','/static/shop/js/');
define('IMG_PATH','/static/shop/images/');
define('INDEX_SWF_VIDEO_PATH', '/static/shop/swf');
define('INDEX_TEMPLATE_INCLUDE_PATH', APP_PATH .'shop/view/index');
define('SHOP_PATH', '/static/shop/');

return [
    'template'=>[
        'layout_on'=>true,
        'layout_name'=>'layout/inbox',
    ],
    'url_html_suffix'   => '',
    'dispatch_success_tmpl'    => THINK_PATH . 'tpl' . DS . 'suc-error.tpl',
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'suc-error.tpl',
];
