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

namespace addon\hsx_phone_query\app\model;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 配置信息模型
 * Class HsxPhoneQueryConfig
 * @package addon\hsx_phone_query\app\model\hsx_phone_query
 */
class HsxPhoneQueryInfo extends BaseModel
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
    protected $name = 'hsx_phone_query_info';

    /**
     * 设置搜索器字段
     * @var array
     */
    // protected $searchFields = ['sn'];

    /**
     * 搜索器:配置信息主键
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchIdAttr($query, $value, $data)
    {
       if ($value) {
            $query->where("id", $value);
        }
    }

    /**
     * type 类型搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    // public function searchTypeAttr($query, $value, $data)
    // {
    //     if ($value) {
    //         $query->where("pid", $value);
    //     }
    // }

    /**
     * 开始时间搜索器 使用between 完成
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchStartTimeAttr($query, $value, $data)
    {
        if ($value) {
            $query->whereBetween('create_time', [$value, $data['end_time']]);
        }
    }

    /**
     * keyword 搜索器 | 序列号模糊搜索 
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchKeywordAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("sn", 'like', '%' . $value . '%');
        }
    }

    /**
     * 关联分类
     */
    public function category()
    {
        return $this->hasOne(HsxPhoneQueryCategory::class, 'id', 'type_id');
    }
    
    /**
     * 获取分类名称
     * @param $value
     * @param $data
     */
    public function getTypeNameAttr($value, $data)
    {
        if (isset($data['type_id'])) {
            $category = (new HsxPhoneQueryCategory())->where('id', '=', $data['type_id'])->find();
            return $category ? $category['name'] : '';
        }
        return '';
    }
    
    /**
     * 关联会员表
     */
    public function member()
    {
        return $this->belongsTo('app\model\member\Member', 'member_id', 'member_id')
            ->field('member_id,nickname,headimg,mobile');
    }

    

    
}
