<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\SignConfig;

/**
 * 签到配置（按站点一行 JSON，admin/api 共用）
 */
class SignConfigCoreService
{
    public function defaultRewards(): array
    {
        return [
            ['day' => 1, 'points' => 10, 'coupon_id' => 0],
            ['day' => 2, 'points' => 20, 'coupon_id' => 0],
            ['day' => 3, 'points' => 30, 'coupon_id' => 0],
            ['day' => 4, 'points' => 40, 'coupon_id' => 0],
            ['day' => 5, 'points' => 50, 'coupon_id' => 0],
            ['day' => 6, 'points' => 60, 'coupon_id' => 0],
            ['day' => 7, 'points' => 100, 'coupon_id' => 0],
        ];
    }

    public function getRewardsList(int $site_id): array
    {
        $row = (new SignConfig())->where('site_id', $site_id)->find();
        if (!$row) {
            return $this->defaultRewards();
        }
        $data = is_array($row) ? $row : $row->toArray();
        $raw = $data['continuous_rewards'] ?? '';
        if ($raw === '' || $raw === null) {
            return $this->defaultRewards();
        }
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) {
                return $this->defaultRewards();
            }
        }
        if (empty($decoded)) {
            return $this->defaultRewards();
        }
        return $this->formatRewardsList($decoded);
    }

    public function saveRewardsList(int $site_id, array $config): void
    {
        $list = $this->formatRewardsList($config);
        if (empty($list)) {
            $list = $this->defaultRewards();
        }
        $now = time();
        $baseReward = (int)($list[0]['points'] ?? 10);
        $json = json_encode($list, JSON_UNESCAPED_UNICODE);

        (new SignConfig())->where('site_id', $site_id)->delete();

        SignConfig::create([
            'site_id' => $site_id,
            'base_reward' => $baseReward,
            'continuous_rewards' => $json,
            'status' => 1,
            'create_time' => $now,
            'update_time' => $now,
        ]);
    }

    public function getDayReward(int $site_id, int $day): array
    {
        $list = $this->getRewardsList($site_id);
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((int)($item['day'] ?? 0) === $day) {
                return [
                    'points' => (int)($item['points'] ?? 10),
                    'coupon_id' => (int)($item['coupon_id'] ?? 0),
                ];
            }
        }
        $defaultPoints = [1 => 10, 2 => 20, 3 => 30, 4 => 40, 5 => 50, 6 => 60, 7 => 100];
        return [
            'points' => $defaultPoints[$day] ?? 10,
            'coupon_id' => 0,
        ];
    }

    private function formatRewardsList($decoded): array
    {
        if (!is_array($decoded)) {
            return $this->defaultRewards();
        }
        $list = [];
        if (isset($decoded[0]) && is_array($decoded[0])) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $d = (int)($item['day'] ?? 0);
                if ($d < 1 || $d > 7) {
                    continue;
                }
                $list[$d] = [
                    'day' => $d,
                    'points' => (int)($item['points'] ?? 10),
                    'coupon_id' => (int)($item['coupon_id'] ?? 0),
                ];
            }
        } else {
            for ($i = 1; $i <= 7; $i++) {
                $val = $decoded[(string)$i] ?? $decoded[$i] ?? null;
                $points = 10 * $i;
                if (is_array($val)) {
                    $points = (int)($val['points'] ?? $points);
                } elseif ($val !== null && $val !== '') {
                    $points = (int)$val;
                }
                $list[$i] = [
                    'day' => $i,
                    'points' => $points,
                    'coupon_id' => 0,
                ];
            }
        }
        $defaults = $this->defaultRewards();
        $out = [];
        for ($i = 1; $i <= 7; $i++) {
            $out[] = $list[$i] ?? $defaults[$i - 1];
        }
        return $out;
    }
}
