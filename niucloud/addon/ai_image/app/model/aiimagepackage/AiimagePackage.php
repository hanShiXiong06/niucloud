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

namespace addon\ai_image\app\model\aiimagepackage;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 套餐列模型
 * Class AiimagePackage
 * @package addon\ai_image\app\model\aiimagepackage
 */
class AiimagePackage extends BaseModel
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
    protected $name = 'aiimage_package';

    

    

    /**
     * 搜索器:套餐列名称
     * @param $value
     * @param $data
     */
    public function searchNameAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("name", "like", "%".$value."%");
        }
    }
    
    /**
     * 搜索器:套餐列类型
     * @param $value
     * @param $data
     */
    public function searchTypeAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("type", $value);
        }
    }
    
    /**
     * 搜索器:套餐列状态
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("status", $value);
        }
    }
    
    

    

    
}
