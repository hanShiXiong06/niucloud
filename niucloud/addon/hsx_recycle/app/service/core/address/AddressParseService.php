<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\address;

use app\model\sys\SysArea;
use core\exception\CommonException;

/**
 * 地址解析能力服务
 */
class AddressParseService
{
    private AddressParseGatewayService $gateway;

    public function __construct()
    {
        $this->gateway = new AddressParseGatewayService();
    }

    public function parse(int $siteId, string $address): array
    {
        $address = trim($address);
        if ($address === '') {
            throw new CommonException('地址内容不能为空');
        }

        $parsed = $this->gateway->parse($siteId, $address);
        $area = $this->matchArea(
            (string)($parsed['province'] ?? ''),
            (string)($parsed['city'] ?? ''),
            (string)($parsed['district'] ?? '')
        );

        return [
            'name' => (string)($parsed['name'] ?? ''),
            'mobile' => (string)($parsed['mobile'] ?? ''),
            'province' => (string)($parsed['province'] ?? ''),
            'city' => (string)($parsed['city'] ?? ''),
            'district' => (string)($parsed['district'] ?? ''),
            'address' => (string)($parsed['info'] ?? ''),
            'full_address' => (string)($parsed['area'] ?? '') . (string)($parsed['info'] ?? ''),
            'area' => $area,
            'raw' => $parsed['raw'] ?? $parsed,
        ];
    }

    private function matchArea(string $provinceName, string $cityName, string $districtName): array
    {
        $province = $this->findArea($provinceName, 1);
        $city = $this->findArea($cityName, 2, (int)($province['id'] ?? 0));
        $district = $this->findArea($districtName, 3, (int)($city['id'] ?? 0));

        return [
            'province' => $province,
            'city' => $city,
            'district' => $district,
        ];
    }

    private function findArea(string $name, int $level, int $pid = 0): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }

        $model = new SysArea();
        $query = $model->where([['level', '=', $level]]);
        if ($pid > 0) {
            $query->where([['pid', '=', $pid]]);
        }

        $area = $query->where(function ($query) use ($name) {
            $query->where('name', '=', $name)
                ->whereOr('shortname', '=', $name)
                ->whereOr('name', 'like', '%' . $name . '%')
                ->whereOr('shortname', 'like', '%' . $this->shortAreaName($name) . '%');
        })->field('id,pid,name,shortname,level')->findOrEmpty()->toArray();

        return $area ?: [];
    }

    private function shortAreaName(string $name): string
    {
        return str_replace(['省', '市', '自治区', '自治州', '地区', '盟', '区', '县', '旗'], '', $name);
    }
}
