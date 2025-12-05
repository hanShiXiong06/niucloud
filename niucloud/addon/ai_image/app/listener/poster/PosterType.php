<?php
declare (strict_types = 1);

namespace addon\ai_image\app\listener\poster;



/**
 * 商品海报类型
 */
class PosterType
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
                'type' => 'ai_image_poster',
                'addon' => 'ai_image',
                'name' => 'AI设计海报',
                'decs' => 'AI设计海报',
                'icon' => 'addon/ai_image/icon.png'
            ]
        ];

    }
}
