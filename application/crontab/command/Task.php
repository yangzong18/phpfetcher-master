<?php
/**
 * 延时任务守护进程
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: Laijunliang at 2016-12-13
 */
namespace app\crontab\command;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Util\Redis;
use think\Db;
use think\Config;
use think\Log;

ini_set('default_socket_timeout', -1);

class Task extends Command{

    public static $connect;

    protected function configure() {
        $this->setName('task')->setDescription('Here is the notify task');
    }

    /**
     * 订阅redis4号库的过期事件，用来进行相关操作
     */
    public function execute(Input $input, Output $output) {
        $redis = Redis::getInstance('pconnect');
        $redis->psubscribe(array('__keyevent@4__:expired'),  function($redis, $pattern, $chan, $key){
            try{
                $config = Config::get('database');
                $model = Db::connect($config, true);
                //$key代表过期的key
                $where = array( 'task_key' => $key );
                $task = $model->name('task')->where( $where )->find();
                //如果任务获取成功
                if ( $task ) {
                    $url = Config::get('url_domain_protocol').Config::get('url_domain_root').$task['method'];
                    //如果执行成功
                    $param = array( 'success' => 2, 'excute_at' => time() );
                    //if ( DoTask::$functionName( json_decode( $task['param'], true ) ) ) {
                    if ( Task::httpRequest( $url, json_decode( $task['param'], true ) ) ) {
                        //更新执行状态
                        $param['success'] = 1;
                    } else {
                        //TODO, 10秒后再次执行
                    }
                    $result = $model->name('task')->where( $where )->update( $param );
                    if ( !$result ) {
                        Log::write('延时任务状态更新失败, key:'.$key,'error');
                    }
                }
                //释放连接
                $model = NULL;
            } catch (\Exception $e) {
                 Log::write('延时任务状态更新失败, key:'.$e->getMessage(), 'error');
            }
            
        });
    }

    /**
     * http请求
     */
    public static function httpRequest( $url, $param ) {
        $ch =curl_init();  
        curl_setopt($ch,CURLOPT_URL, $url);  
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);  
        curl_setopt($ch,CURLOPT_HEADER,false);  
        curl_setopt($ch, CURLOPT_POST,true); // post传输数据
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);  //60秒超时
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));// post传输数据
        $content = curl_exec($ch);  
        curl_close($ch);
        $message = json_decode( $content, true );
        $result  = isset( $message['code'] ) && $message['code'] == 1 ? true : false;
        if ( $result === false ) {
            Log::write('延时任务状态执行失败, url:'.$url.'|||参数:'.json_encode( $param ).'|||响应:'.$content,'error');
        } else {
            Log::write('延时任务状态执行, url:'.$url.'|||参数:'.json_encode( $param ).'|||响应:'.$content,'error');
        }
        return $result;
    }

}