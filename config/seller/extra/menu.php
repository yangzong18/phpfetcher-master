<?php

return [
    'Index'=>[
        'name'=>'首页',
        'c'=>'Index',
        'a'=>'index',
    ],
    'Goods'=>[
        'name'=>'商品',
        'c'=>'GoodsCategory',
        'a'=>'lists',
        'child'=>[
            'Category'=>[//二级
                'name'=>'商品分类',
                'c'=>'GoodsCategory',
                'a'=>'lists',
                'child'=>[//三级
                   'GoodsCategory'=>['name'=>'分类列表',
                       'c'=>'GoodsCategory',
                       'a'=>'lists',
                      ],
                   'GoodsCategoryAdd'=>['name'=>'添加分类',
                       'c'=>'GoodsCategory',
                       'a'=>'add',
                      ],
                 ],
            ],
            'GoodsList'=>[
                'name'=>'商品列表',
                'c'=>'Goods',
                'a'=>'lists',
            ],
        ],
    ],
    'Order'=>[
        'name'=>'交易',//一级
        'c'=>'Order',
        'a'=>'lists',
        'child'=>[
            'OrderList'=>[//二级
                'name'=>'订单列表',
                'c'=>'Order',
                'a'=>'lists'
            ],

        ],
    ],
];