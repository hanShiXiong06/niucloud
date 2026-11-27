<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\api\recycle_order;

use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use addon\hsx_recycle\app\model\order\RecycleConsignmentLog;
use addon\hsx_recycle\app\model\order\RecycleConsignmentOrder;
use addon\hsx_recycle\app\service\core\order\OrderSubmitConfigService;
use core\base\BaseApiService;
use core\exception\ApiException;

/**
 * 用户端代卖订单服务
 */
class RecycleConsignmentOrderService extends BaseApiService
{
    protected $model;
    private OrderSubmitConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleConsignmentOrder();
        $this->configService = new OrderSubmitConfigService();
    }

    public function getPage(array $where = []): array
    {
        $this->checkUserVisible();

        $query = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
            ])
            ->with([
                'sourceOrder' => function ($query) {
                    $query->field('id,order_no,status,create_at');
                },
                'sourceDevice' => function ($query) {
                    $query->field('id,order_id,imei,imei2,sn,model,capacity,color,status,final_price,sell_price,check_images_seller')
                        ->append(['status_name', 'check_images_seller_thumb_small']);
                },
            ])
            ->append(['status_name', 'pay_status_name'])
            ->order('id desc');

        if (($where['status'] ?? '') !== '' && (string)$where['status'] !== 'all') {
            $query->where('status', '=', (int)$where['status']);
        }
        if (($where['keyword'] ?? '') !== '') {
            $keyword = trim((string)$where['keyword']);
            $query->where(function ($query) use ($keyword) {
                $query->whereLike('consignment_no', "%{$keyword}%")
                    ->whereOrLike('source_order_no', "%{$keyword}%")
                    ->whereOrLike('device_imei', "%{$keyword}%")
                    ->whereOrLike('device_model', "%{$keyword}%");
            });
        }

        $result = $this->pageQuery($query);
        $result['data'] = array_map(fn($item) => $this->formatInfo($item, false), $result['data'] ?? []);
        return $result;
    }

    public function getInfo(int $id): array
    {
        $this->checkUserVisible();

        $info = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                ['id', '=', $id],
            ])
            ->with([
                'sourceOrder' => function ($query) {
                    $query->field('id,order_no,status,delivery_type,express_company,express_no,create_at,update_at')
                        ->append(['status_name', 'delivery_type_name']);
                },
                'sourceDevice' => function ($query) {
                    $query->field('id,order_id,imei,imei2,sn,model,capacity,color,status,initial_price,final_price,sell_price,check_result_seller,check_images_seller')
                        ->append(['status_name', 'check_images_seller_thumb_small']);
                },
            ])
            ->append(['status_name', 'pay_status_name'])
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new ApiException('代卖订单不存在');
        }

        $info = $this->formatInfo($info, true);
        $info['logs'] = $this->getPublicLogs($id);
        return $info;
    }

    public function getStatusCount(): array
    {
        $config = $this->configService->getConfig((int)$this->site_id);
        if (empty($config['consignment']['enabled']) || empty($config['consignment']['user_entry_enabled'])) {
            return [
                'enabled' => 0,
                'list' => [],
                'total' => 0,
                'title' => $config['consignment']['user_title'] ?? '代卖订单',
                'desc' => $config['consignment']['user_desc'] ?? '',
            ];
        }

        $counts = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
            ])
            ->group('status')
            ->column('count(*)', 'status');

        $total = array_sum($counts);
        $list = [
            [
                'key' => 'all',
                'text' => '全部',
                'count' => $total,
            ],
        ];
        foreach (RecycleConsignmentDict::getStatus() as $status => $name) {
            $list[] = [
                'key' => (string)$status,
                'text' => $name,
                'count' => (int)($counts[$status] ?? 0),
            ];
        }

        return [
            'enabled' => 1,
            'title' => $config['consignment']['user_title'] ?? '代卖订单',
            'desc' => $config['consignment']['user_desc'] ?? '',
            'total' => $total,
            'list' => $list,
        ];
    }

    public function getStatusOptions(): array
    {
        return RecycleConsignmentDict::getStatusOptions();
    }

    private function checkUserVisible(): void
    {
        if (!$this->configService->canUserViewConsignment((int)$this->site_id)) {
            throw new ApiException('商家暂未开启代卖订单查看');
        }
    }

    private function getPublicLogs(int $id): array
    {
        $logs = (new RecycleConsignmentLog())
            ->where([
                ['site_id', '=', $this->site_id],
                ['consignment_id', '=', $id],
            ])
            ->field('id,action,old_status,new_status,remark,create_time')
            ->order('id desc')
            ->select()
            ->toArray();

        return array_map(function ($log) {
            $log['action_name'] = RecycleConsignmentDict::getActionName((string)($log['action'] ?? ''));
            $log['old_status_name'] = RecycleConsignmentDict::getStatus((int)($log['old_status'] ?? 0));
            $log['new_status_name'] = RecycleConsignmentDict::getStatus((int)($log['new_status'] ?? 0));
            $log['create_time_text'] = $this->formatTime((int)($log['create_time'] ?? 0));
            return $log;
        }, $logs);
    }

    private function formatInfo(array $info, bool $detail): array
    {
        $config = $this->configService->getConfig((int)$this->site_id);
        $showServiceFee = !empty($config['consignment']['show_service_fee']);

        foreach (['quote_price', 'expected_price', 'min_settlement_price', 'listing_price', 'sold_price', 'settlement_amount', 'service_fee'] as $field) {
            $info[$field . '_text'] = number_format((float)($info[$field] ?? 0), 2);
        }
        if (!$showServiceFee) {
            unset($info['service_fee'], $info['service_fee_text']);
        }

        foreach (['listed_time', 'sold_time', 'settle_time', 'pay_time', 'cancel_time', 'create_time', 'update_time'] as $field) {
            $info[$field . '_text'] = $this->formatTime((int)($info[$field] ?? 0));
        }

        $info['progress_text'] = $this->buildProgressText((int)($info['status'] ?? 0));
        $info['can_view_source_order'] = !empty($info['source_order_id']) ? 1 : 0;
        if (!$detail) {
            unset($info['remark']);
        }

        return $info;
    }

    private function buildProgressText(int $status): string
    {
        return match ($status) {
            RecycleConsignmentDict::STATUS_PENDING => '设备已转入代卖，等待工作人员上架。',
            RecycleConsignmentDict::STATUS_SELLING => '设备正在代卖中，有成交后会更新结算信息。',
            RecycleConsignmentDict::STATUS_SOLD, RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT => '设备已成交，等待商家结算。',
            RecycleConsignmentDict::STATUS_SETTLED => '代卖已完成结算。',
            RecycleConsignmentDict::STATUS_RETURNED => '代卖已结束，设备已退回。',
            RecycleConsignmentDict::STATUS_CANCELLED => '代卖已取消。',
            default => '代卖订单处理中。',
        };
    }

    private function formatTime(int $time): string
    {
        return $time > 0 ? date('Y-m-d H:i:s', $time) : '';
    }
}
