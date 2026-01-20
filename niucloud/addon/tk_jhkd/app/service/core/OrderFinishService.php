<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\dict\order\JhkdOrderDict;
use addon\tk_jhkd\app\job\commission\TkPartnerCommissionOrder;
use addon\tk_jhkd\app\job\order_event\OrderFinishAfter;
use addon\tk_jhkd\app\job\tkfenxiao\UpdateOrderJob;
use addon\tk_jhkd\app\model\fenxiao\FenxiaoOrder;
use addon\tk_jhkd\app\model\fenxiao\FenxiaoMember;
use app\dict\member\MemberAccountTypeDict;
use app\model\member\MemberLevel;
use app\service\core\member\CoreMemberAccountService;
use app\service\core\member\CoreMemberConfigService;
use app\service\core\member\CoreMemberService;
use core\base\BaseApiService;
use core\dict\DictLoader;
use think\facade\Db;
use think\facade\Log;
use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;

/**
 * 订单完成
 */
class OrderFinishService extends BaseApiService
{
    /**
     * @Notes:订单完成事件
     * @Interface orderFinish
     * @param $orderInfo
     * @return bool
     * @author: TK
     * @Time: 2024/5/28   下午1:52
     */
    public function orderFinish($orderInfo)
    {
        try {
            //进行成长值发放
            $this->sendGrowth($orderInfo['site_id'], $orderInfo['member_id'], 'tk_jhkd_order', ['from_type' => 'tk_jhkd_order']);
            //积分发放
            $this->sendPoint($orderInfo['site_id'], $orderInfo['member_id'], 'tk_jhkd_order', ['from_type' => 'tk_jhkd_order']);
            //会员等级激励发放
            // $this->sendLevel($orderInfo['site_id'], $orderInfo['member_id']);
            //进行分销结算 判断是否API订单 预留埋点
            (new FenxiaoService())->fenxiaoEvent($orderInfo);
            OrderFinishAfter::dispatch(['order_data' => [
                'site_id' => $orderInfo['site_id'],
                'member_id' => $orderInfo['member_id'],
                'order_id' => $orderInfo['order_id'],
                'order_money' => $orderInfo['order_money'],
            ]]);
            //进行开店宝接入
            $key = 'tk_jhkd_order';
            $config = (new CommonService())->getPartnerCommissionConfig($orderInfo['site_id'])[$key] ?? [];
            if (isset($config['is_use']) && $config['is_use'] == 1) {
                $create_data = [
                    'site_id' => $orderInfo['site_id'],
                    'member_id' => $orderInfo['member_id'],
                    'name' => '快递推广订单',
                    'order_id' => $orderInfo['order_id'],
                    'key' => $key,
                    'order_money' => $orderInfo['pay_money'],
                    'profit' => $orderInfo['pay_money'] * $config['rate'] / 100,
                    'status' => 2,
                ];
                TkPartnerCommissionOrder::dispatch(['data' => $create_data]);
            }
            $fenxiao_data= [
                'site_id' => $orderInfo['site_id'],
                'member_id' => $orderInfo['member_id'],
                'order_id'=>$orderInfo['order_id'],
                'key' => 'tk_jhkd_order',
                'status' => 2,  //0创建 1支付  2完成  -1取消
                'goods_list' => [
                    [
                        'name' => '快递产品下单',//产品名称
                        'image' => '',//主图
                        'order_money' => $orderInfo['order_money'],//订单金额
                        'pay_money' => $orderInfo['pay_money'],//实付金额
                        'num' => 1,//数量
                        'commission' => $orderInfo['pay_money'] ,//分佣金额,
                        'team_commission' => $orderInfo['pay_money'],//参与团队分红的金额
                        'agent_commission' => $orderInfo['pay_money'],//参与代理商分红的金额
                    ]
                ],
                'create_time' => strtotime($orderInfo['create_time']),//创建时间
            ];
            UpdateOrderJob::dispatch(['data' => $fenxiao_data]);
            return true;
        } catch (\Exception $e) {
            Log::write('-------聚合快递订单完成事件错误-------' . date('Y-m-d H:i:s', time()));
            Log::write($e->getMessage());
            return false;
        }
    }

    /**
     * @Notes:会员等级激励
     * @Interface sendLevel
     * @param $site_id
     * @param $member_id
     * @return true
     * @author: TK
     * @Time: 2024/5/22   下午2:39
     */
    public function sendLevel($site_id, $member_id)
    {
        $member_info = (new CoreMemberService())->getInfoByMemberId($site_id, $member_id, 'nickname, point, member_level');
        $member_info['member_level'] = (new MemberLevel())->where([['level_id', '=', $member_info['member_level']]])->field('site_id,level_id,level_benefits')->findOrEmpty()->toArray();
        if ($member_info['member_level'] && !empty($member_info['member_level']['level_benefits'])) {
            $level_benefits = $member_info['member_level']['level_benefits'];
            foreach ($level_benefits as $k => $v) {
                if ($k == 'tk_jhkd_fenxiao' && $v['is_use'] == 1 && isset($v['expand']) && $v['expand'] > 0) {
                    (new CoreMemberAccountService())->addLog($site_id, $member_id, MemberAccountTypeDict::MONEY, $v['expand'], 'bwc_award', '霸王餐等级单单激励');
                }
            }
        }
        return true;
    }

    /**
     * @Notes:发放积分
     * @Interface sendPoint
     * @param int $site_id
     * @param int $member_id
     * @param string $key
     * @param array $param
     * @return true
     * @author: TK
     * @Time: 2024/5/21   下午5:40
     */
    public static function sendPoint(int $site_id, int $member_id, string $key, array $param = [])
    {
        $config = (new CoreMemberConfigService())->getPointRuleConfig($site_id)['grant'] ?? [];
        if (!isset($config[$key]) || empty($config[$key]) || (isset($config[$key]['is_use']) && !$config[$key]['is_use'])) return true;

        $config = $config[$key];

        $dict = (new DictLoader("PointRule"))->load()['grant'] ?? [];
        if (!isset($dict[$key])) return true;
        $dict = $dict[$key];

        $point = $config['point'] ?? 0;
        if (isset($dict['calculate']) && !empty($dict['calculate'])) {
            $calculate = $dict['calculate'];
            if ($calculate instanceof \Closure) {
                $point = $calculate($config, $param);
            } else if (class_exists($calculate)) {
                $point = (new $calculate())->handle($config, $param);
            }
        }

        if ($point <= 0) return true;

        (new CoreMemberAccountService())->addLog($site_id, $member_id, MemberAccountTypeDict::POINT, $point, $param['from_type'] ?? '', $param['memo'] ?? $dict['desc'], $param['related_id'] ?? 0);
        return true;
    }

    /**
     * @Notes:发放会员成长值
     * @Interface sendGrowth
     * @param int $site_id
     * @param int $member_id
     * @param string $key
     * @param array $param
     * @return true
     * @author: TK
     * @Time: 2024/5/21   下午5:41
     */
    public static function sendGrowth(int $site_id, int $member_id, string $key, array $param = [])
    {
        $config = (new CoreMemberConfigService())->getGrowthRuleConfig($site_id);
        if (!isset($config[$key]) || empty($config[$key]) || (isset($config[$key]['is_use']) && !$config[$key]['is_use'])) return true;

        $config = $config[$key];

        $dict = (new DictLoader("GrowthRule"))->load();
        if (!isset($dict[$key])) return true;
        $dict = $dict[$key];

        $growth = $config['growth'] ?? 0;
        if (isset($dict['calculate']) && !empty($dict['calculate'])) {
            $calculate = $dict['calculate'];
            if ($calculate instanceof \Closure) {
                $growth = $calculate($config, $param);
            } else if (class_exists($calculate)) {
                $growth = (new $calculate())->handle($config, $param);
            }
        }

        if ($growth <= 0) return true;

        (new CoreMemberAccountService())->addLog($site_id, $member_id, MemberAccountTypeDict::GROWTH, $growth, $param['from_type'] ?? '', $param['momo'] ?? $dict['desc'], $param['related_id'] ?? 0);
        return true;
    }


}