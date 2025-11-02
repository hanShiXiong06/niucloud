<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
		{
			"root": "addon/home_service/technician",
			"pages": [
				// *********************************** 技师端 ***********************************
				{
				    "path": "pages/index",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.technician.pages.index%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/order/index",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.technician.pages.order.index%"
				    }
				},
				{
				    "path": "pages/order/detail",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.technician.pages.order.detail%"
				    }
				},
				{
				    "path": "pages/order/grabOrder",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.technician.pages.order.grabOrder%"
				    }
				},
				{
				    "path": "pages/order/additional",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.technician.pages.order.additional%"
				    }
				},
				{
				    "path": "pages/member/index",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.index%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/notice/index",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.notice.index%"
				    },
					"needLogin": true
				},

				{
				    "path": "pages/member/account/account_statement",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.member.account.account_statement%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/account/list",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.member.account.list%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/order/statistics",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.member.order.statistics%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/rest/index",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.member.rest.index%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/ranking_list/ranking_list",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.ranking_list.ranking_list%"
				    },
					"needLogin": true
				},

				{
				    "path": "pages/member/help/help",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.help.help%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/help/detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.help.detail%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/feedback/feedback",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.feedback.feedback%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/account/withdraw",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.account.withdraw%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/account/withdraw_list",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.account.withdraw_list%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/account/withdraw_edit",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.account.withdraw_edit%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.detail%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/store/detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.store.detail%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/order/evaluate",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.order.evaluate%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/cash/cash_out",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.cash.cash_out%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/cash/cash_out_detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.member.cash.cash_out_detail%"
				    },
					"needLogin": true
				},
                {
				    "path": "pages/auth/login",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.auth.login%"
				    },
					"needLogin": false
				},
                {
				    "path": "pages/auth/register",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.auth.register%"
				    },
					"needLogin": false
				}
			]
		},
		{
		    "root": "addon/home_service/store",
		    "pages": [
				// *********************************** 门店端 ***********************************
				{
				    "path": "pages/index",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.store.pages.index%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/store/index",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.store.pages.store.index%"
				    },
					"needLogin": true
				},
                {
				    "path": "pages/store/settle",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.store.pages.store.settle%"
				    },
					"needLogin": true
				},
                {
				    "path": "pages/store/submit_success",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.store.submit_success%"
				    },
				    "needLogin": true
				},


				{
				    "path": "pages/member/index",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.store.pages.member.index%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/technician/index",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.technician.index%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/technician/detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.technician.detail%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/technician/rest",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.technician.rest%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/evaluate",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.member.evaluate%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/store_info",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.member.store_info%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/store/account/account_statement",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.store.account.account_statement%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/store/account/list",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.store.account.list%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/store/account/order_statistics",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.store.account.order_statistics%"
				    },
					"needLogin": true
				},

				{
				    "path": "pages/store/account/withdraw",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.store.account.withdraw%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/store/account/withdraw_list",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.store.account.withdraw_list%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/store/account/withdraw_edit",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.store.account.withdraw_edit%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/store_list",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.member.store_list%"
				    },
					"needLogin": true
				},

				{
				    "path": "pages/member/help/help",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.member.help.help%"
				    },
					"needLogin": true
				},
              {
				    "path": "pages/member/feedback/feedback",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.member.feedback.feedback%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/help/detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.member.help.detail%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/order/grapOrder",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.order.grapOrder%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/order/dispatchOrder",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.order.dispatchOrder%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/order/dispatchTechnician",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.order.dispatchTechnician%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/order/detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.order.detail%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/cash/cash_out",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.member.cash.cash_out%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/cash/cash_out_detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.store.pages.member.cash.cash_out_detail%"
				    },
					"needLogin": true
				},
                 {
				    "path": "pages/auth/login",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.auth.login%"
				    },
					"needLogin": false
				},
                {
				    "path": "pages/auth/register",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.technician.pages.auth.register%"
				    },
					"needLogin": false
				}
            ]
		},


		{
		    "root": "addon/home_service/user",
		    "pages": [
				// *********************************** 会员端 ***********************************
				{
				    "path": "pages/index",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.user.pages.index%"
				    }
				},
				{
				    "path": "pages/goods/list",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.user.pages.goods.list%"
				    }
				},
				{
				    "path": "pages/goods/detail",
				    "style": {
                        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.user.pages.goods.detail%"
				    }
				},
				{
				    "path": "pages/goods/category",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.user.pages.goods.category%"
				    }
				},
				{
				    "path": "pages/goods/evaluate",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.goods.evaluate%"
				    }
				},
				{
				    "path": "pages/member/index",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.user.pages.member.index%"
				    }
				},
                 {
				    "path": "pages/member/collect/goods",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.member.collect.goods%"
				    },
					"needLogin": true
				},
				{
				    "path": "pages/member/history",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.member.history%"
				    }
				},
				{
				    "path": "pages/order/payment",
				    "style": {
				        // #ifndef H5
				        "navigationStyle": "custom",
				        // #endif
				        "navigationBarTitleText": "%home_service.user.pages.order.payment%"
				    },
				    "needLogin": true
				},
				{
				    "path": "pages/order/list",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.order.list%"
				    },
				    "needLogin": true
				},
				{
				    "path": "pages/order/detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.order.detail%"
				    },
				    "needLogin": true
				},
				{
				    "path": "pages/order/evaluate/evaluate",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.order.evaluate.evaluate%"
				    },
				    "needLogin": true
				},
                {
				    "path": "pages/order/evaluate/list",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.order.evaluate.list%"
				    },
				    "needLogin": true
				},

				{
				    "path": "pages/order/refund/list",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.order.refund.list%"
				    },
				    "needLogin": true
				},
{
				    "path": "pages/order/refund/apply",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.order.refund.apply%"
				    },
				    "needLogin": true
				},

				{
				    "path": "pages/order/refund/detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.order.refund.detail%"
				    },
				    "needLogin": true
				},


				{
				    "path": "pages/address/index",
				    "style": {
                        // #ifndef H5
						"navigationStyle": "custom",
                        // #endif
				        "navigationBarTitleText": "%home_service.user.pages.address.index%"
				    },
				    "needLogin": true
				},
				{
				    "path": "pages/address/address_edit",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.address.address_edit%"
				    },
				    "needLogin": true
				},
                {
				    "path": "pages/member/help/help",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.member.help.help%"
				    },
				    "needLogin": true
				},
                {
				    "path": "pages/member/help/detail",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.member.help.detail%"
				    },
				    "needLogin": true
				},
                {
				    "path": "pages/member/feedback/feedback",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.member.feedback.feedback%"
				    },
				    "needLogin": true
				},
               {
				    "path": "pages/member/invoice/list",
				    "style": {
                        // #ifndef H5
						"navigationStyle": "custom",
                        // #endif
				        "navigationBarTitleText": "%home_service.user.pages.member.invoice.list%"
				    },
				    "needLogin": true
				},
                {
				    "path": "pages/member/invoice/apply",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.member.invoice.apply%"
				    },
				    "needLogin": true
				},
				{
				    "path": "pages/settle/technician",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.settle.technician%"
				    },
				    "needLogin": true
				},
				{
				    "path": "pages/settle/store",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.settle.store%"
				    },
				    "needLogin": true
				},
				{
				    "path": "pages/settle/store_form",
				    "style": {
                        // #ifndef H5
				        "navigationStyle": "custom",
                        // #endif
				        "navigationBarTitleText": "%home_service.user.pages.settle.store_form%"
				    },
				    "needLogin": true
				},
				{
				    "path": "pages/settle/technician_form",
				    "style": {
                        // #ifndef H5
				        "navigationStyle": "custom",
                        // #endif
				        "navigationBarTitleText": "%home_service.user.pages.settle.technician_form%"
				    },
				    "needLogin": true
				},
				{
				    "path": "pages/settle/submit_success",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.settle.submit_success%"
				    },
				    "needLogin": true
				},

                {
				    "path": "pages/coupon/coupon",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.coupon.coupon%"
				    },
				    "needLogin": true
				},
                 {
				    "path": "pages/coupon/member_coupon",
				    "style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
				        "navigationBarTitleText": "%home_service.user.pages.coupon.member_coupon%"
				    },
				    "needLogin": true
				},
				{
					"path": "pages/card/list",
					"style": {
                        // #ifndef H5
						"navigationStyle": "custom",
                        // #endif
						"navigationBarTitleText": "%home_service.user.pages.card.list%"
					},
					"needLogin": true
				},
				{
					"path": "pages/card/detail",
					"style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
						"navigationBarTitleText": "%home_service.user.pages.card.detail%"
					},
					"needLogin": true
				},
				{
					"path": "pages/card/my_card",
					"style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
						"navigationBarTitleText": "%home_service.user.pages.card.my_card%"
					},
					"needLogin": true
				},
				{
					"path": "pages/card/use_detail",
					"style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
						"navigationBarTitleText": "%home_service.user.pages.card.use_detail%"
					},
					"needLogin": true
				},
				{
					"path": "pages/card/use_card",
					"style": {
						// #ifndef H5
						"navigationStyle": "custom",
						// #endif
						"navigationBarTitleText": "%home_service.user.pages.card.use_card%"
					},
					"needLogin": true
				}
		    ]
		},
        {
            "root": "addon/home_service/components",
            "pages": []
        },
		{
		    "root": "addon/home_service/locale",
		    "pages": []
		},
		// PAGE_END
EOT
];