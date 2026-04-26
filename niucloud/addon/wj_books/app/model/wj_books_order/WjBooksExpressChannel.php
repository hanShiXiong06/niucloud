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

namespace addon\wj_books\app\model\wj_books_order;

use core\base\BaseModel;

/**
 * 物流渠道缓存模型
 * Class WjBooksExpressChannel
 * @package addon\wj_books\app\model\wj_books_order
 */
class WjBooksExpressChannel extends BaseModel
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
    protected $name = 'wj_books_express_channel';

    /**
     * 搜索器:渠道名称
     * @param $query
     * @param $value
     */
    public function searchChannelAttr($query, $value)
    {
        if ($value) {
            $query->where("channel", 'like', "%$value%");
        }
    }

    /**
     * 搜索器:渠道类别
     * @param $query
     * @param $value
     */
    public function searchTagTypeAttr($query, $value)
    {
        if ($value) {
            $query->where("tag_type", $value);
        }
    }

    /**
     * 搜索器:是否启用
     * @param $query
     * @param $value
     */
    public function searchIsEnabledAttr($query, $value)
    {
        if ($value !== '') {
            $query->where("is_enabled", $value);
        }
    }
} 