<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\job\schedule;

use addon\hsx_recycle\app\model\category\RecycleCategory;
use addon\hsx_recycle\app\model\category\RecycleCategoryQuoteHistory;
use core\base\BaseJob;
use think\facade\Log;

/**
 * 每日报价单自动快照
 * 遍历所有分类，如果当天没有快照记录则把当前 images 同步一条到历史表
 */
class QuoteDailySnapshot extends BaseJob
{
    public function doJob(array $params = []): void
    {
        try {
            $todayStart = strtotime(date('Y-m-d 00:00:00'));
            $todayEnd = strtotime(date('Y-m-d 23:59:59'));

            $categoryModel = new RecycleCategory();
            $historyModel = new RecycleCategoryQuoteHistory();

            $categories = $categoryModel
                ->where('images', '<>', '')
                ->whereNotNull('images')
                ->field('category_id, site_id, images')
                ->select()
                ->toArray();

            $count = 0;
            foreach ($categories as $cat) {
                $exists = $historyModel->where([
                    ['site_id', '=', $cat['site_id']],
                    ['category_id', '=', $cat['category_id']],
                    ['create_time', '>=', $todayStart],
                    ['create_time', '<=', $todayEnd],
                ])->findOrEmpty()->toArray();

                if (empty($exists)) {
                    $historyModel->create([
                        'site_id'       => $cat['site_id'],
                        'category_id'   => $cat['category_id'],
                        'images'        => $cat['images'],
                        'operator_id'   => 0,
                        'operator_name' => '系统自动快照',
                        'remark'        => '每日自动快照',
                        'create_time'   => time(),
                    ]);
                    $count++;
                }
            }

            Log::info("报价单每日快照完成，新增 {$count} 条记录");
        } catch (\Throwable $e) {
            Log::error('报价单每日快照失败：' . $e->getMessage());
        }
    }
}
