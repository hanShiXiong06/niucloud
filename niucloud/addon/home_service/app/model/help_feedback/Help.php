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

use addon\home_service\app\dict\help_feedback\HelpDict;
use core\base\BaseModel;

/**
 * 帮助模型
 */
class Help extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'help_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_help';


    /**
     * 通过分类ID获取数量
     * @param $category_id
     * @return int
     * @throws \think\db\exception\DbException
     */
    public function getCountByCategoryID($category_id)
    {
        /**获取商品表中使用该分类的数据条数**/
        return $this->where([['category_id', '=', $category_id]])->count();
    }

    /**
     * 搜索器:名称
     * @param $value
     * @param $data
     */
    public function searchNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("name", 'like', '%' . $this->handelSpecialCharacter($value) . '%');
        }
    }

    /**
     * 搜索器:分类id
     * @param $value
     * @param $data
     */
    public function searchCateGoryIdAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("category_id", '=', $value);
        }
    }

    /**
     * 搜索器:类型
     * @param $value
     * @param $data
     */
    public function searchTypeAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("type", '=', $value);
        }
    }

    public function getCategoryNameAttr($value, $data)
    {
        if (isset($data[ 'category_id' ]) && !empty($data[ 'category_id' ])) {
            $category_model = new HelpCategory();
            return $category_model->where([
                [ 'category_id', '=', $data[ 'category_id' ] ],
            ])->value('category_name');
        }
    }

    public function getTypeNameAttr($value, $data)
    {
        if (isset($data[ 'type' ])) {
            return HelpDict::getType()[$data['type']] ?? '';
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
            return HelpDict::getIsShow()[$data['is_show']] ?? '';
        }
    }
}
