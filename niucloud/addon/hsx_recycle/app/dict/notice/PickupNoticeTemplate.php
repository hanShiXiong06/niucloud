<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\notice;

/** 代码字典：开发维护模板定义，站点统一使用框架获取模板、开关和日志。 */
final class PickupNoticeTemplate
{
    public const KEY = 'hsx_recycle_pickup_update';
    public const DETAIL_PAGE = 'addon/hsx_recycle/pages/order/detail';
    public const VARIABLES = [
        'order_no' => '回收订单编号', 'state_name' => '预约状态', 'carrier_name' => '快递公司',
        'pickup_time' => '预约时间', 'courier_name' => '快递员', 'courier_phone' => '快递员电话',
        'tracking_no' => '运单号', 'message' => '温馨提示', 'update_time' => '状态更新时间',
        'weapp_state' => '取件状态（小程序短文案）', 'weapp_tip' => '取件提示（小程序摘要）',
    ];
    public const STATES = [
        'confirmed' => '预约成功', 'assigned' => '已派快递员',
        'failed' => '预约失败', 'cancelled' => '预约取消', 'picked_up' => '已取件',
    ];

    // 复用本插件已有 recycle_order_add 的 30171 公共字段定义，只选订单号/状态/提示。
    // 独立业务 key、独立关键词组合，不覆盖原下单通知；实际账号类目及可用性由微信获取结果确认。
    public const WEAPP = [
        'tid' => '30171',
        'content' => [
            ['订单编号', '{order_no}', 'character_string1'],
            ['订单状态', '{weapp_state}', 'phrase2'],
            ['温馨提示', '{weapp_tip}', 'thing4'],
        ],
        'kid_list' => [1, 2, 4],
        'scene_desc' => '回收订单预约取件状态更新',
        'tips' => '沿用回收插件现有公共编号 30171，选取订单编号、订单状态、温馨提示。对应类目：商业服务 / 环保回收/废品回收；是否可获取以微信当前账号返回为准。取件时间、快递员电话及完整说明在订单详情查看。',
    ];
    // phrase 类型最多 5 个汉字；完整业务状态保留在 STATES 和订单详情，不直接塞进短字段。
    public const WEAPP_STATES = [
        'confirmed' => '预约成功', 'assigned' => '已派单', 'failed' => '预约失败',
        'cancelled' => '预约取消', 'picked_up' => '已取件',
    ];
    public const WEAPP_TIPS = [
        'confirmed' => '请查看预约时间并准备交件',
        'assigned' => '请查看取件员信息并保持电话畅通',
        'failed' => '上门预约失败，请进入原订单处理寄件',
        'cancelled' => '预约已取消，请在原订单处理寄件',
        'picked_up' => '快件已取走，请查看物流进度',
    ];
    // 公众号需独立核实 temp_key/content/keyword_name_list/tips。
    public const WECHAT = [];
    public const PENDING_MESSAGE = '此渠道的取件通知公共模板尚待开发方核实，当前未开放获取；无需管理员填写模板 ID 或字段映射。';

    public static function channel(string $channel): array
    {
        $template = ['weapp' => self::WEAPP, 'wechat' => self::WECHAT][$channel] ?? [];
        return self::isReady($channel, $template) ? [self::KEY => $template] : [];
    }

    public static function isReady(string $channel, array $template): bool
    {
        $idKey = $channel === 'weapp' ? 'tid' : 'temp_key';
        $fieldsKey = $channel === 'weapp' ? 'kid_list' : 'keyword_name_list';
        if (!in_array($channel, ['weapp', 'wechat'], true)
            || !is_scalar($template[$idKey] ?? null) || trim((string)$template[$idKey]) === ''
            || !is_array($template['content'] ?? null) || !$template['content']
            || !is_array($template[$fieldsKey] ?? null)
            || count($template['content']) !== count($template[$fieldsKey])
            || !empty($template['is_need_closure_content'])) return false;
        $keywords = [];
        foreach ($template['content'] as $row) {
            if (!is_array($row) || count($row) !== 3) return false;
            foreach ([0, 1, 2] as $index) {
                if (!isset($row[$index]) || !is_string($row[$index]) || trim($row[$index]) === '') return false;
            }
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $row[2]) || isset($keywords[$row[2]])) return false;
            $keywords[$row[2]] = true;
        }
        return true;
    }
}
