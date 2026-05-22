<?php
/**
 * Erik Shop Admin API — hg/apidoc 配置
 */
return [
    "enable" => true,
    "apidoc" => [
        "title" => "Erik Shop — Admin API",
        "desc"  => "跨境电商平台管理后台接口文档",
        "apps" => [
            ["title"=>"商品管理","path"=>"plugin\admin\app\controller\shop","key"=>"product",
             "groups"=>[["name"=>"product","title"=>"商品"],["name"=>"category","title"=>"分类"],["name"=>"sku","title"=>"SKU"],["name"=>"review","title"=>"评价"]]],
            ["title"=>"订单管理","path"=>"plugin\admin\app\controller\shop","key"=>"order",
             "groups"=>[["name"=>"order","title"=>"订单"],["name"=>"payment","title"=>"支付"],["name"=>"refund","title"=>"退款"]]],
            ["title"=>"物流海关","path"=>"plugin\admin\app\controller\shop","key"=>"logistics",
             "groups"=>[["name"=>"logistics","title"=>"物流"],["name"=>"warehouse","title"=>"仓库"],["name"=>"customs","title"=>"海关"]]],
            ["title"=>"营销运营","path"=>"plugin\admin\app\controller\shop","key"=>"marketing",
             "groups"=>[["name"=>"coupon","title"=>"优惠券"],["name"=>"banner","title"=>"轮播图"],["name"=>"country","title"=>"国家"],["name"=>"currency","title"=>"货币"],["name"=>"setting","title"=>"配置"]]],
            ["title"=>"数据分析","path"=>"plugin\admin\app\controller\shop","key"=>"data",
             "groups"=>[["name"=>"dashboard","title"=>"仪表盘"],["name"=>"export","title"=>"导出"],["name"=>"operation","title"=>"操作日志"]]],
        ],
        "definitions" => "plugin\admin\app\common\Definitions",
        "auto_url" => ["letter_rule"=>"lcfirst","prefix"=>"/app/admin/shop"],
        "auto_register_routes"=>false,"cache"=>["enable"=>false],"auth"=>["enable"=>false],
        "params" => [
            "header" => [
                ["name"=>"Authorization","type"=>"string","require"=>false,"desc"=>"Session Cookie"],
                ["name"=>"X-Platform","type"=>"string","require"=>false,"default"=>"web","desc"=>"来源平台"],
            ],
        ],
        "responses" => [
            "success" => [["name"=>"code","desc"=>"0=成功","type"=>"int","require"=>1],["name"=>"msg","desc"=>"信息","type"=>"string","require"=>1],["name"=>"data","desc"=>"数据","main"=>true,"type"=>"object","require"=>1]],
            "error" => [["name"=>"code","desc"=>"1=失败","type"=>"int","require"=>1],["name"=>"msg","desc"=>"错误信息","type"=>"string","require"=>1],["name"=>"data","desc"=>"null","type"=>"null","require"=>0]],
        ],
        "default_author"=>"erik <erik@erik.xyz>","default_method"=>"GET","allowCrossDomain"=>true,
    ]
];