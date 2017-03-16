<?php
/**
 * 延时任务守护进程
 *
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: Laijunliang at 2016-12-13
 */
namespace app\api\command;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Util\Redis;
use think\Db;
use think\Config;
use app\common\logic\Task as DoTask;
use think\Log;

ini_set('default_socket_timeout', -1);

class Task extends Command{

    protected function configure() {
        $this->setName('task')->setDescription('Here is the notify task');
    }

    /**
     * 订阅redis4号库的过期事件，用来进行相关操作
     */
    public function execute(Input $input, Output $output) {
        $config = Config::get('database');
        $config['params'] = array(
            \PDO::ATTR_PERSISTENT   => true,
        );
        Db::connect($config);
        $redis = Redis::getInstance('pconnect');
        $redis->psubscribe(array('__keyevent@4__:expired'),  function($redis, $pattern, $chan, $key){
            //$key代表过期的key
            $where = array( 'task_key' => $key );
            $task = Db::name('task')->where( $where )->find();
            //如果任务获取成功
            if ( $task ) {
                $functionName = $task['method'];
                //如果执行成功
                $param = array( 'success' => 2 );
                if ( DoTask::$functionName( json_decode( $task['param'] ) ) ) {
                    //更新执行状态
                    $param['success'] = 1;
                } else {
                    //TODO, 10秒后再次执行
                    Log::write('延时任务状态执行失败, key:'.$key,'error');
                }
                $result = Db::name('task')->where( $where )->save( $param );
                if ( !$result ) {
                    Log::write('延时任务状态更新失败, key:'.$key,'error');
                }
            }
        });
    }

}
