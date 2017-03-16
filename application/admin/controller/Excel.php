<?php
/**
 * Excel控制器
 * Copyright (c) 2016 http://www.changhong.cn All rights reserved.
 */

namespace app\admin\controller;

use think\Controller;

class Excel extends Controller{
    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * excel导出
     * @param $expTitle excel标题名称
     * @param $expCellName excel列名
     * @param $expTableData excel表数据
     */
    public function exportExcel($expTitle, $expCellName, $expTableData) {

        //标题
        $xlsTitle = iconv('utf-8', 'gb2312', $expTitle);
        //$xlsTitle 文件名称
        $fileName = date('Y-m-d').'download';
        $cellNum = count($expCellName);
        $dataNum = count($expTableData);
        //导入PHPExcel类库
        include EXTEND_PATH.'Excel/PHPExcel.php';
        include EXTEND_PATH.'Excel/PHPExcel/IOFactory.php';
        $objPHPExcel = new \PHPExcel();
        $cellName = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
        //合并单元格
        $objPHPExcel->getActiveSheet(0)->mergeCells('A1:'.$cellName[$cellNum-1].'1');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1', $expTitle.'  Export time:'.date('Y-m-d H:i:s'));
        for($i = 0; $i < $cellNum; $i++){
            $test = $objPHPExcel->setActiveSheetIndex(0)->setCellValue($cellName[$i].'2', $expCellName[$i]);
        }
        // 设置excel中对应列中值
        for($i = 0; $i < $dataNum; $i++){
            for($j = 0; $j < $cellNum; $j++){
               $objPHPExcel->getActiveSheet(0)->setCellValue($cellName[$j].($i+3), $expTableData[$i][$j]);
            }
        }
        header('pragma:public');
        header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xlsx"');
        header("Content-Disposition:attachment;filename=$fileName.xlsx");
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        //文件通过浏览器下载
        $objWriter->save('php://output');
    }
}