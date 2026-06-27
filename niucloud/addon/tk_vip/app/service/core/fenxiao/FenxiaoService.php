<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_vip\app\service\core\fenxiao;

use addon\tk_vip\app\model\fenxiao\FenxiaoOrder;
use addon\tk_vip\app\model\fenxiao\FenxiaoMember;
use addon\tk_vip\app\model\order\Order;
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
            ->leftJoin('tkvip_fenxiao_order fo', 'fo.member_id = fm.member_id AND fo.site_id = fm.site_id')
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

    public function getSelfFenxiaoOrder($where = [])
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
            ->order('create_time', 'desc')
            ->where(['site_id' => $this->site_id, 'member_id' => $this->member_id])
            ->with([
                'memberInfo' => function ($query) {
                    $query->field('headimg,nickname,member_id');
                }
            ]);

        $list = $this->pageQuery($orderQuery);
        //查询订单信息
        $mainOrderModel = new Order();
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['order_info'] = $mainOrderModel->where(['order_id' => $v['order_id']])->findOrEmpty();
        }
        return $list;
    }

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
            ->order('create_time', 'desc')
            ->where(['site_id' => $this->site_id])
            ->whereIn('member_id', $firstLevelIds)
            ->with([
                'memberInfo' => function ($query) {
                    $query->field('headimg,nickname,member_id');
                }
            ]);

        $list = $this->pageQuery($orderQuery);
        $mainOrderModel = new Order();
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['order_info'] = $mainOrderModel->where(['order_id' => $v['order_id']])->findOrEmpty();
        }
        return $list;
    }

    /**
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
            ->order('create_time', 'desc')
            ->where(['site_id' => $this->site_id])
            ->whereIn('member_id', $firstLevelIds)
            ->with([
                'memberInfo' => function ($query) {
                    $query->field('headimg,nickname,member_id');
                }
            ]);

        $list = $this->pageQuery($orderQuery);
        $mainOrderModel = new Order();
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['order_info'] = $mainOrderModel->where(['order_id' => $v['order_id']])->findOrEmpty();
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
            ->where(['site_id' => $this->site_id])
            ->order('create_time', 'desc')
            ->whereIn('member_id', $secondLevelIds)
            ->with([
                'memberInfo' => function ($query) {
                    $query->field('headimg,nickname,member_id');
                }
            ]);

        $list = $this->pageQuery($orderQuery);
        $mainOrderModel = new Order();
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['order_info'] = $mainOrderModel->where(['order_id' => $v['order_id']])->findOrEmpty();
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

    /**
     * @Notes:获取分销信息
     * getFenxiaoInfo
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 2024/12/20  08:26
     * author:TK
     */
    public function getFenxiaoInfo()
    {
        $fenxiaoModel = new FenxiaoMember();
        $fenxiaoOrderModel = new FenxiaoOrder();
        $firstFenxiao = $fenxiaoModel
            ->where(['site_id' => $this->site_id, 'pid' => $this->member_id])
            ->column('member_id');

        // 优化条件组合方式
        $firstOrderCondition = [
            ['site_id', '=', $this->site_id],
            ['member_id', 'in', $firstFenxiao]
        ];

        $selfOrderCondition = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ];

        $selfDirectOrderCondition = [
            ['site_id', '=', $this->site_id],
            ['member_id', 'in', $firstFenxiao]
        ];

        return [
            'first_num' => count($firstFenxiao),
            'second_num' => $fenxiaoModel->where(['site_id' => $this->site_id])->whereIn('pid', $firstFenxiao)->count(),
            'first_order_num' => $fenxiaoOrderModel->where($firstOrderCondition)->count(),
            'second_order_num' => $fenxiaoOrderModel->where([
                ['site_id', '=', $this->site_id],
                ['member_id', 'in', $fenxiaoModel->where(['site_id' => $this->site_id])
                    ->whereIn('pid', $firstFenxiao)
                    ->column('member_id')]
            ])->count(),
            'self_order_num' => $fenxiaoOrderModel->where($selfOrderCondition)->count(),
            'self_direct_order_num' => $fenxiaoOrderModel->where($selfDirectOrderCondition)->count()
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
        // 封装分销订单更新
        $updateFenxiaoOrder = function ($orderId, $data) use ($fenxiaoOrderModel) {
            $fenxiaoOrderModel->where(['order_id' => $orderId])->update(array_merge($data, [
                'update_time' => time()
            ]));
        };

        // 封装佣金日志
        $addCommissionLog = function ($memberId, $amount) use ($orderInfo) {
            (new CoreMemberAccountService())->addLog(
                $orderInfo['site_id'],
                $memberId,
                MemberAccountTypeDict::COMMISSION,
                $amount,
                'tk_vip_fee',
                "VIP权益自购返佣金"
            );
        };

        // 处理自购返佣逻辑
        $memberInfo = (new CoreMemberService())->getInfoByMemberId($site_id, $member_id, 'nickname, point, member_level');
        $memberLevel = (new MemberLevel())->where([['level_id', '=', $memberInfo['member_level']]])
            ->field('site_id,level_id,level_benefits')
            ->findOrEmpty()
            ->toArray();

        if (!empty($memberLevel['level_benefits']['tk_vip_fee'])) {
            $config = $memberLevel['level_benefits']['tk_vip_fee'];
            if ($config['is_use'] == 1 && isset($config['is_self']) && $config['is_self'] == 1) {
                // 处理自购佣金
                $selfCommission = $orderInfo['order_money'] * $config['self_commission_rate'] / 100;
                $selfCommission = number_format($selfCommission, 2, '.', '');
                $updateFenxiaoOrder($orderInfo['order_id'], [
                    'self_commission' => $selfCommission,
                    'status' => 1,
                    'type' => 1
                ]);
                if ($selfCommission > 0) {
                    $addCommissionLog($member_id, $selfCommission);
                }
                //自购积分
                $selfPoint = $orderInfo['order_money'] * $config['self_point_rate'] / 100;
                $selfPoint = round($selfPoint);
                $updateFenxiaoOrder($orderInfo['order_id'], [
                    'self_point' => $selfPoint,
                    'status' => 1,
                    'type' => 1
                ]);
                if ($selfPoint >= 1) {
                    (new CoreMemberAccountService())->addLog(
                        $orderInfo['site_id'],
                        $member_id,
                        MemberAccountTypeDict::POINT,
                        $selfPoint,
                        'tk_vip_fee',
                        "自购奖励积分"
                    );
                }
            }
            //进行一二级分销佣金处理
            $this->fenxiaoEventOld($orderInfo);
        }

        // 最终状态更新
        $updateFenxiaoOrder($orderInfo['order_id'], ['status' => 1]);
        return true;
    }

    public function fenxiaoEventOld($orderInfo)
    {
        try {
            $member_id = $orderInfo['member_id'];
            $site_id = $orderInfo['site_id'];
            $fenxiaoMemberModel = new FenxiaoMember();
            $fenxiaoOrderModel = new FenxiaoOrder();
            $fenxiaoModelInfo = $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->findOrEmpty();
            if ($fenxiaoModelInfo->isEmpty()) {
                $fenxiaoOrderModel->save([
                    'order_id' => $orderInfo['order_id'],
                    'site_id' => $orderInfo['site_id'],
                    'member_id' => $orderInfo['member_id'],
                    'type' => '0',
                    'status' => 0,
                    'create_time' => time()
                ]);
            }
            //查询是否有上级会员
            $fenxiaoMemberInfo = $fenxiaoMemberModel->where(['site_id' => $site_id, 'member_id' => $member_id])->findOrEmpty();
            if ($fenxiaoMemberInfo->isEmpty()) return true;
            //获取一级分销会员信息
            $p_member_info = (new CoreMemberService())->getInfoByMemberId($site_id, $fenxiaoMemberInfo['pid'], 'nickname, point, member_level');
            if (empty($p_member_info)) return true;
            $p_member_info['member_level'] = (new MemberLevel())->where([['level_id', '=', $p_member_info['member_level']]])->field('site_id,level_id,level_benefits')->findOrEmpty()->toArray();
            if ($p_member_info['member_level'] && !empty($p_member_info['member_level']['level_benefits'])) {
                $level_benefits = $p_member_info['member_level']['level_benefits'];
                foreach ($level_benefits as $k => $v) {
                    if ($k == 'tk_vip_fee' && $v['is_use'] == 1) {
                        if ($v['is_fenxiao_commission'] == 1) {
                            //佣金
                            $commission = $v['first_commission_rate'] / 100 * $orderInfo['order_money'];
                            $commission = number_format($commission, 2, '.', '');
                            //记录佣金信息
                            $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                                'first_commission' => $commission,
                            ]);
                            (new CoreMemberAccountService())->addLog($orderInfo['site_id'], $fenxiaoMemberInfo['pid'], MemberAccountTypeDict::COMMISSION, $commission, 'tk_vip_fee', '会员权益一级佣金');
                            $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                                'status' => 1,
                                'update_time' => time()
                            ]);
                        }
                        if ($v['is_fenxiao_point'] == 1) {
                            //佣金
                            $point = $v['first_point_rate'] / 100 * $orderInfo['order_money'];
                            $point = round($point);
                            if($point >= 1){
                                //记录佣金信息
                                $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                                    'first_point' => $point,
                                ]);
                                (new CoreMemberAccountService())->addLog($orderInfo['site_id'], $fenxiaoMemberInfo['pid'], MemberAccountTypeDict::POINT, $point, 'tk_vip_fee', '会员权益一级积分激励');
                                $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                                    'status' => 1,
                                    'update_time' => time()
                                ]);
                            }
                        }
                    }
                }
            }
            //获取二级分销信息
            $pp_fenxiaoMemberInfo = $fenxiaoMemberModel->where(['site_id' => $site_id, 'member_id' => $fenxiaoMemberInfo['pid']])->findOrEmpty();
            if ($pp_fenxiaoMemberInfo->isEmpty()) return true;
            $pp_member_info = (new CoreMemberService())->getInfoByMemberId($site_id, $pp_fenxiaoMemberInfo['pid'], 'nickname, point, member_level');
            if (empty($pp_member_info)) return true;
            $pp_member_info['member_level'] = (new MemberLevel())->where([['level_id', '=', $pp_member_info['member_level']]])->field('site_id,level_id,level_benefits')->findOrEmpty()->toArray();
            if ($pp_member_info['member_level'] && !empty($pp_member_info['member_level']['level_benefits'])) {
                $level_benefits = $pp_member_info['member_level']['level_benefits'];
                foreach ($level_benefits as $k => $v) {
                    if ($k == 'tk_vip_fee' && $v['is_use'] == 1) {
                        if ($v['is_fenxiao_commission'] == 1) {
                            //比列
                            $commission = $v['second_commission_rate'] / 100 * $orderInfo['order_money'];
                            $commission = number_format($commission, 2, '.', '');
                            //记录佣金信息
                            $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                                'two_commission' => $commission,
                            ]);
                            if ($commission > 0) {
                                (new CoreMemberAccountService())->addLog($orderInfo['site_id'], $pp_fenxiaoMemberInfo['pid'], MemberAccountTypeDict::COMMISSION, $commission, 'tk_vip_fee', '会员权益二级分销激励');
                            }
                            $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                                'status' => 1,
                                'update_time' => time()
                            ]);
                        }
                        if ($v['is_fenxiao_point'] == 1) {
                            //比列
                            $point = $v['second_point_rate'] / 100 * $orderInfo['order_money'];
                            $point = round($point);
                            if ($point >= 1){
                                //记录佣金信息
                                $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                                    'two_point' => $point,
                                ]);
                                if ($point > 0) {
                                    (new CoreMemberAccountService())->addLog($orderInfo['site_id'], $pp_fenxiaoMemberInfo['pid'], MemberAccountTypeDict::POINT, $point, 'tk_vip_fee', '会员权益二级分销激励');
                                }
                                $fenxiaoOrderModel->where(['order_id' => $orderInfo['order_id']])->update([
                                    'status' => 1,
                                    'update_time' => time()
                                ]);
                            }
                        }
                    }
                }
            }
            return true;
        } catch (\Exception $e) {
            Log::write("===会员权益分销事件执行失败====" . $e->getMessage() . $e->getFile() . $e->getLine());
        }
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
                if ($k == 'tk_vip_fee' && $v['is_use'] == 1) {
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
                if ($k == 'tk_vip_fee' && $v['is_use'] == 1) {
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
}
