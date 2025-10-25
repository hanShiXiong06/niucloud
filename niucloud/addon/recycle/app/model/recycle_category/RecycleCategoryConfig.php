<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\model\recycle_category;

use core\base\BaseModel;

/**
 * 回收分类配置模型
 * Class RecycleCategoryConfig
 * @package addon\recycle\app\model\recycle_category
 */
class RecycleCategoryConfig extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_category_config';

    /**
     * 搜索器:站点id
     * @param $query
     * @param $value
     */
    public function searchSiteIdAttr($query, $value)
    {
        if ($value) {
            $query->where('site_id', $value);
        }
    }
}
