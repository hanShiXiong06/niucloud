<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\api;

use addon\sd_xiaoyuan\app\service\core\XiaoyuanPayService;
use addon\sd_xiaoyuan\app\model\TipOrder;
use addon\sd_xiaoyuan\app\model\order\Order;
use app\dict\common\ChannelDict;
use app\service\core\member\CoreMemberService;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 校园帮支付业务
 */
class PayService extends BaseApiService
{
    /**
     * 获取支付信息
     */
    public function getInfoByTrade(string $trade_type, int $trade_id, array $data = [])
    {
        $this->checkTipPayOwner($trade_type, $trade_id);
        return (new XiaoyuanPayService())->getInfoByTrade(
            $this->site_id,
            $trade_type,
            (string)$trade_id,
            $this->channel,
            $data['scene'] ?? ''
        );
    }

    /**
     * 去支付
     */
    public function pay(string $type, string $trade_type, int $trade_id, string $return_url = '', string $quit_url = '', string $buyer_id = '', string $voucher = '', string $openid = '')
    {
        $this->checkTipPayOwner($trade_type, $trade_id);

        $member = (new CoreMemberService())->getInfoByMemberId($this->site_id, $this->member_id);
        switch ($this->channel) {
            case ChannelDict::WECHAT:
                $openid = $openid ?: ($member['wx_openid'] ?? '');
                break;
            case ChannelDict::WEAPP:
                $openid = $openid ?: ($member['weapp_openid'] ?? '');
                break;
        }
        return (new XiaoyuanPayService())->pay(
            $this->site_id,
            $trade_type,
            $trade_id,
            $type,
            $this->channel,
            $openid,
            $return_url,
            $quit_url,
            $buyer_id,
            $voucher,
            $this->member_id
        );
    }

    /**
     * 小费支付仅允许下单人
     */
    private function checkTipPayOwner(string $trade_type, int $trade_id)
    {
        if ($trade_type !== 'sd_xiaoyuan_tip') {
            return;
        }
        $tipOrder = (new TipOrder())->where([
            ['id', '=', $trade_id],
            ['site_id', '=', $this->site_id],
        ])->find();
        if (empty($tipOrder)) {
            throw new CommonException('小费订单不存在');
        }
        if ((int)$tipOrder['member_id'] !== (int)$this->member_id) {
            throw new CommonException('无权支付此小费');
        }
        $order = (new Order())->where([
            ['id', '=', (int)$tipOrder['order_id']],
            ['site_id', '=', $this->site_id],
        ])->find();
        if (empty($order) || (int)$order['member_id'] !== (int)$this->member_id) {
            throw new CommonException('无权操作此订单');
        }
    }
}
