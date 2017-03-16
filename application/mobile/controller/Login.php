<?php
/**
 * 用户登录接口
 * 罗婷 2017/1/3
 */

namespace app\mobile\controller;
use app\common\controller\AES;
use app\common\controller\SentTemplatesSMS;
use app\common\logic\Auth;
use app\common\model\Member;
use app\common\model\MobileMemberToken;
use think\Config;
use think\Db;
use think\Session;
use think\Validate;
use Util\Redis;
use Util\Tools;

class Login extends MobileHome{

    protected $model;
    public function __construct(){
        parent::__construct();
        $this->model = new Member();
    }

    /**
     * 登录
     */
    public function login() {
        if( !$this->request->has('account') || !$this->request->has('password') ) {
            $this->returnJson('', 1, '登录失败');
        }
        $memberModel = new Member();
        $array = [];
        $array['phone']	= $this->request->post('account', '', 'trim');
        $ace = new AES();
        $password = trim( $ace->decrypt( $this->request->post('password', '', 'trim') ) );
        $password = md5($password);
        //获取手机帐号错误次数
        $redis = Redis::getInstance();
        $errorTime = $redis->get('phone'.$array['phone'].'.error_time');
        if( $errorTime !== false && $errorTime >= 3 ) {//根据登错次数，判断是否需要验证码
            if( !$this->request->has('authCode')  ) {
                $this->returnJson('', 3, '验证码不能为空');
            }
            $authCode = $this->request->post('authCode', '', 'intval');
            $result = $this->authCode( $array['phone']	, $authCode );
            //如果验证码不通过
            if ( $result['code'] != 1 ) {
                $this->returnJson('', 1,$result['msg'] );
            }
        }

        $memberInfo = $memberModel->getMemberInfo($array);
        if( !empty($memberInfo) ) {
            if( $memberInfo['password'] != $password) {//若密码错误
                //假如是第一次登错
                if( $errorTime === false) {
                    $redis->set('phone'.$array['phone'].'.error_time',1);
                } else {
                    $redis->set('phone'.$array['phone'].'.error_time',$errorTime + 1);
                }
                //根据登错次数，返回提示信息
                if( $errorTime !== false && $errorTime +1 >= 3)
                    $this->returnJson('', 3, '密码错误超过3次');
                else
                    $this->returnJson('', 1, '密码错误');
            }
            $token = $this->getToken($memberInfo['member_id'], $memberInfo['account']);
            if($token) {
                //登录成功后
                $redis->delete('phone'.$array['phone'].'.error_time');
                $this->returnJson(['account' => $memberInfo['account'], 'key' => $token]);
            } else {
                $this->returnJson('', 1, '登录失败');
            }
        } else { //假如手机号未注册
            $this->returnJson('', 1, '手机号未注册');
        }
    }

    /**
     * 注册
     */
    public function register() {
        $info = [];
        $info['phone'] = $this->request->post('phone', '', 'trim');
        $ace = new AES();
        $info['password']	= trim( $ace->decrypt( $this->request->post('password', '', 'trim') ) );
        $info['authCode'] = $this->request->post('authCode', '', 'trim');
        //基本数据数据验证
        $validate = $this->registerValidate( $info );
        if( isset($validate['error']) )
            $this->returnJson('', 1, $validate['error']);
        $result = $this->authCode( $info['phone'], $info['authCode'] );
        //如果验证码不通过
        if ( $result['code'] != 1 ) {
            $this->returnJson('', 1,$result['msg'] );
        }
        //新增会员
        unset($info['authCode']);
        $info['member_id'] = Tools::guid();
        $info['account'] = $info['phone'];
        $info['password'] = md5($info['password']);
        if( $this->model->save($info) ) {
            $redis = Redis::getInstance();
            $redis->delete('phone'.$info['phone'].'.code');
            $redis->delete('phone'.$info['phone'].'.time');
            $this->returnJson('', 0, '注册成功');
        }
        else $this->returnJson('', 1, '注册失败');
    }

    /**
     * 获取手机验证码
     */
    public function getAuthCode() {
        $phone = $this->request->post('phone', '', 'trim');
        if ( !preg_match( Config::get('other.phone'), $phone ) ) {
            $this->returnJson('', 1, '手机号码格式错误');
        }
        //次数限制,先注释掉 罗婷
//        $authLogic = new Auth();
//        $ip = $this->request->ip();
//        $msg = $authLogic->codeLimit($ip, $phone);
//        if( !$msg['code'] ) $this->returnJson('', 1, $msg['msg']);
        //假如发送短信
        $sms = new SentTemplatesSMS();
        $code = generateCode();
        $result = $sms->sent($phone, [$code], "phone_code");
        if( $result['code'] == 2 ) {//短信发送成功
            $redis = Redis::getInstance();
            $redis->set('phone'.$phone.'.code',$code);
            $redis->set('phone'.$phone.'.time',time());
            $this->returnJson('', 0, '发送成功');
        } else{
            $this->returnJson('', 1, '发送失败');
        }
    }

    /**
     * 忘记密码
     */
    public function forPassword() {
        $phone = $this->request->post('phone', '', 'trim');
        $code = $this->request->post('authCode', '', 'trim');
        //参数验证
        if ( !preg_match( Config::get('other.phone'), $phone ) )
            $this->returnJson('', 1, '手机号码格式错误');
        if( !$this->model->where('phone', $phone)->find() )
            $this->returnJson('', 1, '手机号尚未注册，请先注册');
        if( $code == '')
            $this->returnJson('', 1, '验证码不能为空');
        //验证码验证
        $result = $this->authCode( $phone, $code );
        //如果验证码不通过
        if ( $result['code'] != 1 ) {
            $this->returnJson('', 1,$result['msg'] );
        } else {//验证码正确
            Session::set('retrievePhone', $phone);
            $this->returnJson(['phone'=>$phone], 0, '验证成功');//跳转到重置密码页面
        }
    }

    /**
     * 不登陆状态下，重置密码
     */
    public function resetPassword() {
        $account = $this->request->post('phone', '', 'trim');
        $ace = new AES();
        $password = trim( $ace->decrypt( $this->request->post('password', '', 'trim') ) );
        $repeatPassword = trim( $ace->decrypt( $this->request->post('repeat_password', '', 'trim') ) );
        $memberInfo = [ 'phone' => $account, 'password' => $password, 'repeatPassword'=>$repeatPassword];

        $rule = array(
            'phone' => 'require|regex:/^1([3-9]{1})([0-9]{1})([0-9]{8})$/',
            'password'=> 'require|regex:/^(\w){6,18}$/',
            'repeatPassword' => 'require|confirm:password'
        );
        $message = array(
            'phone.require' => '手机号不能为空',
            'phone.regex' => '手机号无效',
            'password.require' => '密码不能为空',
            'password.regex' => '请输入6-18个字符的密码，字母数字或下划线',
            'repeatPassword.require' => '确认密码不能为空',
            'repeatPassword.confirm'  => '两次输入密码不一致'
        );
        //参数空验证
        $validate = new Validate($rule, $message);
        if ( !$validate->check( $memberInfo ) )
            $this->returnJson('', 1, $validate->getError() );
        //账户验证
        if( Session::get('retrievePhone') != $account )
            $this->returnJson('', 1, '重置帐号错误' );
        if( $this->model->where('phone', $account)->update(['password'=> md5($password)]) === false) {
            $this->returnJson( '', 1, '重置密码失败');
        }
        //重置成功
        Session::delete('retrievePhone');
        $this->returnJson('',0, '重置密码成功');
    }

    /**
     * 注册信息验证
     * @param $info
     * @return array
     */
    private function registerValidate( $info ) {
        $rule = array(
            'phone' => 'require|unique:member|regex:/^1([3-9]{1})([0-9]{1})([0-9]{8})$/',
            'password'=> 'require|regex:/^(\w){6,18}$/',
            'authCode'=> 'require'
        );
        $message = array(
            'phone.require' => '手机号不能为空',
            'phone.unique' => '手机号已注册',
            'phone.regex' => '手机号无效',
            'password.require' => '密码不能为空',
            'password.regex' => '请输入6-18个字符的密码，字母数字或下划线',
            'authCode.require' => '验证码不能为空'
        );
        //参数空验证
        $validate = new Validate($rule, $message);
        if( !$validate->check( $info ) ){
            return ['code' => 0, 'error' => $validate->getError()];
        }
        return ['code' => 1];

    }

    /**
     *登录状态下修改登录密码
     */
    public function loginResetPassword() {
        //首先判断是否登录
        $key = $this->request->post('key', '', 'trim');
        if( empty($key) )  $this->returnJson('', 2, '请登录');
        $tokenModel = new MobileMemberToken();
        $mobileUserTokenInfo =  $tokenModel->getMobileUserTokenInfo(['token'=>$key]);
        if( empty($mobileUserTokenInfo) )  $this->returnJson('', 2, '请登录');
        $memberModel = new Member();
        $user = $memberModel->getMemberInfo( ['member_id' => $mobileUserTokenInfo['member_id']] );
        if( empty($user) )  $this->returnJson('', 2, '请登录');
        //传入参数空验证
        $phone = $this->request->post('phone', '', 'trim');
        $ace = new AES();
        $password = trim( $ace->decrypt( $this->request->post('password', '', 'trim') ) );
        $newPassword = trim( $ace->decrypt( $this->request->post('new_password', '', 'trim') ) );
        $repeatPassword = trim( $ace->decrypt( $this->request->post('repeat_password', '', 'trim') ) );
        $memberInfo = [ 'phone' => $phone, 'password' => $password, 'newPassword'=>$newPassword,'repeatPassword'=>$repeatPassword];
        $rule = array(
            'phone' => 'require|regex:/^1([3-9]{1})([0-9]{1})([0-9]{8})$/',
            'password'=> 'require|regex:/^(\w){6,18}$/',
            'newPassword'=> 'require',
            'repeatPassword' => 'require|confirm:newPassword'
        );
        $message = array(
            'phone.require' => '手机号不能为空',
            'phone.regex' => '手机号无效',
            'password.require' => '密码不能为空',
            'password.regex' => '请输入6-18个字符的密码，字母数字或下划线',
            'newPassword.require' => '新密码不能为空',
            'repeatPassword.require' => '确认密码不能为空',
            'repeatPassword.confirm'  => '两次新密码输入密码不一致'
        );
        $validate = new Validate($rule, $message);
        if ( !$validate->check( $memberInfo ) )
            $this->returnJson('', 1, $validate->getError() );
        //帐号正确性验证
        if( $user['phone'] != $phone ) $this->returnJson('', 1, '帐号错误' );
        if( $user['password'] != md5($password) ) $this->returnJson('', 1, '原始密码错误' );
        //修改密码
        if( ( new Member() )->update( ['password'=> md5($newPassword),'member_id'=>$user['member_id']] ) === false) {
            $this->returnJson( '', 1, '重置密码失败');
        }
        $this->returnJson('',0, '修改成功');
    }

    /**
     * 手机号码唯一性验证
     */
    public function checkPhone() {
        $phone = $this->request->post('phone', '', 'trim');//手机号
        $type = $this->request->post('type', '', 'intval');//判断是注册手机还是找回密码
        if( $type == 1 ) {//如果是找回密码，手机号码不存在返回false
            if( !$this->model->where('phone', $phone)->find() ) {
                $this->returnJson('', 1, '手机号码未注册' );
            } else {
                $this->returnJson('', 0, '手机号码存在' );
            }
        } else {//假如是注册手机，如果已存在返回false
            if( !$this->model->where('phone', $phone)->find() ) {
                $this->returnJson('', 0, '手机号码可以注册' );
            } else {
                $this->returnJson('', 1, '手机号码已注册' );
            }
        }
    }

}
