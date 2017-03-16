<?php
/**
 * create by: PhpStorm
 * desc:广告位设置管理
 * author:yangmeng
 */
namespace app\admin\controller;

use app\admin\model\Advertise;
use app\admin\model\GoodsCategory;
use app\common\model\GoodsCategory as CommonGoodsCategory;
use app\common\controller\Auth;
use think\Cache;
use think\Validate;

class AdvertiseSetting extends Auth
{
    protected $model;
    protected $field = '*';

    /*
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Advertise();
        $this->modelGoods = new GoodsCategory();
        $this->modelCommonGoods = new CommonGoodsCategory();
    }

    /**
     * 广告位列表
     */
    public function index() {
        $param = $this->request->param();
        $adv_name  = '';

        // 获取参数，构建sql查询语句
        if (isset($param['adv_name'])) {
            $adv_name  = trim($param['adv_name']);
            $datas = $this->model
                ->field( $this->field )
                ->where( array( 'is_delete' => 0 ))
                ->where('adv_name', 'like', "%$adv_name%")
                ->order(array("adv_sort" => "asc"))
                ->paginate();
        } else {
            $datas = $this->model
                ->field( $this->field )
                ->where( array( 'is_delete' => 0 ))
                ->order(array("adv_sort" => "asc"))
                ->paginate();
        }

        //查询所属广告分类
        foreach($datas as $k=>$v){
            if($v['adv_type'] == 0) $datas[$k]['category_name'] = '首页大屏广告位';
            if($v['adv_type'] == 1) $datas[$k]['category_name'] = '首页滚动广告位';
            if($v['adv_type'] == 2) $datas[$k]['category_name'] = '商品分类广告位';
        }

        //变量输出
        $this->assign("adv_name", $adv_name);
        $this->assign('datas',$datas);
        return $this->fetch();
    }

    /**
     * 广告位添加
     */
    public function add() {
        return $this->fetch();
    }

    /*
     * 广告位添加方法
     */
    public function addPost() {

        //广告位信息
        $param = $this->request->param();
        //参数验证
        $validateData = array();
        $validateData['adv_name'] = isset($param['adv_name']) ? trim($param['adv_name']) : '';
        $validateData['adv_img'] = isset($param['adv_img']) ? trim($param['adv_img']) : '';
        $validateData['adv_link'] = isset($param['adv_link']) ? trim($param['adv_link']) : '';
        $result = $this->validateData($validateData);
        if( $result['code'] == 0 ) $this->error($result['msg']);

        //为商品分类广告位时，验证分类信息
        if($param['adv_type'] == 2) {
            if ( !isset( $param['category_id'] )
                || !is_array( $param['category_id'] )
                || count($param['category_id']) != 2
                || $param['category_id'][1] === '0') {
                $this->error( '请完善分类信息' );
            }
            //取二级商品分类
            $param['category_id'] = $param['category_id'][1];
        }else{
            $param['category_id'] = '0';
        }

        //添加方法
        if($this->model->allowField(true)->save($param)){
            Cache::rm('adv');
            $this->success('添加成功',url('index'));
        }else{
            $this->error('添加失败');
        }
    }

    /**
     * 编辑广告位信息
     */
    public function edit() {

        //获取广告位详细信息
        $param = $this->request->param();
        $id = $param['id'];
        $info = $this->model
            ->field($this->field)
            ->where(array('id' => $id))
            ->find();

        //广告位分类
        $category = array('首页大屏广告位','首页滚动广告位','商品分类广告位');
        $this->assign('category',$category);

        //商品分类信息
        if ( $info['category_id'] !== '0' ) {
            $categoryList = $this->modelCommonGoods->getParentId( $info['category_id'] );
            $categoryName  = array();
            foreach ($categoryList as $key => $category) {
                array_push($categoryName, $category['name']);
            }
            $info['goods_category'] = join(' > ', $categoryName);
        }else{
            $info['goods_category'] = '';
        }

        $this->assign("info", $info);
        return $this->fetch();
    }

    /**
     * 编辑广告位方法
     */
    public function editPost() {

        //广告位信息
        $param = $this->request->param();

        //参数验证
        $validateData = array();
        $validateData['adv_name'] = isset($param['adv_name']) ? trim($param['adv_name']) : '';
        $validateData['adv_img'] = isset($param['adv_img']) ? trim($param['adv_img']) : '';
        $validateData['adv_link'] = isset($param['adv_link']) ? trim($param['adv_link']) : '';
        $result = $this->validateData($validateData);
        if( $result['code'] == 0 ) $this->error($result['msg']);

        if($param['adv_type'] == 2){
            if ( isset( $param['category_id'] ) && is_array( $param['category_id'] ) && count( $param['category_id'] ) == 2 ) {
                if($param['category_id'][1] !== '0'){
                    $param['category_id'] = $param['category_id'][1];
                }else{
                    $this->error('请完善分类信息');
                }
            }else{
                if($param['cate_id'] === '0'){
                    $this->error('请完善分类信息');
                }else{
                    $param['category_id'] = $param['cate_id'];
                }
            }
        }else{
            $param['category_id'] = '0';
        }

        //编辑参数
        $data = array(
            'adv_name' => $param['adv_name'],
            'adv_img' => $param['adv_img'],
            'adv_link' => $param['adv_link'],
            'adv_sort' => $param['adv_sort'],
            'adv_type' => $param['adv_type'],
            'category_id' => $param['category_id'],
        );

        //编辑方法
        if($this->model->update($data,array('id'=>$param['id']))){
            Cache::rm('adv');
            $this->success('编辑成功',url('index'));
        }else{
            $this->error('编辑失败');
        }
    }

    /**
     * 删除广告位方法
     */
    public function delete() {
        $id = $this->request->param('id', 0, 'intval');
        if (!is_numeric($id)){
            $this->error('删除失败');
        }
        if ( $this->model->save( array('is_delete'=>1),array('id' => $id)) ) {
            Cache::rm('adv');
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 删除选中的广告位方法
     */
    public function deleteChecked() {
        $id_list  = explode(',', $this->request->param('id_list'));
        if (empty($id_list)){
            $this->error('删除失败');
        }
        $where = array( 'id'=>array( 'in' , $id_list ) );
        if ( $this->model->save( array('is_delete' => 1) ,$where ) ) {
            Cache::rm('adv');
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }

    /**
     * 查看广告详情
     */
    public function detail(){
        $id = $this->request->param('id');
        $info = $this->model->where('id',$id)->find();

        //广告位分类
        if($info['adv_type'] == 0) $info['category_name'] = '首页大屏广告位';
        if($info['adv_type'] == 1) $info['category_name'] = '首页滚动广告位';
        if($info['adv_type'] == 2) $info['category_name'] = '商品分类广告位';

        //商品分类
        if ( $info['category_id'] !== '0' ) {
            $categoryList = $this->modelCommonGoods->getParentId( $info['category_id'] );
            $categoryName  = array();
            foreach ($categoryList as $key => $category) {
                array_push($categoryName, $category['name']);
            }
            $info['goods_category'] = join(' > ', $categoryName);
        }else{
            $info['goods_category'] = '';
        }

        if(empty($info)){
            $this->error('广告信息错误！');
        }else{
            $this->assign('adv_type',$info['adv_type']);
            $this->assign('info',$info);
            return $this->fetch();
        }
    }

    /**
     * 获取商品分类
     */
    public function getCategory(){
        $ids = $this->request->param('ids');
        if(trim($ids) == ''){
            $this->error('请输入正确的ID');
        }
        $res = $this->modelGoods->where('parent_id',$ids)->select();
        if($res){
            $this->success('获取成功','',$res);
        }

        $this->success('','','');
    }

    /**
     * 验证数据
     * @param $data
     * @return array
     */
    private function validateData( $data ){
        $checkRule = array();
        $checkMsg  = array();
        $checkRule['adv_name'] = 'require';
        $checkRule['adv_img'] = 'require';
        $checkRule['adv_link'] = 'require|url';
        $checkMsg['adv_name.require'] = '广告标题不能为空';
        $checkMsg['adv_img.require'] = '请上传广告图片';
        $checkMsg['adv_link.require'] = '广告链接不能为空';
        $checkMsg['adv_link.url'] = '广告链接无效';
        $validate = new Validate($checkRule, $checkMsg);
        $result   = $validate->check($data);
        if(!$result) {
            return array('code'=>0,'msg'=>$validate->getError());
        }
        return array('code'=>1);
    }
}
