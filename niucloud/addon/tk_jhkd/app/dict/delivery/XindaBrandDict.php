<?php

namespace addon\tk_jhkd\app\dict\delivery;

/**
 * 辛达快递品牌枚举
 */
class XindaBrandDict
{

    public static function getBrand($brand=null)
    {
        $data=[
            '申通'=> [
                'name' => '申通',
                'logo' => 'addon/tk_jhkd/logo/st.png'
            ],
            '极兔'=> [
                'name' => '极兔',
                'logo' => 'addon/tk_jhkd/logo/jt.png'
            ],
            '中通'=>[
                'name' => '中通',
                'logo' => 'addon/tk_jhkd/logo/zt.png'
            ],
            '德邦'=>[
                'name' => '德邦',
                'logo' => 'addon/tk_jhkd/logo/db.png'
            ],
            '圆通'=>[
                'name' => '圆通',
                'logo' => 'addon/tk_jhkd/logo/yt.png'
            ],
            '京东'=> [
                'name' => '京东',
                'logo' => 'addon/tk_jhkd/logo/jd.png'
            ],
            '韵达'=>[
                'name' => '韵达',
                'logo' => 'addon/tk_jhkd/logo/yd.png'
            ],
            '菜鸟'=>[
                'name' => '菜鸟',
                'logo' => 'addon/tk_jhkd/logo/cnsd.png'
            ],
            '顺丰'=>[
                'name' => '顺丰',
                'logo' => 'addon/tk_jhkd/logo/sf.png'
            ],
            '顺心捷达'=>[
                'name' => '顺心捷达',
                'logo' => 'addon/tk_jhkd/logo/sxjd.png'
            ],
            '壹米滴答'=>[
                'name' => '壹米滴答',
                'logo' => 'addon/tk_jhkd/logo/ymdd.png'
            ],
            '百世'=>[
                'name' => '百世快运',
                'logo' => 'addon/tk_jhkd/logo/bs.png'
            ],
        ];
        if ($brand == null || !isset($data[$brand])) {
            return ['logo' => '','name'=>$brand];
        }
        return $data[$brand];
    }

}