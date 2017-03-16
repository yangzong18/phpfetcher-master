<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
use think\Config;
use think\Validate;
/**
 * Author laurinda
 *根据控制器名和方法名称获取导航父栏目(支持三级栏目)
 * @param $controllName 控制器名称
 * @param $actionName 控制器名称
 * @param $is_self 是否包含自身
 * return array('1'=>array('tagname'=>‘标识名’,'name'=>'菜单名','c'=>'模块','a'=>'控制器'),2=>..二级栏目,3=>...三级栏目)
**/
function topcategory($controllName='',$actionName='',$is_self=true){
    $array = array();
    if(empty($controllName) || empty($actionName)) return array();
    $menu = config('menu');
    foreach($menu as $k=>$v){
        if(isset($v['child']) && !empty($v['child'])){//查看二级
            foreach($v['child'] as $_k=>$_v){
                if(isset($_v['child']) && !empty($_v['child'])){  //查看三级
                    foreach($_v['child'] as $i=>$j){
                        if($j['c']==$controllName && $j['a']==$actionName){
                            if($is_self){
                                $array[3]['tagname'] = $i;
                                $array[3]['name'] = $j['name'];
                                $array[3]['c'] = $j['c'];
                                $array[3]['a'] = $j['a'];
                            }


                            $array[2]['tagname'] = $_k;
                            $array[2]['name'] = $_v['name'];
                            $array[2]['c'] = $_v['c'];
                            $array[2]['a'] = $_v['a'];

                            $array[1]['tagname'] = $k;
                            $array[1]['name'] = $v['name'];
                            $array[1]['c'] = $v['c'];
                            $array[1]['a'] = $v['a'];
                            break 3;
                        }
                    }
                }
                if($_v['c']==$controllName && $_v['a']==$actionName){
                    if($is_self) {
                        $array[2]['tagname'] = $_k;
                        $array[2]['name'] = $_v['name'];
                        $array[2]['c'] = $_v['c'];
                        $array[2]['a'] = $_v['a'];
                    }

                    $array[1]['tagname'] = $k;
                    $array[1]['name'] = $v['name'];
                    $array[1]['c'] = $v['c'];
                    $array[1]['a'] = $v['a'];
                    break 2;
                }
            }
        }

        if($v['c']==$controllName && $v['a']==$actionName){
            if($is_self) {
                $array[1]['tagname'] = $k;
                $array[1]['name'] = $v['name'];
                $array[1]['c'] = $v['c'];
                $array[1]['a'] = $v['a'];
                break;
            }

        }
    }
    if(!empty($array)) ksort($array);
    return $array;
}

/**
 * Author laurinda
 * 获取子导航
 * @param $controllName 控制器名称
 * @param $actionName 控制器名称
 * @param $is_self 是否包含自身
 * return 子导航数组
 **/

function submenu($controllName='',$actionName='',$is_self=false){
    $array = array();
    if(empty($controllName) || empty($actionName)) return array();
    $menu = config('menu');
    foreach($menu as $k=>$v){
        if($v['c']==$controllName && $v['a']==$actionName){
            if($is_self){
                $array = $v;
            }else{
                $array = isset($v['child']) && !empty($v['child']) ? $v['child'] : array();
            }
            break;
        }

        if(isset($v['child']) && !empty($v['child'])){//查看二级
            foreach($v['child'] as $_k=>$_v){
                if($_v['c']==$controllName && $_v['a']==$actionName){
                    if($is_self){
                        $array = $_v;
                    }else{
                        $array = isset($_v['child']) && !empty($_v['child']) ? $_v['child'] : array();
                    }
                    break 2;
                }

                if(isset($_v['child']) && !empty($_v['child'])){  //查看三级
                    foreach($_v['child'] as $i=>$j){
                        if($j['c']==$controllName && $j['a']==$actionName){
                            $array = $is_self ? $j : array();
                            break 3;
                        }
                    }
                }

            }
        }


    }
    return $array;
}

/**
 * 随机生成手机验证码
 * @param int $length 验证码长度
 * @return string
 */
function generateCode($length = 6) {
    return str_pad(mt_rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * 价格格式化
 *
 * @param int	$price
 * @return string	$price_format
 */
function ncPriceFormat($price) {
    $price_format	= number_format($price,2,'.','');
    return $price_format;
}

/**
 * 规范数据返回函数
 * @param unknown $state
 * @param unknown $msg
 * @param unknown $data
 * @return multitype:unknown
 */
function callback($state = true, $msg = '', $data = array()) {
	return array('state' => $state, 'msg' => $msg, 'data' => $data);
}



/**
 * 取得订单支付类型文字输出形式
 *
 * @param array $payment_code
 * @return string
 */
function orderPaymentName($payment_code) {
    return str_replace(
        array('offline','online','alipay','tenpay','chinabank','predeposit'),
        array('货到付款','在线付款','支付宝','财付通','网银在线','站内余额支付'),
        $payment_code);
}

/**
 * 编辑、新增验证提交数据
 * @param $dataInfo 待验证数据
 * @param $type 场景类型(1=>收货地址新增 2=>收货地址编辑)
 * @return array
 */
function validateAddress($dataInfo, $type = 1)
{
    $rule = [
        'member_id' => 'require',
        'true_name' => 'require|max:52|desc',
        'province_id' => 'require|integer',
        'city_id' => 'require|integer',
        'area_id' => 'require|integer',
        'address' => 'require|max:150|desc',
        'tel_phone' => 'regex:/^([0-9]{3,4})-([0-9]{7,9})$/',
        'mob_phone' => 'regex:/^1([3-9]{1})([0-9]{1})([0-9]{8})$/',
        'member_email' => 'email',
    ];
    $msg = [
        'member_id.require' => '会员id不能为空',
        'true_name.require' => '姓名不能为空',
        'true_name.max' => '姓名不能超过十四个字符',
		'true_name.desc' => '姓名输入的字符串含有非法字符',
        'province_id.require' => '选择的省不能为空',
        'province_id.integer' => '请选择省',
        'city_id.require' => '选择的市不能为空',
        'city_id.integer' => '请选择市',
        'area_id.require' => '选择的县区不能为空',
        'area_id.integer' => '请选择县区',
        'address.require' => '详细地址不能为空',
        'address.max' => '详细地址长度过长',
		'address.desc' => '地址输入的字符串含有非法字符',
        'tel_phone.regex' => '请填写正确的座机号:区号-号码的形式',
        'mob_phone.regex' => '请填写正确的手机号码',
        'member_email.email' => '邮箱格式不正确',
    ];
    $validate = new Validate($rule, $msg);
    $validate->scene('addAddress', ['member_id', 'true_name', 'province_id', 'city_id', 'area_id','address','mob_phone','tel_phone', 'member_email']);
    $validate->scene('editAddress', ['true_name', 'province_id', 'city_id','area_id','address','mob_phone','tel_phone','member_email']);
    switch ($type) {
        case 1: //新增地址
            $result = $validate->scene('addAddress')->check($dataInfo);
            break;
        case 2: //编辑地址
            $result = $validate->scene('editAddress')->check($dataInfo);
            break;
    }
    if (!$result) {
        return ['code' => 0, 'msg' => $validate->getError()];
    }
    if (empty($dataInfo['tel_phone']) && empty($dataInfo['mob_phone'])) {
        return ['code' => 0, 'msg' => '手机号和座机号必填一项'];
    }
    return ['code' => 1];
}

//通过数组键名获取相应的值
function array_get_by_key(array $array, $string)
{
    if (!trim($string)) return false;
    preg_match_all("/\"$string\";\w{1}:(?:\d+:|)(.*?);/", serialize($array), $res);
    return str_replace("\"", "", $res[1]);
}
