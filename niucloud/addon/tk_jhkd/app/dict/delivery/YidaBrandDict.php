<?php

namespace addon\tk_jhkd\app\dict\delivery;

/**
 * 易达快递品牌枚举
 */
class YidaBrandDict
{

    public static function getBrand($brand=null)
    {
        $data=[
            'JD'=> [
                'name' => '京东',
                'logo' => 'addon/tk_jhkd/logo/jd.png'
            ],
            'YTO'=> [
                'name' => '圆通',
                'logo' => 'addon/tk_jhkd/logo/yt.png'
            ],
            'STO-INT'=>[
                'name' => '申通',
                'logo' => 'addon/tk_jhkd/logo/st.png'
            ],
            'DOP'=>[
                'name' => '德邦',
                'logo' => 'addon/tk_jhkd/logo/db.png'
            ],
            'SF'=>[
                'name' => '顺丰',
                'logo' => 'addon/tk_jhkd/logo/sf.png'
            ],
            'JT'=> [
                'name' => '极兔',
                'logo' => 'addon/tk_jhkd/logo/jt.png'
            ],
            'ZTO'=>[
                'name' => '中通',
                'logo' => 'addon/tk_jhkd/logo/zt.png'
            ],
            'YUND'=>[
                'name' => '韵达',
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
                'name' => '邮政EMS',
                'logo' => 'addon/tk_jhkd/logo/ems.png'
            ],
            'KY'=>[
                'name' => '跨越速运',
                'logo' => 'addon/tk_jhkd/logo/ky.png'
            ],
            'BEST'=>[
                'name' => '百事速运',
                'logo' => 'addon/tk_jhkd/logo/bs.png'
            ],
        ];
        if ($brand == null || !isset($data[$brand])) {
            return ['logo' => '','name'=>$brand];
        }
        return $data[$brand];
    }

}