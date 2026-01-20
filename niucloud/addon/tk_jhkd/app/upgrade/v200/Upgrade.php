<?php

namespace addon\tk_jhkd\app\upgrade\v200;

use addon\tk_jhkd\app\model\order\Order;
use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use addon\tk_jhkd\app\service\admin\platform\PlatformService;
use app\model\site\Site;
use app\service\core\sys\CoreConfigService;

class Upgrade
{
    public function handle()
    {
        sleep(3);
        $this->setDriver();
        return true;
    }

    public function setDriver()
    {
        $page = 1;
        $orderDeliveryModel = new OrderDelivery();
        $orderModel = new Order();
        while (true) {
            $currentPageSites = (new Site())
                ->where('site_id', '>', 0)
                ->paginate([
                    'list_rows' => 10,
                    'page' => $page,
                ]);
            if (empty($currentPageSites)) {
                break;
            }
            foreach ($currentPageSites as $k => $v) {
                $site_id = $v['site_id'];
                $info = (new CoreConfigService())->getConfig($site_id, 'JHKD_DELIVERY');
                if (!empty($info)) {
                    $default = $info['value']['default'];
                    if (!empty($default)) {
                        $config = $info['value'][$default];
                        $config['type'] = $default;
                        $config['is_use'] = 1;
                        (new PlatformService())->setConfig($default, $config, $v['site_id']);
                    }
                    //批量对订单platform进行修改
                    $orderlist = $orderModel
                        ->where('create_time', '>', time() - 86400 * 7)
                        ->where(['site_id' => $site_id])->select();
                    foreach ($orderlist as $k1 => $v1) {
                        $orderDeliveryModel->update(['platform' => $default], ['order_id' => $v1['order_id']]);
                    }
                }
            }
            if (count($currentPageSites) < 10) {
                break;
            }
            $page++;
        }
    }
}


