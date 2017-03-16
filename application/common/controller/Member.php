<?php
namespace app\common\controller;
use app\common\controller\Shop;
use think\Config;
use think\Session;

class Member extends Shop {
    
    //添加验证白名单，可不登录
    public $whiteApi = array(
        'shop.logsorder.index',
        'shop.logsorder',
    );
    
    public function __construct() {
        parent::__construct();
        $app        = $this->request->module();
        $controller = $this->request->controller();
        $action     = $this->request->action();
        $url  = strtolower($app.'.'.$controller.'.'.$action);
        if ( !in_array($url, $this->whiteApi) ) {
            //初始化用户登陆信息
            $this->checkMember();
        }
    }
    
    /**
     * 继承该类要进行验证，如果未登陆，则要跳转到登陆
     */
    public function checkMember() {
        if ( $this->login == 0 ) {
            $returnUrl = isset( $_SERVER['HTTP_REFERER'] ) && trim( $_SERVER['HTTP_REFERER'] ) != '' ? $_SERVER['HTTP_REFERER'] : HTTP_SITE_HOST;
            Session::set('returnUrl', $returnUrl);
            $this->redirect($this->request->domain().'/shop/login/login');
        }
    }
}
