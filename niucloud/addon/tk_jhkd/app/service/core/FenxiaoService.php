<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\dict\order\JhkdOrderDict;
use addon\tk_jhkd\app\model\fenxiao\FenxiaoOrder;
use addon\tk_jhkd\app\model\fenxiao\FenxiaoMember;
use addon\tk_jhkd\app\model\order\Order;
use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use app\dict\member\MemberAccountTypeDict;
use app\model\member\MemberLevel;
use app\service\core\member\CoreMemberAccountService;
use app\service\core\member\CoreMemberService;
use core\base\BaseApiService;
use think\facade\Log;

/**
 * 分销公共服务层
 */
class FenxiaoService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getOrderData($where)
    {
        $data = [
            'first_order' => $this->getFirstFenxiaoOrder()['total'],
            'two_order' => $this->getTwoFenxiaoOrder()['total'],
            'self_order' => $this->getSelfFenxiaoOrder()['total'],
            'self_direct_order' => $this->getSelfDirectFenxiaoOrder()['total']
        ];
        return $data;
    }

    /**
     * @Notes:自购返佣订单
     * @Interface getSelfFenxiaoOrder
     * @param $where
     * @return array
     * @author: TK
     * @Time: 2025/3/1 22:08
     */
    public function getSelfFenxiaoOrder($where = [])
    {
        $fenxiaoOrderModel = new FenxiaoOrder();
        $orderQuery = $fenxiaoOrderModel
            ->where(['site_id' => $this->site_id, 'type' => 1, 'member_id' => $this->member_id])
            ->order('create_time', 'desc')
            ->with([
                'memberInfo' => function ($query) {
                    $query->field('headimg,nickname,member_id');
                }
            ]);
        $list = $this->pageQuery($orderQuery);
        if (!empty($list['data'])) {
            $orderIds = array_column($list['data'], 'order_id');

            $orderModel = new Order();
            $orders = $orderModel->whereIn('order_id', $orderIds)
                ->column('*', 'order_id');

            $orderDeliveryModel = new OrderDelivery();
            $deliveries = $orderDeliveryModel->whereIn('order_id', $orderIds)
                ->column('*', 'order_id');

            foreach ($list['data'] as $k => $v) {
                $orderId = $v['order_id'];
                $orderInfo = $orders[$orderId] ?? [];
                $delivery = $deliveries[$orderId] ?? [];

                $list['data'][$k]['order_info'] = $orderInfo;
                $list['data'][$k]['status_name'] = !empty($orderInfo) ?
                    JhkdOrderDict::getStatus($orderInfo['order_status'])['name'] : '';
                $list['data'][$k]['start_address'] = !empty($delivery['start_address']) ?
                    json_decode($delivery['start_address'], true) : [];
                $list['data'][$k]['end_address'] = !empty($delivery['end_address']) ?
                    json_decode($delivery['end_address'], true) : [];
            }
        }

        return $list;
    }

    /**
     * @Notes:自购直推订单
     * @Interface getSelfDirectFenxiaoOrder
     * @param $where
     * @return array
     * @author: TK
     * @Time: 2025/3/1 22:08
     */
    public function getSelfDirectFenxiaoOrder($where = [])
    {
        $fenxiaoModel = new FenxiaoMember();
        $firstLevelIds = $fenxiaoModel
            ->where(['site_id' => $this->site_id, 'pid' => $this->member_id])
            ->column('member_id');

        if (empty($firstLevelIds)) {
            return ['data' => [], 'total' => 0];
        }
        $fenxiaoOrderModel = new FenxiaoOrder();
        $orderQuery = $fenxiaoOrderModel
            ->where(['site_id' => $this->site_id, 'type' => 1])
            ->whereIn('member_id', $firstLevelIds)
            ->order('create_time', 'desc')
            ->with([
                'memberInfo' => function ($query) {
                    $query->field('headimg,nickname,member_id');
                }
            ]);

        $list = $this->pageQuery($orderQuery);

        if (!empty($list['data'])) {
            $orderIds = array_column($list['data'], 'order_id');

            $orderModel = new Order();
            $orders = $orderModel->whereIn('order_id', $orderIds)
                ->column('*', 'order_id');

            $orderDeliveryModel = new OrderDelivery();
            $deliveries = $orderDeliveryModel->whereIn('order_id', $orderIds)
                ->column('*', 'order_id');

            foreach ($list['data'] as $k => $v) {
                $orderId = $v['order_id'];
                $orderInfo = $orders[$orderId] ?? [];
                $delivery = $deliveries[$orderId] ?? [];

                $list['data'][$k]['order_info'] = $orderInfo;
                $list['data'][$k]['status_name'] = !empty($orderInfo) ?
                    JhkdOrderDict::getStatus($orderInfo['order_status'])['name'] : '';
                $list['data'][$k]['start_address'] = !empty($delivery['start_address']) ?
                    json_decode($delivery['start_address'], true) : [];
                $list['data'][$k]['end_address'] = !empty($delivery['end_address']) ?
                    json_decode($delivery['end_address'], true) : [];
            }
        }

        return $list;
    }

    /**
     * @Notes:获取一级分销会员
     * getFirstFenxiaoMember
     * @param $where
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\DbException
     * 2024/12/20  22:52
     * author:TK
     */
    public function getFirstFenxiaoMember($where)
    {
        $fenxiaoModel = new FenxiaoMember();
        $query = $fenxiaoModel
            ->alias('fm')
            ->leftJoin('member m', 'm.member_id = fm.member_id')
            ->leftJoin('tkjhkd_fenxiao_order fo', 'fo.member_id = fm.member_id AND fo.site_id = fm.site_id')
            ->where([
                ['fm.site_id', '=', $this->site_id],
                ['fm.pid', '=', $this->member_id]
            ])
            ->field([
                'fm.member_id',
                'fm.pid',
                'fm.site_id',
                'fm.create_time',
                'm.headimg',
                'm.nickname',
                'COUNT(DISTINCT fo.order_id) as order_num'
            ])
            ->group('fm.member_id');
        if (!empty($where) && is_array($where)) {
            foreach ($where as $key => $value) {
                if (strpos($key, '.') === false) {
                    $key = 'fm.' . $key;
                }
                $allowedFields = ['fm.member_id', 'fm.pid', 'fm.site_id', 'fm.status', 'fm.create_time', 'fm.update_time'];
                if (in_array($key, $allowedFields) && $value !== null && $value !== '') {
                    $query->where($key, $value);
                }
            }
        }

        $total = $fenxiaoModel
            ->alias('fm')
            ->where([
                ['fm.site_id', '=', $this->site_id],
                ['fm.pid', '=', $this->member_id]
            ])
            ->count();
        $pageSize = request()->param('limit', 10);
        $currentPage = request()->param('page', 1);
        $list = $query->page($currentPage, $pageSize)->select();
        $result = [
            'total' => $total,
            'per_page' => intval($pageSize),
            'current_page' => intval($currentPage),
            'data' => []
        ];
        if ($list) {
            $result['data'] = array_map(function ($item) {
                return [
                    'member_id' => $item['member_id'],
                    'pid' => $item['pid'],
                    'site_id' => $item['site_id'],
                    'create_time' => $item['create_time'],
                    'memberInfo' => [
                        'member_id' => $item['member_id'],
                        'headimg' => $item['headimg'] ?? '',
                        'nickname' => $item['nickname'] ?? '',
                    ],
                    'order_num' => intval($item['order_num'])
                ];
            }, $list->toArray());
        }
        return $result;
    }

    /**、
     * @Notes:获取一级分销订单
     * getFirstFenxiaoOrder
     * @param $where
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 2024/12/20  22:51
     * author:TK
     */
    public function getFirstFenxiaoOrder($where = [])
    {
        $fenxiaoModel = new FenxiaoMember();
        $firstLevelIds = $fenxiaoModel
            ->where(['site_id' => $this->site_id, 'pid' => $this->member_id])
            ->column('member_id');

        if (empty($firstLevelIds)) {
            return ['data' => [], 'total' => 0];
        }
        $fenxiaoOrderModel = new FenxiaoOrder();
        $orderQuery = $fenxiaoOrderModel
            ->where(['site_id' => $this->site_id, 'type' => 0])
            ->whereIn('member_id', $firstLevelIds)
            ->order('create_time', 'desc')
            ->with([
                'memberInfo' => function ($query) {
                    $query->field('headimg,nickname,member_id');
                }
            ]);

        $list = $this->pageQuery($orderQuery);

        if (!empty($list['data'])) {
            $orderIds = array_column($list['data'], 'order_id');

            $orderModel = new Order();
            $orders = $orderModel->whereIn('order_id', $orderIds)
                ->column('*', 'order_id');

            $orderDeliveryModel = new OrderDelivery();
            $deliveries = $orderDeliveryModel->whereIn('order_id', $orderIds)
                ->column('*', 'order_id');

            foreach ($list['data'] as $k => $v) {
                $orderId = $v['order_id'];
                $orderInfo = $orders[$orderId] ?? [];
                $delivery = $deliveries[$orderId] ?? [];

                $list['data'][$k]['order_info'] = $orderInfo;
                $list['data'][$k]['status_name'] = !empty($orderInfo) ?
                    JhkdOrderDict::getStatus($orderInfo['order_status'])['name'] : '';
                $list['data'][$k]['start_address'] = !empty($delivery['start_address']) ?
                    json_decode($delivery['start_address'], true) : [];
                $list['data'][$k]['end_address'] = !empty($delivery['end_address']) ?
                    json_decode($delivery['end_address'], true) : [];
            }
        }

        return $list;
    }

    /**
     * @Notes:二级分销统计
     * getTwoFenxiaoOrder
     * @param $where
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 2024/12/21  09:56
     * author:TK
     */
    public function getTwoFenxiaoOrder($where = [])
    {
        $fenxiaoModel = new FenxiaoMember();
        $firstLevelIds = $fenxiaoModel
            ->where(['site_id' => $this->site_id, 'pid' => $this->member_id])
            ->column('member_id');
        $secondLevelIds = $fenxiaoModel
            ->where(['site_id' => $this->site_id])
            ->whereIn('pid', $firstLevelIds)
            ->column('member_id');

        if (empty($secondLevelIds)) {
            return ['data' => [], 'total' => 0];
        }
        $fenxiaoOrderModel = new FenxiaoOrder();
        $orderQuery = $fenxiaoOrderModel
            ->order('create_time', 'desc')
            ->where(['site_id' => $this->site_id, 'type' => 0])
            ->whereIn('member_id', $secondLevelIds)
            ->with([
                'memberInfo' => function ($query) {
                    $query->field('headimg,nickname,member_id');
                }
            ]);

        $list = $this->pageQuery($orderQuery);
        if (!empty($list['data'])) {
            $orderIds = array_column($list['data'], 'order_id');
            $orderModel = new Order();
            $orders = $orderModel->whereIn('order_id', $orderIds)
                ->column('*', 'order_id');

            $orderDeliveryModel = new OrderDelivery();
            $deliveries = $orderDeliveryModel->whereIn('order_id', $orderIds)
                ->column('*', 'order_id');
            foreach ($list['data'] as $k => $v) {
                $orderId = $v['order_id'];
                $orderInfo = $orders[$orderId] ?? [];
                $delivery = $deliveries[$orderId] ?? [];

                $list['data'][$k]['order_info'] = $orderInfo;
                $list['data'][$k]['status_name'] = !empty($orderInfo) ?
                    JhkdOrderDict::getStatus($orderInfo['order_status'])['name'] : '';
                $list['data'][$k]['start_address'] = !empty($delivery['start_address']) ?
                    json_decode($delivery['start_address'], true) : [];
                $list['data'][$k]['end_address'] = !empty($delivery['end_address']) ?
                    json_decode($delivery['end_address'], true) : [];
            }
        }

        return $list;
    }

    /**
     * @Notes:获取二级会员
     * getTwoFenxiaoMember
     * @param $where
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 2024/12/20  22:51
     * author:TK
     */
    public function getTwoFenxiaoMember($where)
    {
        $fenxiaoModel = new FenxiaoMember();
        $fenxiaoOrderModel = new FenxiaoOrder();
        $firstLevelIds = $fenxiaoModel
            ->where(['site_id' => $this->site_id, 'pid' => $this->member_id])
            ->column('member_id');
        $secondLevel = $fenxiaoModel
            ->where(['site_id' => $this->site_id])
            ->whereIn('pid', $firstLevelIds)
            ->with(['memberInfo' => function ($query) {
                $query->field('headimg,nickname,member_id');
            }]);

        $list = $this->pageQuery($secondLevel);
        if (!empty($list['data'])) {
            $memberIds = array_column($list['data'], 'member_id');
            $orderCounts = $fenxiaoOrderModel
                ->where(['site_id' => $this->site_id])
                ->whereIn('member_id', $memberIds)
                ->group('member_id')
                ->column('count(*)', 'member_id');
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['order_num'] = $orderCounts[$v['member_id']] ?? 0;
            }
        }

        return $list;
    }

    public function getFenxiaoInfo()
    {
        $fenxiaoModel = new FenxiaoMember();
        $fenxiaoOrderModel = new FenxiaoOrder();
        $firstFenxiao = $fenxiaoModel
            ->where(['site_id' => $this->site_id, 'pid' => $this->member_id])
            ->column('member_id');
        $firstNum = count($firstFenxiao);
        $secondNum = $fenxiaoModel
            ->where(['site_id' => $this->site_id])
            ->whereIn('pid', $firstFenxiao)
            ->count();
        $firstOrderNum = $fenxiaoOrderModel
            ->where(['site_id' => $this->site_id, 'type' => 0])
            ->whereIn('member_id', $firstFenxiao)
            ->count();
        $selfOrderNum = $fenxiaoOrderModel
            ->where(['site_id' => $this->site_id, 'type' => 1])
            ->where(['member_id' => $this->member_id])
            ->count();
        $selfDirectOrderNum = $fenxiaoOrderModel
            ->where(['site_id' => $this->site_id, 'type' => 1])
            ->whereIn('member_id', $firstFenxiao)
            ->count();
        $secondLevelMembers = $fenxiaoModel
            ->where(['site_id' => $this->site_id])
            ->whereIn('pid', $firstFenxiao)
            ->column('member_id');
        $secondOrderNum = $fenxiaoOrderModel
            ->where(['site_id' => $this->site_id, 'type' => 0])
            ->whereIn('member_id', $secondLevelMembers)
            ->count();

        return [
            'first_num' => $firstNum,
            'second_num' => $secondNum,
            'first_order_num' => $firstOrderNum,
            'second_order_num' => $secondOrderNum,
            'self_order_num' => $selfOrderNum,
            'self_direct_order_num' => $selfDirectOrderNum
        ];
    }

    /**
     * @Notes:分销佣金结算
     * @Interface fenxiaoEvent
     * @param $orderInfo
     * @return true
     * @author: TK
     * @Time: 2024/5/28   下午1:03
     */
    public function fenxiaoEvent($orderInfo)
    {

        // 初始化基础数据
        $member_id = $orderInfo['member_id'];
        $site_id = $orderInfo['site_id'];
        $fenxiaoMemberModel = new FenxiaoMember();
        $fenxiaoOrderModel = new FenxiaoOrder();
        // 创建基础分销订单
        if (!$fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->count()) {
            $fenxiaoOrderModel->create([
                'order_id' => $orderInfo['order_id'],
                'site_id' => $orderInfo['site_id'],
                'member_id' => $orderInfo['member_id'],
                'type' => 0,
                'status' => 0,
                'create_time' => time()
            ]);
        }
        // 封装佣金计算逻辑
        $calculateCommission = function ($config, $orderMoney, $type) {
            $commission = 0;
            if ($config['fenxiao_type'] == 0 && $config[$type . '_rate'] > 0) {
                $commission = $config[$type . '_rate'] / 100 * $orderMoney;
            } elseif ($config['fenxiao_type'] == 1 && $config[$type . '_commission'] > 0) {
                $commission = $config[$type . '_commission'];
            }
            return number_format($commission, 2, '.', '');
        };

        // 封装分销订单更新
        $updateFenxiaoOrder = function ($orderId, $data) use ($fenxiaoOrderModel) {
            $fenxiaoOrderModel->where(['order_id' => $orderId])->update(array_merge($data, [
                'update_time' => time()
            ]));
        };

        // 封装佣金日志
        $addCommissionLog = function ($memberId, $amount, $level) use ($orderInfo) {
            (new CoreMemberAccountService())->addLog(
                $orderInfo['site_id'],
                $memberId,
                MemberAccountTypeDict::COMMISSION,
                $amount,
                'jhkd_award',
                "快递{$level}分销佣金"
            );
        };

        // 处理自购返佣逻辑
        $memberInfo = (new CoreMemberService())->getInfoByMemberId($site_id, $member_id, 'nickname, point, member_level');
        $memberLevel = (new MemberLevel())->where([['level_id', '=', $memberInfo['member_level']]])
            ->field('site_id,level_id,level_benefits')
            ->findOrEmpty()
            ->toArray();

        if (!empty($memberLevel['level_benefits']['tk_jhkd_fenxiao'])) {
            $config = $memberLevel['level_benefits']['tk_jhkd_fenxiao'];

            if ($config['is_use'] == 1 && isset($config['is_self']) && $config['is_self'] == 1) {
                // 处理一级自购佣金
                $firstCommission = $calculateCommission($config, $orderInfo['order_money'], 'first');
                $updateFenxiaoOrder($orderInfo['order_id'], [
                    'first_commission' => $firstCommission,
                    'status' => 1,
                    'type' => 1
                ]);
                $addCommissionLog($member_id, $firstCommission, '自购一级');

                // 处理二级自购佣金（修复原代码中错误的字段）
                if ($fenxiaoMember = $fenxiaoMemberModel->where(['site_id' => $site_id, 'member_id' => $member_id])->find()) {
                    if ($pMember = (new CoreMemberService())->getInfoByMemberId($site_id, $fenxiaoMember['pid'])) {
                        $secondCommission = $calculateCommission($config, $orderInfo['order_money'], 'second');
                        $updateFenxiaoOrder($orderInfo['order_id'], [
                            'two_commission' => $secondCommission  // 修复原代码中的字段错误
                        ]);
                        $addCommissionLog($fenxiaoMember['pid'], $secondCommission, '二级');
                    }
                }
                return true;
            }
        }
        $this->fenxiaoEventOld($orderInfo);
        // 最终状态更新
        $updateFenxiaoOrder($orderInfo['order_id'], ['status' => 1]);
        return true;
    }
    public function fenxiaoEventOld($orderInfo)
    {
        $member_id = $orderInfo['member_id'];
        $site_id = $orderInfo['site_id'];
        $fenxiaoMemberModel = new FenxiaoMember();
        $fenxiaoOrderModel = new FenxiaoOrder();

        //查询是否有上级会员
        $fenxiaoMemberInfo = $fenxiaoMemberModel->where(['site_id' => $site_id, 'member_id' => $member_id])->findOrEmpty();
        if ($fenxiaoMemberInfo->isEmpty()) {
            $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                'status' => 1,
                'update_time' => time()
            ]);
            return true;
        }
        //获取一级分销会员信息
        $p_member_info = (new CoreMemberService())->getInfoByMemberId($site_id, $fenxiaoMemberInfo['pid'], 'nickname, point, member_level');
        if (empty($p_member_info)) {
            $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                'status' => 1,
                'update_time' => time()
            ]);
            return true;
        }
        $p_member_info['member_level'] = (new MemberLevel())->where([['level_id', '=', $p_member_info['member_level']]])->field('site_id,level_id,level_benefits')->findOrEmpty()->toArray();
        if ($p_member_info['member_level'] && !empty($p_member_info['member_level']['level_benefits'])) {
            $level_benefits = $p_member_info['member_level']['level_benefits'];
            foreach ($level_benefits as $k => $v) {
                if ($k == 'tk_jhkd_fenxiao' && $v['is_use'] == 1) {
                    $commission = 0;
                    //比列
                    if ($v['fenxiao_type'] == 0 && $v['first_rate'] > 0) {
                        $commission = $v['first_rate'] / 100 * $orderInfo['order_money'];
                    }
                    //固定金额
                    if ($v['fenxiao_type'] == 1 && $v['first_commission'] > 0) {
                        $commission = $v['first_commission'];
                    }
                    $commission = number_format($commission, 2, '.', '');
                    //记录佣金信息
                    $orderFenxiao = $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                        'first_commission' => $commission,
                    ]);

                    (new CoreMemberAccountService())->addLog($orderInfo['site_id'], $fenxiaoMemberInfo['pid'], MemberAccountTypeDict::COMMISSION, $commission, 'jhkd_award', '快递一级分销佣金');
                    $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                        'status' => 1,
                        'update_time' => time()
                    ]);
                }
            }
        }
        //获取二级分销信息
        $pp_fenxiaoMemberInfo = $fenxiaoMemberModel->where(['site_id' => $site_id, 'member_id' => $fenxiaoMemberInfo['pid']])->findOrEmpty();
        if ($pp_fenxiaoMemberInfo->isEmpty()) {
            $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                'status' => 1,
                'update_time' => time()
            ]);
            return true;
        }
        $pp_member_info = (new CoreMemberService())->getInfoByMemberId($site_id, $pp_fenxiaoMemberInfo['pid'], 'nickname, point, member_level');
        if (empty($pp_member_info)) {
            $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                'status' => 1,
                'update_time' => time()
            ]);
            return true;
        }
        $pp_member_info['member_level'] = (new MemberLevel())->where([['level_id', '=', $pp_member_info['member_level']]])->field('site_id,level_id,level_benefits')->findOrEmpty()->toArray();
        if ($pp_member_info['member_level'] && !empty($pp_member_info['member_level']['level_benefits'])) {
            $level_benefits = $pp_member_info['member_level']['level_benefits'];
            foreach ($level_benefits as $k => $v) {
                if ($k == 'tk_jhkd_fenxiao' && $v['is_use'] == 1) {
                    $commission = 0;
                    //比列
                    if ($v['fenxiao_type'] == 0 && $v['second_rate'] > 0) {
                        $commission = $v['second_rate'] / 100 * $orderInfo['order_money'];
                    }
                    //固定金额
                    if ($v['fenxiao_type'] == 1 && $v['second_commission'] > 0) {
                        $commission = $v['second_commission'];
                    }
                    $commission = number_format($commission, 2, '.', '');
                    //记录佣金信息
                    $orderFenxiao = $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                        'two_commission' => $commission,
                    ]);
                    (new CoreMemberAccountService())->addLog($orderInfo['site_id'], $pp_fenxiaoMemberInfo['pid'], MemberAccountTypeDict::COMMISSION, $commission, 'jhkd_award', '快递二级分销佣金');
                    $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                        'status' => 1,
                        'update_time' => time()
                    ]);
                }
            }
        }
        $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
            'status' => 1,
            'update_time' => time()
        ]);
        return true;
    }

    /**
     * @Notes:分销绑定
     * @Interface checkFenxiao
     * @param $data
     * @return array
     * @author: TK
     * @Time: 2024/5/28   下午1:02
     */
    public function checkFenxiao($data)
    {
        if ($this->member_id == $data['pid']) return [];
        $fenxiaoMemberModel = new FenxiaoMember();
        $fenxiaoMemberInfo = $fenxiaoMemberModel->where(['site_id' => $this->site_id, 'member_id' => $this->member_id])->findOrEmpty();
        //会员分销关系存在不进行管理
        if (!$fenxiaoMemberInfo->isEmpty()) return [];
        //上级会员信息及权限判断
        $p_member_info = (new CoreMemberService())->getInfoByMemberId($this->site_id, $data['pid'], 'nickname, point, member_level');
        if (empty($p_member_info)) return [];
        $p_member_info['member_level'] = (new MemberLevel())->where([['level_id', '=', $p_member_info['member_level']]])->field('site_id,level_id,level_benefits')->findOrEmpty()->toArray();
        if ($p_member_info['member_level'] && !empty($p_member_info['member_level']['level_benefits'])) {
            $level_benefits = $p_member_info['member_level']['level_benefits'];
            foreach ($level_benefits as $k => $v) {
                if ($k == 'tk_jhkd_fenxiao' && $v['is_use'] == 1) {
                    //锁定PID入库
                    $hs_fxiao = $fenxiaoMemberModel->where(['site_id' => $this->site_id, 'member_id' => $data['pid']])->findOrEmpty();
                    if ($hs_fxiao->isEmpty()) {
                        $fenxiaoMemberModel->create([
                            'site_id' => $this->site_id,
                            'member_id' => $data['pid'],
                            'pid' => 0,
                            'create_time' => time()
                        ]);
                    }
                    //等级拥有分销权限
                    $fenxiaoMemberModel->create([
                        'site_id' => $this->site_id,
                        'member_id' => $this->member_id,
                        'pid' => $data['pid'],
                        'create_time' => time()
                    ]);
                }
            }
        }
        return [];
    }

    public function bindFenxiao($data)
    {
        $this->member_id = $data['member_id'];
        $this->site_id = $data['site_id'];
        if ($this->member_id == $data['pid']) return [];
        $fenxiaoMemberModel = new FenxiaoMember();
        $fenxiaoMemberInfo = $fenxiaoMemberModel->where(['site_id' => $this->site_id, 'member_id' => $this->member_id])->findOrEmpty();
        //会员分销关系存在不进行管理
        if (!$fenxiaoMemberInfo->isEmpty()) return [];
        //上级会员信息及权限判断
        $p_member_info = (new CoreMemberService())->getInfoByMemberId($this->site_id, $data['pid'], 'nickname, point, member_level');
        if (empty($p_member_info)) return [];
        $p_member_info['member_level'] = (new MemberLevel())->where([['level_id', '=', $p_member_info['member_level']]])->field('site_id,level_id,level_benefits')->findOrEmpty()->toArray();
        if ($p_member_info['member_level'] && !empty($p_member_info['member_level']['level_benefits'])) {
            $level_benefits = $p_member_info['member_level']['level_benefits'];
            foreach ($level_benefits as $k => $v) {
                if ($k == 'tk_jhkd_fenxiao' && $v['is_use'] == 1) {
                    //锁定PID入库
                    $hs_fxiao = $fenxiaoMemberModel->where(['site_id' => $this->site_id, 'member_id' => $data['pid']])->findOrEmpty();
                    if ($hs_fxiao->isEmpty()) {
                        $fenxiaoMemberModel->create([
                            'site_id' => $this->site_id,
                            'member_id' => $data['pid'],
                            'pid' => 0,
                            'create_time' => time()
                        ]);
                    }
                    //锁定会员绑定信息
                    $fenxiaoMemberModel->create([
                        'site_id' => $this->site_id,
                        'member_id' => $this->member_id,
                        'pid' => $data['pid'],
                        'create_time' => time()
                    ]);
                }
            }
        }
        return [];
    }
}
