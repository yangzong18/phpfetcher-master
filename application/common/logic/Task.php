<?php
/**
 * 延时任务
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: 
 */
namespace app\common\logic;
use think\Db;
use Util\Redis;
use think\Log;

class Task {
	/**
	 * 添加任务
	 * @param string $key 执行任务的key
	 * @param string $method 执行任务的地址, 例如 /crontab/task/demo(返回code为1成功，0代表失败，可参见样例).延时任务只能写在crontab下，控制器自定义
	 * @param array $data 参数
	 * @param int $excuteTime 多少秒后执行
	 * @param bool $result 加入的结果
	 */
	public static function addTask( $key, $method, $excuteTime, $data=array() ) {
		self::removeTask($key);
        $key = 'task_'.$key;
        //将key加入redis
        $redis = Redis::getInstance();
        $param = array(
        	'task_key' => $key,
        	'method'   => $method,
        	'param'    => json_encode( $data ),
        	'excute_time' => $excuteTime,
        	'created_at'  => time()
        );
        Db::startTrans();
        try{
            //如果没有插入成功
	        if ( !Db::name('task')->insert( $param ) ) {
	        	return false;
	        }
	        //将信息加入redis
	        $redis->select(4);
	        if ( !$redis->setex( $key , $excuteTime, $excuteTime ) ) {
	        	Db::rollback();
	        	return false;
	        }
        } catch( \Exception $e ) {
            Db::rollback();
            Log::write('延时任务添加失败, data:'.json_encode($param), 'error');
            return false;
        }
        Db::commit(); 
        return true;
	}

	/**
	 * 删除定时任务
	 */
	public static function removeTask( $key ) {
		$key = 'task_'.$key;
		try{
	        //将key从redis中删除
	        $redis = Redis::getInstance();
            //如果没有插入成功
	        Db::name('task')->where('task_key', $key)->delete();
	        //将信息加入redis
	        $redis->select(4);
	        $redis->delete( $key );
        } catch( \Exception $e ) {
            Log::write('延时任务删除失败, data:'.$key, 'error');
            return false;
        }
        return true;
	}
}
