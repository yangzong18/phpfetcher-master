<?php
/**
 * Created by 长虹.
 * User: 李武
 * Date: 2017/1/4
 * Time: 10:56
 * Desc: 手机端收藏列表接口
 */
namespace app\mobile\controller;
use think\Db;
use app\common\model\Favorites;

class Favorite extends MobileMember
{
    protected $model;

    /**
     * 构造器
     */
    public function __construct() {
        parent::__construct();
        $this->model = new Favorites();
    }

    /*
     * 收藏列表接口
     *
     * */
    public function index()
    {
        $goodsType = $this->request->post('goods_type', 'all', 'trim');
        //搜索商品名字
        $searchName = $this->request->post('search_name');
        $goodsId = Db::name('goods')->where('goods_name','like','%'.$searchName.'%')->column('goods_id');
        if( empty($goodsId) ) $goodsId = array('0');
        $where =  ['member_id'=>$this->user['member_id'], 'f.type'=>0, 'f.goods_id'=>['IN',$goodsId]];

        $whereOr = '';
        $lostWhereOr = 'is_delete =1 or goods_verify !=1';
        $descWhere = $lostWhere = $where;
        switch($goodsType){
            case 'all':
                break;
            case 'desc':
                $where['f.price'] = ['exp','> g.goods_price'];
                $where['g.goods_verify'] = 1;
                break;
            case 'lost':
                $whereOr = $lostWhereOr;
                break;
        }

        $field = 'f.goods_id,goods_name,goods_price,goods_image_main,is_delete,goods_verify,f.price,f.id';
        $list = $this->model->getFavoriteGoodsList($where, $whereOr, $field);
        $page = array('currentPage' => $list->currentPage(), 'lastPage' => $list->lastPage(), 'total' => $list->total());
        $data = array('list'=>$list,'goodsType'=>$goodsType,'page'=>$page,'search_name'=>$searchName);
        $this->returnJson($data);
    }

    /**
     * 收藏商品
     */
    public function addFav(){
        $goodsId = $this->request->post('goods_id', '' ,'trim');
        if( empty($goodsId) ) $this->returnJson('', 1 , '商品参数错误');
        $where = ['goods_id'=>$goodsId, 'is_delete'=>0, 'goods_verify'=>1];
        $goodInfo = Db::name('goods')->field('goods_id,goods_price,is_delete,goods_verify')->where($where)->find();
        if( empty($goodInfo) ) $this->returnJson('', 1 , '商品已下架或不存在');

        $param['member_id'] = $this->user['member_id'];
        $param['goods_id'] = $goodsId;
        $param['price'] = $goodInfo['goods_price'];
        $param['type'] = 0;
        $where = array('member_id'=>$param['member_id'],'goods_id'=>$param['goods_id']);
        $favg = Db::name('favorites')->where($where)->find();
        if($favg) $this->returnJson('', 0 , '收藏成功');
        $result = Db::name('favorites')->insert($param);
        if($result){
            $this->returnJson('', 0 , '收藏成功');
        }else{
            $this->returnJson('', 1 , '收藏失败');
        }

    }

    //取消收藏
    public function delFav(){
        //收藏参数
        $goodsId = $this->request->post('goods_id', '' ,'trim');
        if( empty($goodsId) ) $this->returnJson('', 1 , '商品参数错误');
        //判断商品状态
        $goods = Db::name('goods')->field('goods_id,is_delete,goods_verify')->where('goods_id', $goodsId)->find();
        if( empty($goods) || $goods['is_delete']== 1 || $goods['goods_verify'] != 1)
            $this->returnJson('', 1 , '商品已下架或不存在');
        //判断记录
        $where = array('member_id' =>$this->user['member_id'], 'goods_id' =>$goodsId);
        $res = Db::name('favorites')->where($where)->value('id');

        if($res){
            if( Db::name('favorites')->where('id', $res)->delete() )
                $this->returnJson('', 0 , '操作成功');
            else  $this->returnJson('', 1 , '操作失败');
        }
        else $this->returnJson('', 1 , '操作失败');
    }
}