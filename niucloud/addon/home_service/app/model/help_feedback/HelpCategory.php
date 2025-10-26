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

namespace addon\home_service\app\model\help_feedback;

use addon\home_service\app\dict\help_feedback\HelpCategoryDict;
use core\base\BaseModel;

/**
 * 帮助分类模型
 */
class HelpCategory extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'category_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_help_category';

    /**
     * 搜索器:名称
     * @param $value
     * @param $data
     */
    public function searchCategoryNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("category_name", 'like', '%' . $this->handelSpecialCharacter($value) . '%');
        }
    }

    /**
     * 状态字段转化
     * @param $value
     * @return mixed
     */
    public function getIsShowNameAttr($value, $data)
    {
        if (isset($data['is_show'])) {
            return HelpCategoryDict::getIsShow()[$data['is_show']] ?? '';
        }
    }
}
