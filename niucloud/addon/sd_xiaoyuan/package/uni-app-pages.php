<?php
return [
    'pages' => <<<EOT
        // PAGE_BEGIN
        {
            "root": "addon/sd_xiaoyuan",
            "pages": [
               {
                    "path": "pages/index/index",
                    "style": {
                        "navigationBarTitleText": "校园帮",
                        "navigationStyle": "custom"
                    }
                },
                {
                    "path": "pages/index/diy",
                    "style": {
                        "navigationBarTitleText": "校园帮",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/coupon/list",
                    "style": {
                        "navigationBarTitleText": "优惠券"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/campus/auth",
                    "style": {
                        "navigationBarTitleText": "校园认证"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/credit/log",
                    "style": {
                        "navigationBarTitleText": "信誉分记录"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/order/create",
                    "style": {
                        "navigationBarTitleText": "发布订单"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/order/list",
                    "style": {
                        "navigationBarTitleText": "我的订单",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/order/hall",
                    "style": {
                        "navigationBarTitleText": "大厅",
                        "navigationStyle": "custom"
                    }
                },
                {
                    "path": "pages/order/detail",
                    "style": {
                        "navigationBarTitleText": "订单详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/order/evaluate",
                    "style": {
                        "navigationBarTitleText": "评价订单"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/group/index",
                    "style": {
                        "navigationBarTitleText": "拼单好饭"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/group/create",
                    "style": {
                        "navigationBarTitleText": "发起拼单"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/group/detail",
                    "style": {
                        "navigationBarTitleText": "拼单详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/address/list",
                    "style": {
                        "navigationBarTitleText": "我的地址"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/address/edit",
                    "style": {
                        "navigationBarTitleText": "编辑地址"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/address/select",
                    "style": {
                        "navigationBarTitleText": "选择地址",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/index",
                    "style": {
                        "navigationBarTitleText": "接单员中心",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/apply",
                    "style": {
                        "navigationBarTitleText": "申请成为接单员"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/order-hall",
                    "style": {
                        "navigationBarTitleText": "订单大厅"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/my-orders",
                    "style": {
                        "navigationBarTitleText": "我的订单"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/order-detail",
                    "style": {
                        "navigationBarTitleText": "订单详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/income",
                    "style": {
                        "navigationBarTitleText": "收益明细"
                    },
                    "needLogin": true
                },
                // {
                //     "path": "pages/runner/withdraw",
                //     "style": {
                //         "navigationBarTitleText": "提现"
                //     }
                // },
                // {
                //     "path": "pages/runner/withdraw-records",
                //     "style": {
                //         "navigationBarTitleText": "提现记录"
                //     }
                // },
                {
                    "path": "pages/runner/evaluates",
                    "style": {
                        "navigationBarTitleText": "我的评价"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/appeals",
                    "style": {
                        "navigationBarTitleText": "申诉记录"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/appeal-add",
                    "style": {
                        "navigationBarTitleText": "发起申诉"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/settings",
                    "style": {
                        "navigationBarTitleText": "接单设置"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/agreement",
                    "style": {
                        "navigationBarTitleText": "服务协议"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/help",
                    "style": {
                        "navigationBarTitleText": "帮助中心"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/user/index",
                    "style": {
                        "navigationBarTitleText": "个人中心",
                        "navigationStyle": "custom"
                    }
                },
                {
                    "path": "pages/user/evaluates",
                    "style": {
                        "navigationBarTitleText": "我的评价"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/message/index",
                    "style": {
                        "navigationBarTitleText": "消息",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/community/index",
                    "style": {
                        "navigationBarTitleText": "校园社区"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/confession/index",
                    "style": {
                        "navigationBarTitleText": "表白墙"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/schedule/index",
                    "style": {
                        "navigationBarTitleText": "我的课表"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/sign/index",
                    "style": {
                        "navigationBarTitleText": "每日签到"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/points/mall",
                    "style": {
                        "navigationBarTitleText": "积分商城"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/points/orders",
                    "style": {
                        "navigationBarTitleText": "兑换记录"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/points/order-detail",
                    "style": {
                        "navigationBarTitleText": "订单详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/invite/index",
                    "style": {
                        "navigationBarTitleText": "邀请有礼"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/invite/team",
                    "style": {
                        "navigationBarTitleText": "我的团队"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/task/index",
                    "style": {
                        "navigationBarTitleText": "任务互助"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/task/detail",
                    "style": {
                        "navigationBarTitleText": "任务详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/task/publish",
                    "style": {
                        "navigationBarTitleText": "发布任务"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/task/my",
                    "style": {
                        "navigationBarTitleText": "我的任务"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/house/index",
                    "style": {
                        "navigationBarTitleText": "房屋租赁"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/community/publish",
                    "style": {
                        "navigationBarTitleText": "发布帖子"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/community/detail",
                    "style": {
                        "navigationBarTitleText": "帖子详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/confession/publish",
                    "style": {
                        "navigationBarTitleText": "发表白"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/schedule/add",
                    "style": {
                        "navigationBarTitleText": "添加课程"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/schedule/edit",
                    "style": {
                        "navigationBarTitleText": "编辑课程"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/schedule/setting",
                    "style": {
                        "navigationBarTitleText": "课表设置"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/house/publish",
                    "style": {
                        "navigationBarTitleText": "发布房源"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/house/detail",
                    "style": {
                        "navigationBarTitleText": "房源详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/confession/detail",
                    "style": {
                        "navigationBarTitleText": "表白详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/sign/history",
                    "style": {
                        "navigationBarTitleText": "签到记录"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/house/my",
                    "style": {
                        "navigationBarTitleText": "我的房源"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/community/my",
                    "style": {
                        "navigationBarTitleText": "我的帖子"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/confession/my",
                    "style": {
                        "navigationBarTitleText": "我的表白"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/invite/poster",
                    "style": {
                        "navigationBarTitleText": "邀请海报"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/level",
                    "style": {
                        "navigationBarTitleText": "接单员等级"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/invite",
                    "style": {
                        "navigationBarTitleText": "邀请接单员"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/game/index",
                    "style": {
                        "navigationBarTitleText": "游戏陪玩"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/game/detail",
                    "style": {
                        "navigationBarTitleText": "陪玩详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/game/publish",
                    "style": {
                        "navigationBarTitleText": "发布陪玩"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/game/my",
                    "style": {
                        "navigationBarTitleText": "我的陪玩"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/secondhand/index",
                    "style": {
                        "navigationBarTitleText": "二手闲置",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/secondhand/detail",
                    "style": {
                        "navigationBarTitleText": "商品详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/secondhand/publish",
                    "style": {
                        "navigationBarTitleText": "发布闲置"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/secondhand/my",
                    "style": {
                        "navigationBarTitleText": "我的闲置"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/lost_found/index",
                    "style": {
                        "navigationBarTitleText": "失物招领"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/lost_found/detail",
                    "style": {
                        "navigationBarTitleText": "详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/lost_found/publish",
                    "style": {
                        "navigationBarTitleText": "发布"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/lost_found/my",
                    "style": {
                        "navigationBarTitleText": "我的发布"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/search/index",
                    "style": {
                        "navigationBarTitleText": "搜索",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/school/select",
                    "style": {
                        "navigationBarTitleText": "选择学校",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/express/pickup",
                    "style": {
                        "navigationBarTitleText": "代取快递",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/buy/create",
                    "style": {
                        "navigationBarTitleText": "帮我买",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/send/create",
                    "style": {
                        "navigationBarTitleText": "帮我送",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/print/create",
                    "style": {
                        "navigationBarTitleText": "帮打印",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/trash/create",
                    "style": {
                        "navigationBarTitleText": "扔垃圾",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/carry/create",
                    "style": {
                        "navigationBarTitleText": "帮搬运",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/help/create",
                    "style": {
                        "navigationBarTitleText": "帮帮忙",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/seat/create",
                    "style": {
                        "navigationBarTitleText": "代占座位",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/queue/create",
                    "style": {
                        "navigationBarTitleText": "代排队",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/clean/create",
                    "style": {
                        "navigationBarTitleText": "代清洁",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/notice/index",
                    "style": {
                        "navigationBarTitleText": "公告"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/message/order",
                    "style": {
                        "navigationBarTitleText": "订单消息"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/message/community",
                    "style": {
                        "navigationBarTitleText": "社区消息"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/message/comment",
                    "style": {
                        "navigationBarTitleText": "评论消息"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/guestbook/index",
                    "style": {
                        "navigationBarTitleText": "留言板",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/guestbook/my",
                    "style": {
                        "navigationBarTitleText": "我的留言"
                    },
                    "needLogin": true
                }
            ]
        },
        // PAGE_END
EOT
];