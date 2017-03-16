<?php
/**
 * Created by 长虹.
 * User: 李武
 * Date: 2017/1/4
 * Time: 10:56
 * Desc: 手机端控制器父类
 */
namespace app\mobile\controller;
use think\Config;
use think\Controller;
use think\Db;
use Util\Redis;
use think\Model;

class Mobile extends Controller
{
    //用户信息
    public $user=array();

    /**
     * 初始化构造器
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * 返回封装后的API数据到客户端
     * @access protected
     * @param mixed     $data 要返回的数据
     * @param integer   $code 返回的code
     * @param mixed     $msg 提示信息
     * @param string    $type 返回数据格式  默认返回json
     * @param array     $header 发送的Header信息
     * @return void
     */
    protected function returnJson($data='', $code = 0, $msg = '', $type = 'json', array $header = []){
        return $this->result($data,$code,$msg,$type,$header);
    }

    /*
     * 首页轮播
     *
     * */
    public function getAdv()
    {
        // 首页滚动广告位信息
        $where = array();
        $where['is_delete'] = array('=', 0);
        $where['adv_type'] = 1;
        $advertiseRows = Db::name('advertise')->field('*')->order(array('adv_sort' => 'asc'))->where($where)->select();
        $advertiseRows = Model::getResultByFild($advertiseRows);
        $adverRows = array();
        foreach ($advertiseRows as $key => $advertise) {
            if(empty($advertise['adv_img'])) continue;
            $advertise['adv_img'] = str_replace("\\", '/', $advertise['adv_img']);
            $adverRows[]=$advertise;
        }

        $this->returnJson($adverRows);
    }

    /**
     * 登录生成token
     * @param $memberId
     * @param $memberAccount
     * @return null|string
     */
    protected function getToken($memberId, $memberAccount) {
        //删除旧的令牌
        Db::name('mobile_member_token')->where('member_id',$memberId)->delete();
        //生成新的token
        $mobileUserTokenInfo = [];
        $token = md5($memberAccount . strval(time()) . strval(rand(0,999999)));
        $mobileUserTokenInfo['member_id'] = $memberId;
        $mobileUserTokenInfo['account'] = $memberAccount;
        $mobileUserTokenInfo['token'] = $token;
        $mobileUserTokenInfo['login_time'] = time();

        $result = Db::name('mobile_member_token')->insert($mobileUserTokenInfo);

        if($result) {
            return $token;
        } else {
            return null;
        }
    }

    public function authCode( $phone, $code ) {
        $redis = Redis::getInstance();
        $message = ['code'=> 0, 'msg'=> '手机号码格式错误'];
        //手机号码验证
        if ( !preg_match( Config::get('other.phone'), $phone ) ) {
            return $message;
        }
        $message['msg'] = '验证码错误';
        if( $redis->get('phone'.$phone.'.code') === false )//假如手机号从未获取过验证码
            return $message;
        if(  $redis->get('phone'.$phone.'.time')+300 >= time() ) {//判断验证码是否过期
            if( $code != $redis->get('phone'.$phone.'.code') ) {
                return $message;
            }
            $message = ['code'=> 1, 'msg'=> '成功'];
            return $message;
        } else {//过期,删除session
            $redis->delete('phone'.$phone.'.code');
            $redis->delete('phone'.$phone.'.time');
            $message = ['code'=> 2, 'msg'=> '验证码已过期，请重新获取'];
            return $message;
        }
    }
}