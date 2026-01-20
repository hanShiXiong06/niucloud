<?php

namespace addon\tk_jhkd\app\dict\delivery;

/**
 * 亿速快递品牌枚举
 */
class YisuBrandDict
{

    public static function getBrand($brand=null)
    {
        $data=[
            '1'=> [
                'name' => '申通快递',
                'logo' => 'addon/tk_jhkd/logo/st.png'
            ],
            '2'=> [
                'name' => '圆通快递',
                'logo' => 'addon/tk_jhkd/logo/yt.png'
            ],
            '3'=>[
                'name' => '德邦快递',
                'logo' => 'addon/tk_jhkd/logo/db.png'
            ],
            '4'=>[
                'name' => '德邦快递',
                'logo' => 'addon/tk_jhkd/logo/db.png'
            ],
            '5'=>[
                'name' => '顺丰标快',
                'logo' => 'addon/tk_jhkd/logo/sf.png'
            ],
            '6'=>[
                'name' => '顺丰特快',
                'logo' => 'addon/tk_jhkd/logo/sf.png'
            ],
            '8'=>[
                'name' => '顺丰快递',
                'logo' => 'addon/tk_jhkd/logo/sf.png'
            ],
            '9'=>[
                'name' => '德邦航空',
                'logo' => 'addon/tk_jhkd/logo/db.png'
            ],
            '10'=> [
                'name' => '极兔速递',
                'logo' => 'addon/tk_jhkd/logo/jt.png'
            ],
            '11'=> [
                'name' => '跨越速运',
                'logo' => 'addon/tk_jhkd/logo/ky.png'
            ],
            '12'=>[
                'name' => '韵达快递',
                'logo' => 'addon/tk_jhkd/logo/yd.png'
            ],
            '13'=>[
                'name' => '京东快递',
                'logo' => 'addon/tk_jhkd/logo/jd.png'
            ],
            '36'=>[
                'name' => '菜鸟裹裹',
                'logo' => 'addon/tk_jhkd/logo/cnsd.png'
            ],
            '20'=>[
                'name' => '德邦物流(汽运)',
                'logo' => 'addon/tk_jhkd/logo/db.png'
            ],
            '21'=>[
                'name' => '德邦物流(卡航)',
                'logo' => 'addon/tk_jhkd/logo/db.png'
            ],
            '22'=>[
                'name' => '德邦重包',
                'logo' => 'addon/tk_jhkd/logo/db.png'
            ],
            '23'=>[
                'name' => '顺心捷达',
                'logo' => 'addon/tk_jhkd/logo/sxjd.png'
            ],
            '24'=>[
                'name' => '顺丰快运',
                'logo' => 'addon/tk_jhkd/logo/sfky.png'
            ],
            '25'=>[
                'name' => '京东物流(重货)',
                'logo' => 'addon/tk_jhkd/logo/jdwl.png'
            ],
            '26'=>[
                'name' => '京东物流(特担)',
                'logo' => 'addon/tk_jhkd/logo/jdwl.png'
            ],
            '39'=>[
                'name' => '中通快运',
                'logo' => 'addon/tk_jhkd/logo/zt.png'
            ],
            '40'=>[
                'name' => '百世快运',
                'logo' => 'addon/tk_jhkd/logo/bs.png'
            ],
            '42'=>[
                'name' => '跨越陆运',
                'logo' => 'addon/tk_jhkd/logo/ky.png'
            ],
            '43'=>[
                'name' => '跨越专运',
                'logo' => 'addon/tk_jhkd/logo/ky.png'
            ],
            '69'=>[
                'name' => '申通(限3kg)',
                'logo' => 'addon/tk_jhkd/logo/st.png'
            ],
            '47'=>[
                'name' => 'EMS',
                'logo' => 'addon/tk_jhkd/logo/ems.png'
            ],
            '59'=>[
                'name' => '京东小件',
                'logo' => 'addon/tk_jhkd/logo/jd.png'
            ],
            '70'=>[
                'name' => '顺丰小件',
                'logo' => 'addon/tk_jhkd/logo/sf.png'
            ],
            '71'=>[
                'name' => '安能物流',
                'logo' => 'addon/tk_jhkd/logo/anwl.png'
            ],
            '72'=>[
                'name' => '顺心捷达',
                'logo' => 'addon/tk_jhkd/logo/sxjd.png'
            ],
            '73'=>[
                'name' => '顺心捷达',
                'logo' => 'addon/tk_jhkd/logo/sxjd.png'
            ],
        ];
        if ($brand == null || !isset($data[$brand])) {
            return ['logo' => '','name'=>$brand];
        }
        return $data[$brand];
    }

}