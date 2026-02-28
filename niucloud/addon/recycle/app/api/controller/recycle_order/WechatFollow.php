<?php
declare(strict_types=1);

namespace addon\recycle\app\api\controller\recycle_order;

use core\base\BaseApiController;
use app\service\core\wechat\CoreWechatApiService;
use app\service\core\wechat\CoreWechatConfigService;
use app\model\member\Member;
use app\model\wechat\WechatFans;
use think\App;

/**
 * 公众号关注状态检查控制器
 * Class WechatFollow
 * @package addon\recycle\app\api\controller\recycle_order
 */
class WechatFollow extends BaseApiController
{
    protected $site_id;
    protected $member_id;

    /**
     * 初始化
     */
    public function initialize()
    {
        parent::initialize();
        $this->site_id = $this->request->siteid();
        $this->member_id = $this->request->memberid();
    }

    /**
     * 检查用户是否关注公众号，并返回公众号信息
     * 同时自动通过 unionid 绑定 wx_openid（不依赖框架事件）
     *
     * GET /api/recycle/wechat_follow/check
     */
    public function check()
    {
        // 1. 获取当前用户信息
        $member = (new Member())->where('member_id', $this->member_id)
            ->field('member_id, wx_openid, wx_unionid')
            ->findOrEmpty();

        if ($member->isEmpty()) {
            return success([
                'is_follow' => 0,
                'wechat_name' => '',
                'qr_code' => '',
            ]);
        }

        // 2. 如果没有 wx_openid，尝试通过 unionid 从 wechat_fans 表自动绑定
        if (empty($member['wx_openid']) && !empty($member['wx_unionid'])) {
            $fan = (new WechatFans())->where([
                'unionid' => $member['wx_unionid'],
                'site_id' => $this->site_id,
            ])->field('openid, is_subscribe')->findOrEmpty();

            if (!$fan->isEmpty() && !empty($fan['openid'])) {
                // 回填 wx_openid 到 member 表
                (new Member())->where('member_id', $this->member_id)
                    ->update(['wx_openid' => $fan['openid']]);
                $member['wx_openid'] = $fan['openid'];
            }
        }

        // 3. 查询关注状态
        $is_follow = 0;
        if (!empty($member['wx_openid'])) {
            try {
                $wechatApiService = new CoreWechatApiService();
                $userinfo = $wechatApiService->userInfo($this->site_id, $member['wx_openid']);
                $is_follow = ($userinfo['subscribe'] ?? 0) == 1 ? 1 : 0;
            } catch (\Exception $e) {
                // 查询失败时默认未关注
                $is_follow = 0;
            }
        }

        // 4. 获取公众号配置（名称 + 二维码）
        $wechatConfigService = new CoreWechatConfigService();
        $wechat_config = $wechatConfigService->getWechatConfig($this->site_id);

        return success([
            'is_follow' => $is_follow,
            'wechat_name' => $wechat_config['wechat_name'] ?? '',
            'qr_code' => $wechat_config['qr_code'] ?? '',
        ]);
    }
}
