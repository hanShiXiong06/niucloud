<?php
// +----------------------------------------------------------------------
// | Author: addon888
// +----------------------------------------------------------------------
namespace addon\tk_vip\app\job;

use addon\tk_vip\app\model\vip\Vip;
use addon\tk_vip\app\service\core\MemberService;
use app\model\member\Member;
use app\model\member\MemberLevel;
use app\service\core\member\CoreMemberService;
use core\base\BaseJob;
use think\Exception;
use think\facade\Log;

/**
 * 检测会员等级是否过期按天
 */
class CheckLevelDay extends BaseJob
{
    /**
     * 消费
     * @return true
     */
    public function doJob()
    {
        try {
            Log::write("=== TK VIP 每天修改会员等级到期执行 ===" . date("Y-m-d H:i:s"));
            $vipModel = new Vip();
            $pageSize = 50;
            $currentPage = 1;
            $memberService = new MemberService();
            $memberModel = new Member();
            while (true) {
                $list = $vipModel->where([
                    ['over_time', '>=', time() - 60 * 60 * 24],
                    ['over_time', '<=', time()]
                ])->paginate([
                    'list_rows' => $pageSize,
                    'page' => $currentPage,
                ])->toArray();
                foreach ($list['data'] as $v) {
                    $site_id = $v['site_id'];
                    $member_id = $v['member_id'];
                    if ($v['over_time'] > 0) {
                        // 初始会员等级
                        (new CoreMemberService())->modify($site_id, $member_id, 'member_level', 0);
                        //取消分销
                        $m_info = $memberModel->where(['site_id' => $site_id, 'member_id' => $member_id])->findOrEmpty();
                        $memberLevel = (new MemberLevel())->where([['level_id', '=', $m_info['member_level']]])
                            ->field('site_id,level_id,level_benefits')
                            ->findOrEmpty()
                            ->toArray();
                        if (!empty($memberLevel['level_benefits']['tk_vip_fee'])) {
                            $config = $memberLevel['level_benefits']['tk_vip_fee'];
                            if (isset($config['is_use'])&&$config['is_use'] == 1 && $config['close_shop_fenxiao'] == 1) {
                                $memberService->cancelFenxiao($site_id, $member_id);
                            }
                        }
                    }
                }
                if ($currentPage * $pageSize >= $list['total']) {
                    break;
                }
                $currentPage++;
            }
            return true;
        } catch (Exception $e) {
            Log::write($e->getMessage());
            return true;
        }
    }
}
