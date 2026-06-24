<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\CardOrder;
use addon\sd_xiaoyuan\app\model\MemberCard;
use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\ServiceCard;
use addon\sd_xiaoyuan\app\model\runner\Runner;
use core\base\BaseApiService;
use core\exception\CommonException;

class CardService extends BaseApiService
{
    public function initDefaultCards(int $site_id = 0)
    {
        $site_id = $site_id ?: $this->site_id;
        $exists = (new ServiceCard())->where('site_id', $site_id)->count();
        if ($exists > 0) {
            return;
        }
        $now = time();
        $defaults = [
            ['EXPRESS', '快递卡', '可使用代取快递服务', 3.00, 10.00, 1, 1],
            ['ERRAND', '跑腿卡', '可使用校园跑腿服务', 5.00, 15.00, 1, 2],
            ['PRINT', '打印卡', '可使用代打印服务', 2.00, 8.00, 1, 3],
        ];
        foreach ($defaults as $row) {
            (new ServiceCard())->save([
                'site_id' => $site_id,
                'card_type' => $row[0],
                'name' => $row[1],
                'subtitle' => $row[2],
                'price' => $row[3],
                'origin_price' => $row[4],
                'times' => $row[5],
                'status' => 1,
                'sort' => $row[6],
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }
    }

    public function getList()
    {
        $this->initDefaultCards();
        $list = (new ServiceCard())->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
        ])->order('sort asc,id asc')->select()->toArray();
        foreach ($list as &$item) {
            $item['price'] = number_format((float)$item['price'], 2, '.', '');
            $item['origin_price'] = number_format((float)$item['origin_price'], 2, '.', '');
        }
        return $list;
    }

    public function getDetail(string $card_type)
    {
        $this->initDefaultCards();
        $card = (new ServiceCard())->where([
            ['site_id', '=', $this->site_id],
            ['card_type', '=', strtoupper($card_type)],
            ['status', '=', 1],
        ])->find();
        if (empty($card)) {
            throw new CommonException('次卡不存在或已下架');
        }
        $data = $card->toArray();
        $memberCard = (new MemberCard())->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['card_type', '=', $data['card_type']],
        ])->find();
        $data['remain_times'] = (int)($memberCard['remain_times'] ?? 0);
        return $data;
    }

    public function createOrder(string $card_type)
    {
        $card_type = strtoupper(trim($card_type));
        if (!in_array($card_type, ['EXPRESS', 'ERRAND', 'PRINT'], true)) {
            throw new CommonException('次卡类型错误');
        }
        $this->initDefaultCards();
        $card = (new ServiceCard())->where([
            ['site_id', '=', $this->site_id],
            ['card_type', '=', $card_type],
            ['status', '=', 1],
        ])->find();
        if (empty($card)) {
            throw new CommonException('次卡不存在或已下架');
        }
        $price = floatval($card['price']);
        if ($price <= 0) {
            throw new CommonException('次卡价格未配置');
        }
        $now = time();
        $order = (new CardOrder())->create([
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'card_type' => $card_type,
            'card_id' => (int)$card['id'],
            'runner_id' => 0,
            'order_no' => create_no('XC'),
            'price' => $price,
            'times' => (int)$card['times'],
            'pay_status' => 0,
            'status' => 0,
            'create_time' => $now,
            'update_time' => $now,
        ]);
        return [
            'order_id' => (int)$order['id'],
            'trade_type' => 'sd_xiaoyuan_card',
            'price' => $price,
        ];
    }

    public function createRunnerApplyOrder(int $runner_id, float $fee)
    {
        if ($runner_id <= 0 || $fee <= 0) {
            throw new CommonException('入驻费用异常');
        }
        $now = time();
        $order = (new CardOrder())->create([
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'card_type' => 'RUNNER',
            'card_id' => 0,
            'runner_id' => $runner_id,
            'order_no' => create_no('XR'),
            'price' => $fee,
            'times' => 0,
            'pay_status' => 0,
            'status' => 0,
            'create_time' => $now,
            'update_time' => $now,
        ]);
        return (int)$order['id'];
    }

    public function handlePaySuccess(int $order_id)
    {
        $order = (new CardOrder())->where('id', $order_id)->find();
        if (empty($order) || (int)$order['pay_status'] === 1) {
            return true;
        }
        $now = time();
        $order->save([
            'pay_status' => 1,
            'pay_time' => $now,
            'status' => 1,
            'update_time' => $now,
        ]);

        if ($order['card_type'] === 'RUNNER') {
            $runner = (new Runner())->where('id', (int)$order['runner_id'])->find();
            if ($runner) {
                $runner->save([
                    'status' => 1,
                    'audit_time' => $now,
                    'update_time' => $now,
                ]);
                (new MessageService())->sendDirect(
                    (int)$order['site_id'],
                    (int)$order['member_id'],
                    'SYSTEM',
                    '接单员入驻成功',
                    '入驻费用已支付，您已成为接单员',
                    ['link_type' => 'runner_index']
                );
            }
            return true;
        }

        $times = (int)$order['times'];
        if ($times <= 0) {
            return true;
        }
        $memberCard = (new MemberCard())->where([
            ['site_id', '=', (int)$order['site_id']],
            ['member_id', '=', (int)$order['member_id']],
            ['card_type', '=', $order['card_type']],
        ])->find();
        if ($memberCard) {
            $memberCard->save([
                'total_times' => (int)$memberCard['total_times'] + $times,
                'remain_times' => (int)$memberCard['remain_times'] + $times,
                'update_time' => $now,
            ]);
        } else {
            (new MemberCard())->save([
                'site_id' => (int)$order['site_id'],
                'member_id' => (int)$order['member_id'],
                'card_type' => $order['card_type'],
                'total_times' => $times,
                'used_times' => 0,
                'remain_times' => $times,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }
        return true;
    }

    public function getMyCards()
    {
        $list = (new MemberCard())->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
        ])->select()->toArray();
        $cards = (new ServiceCard())->where('site_id', $this->site_id)->column('name,subtitle', 'card_type');
        foreach ($list as &$item) {
            $cfg = $cards[$item['card_type']] ?? null;
            $item['name'] = $cfg['name'] ?? $item['card_type'];
            $item['subtitle'] = $cfg['subtitle'] ?? '';
        }
        return $list;
    }

    public function getCardTypeByTaskType(string $task_type): ?string
    {
        $task_type = strtoupper(trim($task_type));
        if ($task_type === 'EXPRESS') {
            return 'EXPRESS';
        }
        if ($task_type === 'PRINT') {
            return 'PRINT';
        }
        $errandTypes = ['BUY', 'SEND', 'ERRAND', 'QUEUE', 'CLASS', 'SEAT', 'TRASH', 'CARRY', 'CLEAN', 'HELP', 'GAME', 'GROUP', 'PARTTIME', 'COMPANION'];
        if (in_array($task_type, $errandTypes, true)) {
            return 'ERRAND';
        }
        return null;
    }

    public function getMemberCardRemain(int $site_id, int $member_id, string $card_type): int
    {
        $row = (new MemberCard())->where([
            ['site_id', '=', $site_id],
            ['member_id', '=', $member_id],
            ['card_type', '=', strtoupper($card_type)],
        ])->find();
        return (int)($row['remain_times'] ?? 0);
    }

    public function applyCardForOrder(int $site_id, int $member_id, string $task_type, float $total_fee, array $ext = []): array
    {
        $card_type = $this->getCardTypeByTaskType($task_type);
        if (!$card_type) {
            throw new CommonException('该服务不支持次卡抵扣');
        }
        if ($total_fee <= 0) {
            throw new CommonException('订单金额异常');
        }
        $remain = $this->getMemberCardRemain($site_id, $member_id, $card_type);
        if ($remain < 1) {
            throw new CommonException('次卡次数不足');
        }
        $ext['use_card'] = 1;
        $ext['card_type'] = $card_type;
        $ext['card_discount'] = $total_fee;
        return [
            'ext' => $ext,
            'actual_fee' => 0,
        ];
    }

    public function consumeCardForOrder(int $site_id, int $member_id, string $card_type, int $order_id): bool
    {
        $card_type = strtoupper(trim($card_type));
        $memberCard = (new MemberCard())->where([
            ['site_id', '=', $site_id],
            ['member_id', '=', $member_id],
            ['card_type', '=', $card_type],
        ])->find();
        if (empty($memberCard) || (int)$memberCard['remain_times'] < 1) {
            throw new CommonException('次卡次数不足');
        }
        $now = time();
        $memberCard->save([
            'remain_times' => (int)$memberCard['remain_times'] - 1,
            'used_times' => (int)$memberCard['used_times'] + 1,
            'update_time' => $now,
        ]);
        return true;
    }

    public function restoreCardForOrder(int $site_id, int $member_id, string $card_type): bool
    {
        $card_type = strtoupper(trim($card_type));
        $memberCard = (new MemberCard())->where([
            ['site_id', '=', $site_id],
            ['member_id', '=', $member_id],
            ['card_type', '=', $card_type],
        ])->find();
        if (empty($memberCard)) {
            return false;
        }
        $now = time();
        $used = (int)$memberCard['used_times'];
        $memberCard->save([
            'remain_times' => (int)$memberCard['remain_times'] + 1,
            'used_times' => $used > 0 ? $used - 1 : 0,
            'update_time' => $now,
        ]);
        return true;
    }

    public function handleOrderPaySuccess(array $order): void
    {
        $ext = [];
        if (!empty($order['ext'])) {
            $ext = is_string($order['ext']) ? json_decode($order['ext'], true) : $order['ext'];
            if (!is_array($ext)) {
                $ext = [];
            }
        }
        if (!empty($ext['card_consumed'])) {
            return;
        }
        $site_id = (int)$order['site_id'];
        $member_id = (int)$order['member_id'];
        $order_id = (int)$order['id'];
        $card_type = $ext['card_type'] ?? '';
        if ($card_type === '' && (float)($order['actual_fee'] ?? 0) <= 0 && (float)($order['total_fee'] ?? 0) > 0) {
            $card_type = $this->getCardTypeByTaskType((string)($order['task_type'] ?? '')) ?? '';
            if ($card_type !== '') {
                $ext['use_card'] = 1;
                $ext['card_type'] = $card_type;
                $ext['card_discount'] = (float)($order['total_fee'] ?? 0);
            }
        }
        if (empty($ext['use_card']) || $card_type === '') {
            return;
        }
        $this->consumeCardForOrder($site_id, $member_id, $card_type, $order_id);
        $ext['card_consumed'] = 1;
        (new Order())->where('id', $order_id)->update([
            'ext' => json_encode($ext, JSON_UNESCAPED_UNICODE),
            'update_time' => time(),
        ]);
    }

    public function getUseLogs(string $card_type, int $page = 1, int $limit = 20): array
    {
        $card_type = strtoupper(trim($card_type));
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));
        $where = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['pay_status', '=', 1],
            ['ext', 'like', '%"card_type":"' . $card_type . '"%'],
        ];
        $query = (new Order())->where($where)->where(function ($q) {
            $q->where('ext', 'like', '%"card_consumed":1%')->whereOr('ext', 'like', '%"use_card":1%');
        });
        $count = (int)$query->count();
        $list = $query->field('id,order_no,task_type,goods_name,total_fee,actual_fee,ext,pay_time,create_time,status')
            ->order('pay_time desc,id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        $taskMap = \addon\sd_xiaoyuan\app\dict\order\OrderDict::getTaskType();
        foreach ($list as &$row) {
            $ext = json_decode((string)($row['ext'] ?? ''), true);
            if (!is_array($ext)) {
                $ext = [];
            }
            $useTime = (int)($row['pay_time'] ?? 0) ?: (int)($row['create_time'] ?? 0);
            $row['use_time_text'] = $useTime ? date('Y-m-d H:i', $useTime) : '';
            $row['task_type_text'] = $taskMap[$row['task_type'] ?? ''] ?? ($row['task_type'] ?? '');
            $row['discount'] = number_format((float)($ext['card_discount'] ?? $row['total_fee'] ?? 0), 2, '.', '');
            $row['title'] = $row['goods_name'] ?: $row['task_type_text'];
            unset($row['ext']);
        }
        return [
            'list' => $list,
            'count' => $count,
            'page' => $page,
            'limit' => $limit,
        ];
    }
}
