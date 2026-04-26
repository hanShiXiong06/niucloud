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
use think\model\concern\SoftDelete;

/**
 * Banner模型
 * Class RecycleBanner
 * @package addon\recycle\app\model\recycle_category
 */
class RecycleBanner extends BaseModel
{
    use SoftDelete;

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_banner';

    /**
     * 定义软删除标记字段
     * @var string
     */
    protected $deleteTime = 'delete_time';

    /**
     * 定义软删除字段的默认值
     * @var int
     */
    protected $defaultSoftDelete = 0;

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

    /**
     * 搜索器:图片
     * @param $query
     * @param $value
     */
    public function searchImageAttr($query, $value)
    {
        if ($value) {
            $query->where('image', 'like', '%' . $this->handelSpecialCharacter($value) . '%');
        }
    }
}