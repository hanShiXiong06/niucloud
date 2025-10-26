<?php

namespace addon\home_service\app\model\traits\technician;

/**
 * 师傅地理位置筛选Trait
 */
trait TechnicianScopeNearbyTrait
{
    /**
     * 地理位置筛选 - 专为师傅模型设计
     * @param $query
     * @param float $lat 用户纬度
     * @param float $lng 用户经度
     * @param int $distance 距离，km
     */
    public function scopeNearby($query, $lat, $lng, $distance)
    {
        if (empty($lat) || empty($lng)) {
            return $query; // 没传坐标就不筛选
        }
        $haversine = "(6371 * acos(cos(radians($lat)) * cos(radians(lat))
                     * cos(radians(lng) - radians($lng))
                     + sin(radians($lat)) * sin(radians(lat))))";


        $query->fieldRaw("ROUND($haversine, 1) as distance")
            ->order("distance asc");

        if ($distance !== 'all') {
            $query->whereRaw("$haversine <= ?", [$distance]);
        }

        return $query;
    }
}