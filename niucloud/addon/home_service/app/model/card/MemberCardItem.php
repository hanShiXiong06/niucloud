<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\model\card;

use addon\home_service\app\model\goods\Goods;
use addon\home_service\app\model\goods\GoodsSku;
use core\base\BaseModel;
/**
 * 会员次卡卡项模型
 * Class MemberCardItem
 * @package app\model\member_card_item
 */
class MemberCardItem extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'item_id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'home_service_member_card_item';

    public function memberCard(){
        return $this->hasOne(MemberCard::class, 'id', 'member_card_id');
    }

    public function goods(){
        return $this->hasOne(Goods::class, 'goods_id', 'goods_id');
    }

    public function goodsSku(){
        return $this->hasOne(GoodsSku::class, 'sku_id', 'goods_sku_id');
    }

}
