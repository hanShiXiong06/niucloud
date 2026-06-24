<?php
namespace addon\sd_xiaoyuan\app\service\api;

use addon\sd_xiaoyuan\app\model\ExpressStation;
use addon\sd_xiaoyuan\app\model\PackagePrice;
use core\base\BaseApiService;

class ExpressService extends BaseApiService
{
    public function getStations($school_id = 0)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ];
        
        if ($school_id) {
            $where[] = ['school_id', '=', $school_id];
        }
        
        $list = (new ExpressStation())->where($where)
            ->field('id,name,logo,address,express_company,contact_mobile,business_hours')
            ->order('sort desc, id desc')
            ->select()
            ->toArray();
            
        return ['list' => $list];
    }
    
    public function getPackagePrices()
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ];
        
        try {
            $list = (new PackagePrice())->where($where)
                ->field('id,size,name,description,price')
                ->order('sort asc, id asc')
                ->select()
                ->toArray();
        } catch (\Exception $e) {
            $list = [
                ['id' => 1, 'size' => 'small', 'name' => '小件', 'description' => '小礼盒大小', 'price' => '2.00'],
                ['id' => 2, 'size' => 'medium', 'name' => '中件', 'description' => '标准鞋盒大小', 'price' => '3.00'],
                ['id' => 3, 'size' => 'large', 'name' => '中大件', 'description' => '微波炉/中型收纳箱大小', 'price' => '5.00'],
                ['id' => 4, 'size' => 'xlarge', 'name' => '大件', 'description' => '24-26寸行李箱大小', 'price' => '8.00'],
            ];
        }
            
        return ['list' => $list];
    }
}
