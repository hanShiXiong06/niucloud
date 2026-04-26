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

namespace addon\wj_books\app\adminapi\controller;

use addon\wj_books\app\service\admin\WjBooksMemberService;
use app\model\member\Member;
use core\base\BaseAdminController;
use think\facade\Log;
use think\facade\Db;
use think\Response;

/**
 * 二手书回收会员管理控制器
 * Class WjBooksMember
 * @package addon\wj_books\app\adminapi\controller
 */
class WjBooksMember extends BaseAdminController
{
    /**
     * 调整会员可提现余额
     * @return Response
     */
    public function adjustMoney()
    {
        $data = $this->request->params([
            ['member_id', ''],  // 会员ID
            ['account_data', 0], // 账户数据
            ['memo', ''],       // 备注
            ['from_type', 'adjust'], // 来源类型，默认为adjust
            ['related_id', ''], // 关联ID
        ]);
        
        // 日志记录
        Log::write('WjBooksMember::adjustMoney raw params: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        // 参数验证
        if (empty($data['member_id'])) {
            return fail('会员ID不能为空');
        }
        
        // 验证会员是否存在
        $site_id = $this->request->siteId();
        Log::write('WjBooksMember::adjustMoney site_id: ' . $site_id . ', member_id: ' . $data['member_id']);
        
        // 直接查询会员信息并记录日志
        $member_info = (new Member())->where([
            ['member_id', '=', intval($data['member_id'])],
        ])->find();
        
        if (!$member_info) {
            Log::error('WjBooksMember::adjustMoney 会员不存在，member_id: ' . $data['member_id']);
            return fail('会员不存在');
        }
        
        Log::write('WjBooksMember::adjustMoney 会员信息: ' . json_encode($member_info->toArray(), JSON_UNESCAPED_UNICODE));
        
        // 继续验证site_id和is_del
        $member_exists = (new Member())->where([
            ['member_id', '=', intval($data['member_id'])],
            ['site_id', '=', $site_id],
            ['is_del', '=', 0] // 未删除
        ])->count();
        
        if (!$member_exists) {
            // 记录更详细的验证失败原因
            $check1 = (new Member())->where([
                ['member_id', '=', intval($data['member_id'])],
            ])->count();
            
            $check2 = (new Member())->where([
                ['member_id', '=', intval($data['member_id'])],
                ['site_id', '=', $site_id],
            ])->count();
            
            Log::error('WjBooksMember::adjustMoney 会员验证失败，member_id匹配: ' . $check1 . ', site_id匹配: ' . $check2);
            return fail('会员不存在或已被删除');
        }
        
        try {
            $res = (new WjBooksMemberService())->adjustMoney($data);
            return success('SUCCESS', ['id' => $res]);
        } catch (\Exception $e) {
            Log::error('调整可提现余额失败: ' . $e->getMessage());
            return fail($e->getMessage());
        }
    }
} 