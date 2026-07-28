<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\listener\notice;

use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\model\MemberCardItem;
use addon\hsx_member_card\app\model\MemberCardRedemption;
use addon\hsx_member_card\app\service\core\MemberCardNoticeService;
use app\listener\notice_template\BaseNoticeTemplate;

/**
 * 核销与核销撤销通知数据。
 */
final class MemberCardRedeemNotice extends BaseNoticeTemplate
{
    public function handle(array $params)
    {
        $key = (string)($params['key'] ?? '');
        if (!in_array($key, [
            MemberCardNoticeService::REDEEM_SUCCESS,
            MemberCardNoticeService::REDEEM_REVERSED,
        ], true)) {
            return;
        }

        $redemptionId = (int)($params['data']['redemption_id'] ?? 0);
        $redemption = MemberCardRedemption::where('id', '=', $redemptionId)->findOrEmpty()->toArray();
        if ($redemption === []) return;

        $card = MemberCard::where([
            ['site_id', '=', (int)$redemption['site_id']],
            ['id', '=', (int)$redemption['card_id']],
        ])->field('product_name')->findOrEmpty()->toArray();
        $item = MemberCardItem::where([
            ['site_id', '=', (int)$redemption['site_id']],
            ['id', '=', (int)$redemption['card_item_id']],
        ])->field('usage_mode,remaining_times')->findOrEmpty()->toArray();

        $remainingTimes = trim((string)($params['data']['remaining_times'] ?? ''));
        if ($remainingTimes === '') {
            $remainingTimes = (string)($item['usage_mode'] ?? '') === 'unlimited'
                ? '不限次数'
                : (string)max(0, (int)($item['remaining_times'] ?? 0));
        }
        $remainingText = $remainingTimes === '不限次数' ? $remainingTimes : $remainingTimes . '次';
        $isReversed = $key === MemberCardNoticeService::REDEEM_REVERSED;
        $siteId = (int)$redemption['site_id'];
        $page = 'addon/hsx_member_card/pages/member/detail?id=' . (int)$redemption['card_id'];
        $wapDomain = get_wap_domain($siteId);

        return $this->toReturn([
            '__wechat_page' => $wapDomain . '/' . $page,
            '__weapp_page' => $page,
            'card_name' => (string)($card['product_name'] ?? '会员卡'),
            'item_name' => (string)($redemption['item_name'] ?? '会员权益'),
            'remaining_times' => $remainingTimes,
            'remaining_text' => $remainingText,
            'operation_result' => $isReversed ? '核销已撤销，次数已恢复' : '核销成功',
            'operation_time' => date(
                'Y-m-d H:i:s',
                (int)($isReversed ? ($redemption['reversed_at'] ?? 0) : ($redemption['occurred_at'] ?? 0)) ?: time()
            ),
            'operator_name' => (string)($isReversed
                ? ($redemption['reverse_name'] ?? '')
                : ($redemption['operator_name'] ?? '')),
            'url' => $wapDomain . '/' . $page,
        ], [
            'member_id' => (int)$redemption['member_id'],
        ]);
    }
}
