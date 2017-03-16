<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/12/8
 * Time: 15:46
 *
 * 用于一些方法的测试
 */
namespace app\shop\controller;
use app\common\controller\Shop;
use app\common\controller\AES;
use think\Validate;
header("Content-type:text/html;charset=utf-8");
class Test extends Shop{

	public function index(){
		$ace = new AES();
		$password = trim($ace->encrypt('123456') );
		echo $password;
	}

	/**
	 * 商品详情页面
	 */
	public function testA() {
		$token = $this->request->token('__token__', '', 'trim');
		if(!Validate::token('__token__','',['__token__'=>$token])){
			$this->error( 'token校验不正确');
		}
		return $this->fetch();
	}
}
