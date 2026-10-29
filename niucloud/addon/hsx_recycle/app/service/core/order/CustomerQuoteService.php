<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\order;

use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\exception\CommonException;
use addon\hsx_recycle\app\service\admin\stat\TaskService;
use addon\hsx_recycle\app\dict\stat\RecycleStageDict;

/** 预估报价来源与可选定价交接规则；不确认成交价，不触发付款。 */
class CustomerQuoteService
{
    public static function normalizeUserIds($value, bool $strict = false): array
    {
        if (!is_array($value)) {
            if ($strict) throw new CommonException('报价员配置格式不正确，请重新选择');
            return [];
        }
        $ids = [];
        foreach ($value as $id) {
            if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string)$id)) {
                if ($strict) throw new CommonException('报价员配置格式不正确，请重新选择');
                continue;
            }
            $ids[] = (int)$id;
        }
        return array_values(array_unique($ids));
    }

    /** 仅本站有效员工，遵守成员关系和账号的停用/软删除状态；不返回手机等个人资料。 */
    public function getStaffOptions(int $siteId): array
    {
        if ($siteId <= 0) return [];
        $ids = SysUserRole::where([['site_id', '=', $siteId], ['status', '=', 1]])->column('uid');
        if (!$ids) return [];
        $users = SysUser::whereIn('uid', array_values(array_unique($ids)))->where('status', '=', 1)
            ->field('uid,real_name,username')->order('uid asc')->select()->toArray();
        return array_map(static function (array $user): array {
            return ['uid' => (int)$user['uid'], 'name' => trim((string)$user['real_name']) ?: (string)$user['username']];
        }, $users);
    }

    public function getQuoteUsers(int $siteId, array $ids, bool $pricingOnly = true): array
    {
        if (!$ids) return [];
        $map = array_column($this->getStaffOptions($siteId), null, 'uid');
        if ($pricingOnly) {
            $allowed = array_column(TaskService::forSite($siteId)->getAssignableUsers(RecycleStageDict::STAGE_PRICE, true), 'uid');
            $map = array_intersect_key($map, array_flip($allowed));
        }
        $result = [];
        foreach ($ids as $id) {
            if (isset($map[$id])) $result[] = $map[$id];
        }
        return $result;
    }

    public function validateConfig(int $siteId, array $config): void
    {
        $pricing = $config['device_pricing'];
        if (empty($config['device_add_enabled']) || empty($pricing['enabled']) || empty($pricing['assign_to_quote_user'])) return;
        $ids = $pricing['quote_user_ids'];
        if (count($this->getQuoteUsers($siteId, $ids)) !== count($ids)) {
            throw new CommonException('报价员须为本站有效员工且具有定价权限，请检查成员状态和定价权限后重新保存');
        }
    }

    /** 创建订单前一次性校验所有设备，不能先落单、再报缺报价员。 */
    public function prepareDevices(int $siteId, $devices, array $config): array
    {
        if (!is_array($devices)) throw new CommonException('设备清单格式不正确');
        if ($devices && empty($config['device_add_enabled'])) {
            throw new CommonException('门店已关闭添加设备明细，请返回下单页刷新后重试');
        }
        $pricing = $config['device_pricing'] ?? ['enabled' => 1, 'quote_user_ids' => []];
        $users = !empty($pricing['enabled']) && !empty($pricing['assign_to_quote_user'])
            ? $this->getQuoteUsers($siteId, $pricing['quote_user_ids'] ?? []) : [];
        $result = [];
        foreach (array_values($devices) as $index => $device) {
            if (!is_array($device)) throw new CommonException('设备清单格式不正确');
            try {
                $quote = self::normalizeDeviceQuote($device, !empty($pricing['enabled']), $users);
            } catch (CommonException $e) {
                throw new CommonException('第' . ($index + 1) . '台设备：' . $e->getMessage());
            }
            // 客户传来的姓名和快照不可信，由本站有效员工信息重新生成。
            $device['initial_price'] = $quote['price'];
            $device['customer_quote'] = $quote['snapshot'];
            unset($device['initial_price_uid'], $device['initial_price_name']);
            $result[] = $device;
        }
        return $result;
    }

    public static function normalizeDeviceQuote(array $device, bool $enabled, array $users): array
    {
        $empty = ['price' => '0.00', 'snapshot' => []];
        if (!$enabled) return $empty;
        $raw = $device['initial_price'] ?? '';
        if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
            throw new CommonException('请填写正确的预估价格');
        }
        $raw = trim((string)$raw);
        if ($raw === '') return $empty;
        if (!preg_match('/^[0-9]{1,8}(?:\.[0-9]{0,2})?$/D', $raw)) {
            throw new CommonException('预估价格须为非负金额，最多两位小数');
        }
        $price = number_format((float)$raw, 2, '.', '');
        $uid = $device['initial_price_uid'] ?? 0;
        $validUid = (is_int($uid) || is_string($uid)) && preg_match('/^[1-9][0-9]*$/D', (string)$uid);
        $map = array_column($users, null, 'uid');
        if ($users && (!$validUid || !isset($map[(int)$uid]))) {
            throw new CommonException('填写预估价格后，请选择本店报价员；如人员已调整，请刷新后重选');
        }
        return ['price' => $price, 'snapshot' => [
            'uid' => $users ? (int)$uid : 0,
            'name' => $users ? (string)$map[(int)$uid]['name'] : '',
            'price' => $price,
            'source' => 'customer',
            'assign_to_quote_user' => $users ? 1 : 0,
        ]];
    }

    /** 后台录单/签收：报价员选填；姓名只能取本站员工，不能相信表单中的姓名。 */
    public function applyAdminQuote(int $siteId, array $info, array $device): array
    {
        if (!array_key_exists('initial_price_uid', $device)) return $info;
        $uid = $device['initial_price_uid'];
        if ($uid === null || $uid === '' || $uid === 0 || $uid === '0') {
            $info['customer_quote'] = [];
            return $info;
        }
        $ids = self::normalizeUserIds([$uid], true);
        $config = (new OrderSubmitConfigService())->getConfig($siteId);
        $enabled = self::assignmentEnabled($config);
        $users = $this->getQuoteUsers($siteId, $ids, $enabled);
        if (!$users) throw new CommonException($enabled ? '报价员已停用、非本站员工或无定价权限，请重新选择或留空' : '报价员已停用或非本站员工，请重新选择或留空');
        $old = (array)($info['customer_quote'] ?? []);
        $info['customer_quote'] = [
            'uid' => $ids[0], 'name' => $users[0]['name'],
            'price' => number_format((float)($device['initial_price'] ?? $old['price'] ?? 0), 2, '.', ''),
            'source' => (int)($old['uid'] ?? 0) === $ids[0] ? (string)($old['source'] ?? 'admin') : 'admin',
            'assign_to_quote_user' => $enabled ? 1 : 0,
        ];
        return $info;
    }

    public static function assignmentEnabled(array $config): bool
    {
        return !empty($config['device_add_enabled']) && !empty($config['device_pricing']['enabled'])
            && !empty($config['device_pricing']['assign_to_quote_user']);
    }

    /** 当前开关只影响下一次交接，不追溯改派已经分配的任务。 */
    public function pricingAssignment(int $siteId, $info): array
    {
        $manual = ['mode' => 'manual', 'uid' => 0, 'name' => '', 'message' => ''];
        if (is_string($info)) $info = json_decode($info, true);
        if (is_object($info)) $info = json_decode(json_encode($info), true);
        $quote = (array)($info['customer_quote'] ?? []);
        if (empty($quote['uid']) || empty($quote['assign_to_quote_user'])) return $manual;
        if (!self::assignmentEnabled((new OrderSubmitConfigService())->getConfig($siteId))) return $manual;
        $users = $this->getQuoteUsers($siteId, [(int)$quote['uid']]);
        if (!$users) return ['mode' => 'unavailable', 'uid' => 0, 'name' => (string)($quote['name'] ?? ''),
            'message' => '原报价员已停用或无定价权限，请选择新的定价负责人后完成质检'];
        return ['mode' => 'quote_user', 'uid' => (int)$users[0]['uid'], 'name' => $users[0]['name'],
            'message' => '按录入的报价员交接定价；如需改派，可在任务中转交'];
    }
}
