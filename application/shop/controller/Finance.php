<?php
/**
 * 分期界面
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 * Author: ss.wu <linkwss@foxmail.com> at: 2016/12/13  10:23
 */
namespace app\shop\controller;

use app\common\controller\Shop;

class Finance extends Shop
{
    public function index(){
    	$goodsList = array(
    		array( 'name' => '原木餐桌组合', 'sales_number' => 45, 'prize' => '8888.00' ),
    		array( 'name' => '原木展示桌', 'sales_number' => 88, 'prize' => '5888.00' ),
    		array( 'name' => '欧式沙发套装', 'sales_number' => 45, 'prize' => '12888.00' ),
    		array( 'name' => '宫廷法式沙发', 'sales_number' => 67, 'prize' => '13888.00' ),
    		array( 'name' => '中式客厅套装家具组合', 'sales_number' => 89, 'prize' => '13356.00' ),
    		array( 'name' => '中式原木衣柜组合套装', 'sales_number' => 67, 'prize' => '16688.00' ),
    		array( 'name' => '中式书房书桌组合', 'sales_number' => 43, 'prize' => '5666.00' ),
    		array( 'name' => '中式原木私人酒窖', 'sales_number' => 35, 'prize' => '18988.00' ),
    		array( 'name' => '欧式原木私人酒窖', 'sales_number' => 46, 'prize' => '8989.00' ),
    		array( 'name' => '客厅会客中式原木桌', 'sales_number' => 67, 'prize' => '5889.00' ),
    		array( 'name' => '客厅会客中式原木桌02', 'sales_number' => 35, 'prize' => '4896.00' ),
    		array( 'name' => '客厅会客中式原木桌03', 'sales_number' => 45, 'prize' => '9899.00' ),
    		array( 'name' => '原木中式餐厅餐桌套装', 'sales_number' => 45, 'prize' => '9888.00' ),
    		array( 'name' => '欧式豪华书房书桌', 'sales_number' => 16, 'prize' => '4889.00' ),
    		array( 'name' => '法式豪华书房书桌', 'sales_number' => 45, 'prize' => '6889.00' ),
    		array( 'name' => '中式原木餐桌组合', 'sales_number' => 10, 'prize' => '8998.00' ),
    	);
        $this->assign( 'goodsList', $goodsList );
        $this->assign( 'host', HTTP_SITE_HOST );
        return $this->fetch();
    }
    
}