<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\diy;

/**
 * DIY 自定义链接监听器
 */
class DiyLinkListener
{
    /**
     * 自定义链接
     * @param array $data
     * @return array
     */
    public function handle($data = [])
    {
        return [
            [
                'key' => 'sd_xiaoyuan',
                'addon_title' => '校园帮',
                'title' => '校园帮',
                'child_list' => [
                    [
                        'name' => 'SD_XIAOYUAN_INDEX',
                        'title' => '首页',
                        'url' => '/addon/sd_xiaoyuan/pages/index/index',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_TASK_LIST',
                        'title' => '任务大厅',
                        'url' => '/addon/sd_xiaoyuan/pages/task/index',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_TASK_PUBLISH',
                        'title' => '发布任务',
                        'url' => '/addon/sd_xiaoyuan/pages/task/publish',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_GROUP_LIST',
                        'title' => '拼单大厅',
                        'url' => '/addon/sd_xiaoyuan/pages/group/index',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_GROUP_CREATE',
                        'title' => '发起拼单',
                        'url' => '/addon/sd_xiaoyuan/pages/group/create',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_SECONDHAND_LIST',
                        'title' => '二手市场',
                        'url' => '/addon/sd_xiaoyuan/pages/secondhand/index',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_SECONDHAND_PUBLISH',
                        'title' => '发布二手',
                        'url' => '/addon/sd_xiaoyuan/pages/secondhand/publish',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_LOSTFOUND_LIST',
                        'title' => '失物招领',
                        'url' => '/addon/sd_xiaoyuan/pages/lost_found/index',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_LOSTFOUND_PUBLISH',
                        'title' => '发布失物招领',
                        'url' => '/addon/sd_xiaoyuan/pages/lost_found/publish',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_RUNNER_INDEX',
                        'title' => '接单员中心',
                        'url' => '/addon/sd_xiaoyuan/pages/runner/index',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_RUNNER_APPLY',
                        'title' => '申请成为接单员',
                        'url' => '/addon/sd_xiaoyuan/pages/runner/apply',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_USER_CENTER',
                        'title' => '个人中心',
                        'url' => '/addon/sd_xiaoyuan/pages/user/index',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_ORDER_LIST',
                        'title' => '我的订单',
                        'url' => '/addon/sd_xiaoyuan/pages/order/list',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_TASK_MY',
                        'title' => '我的任务',
                        'url' => '/addon/sd_xiaoyuan/pages/task/my',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_GROUP_MY',
                        'title' => '我的拼单',
                        'url' => '/addon/sd_xiaoyuan/pages/group/my',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_MESSAGE_LIST',
                        'title' => '消息中心',
                        'url' => '/addon/sd_xiaoyuan/pages/message/index',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_WALLET',
                        'title' => '我的钱包',
                        'url' => '/addon/sd_xiaoyuan/pages/wallet/index',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_CAMPUS_AUTH',
                        'title' => '校园认证',
                        'url' => '/addon/sd_xiaoyuan/pages/campus/auth',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_SCHEDULE',
                        'title' => '我的课表',
                        'url' => '/addon/sd_xiaoyuan/pages/schedule/index',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_CREDIT',
                        'title' => '信誉分',
                        'url' => '/addon/sd_xiaoyuan/pages/credit/index',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_BUY_CREATE',
                        'title' => '帮我买',
                        'url' => '/addon/sd_xiaoyuan/pages/buy/create',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_SEND_CREATE',
                        'title' => '帮我送',
                        'url' => '/addon/sd_xiaoyuan/pages/send/create',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_EXPRESS_PICKUP',
                        'title' => '代取快递',
                        'url' => '/addon/sd_xiaoyuan/pages/express/pickup',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_PRINT_CREATE',
                        'title' => '帮打印',
                        'url' => '/addon/sd_xiaoyuan/pages/print/create',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_TRASH_CREATE',
                        'title' => '扔垃圾',
                        'url' => '/addon/sd_xiaoyuan/pages/trash/create',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_CARRY_CREATE',
                        'title' => '帮搬运',
                        'url' => '/addon/sd_xiaoyuan/pages/carry/create',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_CLEAN_CREATE',
                        'title' => '代清洁',
                        'url' => '/addon/sd_xiaoyuan/pages/clean/create',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_HELP_CREATE',
                        'title' => '帮帮忙',
                        'url' => '/addon/sd_xiaoyuan/pages/help/create',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_CONFESSION_INDEX',
                        'title' => '表白墙',
                        'url' => '/addon/sd_xiaoyuan/pages/confession/index',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_GAME_PUBLISH',
                        'title' => '游戏陪练',
                        'url' => '/addon/sd_xiaoyuan/pages/game/publish',
                        'is_share' => 0,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_HOUSE_INDEX',
                        'title' => '房屋租赁',
                        'url' => '/addon/sd_xiaoyuan/pages/house/index',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_COMMUNITY_INDEX',
                        'title' => '校园树洞',
                        'url' => '/addon/sd_xiaoyuan/pages/community/index',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_ORDER_HALL',
                        'title' => '订单大厅',
                        'url' => '/addon/sd_xiaoyuan/pages/order/hall',
                        'is_share' => 1,
                        'action' => ''
                    ],
                    [
                        'name' => 'SD_XIAOYUAN_SEARCH',
                        'title' => '搜索',
                        'url' => '/addon/sd_xiaoyuan/pages/search/index',
                        'is_share' => 0,
                        'action' => ''
                    ],
                ]
            ]
        ];
    }
}
