<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

/** 纯状态规则，可在无数据库和无外部快递的情况下回归测试。 */
class PickupState
{
    public static function merge(array $current, array $incoming): array
    {
        if (array_key_exists('booking_state', $incoming) && $incoming['booking_state'] === null) unset($incoming['booking_state']);
        $before = (string)($current['booking_state'] ?? '');
        $next = (string)($incoming['booking_state'] ?? $before);
        if ($next === '') {
            unset($incoming['booking_state']);
            $next = $before;
        }
        if (!empty($current['conflict'])) {
            $incoming['conflict'] = true; // 自动回调无权解除人工待核实状态。
        }
        // 不带计费信息的普通状态查询不能把人工已核对的费用重新标成待核对。
        if (($incoming['reported_freight'] ?? null) === null) {
            unset($incoming['reported_freight'], $incoming['fee_verification_state']);
        } elseif (($current['fee_verification_state'] ?? '') === 'manual_confirmed'
            && (string)($incoming['reported_freight'] ?? '') === (string)($current['reported_freight'] ?? '')) {
            unset($incoming['fee_verification_state']);
        }
        // 普通状态回调可能把未提供的重量/计费明细规范化为 null/[]，不能据此清除已知证据。
        // 不使用 empty()：渠道明确返回的 0、0.0 或 "0" 都是有效新值。
        foreach (['chargeable_weight', 'actual_weight'] as $field) {
            if (array_key_exists($field, $incoming)
                && ($incoming[$field] === null || (is_string($incoming[$field]) && trim($incoming[$field]) === ''))) {
                unset($incoming[$field]);
            }
        }
        if (!is_array($incoming['fee_details'] ?? null) || $incoming['fee_details'] === []) {
            unset($incoming['fee_details']);
        }
        $rank = ['submitting' => 0, 'unknown' => 0, 'accepted' => 1, 'confirmed' => 2, 'assigned' => 3,
            'picked_up' => 4, 'in_transit' => 5, 'delivered' => 6];
        $highest = (string)($current['highest_booking_state'] ?? $before);
        if (!isset($rank[$highest])) $highest = $before;
        $incomingHighest = (string)($incoming['highest_booking_state'] ?? '');
        if (isset($rank[$incomingHighest]) && $rank[$incomingHighest] > ($rank[$highest] ?? -1)) $highest = $incomingHighest;
        if (isset($rank[$highest], $rank[$next]) && $rank[$next] < $rank[$highest]) {
            unset($incoming['booking_state']);
        }
        if (isset($rank[$next]) && $rank[$next] >= ($rank[$highest] ?? -1)) {
            $incoming['highest_booking_state'] = $next;
        } elseif (isset($rank[$highest])) {
            $incoming['highest_booking_state'] = $highest;
        }
        if (isset($rank[$before], $rank[$next]) && $rank[$next] < $rank[$before]) {
            unset($incoming['booking_state']);
        }
        // 终态冲突保留为待核实，不用简单的状态大小规则吞掉真实履约。
        if (in_array($before, ['cancelled', 'failed', 'manual'], true)
            && in_array($next, ['accepted', 'confirmed', 'assigned', 'picked_up', 'in_transit', 'delivered'], true)) {
            $incoming['conflict'] = true;
            $incoming['conflict_state'] = $next;
            $incoming['booking_state'] = $before;
        }
        if ($before === 'manual') {
            // 原预约的取消/失败回执也不能把客户已登记的新运单改回旧单。
            $incoming['booking_state'] = 'manual';
            foreach (['deliveryId', 'carrier_name', 'carrier_code', 'pickup_time', 'courier_name', 'courier_phone', 'courier_mobile'] as $field) {
                unset($incoming[$field]);
            }
        }
        if ((in_array($before, ['picked_up', 'in_transit', 'delivered'], true)
                || in_array($highest, ['picked_up', 'in_transit', 'delivered'], true))
            && in_array($next, ['cancelled', 'failed'], true)) {
            $incoming['conflict'] = true;
            $incoming['conflict_state'] = $next;
            $incoming['booking_state'] = $before;
        }
        foreach (['deliveryId', 'orderNo', 'carrier_name', 'courier_name', 'courier_phone', 'courier_mobile'] as $field) {
            if (isset($incoming[$field]) && $incoming[$field] === '' && !empty($current[$field])) {
                unset($incoming[$field]);
            }
        }
        return array_replace($current, $incoming);
    }

    public static function view(array $data, array $order = []): array
    {
        $state = (string)($data['booking_state'] ?? 'not_requested');
        if ($state === 'submitting' && !empty($data['requested_at']) && time() - (int)$data['requested_at'] > 120) {
            $state = 'unknown'; // 长时间无回应时不能一直显示“提交中”，也不能推断渠道失败。
        }
        $titles = ['not_requested' => '尚未预约取件', 'submitting' => '正在提交取件预约',
            'accepted' => '正在确认取件安排', 'unknown' => '取件安排待核实', 'confirmed' => '预约成功，等待分配取件员',
            'assigned' => '已安排取件员', 'picked_up' => '快递已取件', 'in_transit' => '运输中',
            'delivered' => '快递已签收', 'cancelled' => '取件预约已取消', 'failed' => '上门取件预约失败', 'manual' => '已登记自行寄件', 'exception' => '取件/运输异常，请联系门店'];
        $messages = ['failed' => '本次未能安排快递员上门。您可以自行寄件，或联系门店协助。',
            'unknown' => '正在核实是否已预约，请勿重复叫件；如有疑问请联系门店。',
            'submitting' => '正在提交取件预约，请勿重复操作。',
            'accepted' => '已提交快递公司，等待确认取件安排。',
            'confirmed' => '取件员信息尚未返回，请留意后续安排。',
            'assigned' => '请保持电话畅通，实际到达时间以取件员联系为准。',
            'cancelled' => '原取件预约已取消，可自行寄件并补填运单号。',
            'manual' => '请按实际运单查看物流，门店收到设备后继续办理回收。'];
        $conflict = !empty($data['conflict']);
        $receiver = is_array($data['receiver'] ?? null) ? $data['receiver'] : [];
        return [
            'state' => $state, 'title' => $conflict ? '取件记录待人工核实' : ($titles[$state] ?? '取件状态待核实'),
            'message' => $conflict ? '取件记录存在冲突，请联系门店核实，不要重复寄件。' : ($messages[$state] ?? ''),
            'carrier_name' => (string)($data['carrier_name'] ?? ''),
            'pickup_time' => (string)($data['pickup_time'] ?? $order['pickup_time'] ?? ''),
            'courier_name' => (string)($data['courier_name'] ?? ''),
            'courier_phone' => (string)($data['courier_phone'] ?? $data['courier_mobile'] ?? ''),
            'tracking_no' => (string)($data['deliveryId'] ?? $order['express_no'] ?? ''),
            'record_id' => (int)($data['record_id'] ?? 0),
            'attempt_id' => (string)($data['attempt_id'] ?? ''),
            'can_manual' => !$conflict && in_array($state, ['failed', 'cancelled'], true)
                && (!isset($order['status']) || (int)$order['status'] === 1),
            'can_refresh' => !in_array($state, ['not_requested', 'manual'], true),
            'failure_message' => '',
            'receiver' => array_intersect_key($receiver, array_flip(['contact_name', 'name', 'mobile', 'province', 'city', 'district', 'address'])),
            'conflict' => $conflict,
        ];
    }
}
