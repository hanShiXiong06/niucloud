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

namespace addon\wj_books\app\service\admin;

use app\dict\member\MemberAccountTypeDict;
use app\service\core\member\CoreMemberAccountService;
use core\base\BaseAdminService;
use think\facade\Log;

/**
 * 二手书回收会员服务类
 * Class WjBooksMemberService
 * @package addon\wj_books\app\service\admin
 */
class WjBooksMemberService extends BaseAdminService
{
    /**
     * 调整会员可提现余额
     * @param array $data
     * @return int
     */
    public function adjustMoney(array $data)
    {
        // 日志记录请求参数，方便调试
        Log::write('adjustMoney params: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        
        // 处理备注信息
        $memo = $data['memo'] ?? '';
        $from_type = $data['from_type'] ?? 'adjust';
        
        // 如果是特定类型，添加自定义前缀以区分
        if (!empty($from_type) && $from_type != 'adjust') {
            $memo = "【二手书回收】" . $memo;
        }
        
        // 确保member_id是整数
        $member_id = intval($data['member_id']);
        $account_data = floatval($data['account_data']);
        $related_id = $data['related_id'] ?? '';
        
        Log::write('调用CoreMemberAccountService参数: site_id=' . $this->site_id . 
                   ', member_id=' . $member_id . 
                   ', type=' . MemberAccountTypeDict::MONEY . 
                   ', account_data=' . $account_data . 
                   ', from_type=' . $from_type . 
                   ', memo=' . $memo . 
                   ', related_id=' . $related_id);
        
        // 调用核心服务添加可提现余额
        return (new CoreMemberAccountService())->addLog(
            $this->site_id, 
            $member_id, 
            MemberAccountTypeDict::MONEY, // 明确指定是可提现余额
            $account_data, 
            $from_type, 
            $memo, 
            $related_id
        );
    }
} 