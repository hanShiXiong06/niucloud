<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 接单员位置模型
 */
class RunnerLocation extends BaseModel
{
    protected $name = 'xiaoyuan_runner_location';
    protected $pk = 'id';

    protected $type = [
        'update_time' => 'timestamp'
    ];

    /**
     * 站点ID搜索器
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('site_id', $value);
        }
    }

    /**
     * 接单员ID搜索器
     */
    public function searchRunnerIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('runner_id', $value);
        }
    }
}
