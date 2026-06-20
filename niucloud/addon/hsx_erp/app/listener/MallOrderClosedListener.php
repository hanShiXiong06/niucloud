<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpOutboundService;
use think\facade\Log;

/**
 * 监听"商城关闭挂账订单"(phone_shop) → 反向回写 ERP。
 * 通道(ThinkPHP事件名): PhoneShopOrderClosedToErp
 * 口径: 仅未收款挂账单 → 作废未结应收 + 设备回可售(不碰应付/代卖)。
 * 故障隔离: 失败只记日志, 不影响商城关单。不回发商城(商城已关单, 防回环)。
 */
class MallOrderClosedListener
{
    public function handle($event)
    {
        try {
            $p = is_array($event) ? $event : (array)$event;
            $siteId     = (int)($p['site_id'] ?? 0);
            $outboundNo = (string)($p['outbound_no'] ?? '');
            $assetIds   = is_array($p['asset_ids'] ?? null) ? $p['asset_ids'] : [];
            $reason     = (string)($p['reason'] ?? '商城关单');
            if ($siteId <= 0 || $outboundNo === '') {
                return ['skipped' => true, 'reason' => 'missing_key'];
            }
            $res = (new ErpOutboundService())->returnByMallClose($siteId, $outboundNo, $assetIds, $reason);
            Log::write('[erp] 商城关单回写 outbound_no=' . $outboundNo . ' result=' . json_encode($res, JSON_UNESCAPED_UNICODE));
            return $res;
        } catch (\Throwable $e) {
            Log::error('[erp] 商城关单回写失败: ' . $e->getMessage());
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
