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

namespace addon\home_service\app\service\core\strategy;

use addon\home_service\app\model\CityStrategy;
use core\base\BaseCoreService;
use think\Model;

/**
 * 城市策略服务层
 */
class CoreCityStrategyService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new CityStrategy();
    }


    /**
     * 城市策略价格获取
     */
    public function getStrategyPrice($price, $city_id = 0, $site_id = 0, &$v = null)
    {
        // 城市ID为空，直接返回原始价格
        if (empty($city_id)) {
            if (is_array($v)) {
                $v['goods_original_price'] = 0;
            }
            return $price;
        }
        // 查询策略信息
        $strategy_info = $this->model
            ->where([['city_id', '=', $city_id], ['site_id', '=', $site_id]])
            ->field('value, way')
            ->findOrEmpty()
            ->toArray();
        // 策略不存在，返回原始价格
        if (empty($strategy_info) || !isset($strategy_info['way'], $strategy_info['value'])) {
            if (is_array($v)) {
                $v['goods_original_price'] = 0;
            }
            return $price;
        }
        // 确保value是数字类型
        $value = is_numeric($strategy_info['value']) ? $strategy_info['value'] : 0;
        // 根据策略方式计算价格
        $goods_original_price = $v['price'] ?? '';

        switch ($strategy_info['way']) {
            case '*':
                // 乘法策略：价格 = 原始价格 + 原始价格 * (value%)
                $strategy_price = bcmul($price, $value * 0.01, 2);
                $price = bcadd($price, $strategy_price, 2);
                $goods_original_price = bcadd($v['price'], $strategy_price, 2);
                break;
            case '+':
                // 加法策略：价格 = 原始价格 + value
                $price = bcadd($price, $value, 2); // 补充精度参数，保持一致性
                $goods_original_price = bcadd($v['price'], $value, 2);
                break;
            default:
                // 未知策略方式，记录日志并返回原始价格
                // Log::warning("未知的价格策略方式: " . $strategy_info['way']);
                break;
        }

        if (!empty($goods_original_price) && $price < $goods_original_price){
            $v['goods_original_price'] = $goods_original_price;
        }else{
            $v['goods_original_price'] = 0;
        }

        return $price;
    }


}
