<?php
declare (strict_types = 1);

namespace addon\fast_pay\app\listener\poster;



/**
 * 海报类型
 */
class PayPosterType
{
    /**
     * 收款二维码海报
     * @param $data
     * @return void
     */
    public function handle($data = [])
    {
        return [
            [
                'type' => 'fast_pay_poster',
                'addon' => 'fast_pay',
                'name' => '商家收款码海报',
                'decs' => '商家收款码海报',
                'icon' => 'addon/fast_pay/icon.png'
            ]
        ];

    }
}
