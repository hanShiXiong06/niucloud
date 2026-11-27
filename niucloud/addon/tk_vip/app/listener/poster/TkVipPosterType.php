<?php
declare (strict_types=1);

namespace addon\tk_vip\app\listener\poster;


/**
 * 商品海报类型
 */
class TkVipPosterType
{
    /**
     * 商品海报
     * @param $data
     * @return void
     */
    public function handle($data = [])
    {
        return [
            [
                'type' => 'tk_vip_poster',
                'addon' => 'tk_vip',
                'name' => '付费会员权益海报',
                'decs' => '付费会员权益海报',
                'icon' => 'addon/tk_vip/icon.png'
            ]
        ];
    }
}
