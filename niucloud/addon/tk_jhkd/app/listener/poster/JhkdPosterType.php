<?php
declare (strict_types = 1);

namespace addon\tk_jhkd\app\listener\poster;



/**
 * 商品海报类型
 */
class JhkdPosterType
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
                'type' => 'tk_jhkd_poster',
                'addon' => 'tk_jhkd',
                'name' => '聚合快递海报',
                'decs' => '聚合快递海报',
                'icon' => 'addon/tk_jhkd/icon.png'
            ]
        ];

    }
}
