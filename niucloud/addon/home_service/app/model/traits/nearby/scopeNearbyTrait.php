<?php


namespace addon\home_service\app\model\traits\nearby;

trait scopeNearbyTrait
{
    /**
     * 地理位置筛选
     * @param $query
     * @param float $lat 用户纬度
     * @param float $lng 用户经度
     * @param int   $distance 距离，km
     */
    public function scopeNearby($query, $lat, $lng, $distance)
    {
        if (empty($lat) || empty($lng)) {
            return $query; // 没传坐标就不筛选
        }

        $haversine = "(6371 * acos(cos(radians($lat)) * cos(radians(taker_latitude))
                     * cos(radians(taker_longitude) - radians($lng))
                     + sin(radians($lat)) * sin(radians(taker_latitude))))";

        $query->fieldRaw("ROUND($haversine, 1) as distance")
            ->order("distance asc");

        if ($distance !== 'all') {
            $query->whereRaw("$haversine <= ?", [$distance]);
        }

        return $query;
    }

    /**
     * 地理位置筛选
     * @param $query
     * @param float $lat 用户纬度
     * @param float $lng 用户经度
     * @param int   $distance 距离，km
     */
    public function scopeStoreDistance($query, $lat, $lng)
    {
        if (empty($lat) || empty($lng)) {
            return $query; // 没传坐标就不筛选
        }

        $haversine = "(6371 * acos(cos(radians($lat)) * cos(radians(lat))
                     * cos(radians(lng) - radians($lng))
                     + sin(radians($lat)) * sin(radians(lat))))";

        $query->fieldRaw("ROUND($haversine, 1) as distance")
            ->order("distance asc");

        return $query;
    }
}
