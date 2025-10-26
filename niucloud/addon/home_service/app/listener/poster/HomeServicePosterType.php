<?php
declare ( strict_types = 1 );

namespace addon\home_service\app\listener\poster;


/**
 * 项目海报类型
 */
class HomeServicePosterType
{
    /**
     * 项目海报
     * @param array $data
     * @return array
     */
    public function handle($data = [])
    {
        return [
            [
                'type' => 'home_service_goods',
                'addon' => 'home_service',
                'name' => '上门家政海报',
                'desc' => '推广项目，分享后进入项目详情页',
                'icon' => 'addon/home_service/poster/type_home_service_goods.png'
            ]
        ];

    }
}
