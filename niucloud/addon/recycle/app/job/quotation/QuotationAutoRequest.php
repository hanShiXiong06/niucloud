<?php
declare(strict_types=1);

namespace addon\recycle\app\job\quotation;

use addon\recycle\app\service\admin\quotation\QuotationConfigService;
use addon\recycle\app\service\admin\quotation\QuotationRequestService;
use core\base\BaseJob;
use think\facade\Log;

/**
 * 报价接口自动请求任务
 * Class QuotationAutoRequest
 * @package addon\recycle\app\job\quotation
 */
class QuotationAutoRequest extends BaseJob
{
    /**
     * 执行任务
     * @param array $params
     * @return void
     */
    public function doJob(array $params = []): void
    {
        try {
            $siteId = $params['site_id'] ?? 0;
            
            if (empty($siteId)) {
                Log::error('报价自动请求任务失败：缺少site_id参数');
                return;
            }

            // 获取所有启用的报价单配置
            $configService = new QuotationConfigService();
            $configs = $configService->getEnabledConfigs($siteId);

            if (empty($configs)) {
                Log::info('报价自动请求任务：站点' . $siteId . '没有启用的报价单配置');
                return;
            }

            $requestService = new QuotationRequestService();
            $successCount = 0;
            $failCount = 0;

            foreach ($configs as $config) {
                // 检查是否到了自动请求时间
                $requestTime = $config['request_time'] ?? '00:00';
                $currentTime = date('H:i');
                
                // 如果配置了特定时间，检查是否匹配
                if ($requestTime !== '00:00' && $currentTime !== $requestTime) {
                    continue;
                }

                try {
                    $requestService->sendRequest($config['id']);
                    $successCount++;
                    Log::info('报价自动请求成功', [
                        'site_id' => $siteId,
                        'config_id' => $config['id'],
                        'quotation_id' => $config['quotation_id'],
                        'price_name' => $config['price_name'],
                    ]);
                } catch (\Exception $e) {
                    $failCount++;
                    Log::error('报价自动请求失败', [
                        'site_id' => $siteId,
                        'config_id' => $config['id'],
                        'quotation_id' => $config['quotation_id'],
                        'price_name' => $config['price_name'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('报价自动请求任务完成', [
                'site_id' => $siteId,
                'total' => count($configs),
                'success' => $successCount,
                'fail' => $failCount,
            ]);
        } catch (\Exception $e) {
            Log::error('报价自动请求任务异常（站点：' . $siteId . '）', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 获取所有站点ID
     * @return array
     */
    protected function getAllSiteIds(): array
    {
        try {
            return \think\facade\Db::name('site')
                ->where('status', 1)
                ->column('site_id');
        } catch (\Exception $e) {
            Log::error('获取站点列表失败：' . $e->getMessage());
            return [];
        }
    }
}

