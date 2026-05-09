<?php

namespace addon\sd_xiaoyuan\app\adminapi\service;

use addon\sd_xiaoyuan\app\model\SignConfig;
use core\base\BaseAdminService;

/**
 * 签到配置服务
 */
class SignConfigService extends BaseAdminService
{
    /**
     * 获取签到配置
     */
    public function getConfig()
    {
        $list = (new SignConfig())->where('site_id', $this->siteId)
            ->order('day asc')
            ->select()
            ->toArray();

        if (empty($list)) {
            // 返回默认配置
            return [
                ['day' => 1, 'points' => 10, 'coupon_id' => 0],
                ['day' => 2, 'points' => 20, 'coupon_id' => 0],
                ['day' => 3, 'points' => 30, 'coupon_id' => 0],
                ['day' => 4, 'points' => 40, 'coupon_id' => 0],
                ['day' => 5, 'points' => 50, 'coupon_id' => 0],
                ['day' => 6, 'points' => 60, 'coupon_id' => 0],
                ['day' => 7, 'points' => 100, 'coupon_id' => 0]
            ];
        }

        return $list;
    }

    /**
     * 保存签到配置
     */
    public function saveConfig(array $config)
    {
        // 删除旧配置
        (new SignConfig())->where('site_id', $this->siteId)->delete();

        // 保存新配置
        foreach ($config as $item) {
            SignConfig::create([
                'site_id' => $this->siteId,
                'day' => $item['day'],
                'points' => $item['points'] ?? 0,
                'coupon_id' => $item['coupon_id'] ?? 0,
                'create_time' => time()
            ]);
        }

        return $this->success('保存成功');
    }

    /**
     * 获取指定天数的奖励配置
     */
    public function getDayReward(int $day)
    {
        $config = (new SignConfig())->where([
            ['site_id', '=', $this->siteId],
            ['day', '=', $day]
        ])->find();

        if (empty($config)) {
            // 返回默认奖励
            $defaultPoints = [1 => 10, 2 => 20, 3 => 30, 4 => 40, 5 => 50, 6 => 60, 7 => 100];
            return [
                'points' => $defaultPoints[$day] ?? 10,
                'coupon_id' => 0
            ];
        }

        return [
            'points' => $config->points,
            'coupon_id' => $config->coupon_id
        ];
    }
}
