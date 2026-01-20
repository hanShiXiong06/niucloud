<?php

namespace addon\tk_jhkd\app\dict\delivery;

use app\dict\pay\PayDict;

/**
 * 快递鸟快递品牌
 */
class KdniaoBrandDict
{

    public static function getBrand($brand=null)
    {
        $data=[
            'JD'=> [
                'name' => '京东物流',
                'logo' => 'addon/tk_jhkd/logo/jd.png'
            ],
            'YTO'=> [
                'name' => '圆通速递',
                'logo' => 'addon/tk_jhkd/logo/yt.png'
            ],
            'STO'=>[
                'name' => '申通快递',
                'logo' => 'addon/tk_jhkd/logo/st.png'
            ],
            'DBL'=>[
                'name' => '德邦快递',
                'logo' => 'addon/tk_jhkd/logo/db.png'
            ],
            'SF'=>[
                'name' => '顺丰速运',
                'logo' => 'addon/tk_jhkd/logo/sf.png'
            ],
            'JTSD'=> [
                'name' => '极兔速递',
                'logo' => 'addon/tk_jhkd/logo/jt.png'
            ],
            'ZTO'=>[
                'name' => '中通快递',
                'logo' => 'addon/tk_jhkd/logo/zt.png'
            ],
            'YD'=>[
                'name' => '韵达速递',
                'logo' => 'addon/tk_jhkd/logo/yd.png'
            ],
            'CNSD'=>[
                'name' => '菜鸟速递',
                'logo' => 'addon/tk_jhkd/logo/cn.png'
            ],
            'CAINIAO'=>[
                'name' => '菜鸟裹裹',
                'logo' => 'addon/tk_jhkd/logo/cnsd.png'
            ],
            'EMS'=>[
                'name' => '邮政速递',
                'logo' => 'addon/tk_jhkd/logo/ems.png'
            ],
            'DNWL'=>[
                'name' => '丹鸟',
                'logo' => 'addon/tk_jhkd/logo/cnsd.png'
            ],
            '半日收'=>[
                'name' => '半日收',
                'logo' => 'addon/tk_jhkd/logo/brs.png'
            ],

        ];
        if ($brand == null || !isset($data[$brand])) {
            return ['logo' => '','name'=>$brand];
        }
        return $data[$brand];
    }

}