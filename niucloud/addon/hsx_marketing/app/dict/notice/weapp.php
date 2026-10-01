<?php
declare(strict_types=1);

/*
基础信息
模版ID
n7Hj817ZU8QsyPUjw0Jq5g4S01tQJPKa3ErUuoDsiCM
模版编号
10770
标题
奖励金待领取通知
类目
信息查询
操作人
hs****sx 2026-10-01 添加
详细内容
活动名称
{{thing2.DATA}}
温馨提示
{{thing3.DATA}}
场景说明
用户完成任务后给的奖励通知。。
2.
基础信息
模版ID
hbGAtYKSOPgaWdVHoCskRn-ueZll04jzudak71MS9GU
模版编号
13943
标题
奖励到账通知
类目
信息查询
操作人
hs****sx 2026-10-01 添加
详细内容
活动名称
{{thing5.DATA}}
奖励说明
{{thing2.DATA}}
备注
{{thing4.DATA}}
结算状态
{{phrase7.DATA}}
场景说明
奖励到账通知。

3. 基础信息
模版ID
u61PagzEdp1CxfzZz-KfoE4XNEydgZtogmaV0XPKkUg
模版编号
73549
标题
奖励发放失败通知
类目
软件/建站/技术开发
操作人
hs****sx 2026-10-01 添加
详细内容
活动类型
{{thing1.DATA}}
温馨提示
{{thing2.DATA}}
场景说明
失败时候的通知。
4.
基础信息
模版ID
s-eFEEy81Ri6DWCTHurcXp77FxLksWWypD4MfmcoJes
模版编号
73881
标题
奖励到期提醒
类目
信息查询
操作人
hs****sx 2026-10-01 添加
详细内容
奖励值
{{number2.DATA}}
过期时间
{{time3.DATA}}
备注
{{thing4.DATA}}
场景说明
奖励到期提醒。

*/
// tid 是公共模板编号；上方长字符串是申请账号自己的模板 ID，不能跨 SaaS 站点硬编码复用。
// 本站模板 ID 由牛云「小程序 → 订阅消息 → 重新获取」保存到本站通知配置。
return [
    'hsx_marketing_reward_available' => [
        'tid' => '10770',
        'content' => [
            ['活动名称', '{campaign_name}', 'thing2'],
            ['温馨提示', '{notice_remark}', 'thing3'],
        ],
        'kid_list' => [2, 3],
        'scene_desc' => '任务奖励待领取提醒',
        'tips' => '公共模板 10770「奖励金待领取通知」，类目：信息查询。请确认本站小程序已开通该类目，再点“重新获取”并开启；不是填写其他小程序的模板 ID。',
    ],
    'hsx_marketing_reward_grant_success' => [
        'tid' => '13943',
        'content' => [
            ['活动名称', '{campaign_name}', 'thing5'],
            ['奖励说明', '{reward_summary}', 'thing2'],
            ['备注', '{notice_remark}', 'thing4'],
            ['结算状态', '{status_short}', 'phrase7'],
        ],
        'kid_list' => [5, 2, 4, 7],
        'scene_desc' => '任务奖励到账提醒',
        'tips' => '公共模板 13943「奖励到账通知」，类目：信息查询。按实际奖励到账结果通知；请在本站小程序重新获取模板并开启。',
    ],
    'hsx_marketing_reward_grant_failed' => [
        'tid' => '73549',
        'content' => [
            ['活动类型', '{activity_type}', 'thing1'],
            ['温馨提示', '{notice_remark}', 'thing2'],
        ],
        'kid_list' => [1, 2],
        'scene_desc' => '任务奖励发放失败提醒',
        'tips' => '公共模板 73549「奖励发放失败通知」，类目：软件/建站/技术开发。该类目与另外三条不同；获取失败时先核对本站小程序服务类目。',
    ],
    'hsx_marketing_reward_expiring' => [
        'tid' => '73881',
        'content' => [
            ['奖励值', '{reward_number}', 'number2'],
            ['过期时间', '{expire_time}', 'time3'],
            ['备注', '{notice_remark}', 'thing4'],
        ],
        'kid_list' => [2, 3, 4],
        'scene_desc' => '任务奖励到期提醒',
        'tips' => '公共模板 73881「奖励到期提醒」，类目：信息查询。奖励值只传数字；积分/成长值为总数，券等权益为份数，备注说明奖励内容。无到期时间不发送本提醒。',
    ],
];
