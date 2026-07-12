<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\erp;

use addon\hsx_recycle\app\model\address\PhoneShopPaymentInfo;
use app\model\member\Member;

/** 向已授权站点的 ERP 提供会员收款方式，只返回业务展示所需字段。 */
class RecyclePaymentMethodsProvider
{
    public function handle(array $event): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $memberIds = array_values(array_unique(array_filter(array_map('intval', (array)($event['member_ids'] ?? [])))));
        if ($siteId <= 0 || empty($memberIds)) return [];

        $validMemberIds = Member::where([['site_id', '=', $siteId]])
            ->whereIn('member_id', $memberIds)->column('member_id');
        if (empty($validMemberIds)) return [];

        $rows = PhoneShopPaymentInfo::whereIn('member_id', $validMemberIds)
            ->field('member_id,pay_type,account,qrcode_image,is_default')
            ->order('is_default desc,id desc')->select()->toArray();
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['member_id']][] = [
                'pay_type' => trim((string)($row['pay_type'] ?? '')),
                'account' => trim((string)($row['account'] ?? '')),
                'qrcode_image' => trim((string)($row['qrcode_image'] ?? '')),
                'is_default' => (int)($row['is_default'] ?? 0),
            ];
        }
        return $result;
    }
}
