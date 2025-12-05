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

namespace addon\ai_image\app\model\aiimagecard;

use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

use app\model\member\Member;

/**
 * 卡密兑换模型
 * Class AiimageCard
 * @package addon\ai_image\app\model\aiimagecard
 */
class AiimageCard extends BaseModel
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
    protected $name = 'aiimage_card';

    protected $type = [
        'expire_time' => 'timestamp',
    ];


    /**
     * 搜索器:卡密兑换卡号
     * @param $value
     * @param $data
     */
    public function searchCardNumAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("card_num", $value);
        }
    }

    /**
     * 搜索器:卡密兑换是否使用
     * @param $value
     * @param $data
     */
    public function searchIsUseAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("is_use", $value);
        }
    }

    /**
     * 搜索器:卡密兑换是否导出
     * @param $value
     * @param $data
     */
    public function searchIsExportAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where("is_export", $value);
        }
    }

    /**
     * 搜索器:卡密兑换所属用户
     * @param $value
     * @param $data
     */
    public function searchPidAttr($query, $value, $data)
    {
        if ($value) {
            $query->where("pid", $value);
        }
    }


    public function member()
    {
        return $this->hasOne(Member::class, 'member_id', 'pid')->joinType('left')->withField('nickname,member_id')->bind(['pid_name' => 'nickname']);
    }

}
