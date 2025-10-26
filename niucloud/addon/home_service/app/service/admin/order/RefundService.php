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

namespace addon\home_service\app\service\admin\order;

use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\dict\order\OrderRefundLogDict;
use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\model\account\StoreAccount;
use addon\home_service\app\model\account\TechnicianAccount;
use addon\home_service\app\model\order\OrderItem;
use addon\home_service\app\model\order\OrderRefund;
use addon\home_service\app\service\core\order\CoreOrderRefundLogService;
use addon\home_service\app\service\core\order\CoreOrderRefundService;
use app\model\pay\Refund;
use app\service\core\notice\NoticeService;
use app\service\core\pay\CoreRefundService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use addon\home_service\app\service\core\statistics\CoreRefundService as staCoreRefundService;

/**
 *
 * Class RefundService
 */
class RefundService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderRefund();
    }


    public function getStatus()
    {
        return RefundDict::getRefundStatus();
    }


    /**
     * 订单售后昨天带查询
     * @param array $where
     * @return mixed
     */
    public function getTaskStatus()
    {
        // 初始化退款状态列表，设置初始数量为0
        $taskStatusList = array_map(function ($item) {
            return [
                'name' => $item['name'],
                'status' => $item['status'],
                'count' => 0 // 初始化数量为0
            ];
        }, RefundDict::getRefundStatus());
        // 获取统计结果
        $statisticsRes = (new staCoreRefundService)->batchGetTimeRangeStats($this->site_id, 'site_id', [$this->site_id]);
        // 提取当前站点的统计数据（默认取第一个元素）
        $currentSiteStats = !empty($statisticsRes) ? reset($statisticsRes) : [];
        // 状态映射关系：状态标识 => 统计结果中的字段名
        $statusMap = [
            'wait_refund' => 'wait_refund_count',
            'refund_completed' => 'refund_completed_count',
            'refund_refuse' => 'refund_refuse_count',
            'cancel' => 'cancel_count'
        ];
        // 为每个状态匹配对应的数量
        foreach ($taskStatusList as &$statusItem) {
            $status = $statusItem['status'];
            // 从统计结果中获取对应状态的数量，注意转换为整数
            if (isset($statusMap[$status]) && isset($currentSiteStats[$statusMap[$status]])) {
                $statusItem['count'] = intval($currentSiteStats[$statusMap[$status]]);
            }
        }
        unset($statusItem); // 解除引用
        // 计算总退款单数
        $totalCount = array_sum(array_column($taskStatusList, 'count'));
        return [
            'total' => $totalCount,
            'status_list' => $taskStatusList
        ];
    }

    /**
     * 订单售后记录分页列表
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where)
    {

        $join_where = [];
        if (isset($where['member_search']) && $where['member_search'] != '') $join_where[] = ['member.member_no|member.nickname|member.username|member.mobile', 'like', "%" . $where['member_search'] . "%"];
        if (isset($where['technician_name']) && $where['technician_name'] != '') $join_where[] = ['technician.real_name', 'like', "%" . $where['technician_name'] . "%"];
        if (isset($where['store_name']) && $where['store_name'] != '') $join_where[] = ['store.store_name', 'like', "%" . $where['store_name'] . "%"];
        if (isset($where['order_no']) && $where['order_no'] != '') $join_where[] = ['orderMain.order_no', 'like', "%" . $where['order_no'] . "%"];
        if (isset($where['store_id']) && $where['store_id'] != '') $join_where[] = ['store.store_id', '=', $where['store_id']];
        if (isset($where['technician_id']) && $where['technician_id'] != '') $join_where[] = ['order_refund.technician_id', '=', $where['technician_id']];
        $field = 'store_id,reason,technician_id,refund_id, order_id, site_id, member_id, refund_no, status, create_time, audit_time, transfer_time, money,refuse_reason,reason,apply_money,remark,voucher';
        $order = 'create_time desc';
        $search_model = $this->model
            ->where([['order_refund.site_id', '=', $this->site_id]])
            ->where($join_where)
            ->withSearch(['refund_no', 'join_create_time', 'status', 'join_status'], $where)->field($field)
            ->withJoin([
                'member' => ['nickname', 'member_id', 'mobile'],
                'orderMain' => ['order_id', 'order_no', 'order_name', 'order_money'],
                'technician' => ['real_name'],
                'store' => ['store_name']
            ], 'left')
            ->order($order)->append(['status_name','reason_name']);
        $list = $this->pageQuery($search_model);
        return $list;

    }

    /**
     * 订单售后记录详情
     * @param array $where
     * @return mixed
     */
    public function getDetail(int $refund_id)
    {
        $field = 'refund_id,order_id, member_id, refund_no, status, create_time, audit_time, transfer_time, money,refuse_reason,reason,apply_money,remark,voucher';
        return $this->model->where([['refund_id', '=', $refund_id], ['order_refund.site_id', '=', $this->site_id]])
            ->field($field)
            ->withJoin([
                'member' => ['nickname', 'member_id', 'mobile'],
                'orderMain' => ['order_id', 'order_no', 'order_name', 'order_money'],
                'technician' => ['real_name'],
                'store' => ['store_name']
            ], 'left')
            ->with(
                [
                    'refund_log' => function ($query) {
                        $query->field('refund_id, action, action_time, action_way, uid')->append(['nickname', 'action_name']);
                    }
                ])->append(['status_name'])->findOrEmpty()->toArray();
    }


    /**
     * 最新的时候售后订单
     * @param array $where
     * @return mixed
     */
    public function getLatestByOrderId(int $order_id)
    {
        $field = 'refund_id,order_id, member_id, refund_no, status, create_time, audit_time, transfer_time, money,refuse_reason,reason,apply_money,remark,voucher';

        return $this->model->where([
                ['order_refund.order_id', '=', $order_id],  // 按order_id查询
                ['order_refund.site_id', '=', $this->site_id]  // 保留站点过滤
            ])
                ->field($field)
                ->withJoin([
                    'member' => ['nickname', 'member_id', 'mobile'],
                    'orderMain' => ['order_id', 'order_no', 'order_name', 'order_money'],
                    'technician' => ['real_name'],
                    'store' => ['store_name']
                ], 'left')
                ->with([
                    'refund_log' => function ($query) {
                        $query->field('refund_id, action, action_time, action_way, uid')->append(['nickname', 'action_name']);
                    }
                ])
                ->append(['status_name','reason_name'])
                ->order('create_time', 'desc')  // TP8中使用order()替代了orderBy()
                ->find()  // 取第一条（最新的）
                ?->toArray() ?? [];  // 处理空结果

    }


    /**
     * 拒绝已退款
     * @param int $refund_id
     * @param string $refuse_reason
     * @return true
     */
    public function refuse(int $refund_id, string $refuse_reason = '')
    {
        $refund = (new OrderRefund())->where([['refund_id', '=', $refund_id], ['site_id', '=', $this->site_id]])->findOrEmpty();
        if ($refund->isEmpty()) throw new CommonException('HOME_SERVICE_REFUND_NOT_EXIST');
        if ($refund->status != RefundDict::WAIT_REFUND) throw new CommonException('HOME_SERVICE_REFUND_STATUS_ERROR');

        $order = $refund->orderMain;

        $refund->status = RefundDict::REFUND_REFUSE;
        $refund->audit_time = time();
        $refund->refuse_reason = $refuse_reason;
        $refund->save();
        $refund->orderMain->refund_status = '';
        $refund->orderMain->refund_apply_time = 0;
        $refund->orderMain->is_abnormal = 0;
        $refund->orderMain->save();
        (new OrderItem())->where([['order_id', '=', $order->order_id]])->update(['refund_status' => '']);

        // 添加售后日志
        CoreOrderRefundLogService::addLog($this->site_id, $refund_id, OrderRefundLogDict::REFUSE, 'user', $this->uid);

//        // 发送退款拒绝提醒通知
//        (new NoticeService())->send($refund['site_id'], 'home_service_refund_refuse', ['refund_id' => $refund_id ]);

        (new Refund())->where([['refund_no', '=', $refund->refund_no]])->delete();

        if (!empty($order) && !empty($order->technician_id)) {
            event('NotificationEvent', [
                'identity' => [NoticeDict::TECHNICIAN],
                'type' => NoticeDict::REFUND_FAIL,
                'notice_source' => NoticeDict::ORDER,
                'order_id' => $order->order_id ?? 0,
                'technician_id' => $order->technician_id ?? 0,
                'member_id' => $order->member_id ?? 0,
                'site_id' => $order->site_id,
            ]);
        }

        return true;
    }


    /**
     * 退款
     * @param int $refund_id
     * @param float $money
     * @return true
     */
    public function refund(int $refund_id, float $money)
    {
        $refund = (new OrderRefund())->where([['refund_id', '=', $refund_id], ['site_id', '=', $this->site_id]])->findOrEmpty();
        if ($refund->isEmpty()) throw new CommonException('HOME_SERVICE_REFUND_NOT_EXIST');

        if ($refund->status != RefundDict::WAIT_REFUND) throw new CommonException('HOME_SERVICE_REFUND_STATUS_ERROR');
        if (bccomp($money, $refund->orderMain->order_money, 2) == 1) throw new CommonException('HOME_SERVICE_REFUND_MONEY_CANNOT_GT_PAYMONEY');

        if ($refund->apply_money <= 0 && $money > 0) throw new CommonException('HOME_SERVICE_REFUND_MONEY_NOT_GT_APPLY_MONEY');

        if ($refund->apply_money > 0 && $money <= 0) throw new CommonException('HOME_SERVICE_REFUND_MONEY_GT_ZERO');

        $order_item = (new OrderItem())->field('out_trade_no,SUM(item_money - discount_money) as apply_money')->where([['order_id','=',$refund->order_id]])->group('batch_id')->select();
        Db::startTrans();
        try {
            // 添加售后日志
            CoreOrderRefundLogService::addLog($this->site_id, $refund_id, OrderRefundLogDict::REFUND, 'user', $this->uid);

            (new OrderRefund())->where([['refund_id', '=', $refund_id], ['site_id', '=', $this->site_id]])->update([
                'money' => $money,
            ]);

            (new Refund())->update(['money' => $money], [['refund_no', '=', $refund->refund_no]]);
            //金额为0，直接完成
            if ($money > 0) {
                $remaining_amount = $money;
                foreach($order_item as $value){
                    // 该项最多能退多少钱（比如订单明细原金额）
                    $max_refund = $value->apply_money;

                    // 如果剩余退款金额 <= 0，直接跳出
                    if (bccomp($remaining_amount, 0, 2) <= 0) break;

                    // 实际本次退款金额：取剩余金额和该项可退金额的最小值
                    $refund_amount = (bccomp($remaining_amount, $max_refund, 2) == 1)
                        ? $max_refund
                        : $remaining_amount;
                    // 创建退款单
                    $order_refund_no = (new CoreRefundService())->create($this->site_id, $value->out_trade_no,$refund_amount, '');

                    if (empty($refund->refund_no)) {
                        $refund->refund_no = $order_refund_no;
                        $refund->save();
                    }

                    (new OrderItem())->where([['out_trade_no', '=', $value['out_trade_no']]])->update(['refund_no' => $order_refund_no]);

                    // 调用退款
                    (new CoreRefundService())->refund($this->site_id, $order_refund_no);

                    // 扣除已退金额
                    $remaining_amount = bcsub($remaining_amount, $refund_amount, 2);

                }
            } else {
                (new CoreOrderRefundService())->refundSuccess($refund->refund_no);
            }
            //删除待结算佣金
            (new TechnicianAccount())->where([['related_id', '=', $refund->orderMain->order_no], ['status', '=', 0]])->delete();
            (new StoreAccount())->where([['related_id', '=', $refund->orderMain->order_no], ['status', '=', 0]])->delete();
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

}
