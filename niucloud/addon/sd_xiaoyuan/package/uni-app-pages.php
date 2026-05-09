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
                        "navigationBarTitleText": "校园服务",
                        "navigationStyle": "custom"
                    }
                },
                {
                    "path": "pages/index/diy",
                    "style": {
                        "navigationBarTitleText": "校园服务",
                        "navigationStyle": "custom"
                    }
                },
                {
                    "path": "pages/campus/auth",
                    "style": {
                        "navigationBarTitleText": "校园认证"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/school/select",
                    "style": {
                        "navigationBarTitleText": "选择学校",
                        "navigationStyle": "custom"
                    }
                },
                {
                    "path": "pages/search/index",
                    "style": {
                        "navigationBarTitleText": "搜索"
                    }
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
                    "path": "pages/order/detail",
                    "style": {
                        "navigationBarTitleText": "订单详情"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/order/hall",
                    "style": {
                        "navigationBarTitleText": "订单大厅",
                        "navigationStyle": "custom"
                    }
                },
                {
                    "path": "pages/order/publish",
                    "style": {
                        "navigationBarTitleText": "发布任务"
                    }
                },
                {
                    "path": "pages/order/evaluate",
                    "style": {
                        "navigationBarTitleText": "评价订单"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/index",
                    "style": {
                        "navigationBarTitleText": "跑腿员中心",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/apply",
                    "style": {
                        "navigationBarTitleText": "申请成为跑腿员"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/agreement",
                    "style": {
                        "navigationBarTitleText": "跑腿员协议"
                    }
                },
                {
                    "path": "pages/runner/order-hall",
                    "style": {
                        "navigationBarTitleText": "接单大厅"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/my-orders",
                    "style": {
                        "navigationBarTitleText": "我的接单"
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
                        "navigationBarTitleText": "收入明细"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/evaluates",
                    "style": {
                        "navigationBarTitleText": "我的评价"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/settings",
                    "style": {
                        "navigationBarTitleText": "跑腿员设置"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/level",
                    "style": {
                        "navigationBarTitleText": "等级说明"
                    }
                },
                {
                    "path": "pages/runner/invite",
                    "style": {
                        "navigationBarTitleText": "邀请好友"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/runner/help",
                    "style": {
                        "navigationBarTitleText": "帮助中心"
                    }
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
                        "navigationBarTitleText": "提交申诉"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/address/list",
                    "style": {
                        "navigationBarTitleText": "地址管理"
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
                        "navigationStyle":"custom"
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
                    "path": "pages/carry/create",
                    "style": {
                        "navigationBarTitleText": "帮我取",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/help/create",
                    "style": {
                        "navigationBarTitleText": "帮我办",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/queue/create",
                    "style": {
                        "navigationBarTitleText": "帮我排队",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/print/create",
                    "style": {
                        "navigationBarTitleText": "帮我打印",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/clean/create",
                    "style": {
                        "navigationBarTitleText": "帮我打扫",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/trash/create",
                    "style": {
                        "navigationBarTitleText": "帮我扔垃圾",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/seat/create",
                    "style": {
                        "navigationBarTitleText": "帮我占座"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/express/pickup",
                    "style": {
                        "navigationBarTitleText": "快递代取",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/secondhand/index",
                    "style": {
                        "navigationBarTitleText": "二手市场"
                    }
                },
                {
                    "path": "pages/secondhand/detail",
                    "style": {
                        "navigationBarTitleText": "商品详情"
                    }
                },
                {
                    "path": "pages/secondhand/publish",
                    "style": {
                        "navigationBarTitleText": "发布商品"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/secondhand/my",
                    "style": {
                        "navigationBarTitleText": "我的发布"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/lost_found/index",
                    "style": {
                        "navigationBarTitleText": "失物招领"
                    }
                },
                {
                    "path": "pages/lost_found/detail",
                    "style": {
                        "navigationBarTitleText": "详情"
                    }
                },
                {
                    "path": "pages/lost_found/publish",
                    "style": {
                        "navigationBarTitleText": "发布信息"
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
                    "path": "pages/house/index",
                    "style": {
                        "navigationBarTitleText": "租房信息"
                    }
                },
                {
                    "path": "pages/house/detail",
                    "style": {
                        "navigationBarTitleText": "房源详情"
                    }
                },
                {
                    "path": "pages/house/publish",
                    "style": {
                        "navigationBarTitleText": "发布房源"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/house/my",
                    "style": {
                        "navigationBarTitleText": "我的发布"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/confession/index",
                    "style": {
                        "navigationBarTitleText": "表白墙"
                    }
                },
                {
                    "path": "pages/confession/detail",
                    "style": {
                        "navigationBarTitleText": "表白详情"
                    }
                },
                {
                    "path": "pages/confession/publish",
                    "style": {
                        "navigationBarTitleText": "发布表白"
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
                    "path": "pages/community/index",
                    "style": {
                        "navigationBarTitleText": "校园社区"
                    }
                },
                {
                    "path": "pages/community/detail",
                    "style": {
                        "navigationBarTitleText": "帖子详情"
                    }
                },
                {
                    "path": "pages/community/publish",
                    "style": {
                        "navigationBarTitleText": "发布帖子"
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
                    "path": "pages/game/index",
                    "style": {
                        "navigationBarTitleText": "游戏陪玩"
                    }
                },
                {
                    "path": "pages/game/detail",
                    "style": {
                        "navigationBarTitleText": "陪玩详情"
                    }
                },
                {
                    "path": "pages/game/publish",
                    "style": {
                        "navigationBarTitleText": "发布陪玩"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/parttime/create",
                    "style": {
                        "navigationBarTitleText": "兼职招聘"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/companion/create",
                    "style": {
                        "navigationBarTitleText": "约伴组局"
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
                    "path": "pages/group/index",
                    "style": {
                        "navigationBarTitleText": "拼团拼车"
                    }
                },
                {
                    "path": "pages/group/detail",
                    "style": {
                        "navigationBarTitleText": "拼团详情"
                    }
                },
                {
                    "path": "pages/group/create",
                    "style": {
                        "navigationBarTitleText": "发起拼团"
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
                    "path": "pages/sign/index",
                    "style": {
                        "navigationBarTitleText": "签到打卡"
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
                    "path": "pages/points/mall",
                    "style": {
                        "navigationBarTitleText": "积分商城"
                    }
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
                    "path": "pages/points/record",
                    "style": {
                        "navigationBarTitleText": "积分明细"
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
                    "path": "pages/coupon/list",
                    "style": {
                        "navigationBarTitleText": "我的优惠券"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/invite/index",
                    "style": {
                        "navigationBarTitleText": "邀请好友"
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
                    "path": "pages/invite/team",
                    "style": {
                        "navigationBarTitleText": "我的团队"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/message/index",
                    "style": {
                        "navigationBarTitleText": "消息中心",
                        "navigationStyle": "custom"
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
                    "path": "pages/notice/index",
                    "style": {
                        "navigationBarTitleText": "系统公告"
                    }
                },
                {
                    "path": "pages/guestbook/index",
                    "style": {
                        "navigationBarTitleText": "留言板"
                    }
                },
                {
                    "path": "pages/guestbook/my",
                    "style": {
                        "navigationBarTitleText": "我的留言"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/user/index",
                    "style": {
                        "navigationBarTitleText": "个人中心",
                        "navigationStyle": "custom"
                    },
                    "needLogin": true
                },
                {
                    "path": "pages/user/evaluates",
                    "style": {
                        "navigationBarTitleText": "我的评价"
                    },
                    "needLogin": true
                }
            ]
        },
        // PAGE_END
EOT
];