<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\dict;

final class MarketingDict
{
    public const CAMPAIGN_DRAFT = 0;
    public const CAMPAIGN_ACTIVE = 1;
    public const CAMPAIGN_PAUSED = 2;
    public const CAMPAIGN_ENDED = 3;

    public const CLAIM_RUNNING = 'running';
    public const CLAIM_COMPLETED = 'completed';
    public const CLAIM_REWARDED = 'rewarded';
    public const CLAIM_EXPIRED = 'expired';

    public const REWARD_CLAIMABLE = 'claimable';
    public const REWARD_PENDING = 'pending';
    public const REWARD_PROCESSING = 'processing';
    public const REWARD_SUCCESS = 'success';
    public const REWARD_FAILED = 'failed';
    public const REWARD_EXPIRED = 'expired';

    public static function campaignStatuses(): array
    {
        return [0 => '草稿', 1 => '进行中', 2 => '已暂停', 3 => '已结束'];
    }

    public static function factOptions(): array
    {
        return [
            ['key' => 'recycle_device_delivered', 'name' => '回收设备完成交货', 'unit' => '台', 'source_plugin' => 'hsx_recycle'],
            ['key' => 'phone_shop_order_paid', 'name' => '商城订单完成付款', 'unit' => '单', 'source_plugin' => 'phone_shop', 'disabled' => true],
            ['key' => 'member_card_redeemed', 'name' => '会员卡完成核销', 'unit' => '次', 'source_plugin' => 'hsx_member_card', 'disabled' => true],
        ];
    }
}
