<?php


namespace addon\tk_jhkd\app\listener\order\delivery;

use addon\tk_jhkd\app\service\core\CommonService;
use addon\tk_jhkd\app\service\core\delivery\DeliveryLoader;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use Exception;

/**
 * 预下单事件
 */
class DeliveryPreOrderListener
{
    /**
     * @Notes:多渠道预下单公共处理
     * handle
     * @param $data
     * 2024/12/12  08:59
     * author:TK
     */
    public function handle($data)
    {
        $drivers = (new commonService())->getSiteDriver($data['site_id']);
        $pre_data=[];
        $low_data=[];
        foreach ($drivers as $k => $v) {
           $pre= (new DeliveryLoader($v['type'], $v['params']))->preOrder($data['data']);
           $pre_list=[];
           foreach ($pre as $k1=>$v1){
               $newPrice = $this->newPriceAndRule($v1, $data['data']['weight'],$data['site_id']);
               $v1['showPrice'] = $newPrice['price']+$v1['preBjFee'];
               $v1['price_rule'] = $newPrice['price_rule'];
               $v1['original_rule'] = $newPrice['original_rule'];
               $pre_list[]=$v1;
           }
           if($pre_list!=[]){
               usort($pre_list, function ($a, $b) {
                   return $a['showPrice'] <=> $b['showPrice'];
               });
               $pre_data[]=[
                   'driver'=>$v['type'],
                   'data'=>$pre_list
               ];

               $low_data[]=[
                   'driver'=>$v['type'],
                   'price'=>$pre_list[0]['showPrice']
               ];
               $pre_list=[];
           }
        }
        if( $low_data!=[]){
            usort($low_data, function ($a, $b) {
                return $a['price'] <=> $b['price'];
            });
            $driver= $low_data[0]['driver'];
            foreach ($pre_data as $k=>$v){
                if($v['driver']==$driver){
                    return $v;
                }
            }
        }else{
            throw new CommonException('暂无可用优惠渠道');
        }

    }

    /**
     * @Notes:计算展示价格
     * newPriceAndRule
     * @param $data
     * @param $weight
     * 2024/12/12  08:45
     * author:TK
     */
    public function newPriceAndRule($data, $weight,$site_id)
    {
        $this->config=(new CommonService())->getConfig($site_id);
        $price = $data['preOrderFee'];
        // 易达通票计算价格
        if ($data['calcFeeType'] == 'profit') {
            if (is_array($data['price'])) {
                $pricerules = $data['price'];
            } else {
                $pricerules = json_decode($data['price'], true);
            }
            if (is_array($data['originalPrice'])) {
                $original_price = $data['originalPrice'];
            } else {
                $original_price = json_decode($data['originalPrice'], true);
            }
            $selectedRule = null;
            $index = 0;
            // 遍历所有规则，找到适用于当前重量的规则
            foreach ($pricerules as $k => $rule) {
                if ($weight >= $rule['start'] && ($weight <= $rule['end'] || $rule['end'] == 0)) {
                    $selectedRule = $rule;
                    $index = $k;
                    break;
                }
            }
            if (!$selectedRule) {
                $firstRule = reset($pricerules);
                if ($weight < $firstRule['start']) {
                    $selectedRule = $firstRule;
                    $index = key($pricerules);
                }
            }
            if ($this->config['floatWay'] == 'floatWayFixed') {
                $selectedRule['first'] += $this->config['floatAmount'];
                if ($selectedRule['add'] > 0) {
                    $selectedRule['add'] += $this->config['floatAmount'];
                } else {
                    $selectedRule['add'] = 3;
                }

            }
            if ($this->config['floatWay'] == 'floatWayRate') {
                $selectedRule['first'] = $selectedRule['first'] * (1 + $this->config['floatRate'] / 100);
                if ($selectedRule['add'] > 0) {
                    $selectedRule['add'] = $selectedRule['add'] * (1 + $this->config['floatRate'] / 100);
                } else {
                    $selectedRule['add'] = 3;
                }
            }
            if ($this->config['floatWay'] == 'floatWayBetwn') {
                $selectedRule['first'] = $selectedRule['first'] + $this->config['firstAmount'];
                if ($selectedRule['add'] > 0) {
                    $selectedRule['add'] = $selectedRule['add'] + $this->config['secondAmount'];
                } else {
                    $selectedRule['add'] = 3;
                }

            }
            $selectedRule['perAdd'] = 0;   // 单笔加收
            $selectedRule['discount'] = 1;    // 折扣

        } else {
            if (is_array($data['price'])) {
                $pricerules = $data['price'];
            } else {
                $pricerules = json_decode($data['price'], true);
            }
            if (is_array($data['originalPrice'])) {
                $original_price = $data['originalPrice'];
            } else {
                $original_price = json_decode($data['originalPrice'], true);
            }
            $selectedRule = null;
            $index = 0;
            // 遍历所有规则，找到适用于当前重量的规则
            foreach ($original_price as $k => $rule) {
                if ($weight >= $rule['start'] && ($weight <= $rule['end'] || $rule['end'] == 0)) {
                    $selectedRule = $rule;
                    $index = $k;
                    break;
                }
            }
            if (!$selectedRule) {
                $firstRule = reset($original_price);
                if ($weight < $firstRule['start']) {
                    $selectedRule = $firstRule;
                    $index = key($original_price);
                }
            }
            if ($this->config['floatWay'] == 'floatWayFixed') {
                $selectedRule['first'] = $selectedRule['first'] * $pricerules['discount'] + $pricerules['perAdd'] + $this->config['floatAmount'];
                $selectedRule['add'] = $selectedRule['add'] * $pricerules['discount'] + $this->config['floatAmount'];
            }
            if ($this->config['floatWay'] == 'floatWayRate') {
                $selectedRule['first'] = ($selectedRule['first'] * $pricerules['discount'] + $pricerules['perAdd']) * (1 + $this->config['floatRate'] / 100);
                $selectedRule['add'] = $selectedRule['add'] * $pricerules['discount'] * (1 + $this->config['floatRate'] / 100);
            }
            if ($this->config['floatWay'] == 'floatWayBetwn') {
                $selectedRule['first'] = $selectedRule['first'] * $pricerules['discount'] + $pricerules['perAdd'] + $this->config['firstAmount'];
                $selectedRule['add'] = $selectedRule['add'] * $pricerules['discount'] + $this->config['secondAmount'];
            }
            $selectedRule['perAdd'] = $pricerules['perAdd'];   // 单笔加收
            $selectedRule['discount'] = $pricerules['discount'];    // 折扣
        }
        $selectedRule['first'] = round($selectedRule['first'], 2);
        $selectedRule['add'] = round($selectedRule['add'], 2);
        $weight_num = $weight - $selectedRule['start'];
        if ($weight_num < 0) {
            $weight_num = 0;
        }
        $price = $selectedRule['first'] + $weight_num * $selectedRule['add'];
        $price = round($price, 2);
        return ['price' => $price, 'price_rule' => $selectedRule, 'original_rule' => $original_price[$index]];
    }

}


