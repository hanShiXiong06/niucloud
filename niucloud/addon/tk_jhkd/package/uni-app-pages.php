<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
			"root": "addon/tk_jhkd",
			"pages": [
				{
					"path": "pages/address/address",
					"style": {
						"navigationBarTitleText": "地址列表"
					},
					"needLogin": true
				},
				{
					"path": "pages/address/address_edit",
					"style": {
						"navigationBarTitleText": "快递地址"
					},
					"needLogin": true
				},
				{
					"path": "pages/address/location_address_edit",
					"style": {
						"navigationBarTitleText": "同城地址"
					},
					"needLogin": true
				},
				{
					"path": "pages/agreement",
					"style": {
						"navigationBarTitleText": "协议详情"
					},
					"needLogin": true
				},
				{
					"path": "pages/help",
					"style": {
						"navigationBarTitleText": "帮助中心"
					},
					"needLogin": false
				},
				{
					"path": "pages/ordersubmit",
					"style": {
						"navigationBarTitleText": "快递下单"
					},
					"needLogin": false
				},
				{
					"path": "pages/orderlist",
					"style": {
						"navigationBarTitleText": "订单列表"
					},
					"needLogin": true
				},
				{
					"path": "pages/orderaddlist",
					"style": {
						"navigationBarTitleText": "补单列表"
					},
					"needLogin": true
				},
				{
					"path": "pages/orderdetail",
					"style": {
						"navigationBarTitleText": "订单详情"
					},
					"needLogin": true
				},
				{
					"path": "pages/index",
					"style": {
						"navigationBarTitleText": "首页",
						// #ifndef H5
						"navigationStyle": "custom",
						"app-plus": {
							"titleView": false
						}
						// #endif 
					}
				},
				{
					"path": "pages/member",
					"style": {
						"navigationBarTitleText": "个人中心",
						// #ifndef H5
						"navigationStyle": "custom",
						"app-plus": {
							"titleView": false
						}
						// #endif 
					}
				},
				{
					"path": "pages/fenxiao/index",
					"style": {
						"navigationBarTitleText": "分销中心"
					},
					"needLogin": true
				},
				{
					"path": "pages/fenxiao/member",
					"style": {
						"navigationBarTitleText": "分销会员"
					},
					"needLogin": true
				},
				{
					"path": "pages/fenxiao/order",
					"style": {
						"navigationBarTitleText": "分销推广订单"
					},
					"needLogin": true
				},
				{
					"path": "pages/fenxiao/orderself",
					"style": {
						"navigationBarTitleText": "自购推广订单"
					},
					"needLogin": true
				},
				{
					"path": "pages/coupon/list",
					"style": {
						"navigationBarTitleText": "优惠券列表",
						// #ifndef H5
						"navigationStyle": "custom",
						"app-plus": {
							"titleView": false
						}
						// #endif 
					},
					"needLogin": true
				},
				{
					"path": "pages/coupon/detail",
					"style": {
						"navigationBarTitleText": "优惠券详情",
						// #ifndef H5
						"navigationStyle": "custom",
						"app-plus": {
							"titleView": false
						}
						// #endif 
					},
					"needLogin": true
				}
			],
			"plugins": {
				"express-plugin": {
					"version": "latest",
					"provider": "wx1b1f66316b427c74"
				}
			}
		},
// PAGE_END
EOT
];