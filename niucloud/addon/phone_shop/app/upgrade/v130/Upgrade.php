<?php

namespace addon\phone_shop\app\upgrade\v130;

use app\model\site\Site;
use app\model\sys\Poster;
use app\service\admin\diy\DiyService;
use app\service\core\poster\CorePosterService;

class Upgrade
{

    public function handle()
    {
        $this->handleData();
    }

    /**
     * 处理商品数据
     */
    private function handleData()
    {

        $site_model = new Site();
        $site_list = $site_model->where([
            [ 'app_type', '=', 'site' ],
            [ 'app', 'like', '%"phone_shop"%' ],
        ])->field('site_id,site_name')->select()->toArray();

        if (!empty($site_list)) {

            $poster = new CorePosterService();
            $poster_model = new Poster();

            foreach ($site_list as $k => $v) {

                // 删除旧模板
                $poster_model->where([ [ 'site_id', '=', $v[ 'site_id' ] ], [ 'addon', '=', 'phone_shop' ] ])->delete();

                // 创建默认商品海报
                $template = $poster->getTemplateList('phone_shop', 'phone_shop_goods')[ 0 ];
                $poster->add($v[ 'site_id' ], 'phone_shop', [
                    'name' => $template[ 'name' ],
                    'type' => $template[ 'type' ],
                    'value' => $template[ 'data' ],
                    'status' => 1,
                    'is_default' => 1
                ]);

                // 创建默认积分商品海报
                $template = $poster->getTemplateList('phone_shop', 'shop_point_goods')[ 0 ];
                $poster->add($v[ 'site_id' ], 'phone_shop', [
                    'name' => $template[ 'name' ],
                    'type' => $template[ 'type' ],
                    'value' => $template[ 'data' ],
                    'status' => 1,
                    'is_default' => 1
                ]);

            }
        }

    }

}