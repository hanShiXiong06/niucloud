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

namespace addon\home_service\app\dict\goods;

/**
 * 次卡商品项目相关字典类
 * Class HotelOrderDict
 * @package app\dict\order
 */
class CardDict
{

    const MONTHLY_CARD = 'monthly_card';

    const SEASON_CARD = 'season_card';

    const YEAR_CARD = 'year_card';

    const PERMANENT_CARD = 'permanent_card';


    /**
     *次卡有效期类型
     */
    public static function getValidType($status = '')
    {
        $list = [
            self::MONTHLY_CARD => get_lang('dict_home_service_card_valid.monthly_card'),
            self::SEASON_CARD => get_lang('dict_home_service_card_valid.season_card'),
            self::YEAR_CARD => get_lang('dict_home_service_card_valid.year_card'),
            self::PERMANENT_CARD => get_lang('dict_home_service_card_valid.permanent_card')
        ];
        if ($status == '') return $list;
        return $list[$status] ?? '';
    }


}