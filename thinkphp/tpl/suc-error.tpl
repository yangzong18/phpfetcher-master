
<!-- content start -->
<div class="container">
    <ul class="list-box">
        <li class="goods-sort mt-suc-item mt-suc-error">
            <ul class="marbox">
                <li class="mt-xb">
                    <div>
                        <span></span>
                        <span></span>
                    </div>
                </li>
                <li class="mt-sc">
                    <p class="error">{$msg}</p>
                </li>
                <li class="mt-er">

                    页面自动 <a id="href" href="{$url}">返回</a> 上一页等待时间： <b id="wait">{$wait}</b>
                </li>
                <li class="mt-btn">
                    <a href="{$shop}">返回首页</a>
                </li>
            </ul>

        </li>
    </ul>
    <ul class="marbox mb50">
        <li>
            <div class="order-thumbnail h-content-container">
            <div class="order-thumbnail h-content-container list-goods-item guess-goods-item">
                <div class="order-thumbnail-head font-color-pink">
                    猜你喜欢
                </div>
                <div class="order-thumbnail-carousel mt-content goods-tc" id="favGoods">
                    <div class="order-thumbnail-carousel-lt font-color-gray">&lt;</div>
                    <div class="order-thumbnail-carousel-gt font-color-gray">&gt;</div>
                    
                </div>
            </div>

        </li>
    </ul>

</div>
<!-- content end -->
<script src="{$Think.JS_PATH}template.js" charset="utf-8"></script>
<!--猜你喜欢-->
<script id="favList" type="text/html">
    <div class="fl">
        <p class="box">
            <a href="{{goods_url}}">
                <img class="lazy" data-original="{{goods_image_main}}@w364_h220.png" width="360" height="216" alt="" src="{{goods_image_main}}@w364_h220.png" style="display: inline;">
            </a>
        </p>
        <div class="mt-wz">
            <p><a href="{{goods_url}}">{{goods_name}}</a></p>
            <p>
                <span>已售：<i>{{goods_sale_number}}</i>件</span>
                <span class="fr">抢购价：<i>￥{{goods_price}}</i></span>
            </p>
        </div>
    </div>
</script>
<script type="text/javascript">
    $(function(){
        $("img.lazy").lazyload({
            placeholder: "images/loading.gif",
            effect: "fadeIn",
        });

        //自动跳转
        var wait = document.getElementById('wait'),
                href = document.getElementById('href').href;
        var interval = setInterval(function(){
            var time = --wait.innerHTML;
            if(time <= 0) {
                location.href = href;
                clearInterval(interval);
            };
        }, 1000);

        //猜你喜欢
        $.ajax({
            url:'{:url("shop/goods/favGoods")}',
            async :false,
            type:'post',
            dataType:'json',
            success:function(result){
                    var list = '';
                    $.each(result, function(index, value) {
                        list += template('favList', value);
                    });
                    $('#favGoods').append(list);
            }
        })

    })

</script>
