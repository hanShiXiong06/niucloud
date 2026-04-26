<?php
return [
    'pages' => <<<EOT
            // WJ_BOOKS_PAGE_BEGIN
            // *********************************** wj_books ***********************************
            {
                "root": "addon/wj_books",
                "pages": [
                    {
					   "path": "pages/home/index",
					   "style": {
						   "navigationBarTitleText": "%pages.home.index%"
					   }
				   },
                    {
                        "path": "pages/order/index",
                        "style": {
                            "navigationBarTitleText": "创建回收订单"
                        }
                    },
                    {
                        "path": "pages/address/index",
                        "style": {
                            "navigationBarTitleText": "地址管理"
                        }
                    },
                    {
                        "path": "pages/address/address_edit",
                        "style": {
                            "navigationBarTitleText": "编辑地址"
                        }
                    },
                    {
                        "path": "pages/order/success",
                        "style": {
                            "navigationBarTitleText": "下单成功"
                        }
                    },
                    {
                        "path": "pages/order/list",
                        "style": {
                            "navigationBarTitleText": "我的订单"
                        }
                    },
                    {
                        "path": "pages/order/detail",
                        "style": {
                            "navigationBarTitleText": "订单详情"
                        }
                    },
                    {
                        "path": "pages/home/my",
                        "style": {
                            "navigationBarTitleText": "个人中心"
                        }
                    }
                ]
            },
            // WJ_BOOKS_PAGE_END
EOT
];
