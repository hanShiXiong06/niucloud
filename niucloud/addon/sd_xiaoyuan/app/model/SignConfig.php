<?php

namespace addon\sd_xiaoyuan\app\model;

use core\base\BaseModel;

/**
 * 签到配置模型
 */
class SignConfig extends BaseModel
{
    protected $name = 'xiaoyuan_sign_config';
    protected $pk = 'id';

    /**
     * 搜索器：站点ID
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('site_id', '=', $value);
        }
    }
}
