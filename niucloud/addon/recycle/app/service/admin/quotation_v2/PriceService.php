<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation_v2;

use addon\recycle\app\model\quotation_v2\QuotationPrice;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 报价 2.0 价格调整
 */
class PriceService extends BaseAdminService
{
    public function adjust(int $id, array $data): bool
    {
        $info = (new QuotationPrice())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
        ])->findOrEmpty();
        if ($info->isEmpty()) {
            throw new CommonException('价格记录不存在');
        }

        $adjustType = (int)($data['adjust_type'] ?? $info['adjust_type'] ?? 1);
        $adjustValue = (float)($data['adjust_value'] ?? $info['adjust_value'] ?? 0);
        $crawlerPrice = (float)$info['crawler_price'];
        $finalPrice = $this->calcFinalPrice($crawlerPrice, $adjustType, $adjustValue);

        return $info->save([
            'adjust_type' => $adjustType,
            'adjust_value' => $adjustValue,
            'final_price' => $finalPrice,
            'locked' => (int)($data['locked'] ?? $info['locked'] ?? 0),
            'update_at' => time(),
        ]);
    }

    public function batchAdjust(array $data): array
    {
        $ids = array_values(array_filter(array_map('intval', $data['ids'] ?? [])));
        if (empty($ids)) {
            throw new CommonException('请选择价格记录');
        }

        $success = 0;
        foreach ($ids as $id) {
            $this->adjust($id, $data);
            $success++;
        }

        return ['success' => $success, 'total' => count($ids)];
    }

    private function calcFinalPrice(float $crawlerPrice, int $adjustType, float $adjustValue): float
    {
        if ($adjustType === 2) {
            return round($crawlerPrice * (1 + $adjustValue / 100), 2);
        }
        if ($adjustType === 3) {
            return round($adjustValue, 2);
        }

        return round($crawlerPrice + $adjustValue, 2);
    }
}
