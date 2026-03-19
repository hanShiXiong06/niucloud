<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\RunnerLocation;
use addon\sd_xiaoyuan\app\model\Runner;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Cache;

/**
 * 接单员位置服务
 * 每5分钟更新位置，5分钟内获取缓存位置
 */
class RunnerLocationService extends BaseApiService
{
    // 位置缓存时间（秒）
    const CACHE_TIME = 300; // 5分钟

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 更新接单员位置
     */
    public function updateLocation(int $runnerId, float $latitude, float $longitude, string $address = '')
    {
        $cacheKey = "runner_location_{$this->site_id}_{$runnerId}";
        $now = time();

        // 检查是否需要更新（5分钟内不重复更新数据库）
        $lastUpdate = Cache::get($cacheKey . '_time');
        $needDbUpdate = !$lastUpdate || ($now - $lastUpdate) >= self::CACHE_TIME;

        // 更新缓存
        $locationData = [
            'runner_id' => $runnerId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address,
            'update_time' => $now
        ];
        Cache::set($cacheKey, $locationData, self::CACHE_TIME * 2);

        // 5分钟后才更新数据库
        if ($needDbUpdate) {
            $model = new RunnerLocation();
            $location = $model->where([
                ['site_id', '=', $this->site_id],
                ['runner_id', '=', $runnerId]
            ])->find();

            if ($location) {
                $location->save([
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'address' => $address,
                    'update_time' => $now
                ]);
            } else {
                RunnerLocation::create([
                    'site_id' => $this->site_id,
                    'runner_id' => $runnerId,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'address' => $address,
                    'update_time' => $now
                ]);
            }

            Cache::set($cacheKey . '_time', $now, self::CACHE_TIME * 2);
        }

        return true;
    }

    /**
     * 获取接单员位置（优先从缓存获取）
     */
    public function getLocation(int $runnerId)
    {
        $cacheKey = "runner_location_{$this->site_id}_{$runnerId}";

        // 先从缓存获取
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // 缓存没有，从数据库获取
        $location = (new RunnerLocation())->where([
            ['site_id', '=', $this->site_id],
            ['runner_id', '=', $runnerId]
        ])->find();

        if ($location) {
            $data = $location->toArray();
            Cache::set($cacheKey, $data, self::CACHE_TIME);
            return $data;
        }

        throw new CommonException('未找到接单员位置');
    }

    /**
     * 获取附近接单员列表
     */
    public function getNearbyRunners(float $latitude, float $longitude, float $radius = 5)
    {
        // 计算经纬度范围（简单计算，1度约111公里）
        $latRange = $radius / 111;
        $lngRange = $radius / (111 * cos(deg2rad($latitude)));

        $minLat = $latitude - $latRange;
        $maxLat = $latitude + $latRange;
        $minLng = $longitude - $lngRange;
        $maxLng = $longitude + $lngRange;

        $locations = (new RunnerLocation())->where([
            ['site_id', '=', $this->site_id],
            ['latitude', 'between', [$minLat, $maxLat]],
            ['longitude', 'between', [$minLng, $maxLng]],
            ['update_time', '>=', time() - self::CACHE_TIME * 2] // 10分钟内有更新的
        ])->select()->toArray();

        // 计算距离并排序
        foreach ($locations as &$loc) {
            $loc['distance'] = $this->calculateDistance($latitude, $longitude, $loc['latitude'], $loc['longitude']);
        }

        usort($locations, function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        // 过滤超出范围的
        $locations = array_filter($locations, function ($loc) use ($radius) {
            return $loc['distance'] <= $radius;
        });

        return array_values($locations);
    }

    /**
     * 计算两点距离（公里）
     */
    protected function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371;

        $dlat = deg2rad($lat2 - $lat1);
        $dlng = deg2rad($lng2 - $lng1);

        $a = sin($dlat / 2) * sin($dlat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dlng / 2) * sin($dlng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * 批量获取接单员位置
     */
    public function getBatchLocations(array $runnerIds)
    {
        $result = [];

        foreach ($runnerIds as $runnerId) {
            $cacheKey = "runner_location_{$this->site_id}_{$runnerId}";
            $cached = Cache::get($cacheKey);

            if ($cached) {
                $result[$runnerId] = $cached;
            }
        }

        // 获取缓存中没有的
        $missingIds = array_diff($runnerIds, array_keys($result));
        if (!empty($missingIds)) {
            $locations = (new RunnerLocation())->where([
                ['site_id', '=', $this->site_id],
                ['runner_id', 'in', $missingIds]
            ])->select()->toArray();

            foreach ($locations as $loc) {
                $result[$loc['runner_id']] = $loc;
                $cacheKey = "runner_location_{$this->site_id}_{$loc['runner_id']}";
                Cache::set($cacheKey, $loc, self::CACHE_TIME);
            }
        }

        return $result;
    }
}
