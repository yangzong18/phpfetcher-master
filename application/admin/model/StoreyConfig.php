<?php
/**
 * create by: PhpStorm
 * desc:楼层模板模型
 * author:yangmeng
 * create time:2016/11/21
 * modified by:修改人
 * modified time:修改时间
 * modified mark:修改备注 
 */
namespace app\admin\model;
use app\admin\model\StoreyTemplate;
use think\Cache;
use think\Model;
class StoreyConfig extends Model
{
    // 所有楼层配置缓存索引键
    private $all_storey_config = 'storey_config';
    
    /**
     * 获取楼层配置，该数据使用 id 作为索引获取分类信息
     */
    public function getAllCache(){
        $allConfigData = Cache::get($this->all_storey_config);
        if (!empty($allConfigData)){
            return $allConfigData;
        }
        // 获取所有的楼层配置
        $tblRows = $this->field('*')
                        ->select();
        $allConfigData = array();
        foreach ($tblRows as $key => $row){
            $allConfigData[$row['id']] = $row;
        }
        $result = Cache::set($this->all_storey_config, $allConfigData);
        return $allConfigData;
    }

    /**
     * 刷新楼层配置信息
     */
    public function flushCache(){
        Cache::rm($this->all_storey_config);
        $this->getAllCache();
    }

    /**
     * 移除失效的模板配置数据
     * @param string|array list storey_template_id 失效的模板 id
     */
    public function removeInvalidConfig($storey_template_id){
        if (is_array($storey_template_id)){
            $templateIdList = $storey_template_id;
        }
        else {
            $templateIdList = explode(',', $storey_template_id);
        }
        if ($this->destroy('storey_template_id', 'in', $templateIdList)){
            $this->makeIndexIncludeFile();
            return true;
        }
        return false;
    }

    /**
     * 生成首页 include 楼层模板加载文件 html
     */
    public function makeIndexIncludeFile(){
        $myStoreyTemplate = new StoreyTemplate();
        $templateData = $myStoreyTemplate->getCache();
        $configData = $this->getAllCache();
        $index_include_file_str = '';
        foreach ($configData as $key => $config) {
            if ($config['is_disable']){
                continue;
            }
            $template_file = $templateData[$config['storey_template_id']]['template_path'];
            $template_file = $_SERVER['DOCUMENT_ROOT'] . $template_file;
            if (file_exists($template_file)) {
                // 获取模板文件
                $template_content = file_get_contents($template_file);
                // 在模板文件中进行数据标记替换
                $data_tag = $config['unique_name'];
                $template_content = str_replace('templateConfigList', $data_tag, $template_content);
                // 生成 include 加载文件
                $file_name = $config['unique_name'];
                $include_template_file = INDEX_TEMPLATE_INCLUDE_PATH . DS . $file_name .'.html';
                file_put_contents($include_template_file, $template_content);
                $index_include_file_str .= "
                {include file='index/". $file_name ."' /} \n";
            }
        }
        // 生成 index include 加载模块
        $index_include_story = INDEX_TEMPLATE_INCLUDE_PATH . DS .'index_include_story.html';
        file_put_contents($index_include_story, $index_include_file_str);
    }
}
