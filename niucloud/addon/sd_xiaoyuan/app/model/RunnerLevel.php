<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 接单员等级配置模型
 */
class RunnerLevel extends BaseModel
{
    protected $name = 'xiaoyuan_runner_level';
    protected $pk = 'id';

    protected $type = [
        'create_time' => 'timestamp'
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
     * 等级搜索器
     */
    public function searchLevelAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('level', $value);
        }
    }

    /**
     * 状态搜索器
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('status', $value);
        }
    }
}
