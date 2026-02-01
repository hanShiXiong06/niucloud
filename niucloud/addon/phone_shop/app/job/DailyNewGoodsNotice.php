<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\job;

use addon\phone_shop\app\model\goods\Goods;
use app\model\member\Member;
use app\service\core\notice\NoticeService;
use core\base\BaseJob;
use think\facade\Log;

/**
 * 每日新品通知定时任务
 */
class DailyNewGoodsNotice extends BaseJob
{
    /**
     * 执行定时任务
     * @return true
     */
    public function doJob()
    {
        try {
            // 1. 获取今日新上架的商品（status=1 且 create_time 是今天）
            $todayStart = strtotime(date('Y-m-d 00:00:00'));
            $todayEnd = strtotime(date('Y-m-d 23:59:59'));

            $goodsModel = new Goods();
            $todayGoods = $goodsModel->where([
                ['status', '=', 1],
                ['create_time', '>=', $todayStart],
                ['create_time', '<=', $todayEnd]
            ])->field('goods_id, goods_name, site_id')->select();

            if ($todayGoods->isEmpty()) {
                Log::write('每日新品通知：今日无新品上架', 'info');
                return true;
            }

            // 按站点分组
            $goodsBySite = [];
            foreach ($todayGoods as $goods) {
                $siteId = $goods['site_id'];
                if (!isset($goodsBySite[$siteId])) {
                    $goodsBySite[$siteId] = [];
                }
                $goodsBySite[$siteId][] = $goods;
            }

            // 2. 按站点推送通知
            $totalSuccess = 0;
            foreach ($goodsBySite as $siteId => $goods) {
                $successCount = $this->sendNoticeForSite($siteId, $goods);
                $totalSuccess += $successCount;
            }

            Log::write("每日新品通知推送完成，成功推送 {$totalSuccess} 人", 'info');
            return true;

        } catch (\Exception $e) {
            Log::write('每日新品通知推送失败：' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 为指定站点发送通知
     * @param int $siteId 站点ID
     * @param array $goods 商品列表
     * @return int 成功推送的用户数
     */
    private function sendNoticeForSite($siteId, $goods)
    {
        // 获取站点名称
        $siteName = $this->getSiteName($siteId);

        // 获取该站点所有关注了公众号的用户（有 wx_openid 的用户）
        $memberModel = new Member();
        $members = $memberModel->where([
            ['site_id', '=', $siteId],
            ['wx_openid', '<>', '']
        ])->field('member_id, site_id, wx_openid')->select();

        if ($members->isEmpty()) {
            Log::write("站点 {$siteId} 无关注用户", 'info');
            return 0;
        }

        // 格式化商品名称
        $goodsNames = array_column($goods, 'goods_name');
        $goodsNamesStr = implode('、', array_slice($goodsNames, 0, 3));
        if (count($goodsNames) > 3) {
            $goodsNamesStr .= '等';
        }

        // 批量发送通知
        $successCount = 0;
        foreach ($members as $member) {
            try {
                $result = NoticeService::send(
                    $member['site_id'],
                    'phone_shop_new_goods',
                    [
                        'member_id' => $member['member_id'],
                        'goods_count' => count($goods),
                        'goods_names' => $goodsNamesStr,
                        'update_time' => date('Y-m-d H:i:s'),
                        'site_name' => $siteName,
                    ]
                );

                if ($result) {
                    $successCount++;
                }
            } catch (\Exception $e) {
                Log::write("发送通知失败 member_id:{$member['member_id']}, error:{$e->getMessage()}", 'error');
            }
        }

        Log::write("站点 {$siteId} 推送完成，成功 {$successCount}/" . count($members) . " 人", 'info');
        return $successCount;
    }

    /**
     * 获取站点名称
     * @param int $siteId 站点ID
     * @return string 站点名称
     */
    private function getSiteName($siteId)
    {
        try {
            $site = \think\facade\Db::name('site')->where('site_id', $siteId)->field('site_name')->find();
            return $site['site_name'] ?? '';
        } catch (\Exception $e) {
            Log::write("获取站点名称失败 site_id:{$siteId}, error:{$e->getMessage()}", 'error');
            return '';
        }
    }
}
