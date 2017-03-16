<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta name="renderer" content="webkit|ie-comp|ie-stand" />
    <link rel="stylesheet" href="/static/shop/css/base.css" media="screen" title="no title" charset="utf-8">
    <link rel="stylesheet" href="/static/shop/css/style.css" media="screen" title="no title" charset="utf-8">
    <script src="/static/shop/js/jquery-1.9.1.min.js" charset="utf-8"></script>
    <title>木筑网-错误页面</title>
    <!--[if lt IE 9]>
    <script src="/static/shop/js//html5shiv.min.js"></script>
    <![endif]-->
</head>
<body>
<div class="container">
    <ul class="list-box">
        <li class="goods-sort mt-suc-item mt-suc-error">
            <ul class="marbox">
                <li class="mt-xb">
                </li>
                <li class="mt-sc">
                    <p class="error"><?php echo htmlentities($message); ?></p>
                </li>
                <li class="mt-er">

                    页面自动 <a id="href" href="javascript:history.back(-1);">返回</a> 上一页等待时间： <b id="wait">5</b>
                </li>
                <li class="mt-btn">
                    <a href="<?php echo HTTP_SITE_HOST?>">返回首页</a>
                </li>
            </ul>
        </li>
    </ul>
</div>
<!-- content end -->
<script type="text/javascript">
    $(function(){
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
    })
</script>
<!-- content end -->
</body>
</html>
