<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener;

/**
 * 自定义链接监听器
 */
class WapLinkListener
{
    public function handle($data)
    {
        return [
            'key' => 'sd_xiaoyuan',
            'title' => '校园帮',
            'child_list' => [
                [
                    'name' => 'sd_xiaoyuan_index',
                    'title' => '首页',
                    'url' => '/addon/sd_xiaoyuan/pages/index/index',
                ],
                [
                    'name' => 'sd_xiaoyuan_task_list',
                    'title' => '任务大厅',
                    'url' => '/addon/sd_xiaoyuan/pages/task/list',
                ],
                [
                    'name' => 'sd_xiaoyuan_task_publish',
                    'title' => '发布任务',
                    'url' => '/addon/sd_xiaoyuan/pages/task/publish',
                ],
                [
                    'name' => 'sd_xiaoyuan_group_list',
                    'title' => '拼单大厅',
                    'url' => '/addon/sd_xiaoyuan/pages/group/list',
                ],
                [
                    'name' => 'sd_xiaoyuan_secondhand_list',
                    'title' => '二手市场',
                    'url' => '/addon/sd_xiaoyuan/pages/secondhand/list',
                ],
                [
                    'name' => 'sd_xiaoyuan_lostfound_list',
                    'title' => '失物招领',
                    'url' => '/addon/sd_xiaoyuan/pages/lostfound/list',
                ],
                [
                    'name' => 'sd_xiaoyuan_runner_index',
                    'title' => '接单员中心',
                    'url' => '/addon/sd_xiaoyuan/pages/runner/index',
                ],
                [
                    'name' => 'sd_xiaoyuan_runner_apply',
                    'title' => '申请成为接单员',
                    'url' => '/addon/sd_xiaoyuan/pages/runner/apply',
                ],
                [
                    'name' => 'sd_xiaoyuan_user_center',
                    'title' => '个人中心',
                    'url' => '/addon/sd_xiaoyuan/pages/user/center',
                ],
                [
                    'name' => 'sd_xiaoyuan_order_list',
                    'title' => '我的订单',
                    'url' => '/addon/sd_xiaoyuan/pages/order/list',
                ],
                [
                    'name' => 'sd_xiaoyuan_message_list',
                    'title' => '消息中心',
                    'url' => '/addon/sd_xiaoyuan/pages/message/list',
                ],
                [
                    'name' => 'sd_xiaoyuan_wallet',
                    'title' => '我的钱包',
                    'url' => '/addon/sd_xiaoyuan/pages/wallet/index',
                ],
                [
                    'name' => 'sd_xiaoyuan_campus_auth',
                    'title' => '校园认证',
                    'url' => '/addon/sd_xiaoyuan/pages/auth/campus',
                ],
                [
                    'name' => 'sd_xiaoyuan_schedule',
                    'title' => '我的课表',
                    'url' => '/addon/sd_xiaoyuan/pages/schedule/index',
                ],
            ],
        ];
    }
}
