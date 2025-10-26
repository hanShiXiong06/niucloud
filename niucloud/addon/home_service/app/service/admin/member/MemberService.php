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

namespace addon\home_service\app\service\admin\member;

use app\model\member\Member;
use core\base\BaseAdminService;
use think\db\Query;


/**
 * 商品评价服务层
 */
class MemberService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Member();
    }


    /**
     * 会员列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'member_id, member_no, site_id, username, mobile, password, register_channel, register_type, nickname, headimg, member_level, member_label, wx_openid, weapp_openid, wx_unionid, ali_openid, douyin_openid, login_ip, login_type, login_channel, login_count, login_time, create_time, last_visit_time, last_consum_time, sex, status, birthday, point, point_get, balance, balance_get, growth, growth_get, is_member, member_time, is_del, province_id, city_id, district_id, address, location, delete_time, money, money_get, commission, commission_get, commission_cash_outing';
        $search_model = $this->model->where([['site_id', '=', $this->site_id]])->withSearch(['keyword', 'register_type', 'create_time', 'is_del', 'member_label', 'register_channel', 'member_level'], $where)
            ->field($field)
            ->order('member_id desc')
            ->with('member_level_name_bind')
            ->append(['register_channel_name', 'register_type_name', 'sex_name', 'login_channel_name', 'login_type_name', 'status_name']);
        return $this->pageQuery($search_model, function ($item, $key) {
        });
    }


}
