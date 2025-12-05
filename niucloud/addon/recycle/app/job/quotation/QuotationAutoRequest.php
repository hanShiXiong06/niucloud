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
            // 如果指定了site_id，只处理该站点；否则处理所有站点
            $siteIds = [];
            if (!empty($params['site_id'])) {
                $siteIds = [$params['site_id']];
            } else {
                // 自动获取所有启用的站点
                $siteIds = $this->getAllSiteIds();
            }

            if (empty($siteIds)) {
                Log::info('报价自动请求任务：没有可用的站点');
                return;
            }

            $totalSuccessCount = 0;
            $totalFailCount = 0;
            $totalConfigs = 0;

            // 遍历所有站点
            foreach ($siteIds as $siteId) {
                try {
                    // 获取该站点所有启用的报价单配置
                    $configService = new QuotationConfigService();
                    $configs = $configService->getEnabledConfigs($siteId);

                    if (empty($configs)) {
                        Log::info('报价自动请求任务：站点' . $siteId . '没有启用的报价单配置');
                        continue;
                    }

                    $requestService = new QuotationRequestService();
                    $successCount = 0;
                    $failCount = 0;

                    foreach ($configs as $config) {
                        $totalConfigs++;
                        
                        // 检查是否到了自动请求时间
                        $requestTime = $config['request_time'] ?? '00:00';
                        $currentTime = date('H:i');
                        
                        // 如果配置了特定时间，检查是否匹配
                        if ($requestTime !== '00:00' && $currentTime !== $requestTime) {
                            Log::info('报价自动请求任务：配置' . $config['id'] . '未到请求时间（配置时间：' . $requestTime . '，当前时间：' . $currentTime . '）');
                            continue;
                        }

                        try {
                            $requestService->sendRequest($config['id'] , $siteId);
                            $successCount++;
                            $totalSuccessCount++;
                            Log::info('报价自动请求成功', [
                                'site_id' => $siteId,
                                'config_id' => $config['id'],
                                'quotation_id' => $config['quotation_id'],
                                'price_name' => $config['price_name'],
                            ]);
                        } catch (\Exception $e) {
                            $failCount++;
                            $totalFailCount++;
                            Log::error('报价自动请求失败', [
                                'site_id' => $siteId,
                                'config_id' => $config['id'],
                                'quotation_id' => $config['quotation_id'],
                                'price_name' => $config['price_name'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    Log::info('报价自动请求任务完成（站点：' . $siteId . '）', [
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

            Log::info('报价自动请求任务全部完成', [
                'total_sites' => count($siteIds),
                'total_configs' => $totalConfigs,
                'total_success' => $totalSuccessCount,
                'total_fail' => $totalFailCount,
            ]);
        } catch (\Exception $e) {
            Log::error('报价自动请求任务异常', [
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

