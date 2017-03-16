<?php
/**
 * Created by PhpStorm.
 * User: LUOTING
 * Date: 2017/2/21
 * Time: 17:31
 */

namespace app\common\controller;

class AES{
    //密钥
    const KEY = '1298132445423313';

    /**
     * 加密方法
     * @param string $str
     * @return string
     */
    public function encrypt( $string ){
        //AES, 128 ECB模式加密数据
        $secretKey = self::KEY;
        //$secretKey = base64_decode($secretKey);
        $str = trim($string);
        $str = $this->addPKCS7Padding($str);
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128,MCRYPT_MODE_ECB),MCRYPT_RAND);
        $encrypt_str =  mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $secretKey, $str, MCRYPT_MODE_ECB, $iv);
        return base64_encode($encrypt_str);
    }

    /**
     * 解密方法
     * @param string $str
     * @return string
     */
    public function decrypt($string){
        //AES, 128 ECB模式加密数据
        $secretKey = self::KEY;
        $str = base64_decode($string);
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128,MCRYPT_MODE_ECB),MCRYPT_RAND);
        $encrypt_str =  mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $secretKey, $str, MCRYPT_MODE_ECB, $iv);
        //$encrypt_str = trim($encrypt_str);
        $encrypt_str = $this->stripPKSC7Padding($encrypt_str);
        return $encrypt_str;

    }

    /**
     * 填充算法
     * @param string $source
     * @return string
     */
    function addPKCS7Padding($source){
        $source = trim($source);
        $block = mcrypt_get_block_size('rijndael-128', 'ecb');
        $pad = $block - (strlen($source) % $block);
        if ($pad <= $block) {
            $char = chr($pad);
            $source .= str_repeat($char, $pad);
        }
        return $source;
    }
    /**
     * 移去填充算法
     * @param string $source
     * @return string
     */
    function stripPKSC7Padding($source){
        //$source = trim($source);
        $char = substr($source, -1);
        $num = ord($char);
        if($num == 62 )return $source;
        $source = substr($source,0,-$num);
        return $source;
    }
}