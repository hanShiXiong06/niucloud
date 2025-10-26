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

namespace addon\home_service\app\model\goods;

use core\base\BaseModel;

/**
 * 服务保障
 */
class Guarantee extends BaseModel
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
    protected $name = 'home_service_goods_guarantee';

    //类型
    protected $type = [

    ];


    /**
     * 保障标题搜索
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchGuaranteeTitleAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('guarantee_title', 'like', '%' . $value . '%');
        }
    }
}