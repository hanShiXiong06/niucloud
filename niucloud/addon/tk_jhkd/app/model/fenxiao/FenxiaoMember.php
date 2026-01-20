<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\model\fenxiao;

use app\model\member\Member;
use core\base\BaseModel;
use think\model\concern\SoftDelete;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 霸王餐订单模型
 * Class BwcOrder
 * @package addon\tk_cps\app\model\bwcorder
 */
class FenxiaoMember extends BaseModel
{

    

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'member_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'tkjhkd_fenxiao_member';
    public function memberInfo()
    {
        return $this->hasOne(Member::class, 'member_id', 'member_id');
    }


}
