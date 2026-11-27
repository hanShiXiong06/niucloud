<?php

namespace addon\hsx_recycle\app\service\admin\adminapp;

use addon\hsx_recycle\app\service\core\adminapp\CoreAdminAppService;
use addon\hsx_recycle\app\service\core\adminapp\CoreIndexService;
use core\base\BaseAdminService;

/**
 * 手机管理端统计设置。
 */
class StatService extends BaseAdminService
{
    public function getStatList(array $param = [])
    {
        $list = (new CoreIndexService())->getStatList();
        $keys = (new CoreAdminAppService())->getValue($this->uid, $this->site_id, 'stat');

        $stat_limit = env('system.stat_limit', 6);
        if (empty($keys)) {
            $stat_list = array_slice($list, 0, $stat_limit);
        } else {
            $stat_list = [];
            foreach ($list as $item) {
                if (in_array($item['key'], $keys)) {
                    $item['sort'] = array_search($item['key'], $keys);
                    $stat_list[] = $item;
                }
            }
        }

        usort($stat_list, function ($list_a, $list_b) {
            return $list_a['sort'] <=> $list_b['sort'];
        });

        return $stat_list;
    }

    public function setStatList(array $param = [])
    {
        (new CoreAdminAppService())->setValue($this->uid, $this->site_id, 'stat', $param);
        return true;
    }

    public function getAllStatList(array $param = [])
    {
        $list = (new CoreIndexService())->getStatList();
        usort($list, function ($list_a, $list_b) {
            return $list_a['sort'] <=> $list_b['sort'];
        });
        return $list;
    }
}
