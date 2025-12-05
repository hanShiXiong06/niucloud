<?php

namespace addon\phone_shop\app\job\goods;

use addon\phone_shop\app\service\admin\goods\GoodsService;
use app\model\site\Site;
use core\base\BaseJob;
use think\facade\Log;

class SyncGoods extends BaseJob
{
    public function doJob()
    {
    Log::write('同步商品列表');
    try {
        // 查询所有的站点id 
        $site_model = new Site();
        // 查询 gourp_id = 5 得到站点id
        $site_list = $site_model->where('group_id', '=',6)->select()->toArray();
        // 循环调用
        
        foreach ($site_list as $site) {
            $site_id = $site['site_id'];
            // 调用同步商品列表
            Log::write(  (new GoodsService())->syncGoodsList($site_id));
        }
       return true;
    } catch (\Exception $e) {
        Log::write('同步商品列表同步失败' . $e->getMessage() . $e->getFile() . $e->getLine());
        return false;
    }

    }
}