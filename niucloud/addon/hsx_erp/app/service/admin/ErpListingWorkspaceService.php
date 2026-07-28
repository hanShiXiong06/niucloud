<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpAsset;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 销售资料工作台能力编排。
 *
 * ERP 只依赖事件契约，不直接引用拍照中台类。外部能力不可用时始终回退到
 * ERP 自带上传，保证插件卸载、停用或异常不会截断库存主流程。
 */
class ErpListingWorkspaceService extends BaseAdminService
{
    public function describe(int $assetId = 0): array
    {
        $rules = (new ErpConfigService())->getRules();
        $config = (array)($rules['listing_workspace'] ?? []);
        $external = [];
        try {
            foreach ((array)event('HsxErpListingMediaCapability', [
                'action' => 'describe',
                'site_id' => (int)$this->site_id,
                'asset_id' => $assetId,
            ]) as $capability) {
                if (!is_array($capability) || empty($capability['provider'])) continue;
                $external[(string)$capability['provider']] = $capability;
            }
        } catch (\Throwable) {
            // 能力发现失败不能影响 ERP 主流程。
        }

        $requested = (string)($config['media_provider'] ?? 'auto');
        $deviceAssetReady = !empty($external['device_asset']['available']);
        $selected = match ($requested) {
            'device_asset' => $deviceAssetReady ? 'device_asset' : 'erp',
            'erp' => 'erp',
            default => $deviceAssetReady ? 'device_asset' : 'erp',
        };
        $degraded = $requested === 'device_asset' && !$deviceAssetReady;

        return [
            'mode' => (string)($config['mode'] ?? 'one_stop'),
            'mode_name' => match ((string)($config['mode'] ?? 'one_stop')) {
                'split' => '专业分工',
                'photo_price' => '拍摄定价合并',
                default => '一站式录入',
            },
            'requested_provider' => $requested,
            'media_provider' => $selected,
            'media_provider_name' => $selected === 'device_asset' ? '标准化拍照中台' : 'ERP 普通上传',
            'degraded' => $degraded ? 1 : 0,
            'degraded_message' => $degraded ? '拍照中台当前不可用，已自动切换为 ERP 普通上传' : '',
            'auto_publish' => (int)($config['auto_publish'] ?? 0),
            'fallback_to_erp' => 1,
            'providers' => array_values(array_merge([[
                'provider' => 'erp',
                'name' => 'ERP 普通上传',
                'available' => 1,
                'features' => ['image_upload', 'video_upload', 'sale_price'],
            ]], $external)),
        ];
    }

    public function prepareMedia(int $assetId): array
    {
        $asset = ErpAsset::where([
            ['site_id', '=', (int)$this->site_id],
            ['id', '=', $assetId],
        ])->findOrEmpty();
        if ($asset->isEmpty()) throw new CommonException('库存设备不存在');
        if ((string)$asset->status !== ErpDict::ASSET_IN_STOCK) throw new CommonException('只有库存中的设备可以整理销售资料');

        $workspace = $this->describe($assetId);
        if ((string)$workspace['media_provider'] !== 'device_asset') {
            return $workspace + [
                'prepared' => true,
                'provider' => 'erp',
                'message' => (string)($workspace['degraded_message'] ?: '请直接在当前 ERP 工作台上传图片和填写销售价格'),
            ];
        }

        $event = [
            'action' => 'ensure_task',
            'site_id' => (int)$this->site_id,
            'asset_id' => $assetId,
            'operator' => ['id' => (int)$this->uid, 'name' => (string)$this->username],
            'asset' => $asset->toArray(),
        ];
        try {
            foreach ((array)event('HsxErpListingMediaCapability', $event) as $result) {
                if (!is_array($result) || (string)($result['provider'] ?? '') !== 'device_asset') continue;
                if (!empty($result['prepared']) && empty($result['error'])) {
                    return $workspace + $result;
                }
            }
        } catch (\Throwable $e) {
            $event['failure_message'] = $e->getMessage();
        }

        return array_merge($workspace, [
            'media_provider' => 'erp',
            'media_provider_name' => 'ERP 普通上传',
            'degraded' => 1,
            'prepared' => true,
            'provider' => 'erp',
            'message' => '拍照中台暂时不可用，已降级为 ERP 普通上传，不影响继续录入',
        ]);
    }
}
