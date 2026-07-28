<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\core;

use addon\hsx_member_card\app\model\MemberCardItem;
use app\service\core\notice\NoticeService;
use think\facade\Log;

/**
 * 会员卡用户通知。
 *
 * 通知属于核销事务完成后的附加能力，发送失败不能反向影响已经成功的核销。
 */
final class MemberCardNoticeService
{
    public const REDEEM_SUCCESS = 'hsx_member_card_redeem_success';
    public const REDEEM_REVERSED = 'hsx_member_card_redeem_reversed';

    public function sendRedeemSuccess(array $redemption): void
    {
        $this->send(self::REDEEM_SUCCESS, $redemption);
    }

    public function sendRedeemReversed(array $redemption): void
    {
        $this->send(self::REDEEM_REVERSED, $redemption);
    }

    private function send(string $key, array $redemption): void
    {
        $siteId = (int)($redemption['site_id'] ?? 0);
        $redemptionId = (int)($redemption['id'] ?? 0);
        if ($siteId <= 0 || $redemptionId <= 0 || (int)($redemption['member_id'] ?? 0) <= 0) return;

        $item = MemberCardItem::where([
            ['site_id', '=', $siteId],
            ['id', '=', (int)($redemption['card_item_id'] ?? 0)],
        ])->field('usage_mode,remaining_times')->findOrEmpty()->toArray();

        try {
            NoticeService::send($siteId, $key, [
                'redemption_id' => $redemptionId,
                'remaining_times' => (string)($item['usage_mode'] ?? '') === 'unlimited'
                    ? '不限次数'
                    : (string)max(0, (int)($item['remaining_times'] ?? 0)),
            ]);
        } catch (\Throwable $e) {
            Log::warning(sprintf(
                '[hsx_member_card] notification failed: key=%s redemption_id=%d error=%s',
                $key,
                $redemptionId,
                $e->getMessage()
            ));
        }
    }
}
