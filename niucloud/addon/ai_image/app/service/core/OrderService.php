<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\ai_image\app\service\core;

use addon\ai_image\app\dict\order\OrderStatusDict;
use addon\ai_image\app\model\aiimagecard\AiimageCard;
use addon\ai_image\app\model\aiimageorder\AiimageOrder;
use app\dict\member\MemberAccountTypeDict;
use app\service\core\member\CoreMemberAccountService;
use app\service\core\sys\CoreConfigService;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\Exception;
use think\facade\Log;


/**
 * 订单列服务层
 * Class TksoraOrderService
 * @package addon\tk_sora\app\service\admin\tksoraorder
 */
class OrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new AiimageOrder();
    }

    public function paySuccess($pay_info)
    {
        try {
            $trade_id = $pay_info['trade_id'] ?? 0;
            $order_model = $this->model;
            $order_info = $order_model->where([['site_id', '=', $pay_info['site_id']], ['id', '=', $trade_id]])->findOrEmpty();
            if ($order_info['status'] != OrderStatusDict::WAIT_PAY) return true;
            $order_data = [
                'pay_time' => time(),
                'status' => OrderStatusDict::FINISH,
                'out_trade_no' => $pay_info['out_trade_no']//支付后的交易流水号
            ];
            $order_model->where([['site_id', '=', $pay_info['site_id']], ['id', '=', $trade_id]])->update($order_data);
            //进行支付后充值
            $this->packageSuccess($order_info);
            return true;
        } catch (Exception $e) {
            Log::write('===AI设计支付成功处理异常===' . date('Y-m-d H:i:s'));
            Log::write($e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }

    public function packageSuccess($order_info)
    {
        if ($order_info['type'] == 'point') {
            //进行积分充值
            (new CoreMemberAccountService())->addLog($order_info['site_id'], $order_info['member_id'], MemberAccountTypeDict::POINT, $order_info['point'], 'ai_image_award', 'AI设计积分充值', $order_info['id']);
        }
        if ($order_info['type'] == 'card') {
            $this->model = new AiimageCard();
            $data['site_id'] = $order_info['site_id'];
            $data['is_use'] = 0;
            $data['is_export'] = 0;
            $data['member_id'] = 0;
            $data['point']=$order_info['point'];
            $data['pid'] = $order_info['member_id'];
            $data['expire_time'] = $order_info['day'] * 24 * 60 * 60 + time();
            $num = $order_info['num'];
            for ($i = 0; $i < $num; $i++) {
                $data['card_num'] = $this->generateCardNum();
                $this->model->create($data);
            }
        }
        return true;
    }

    private function generateCardNum()
    {
        do {
            $cardNum = '';
            // 生成两个随机字母
            $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $randomLetters = $letters[random_int(0, 25)] . $letters[random_int(0, 25)];
            for ($i = 0; $i < 8; $i++) {
                $cardNum .= random_int(0, 8); // 生成16位纯数字卡密
            }
            $exists = $this->model->where('card_num', $randomLetters . $cardNum)->find();
        } while ($exists);

        return $randomLetters . $cardNum;
    }

}
