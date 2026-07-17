<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\report;

use app\model\sys\SysUser;
use think\facade\Db;

final class RecycleBusinessReportMetricsService
{
    public function collect(array $request): array
    {
        $siteId = (int)($request['site_id'] ?? 0);
        $start = (int)($request['start_at'] ?? 0);
        $end = (int)($request['end_at'] ?? 0);
        if ($siteId <= 0 || $start <= 0 || $end < $start) throw new \InvalidArgumentException('回收经营指标缺少有效统计周期');

        $signedLogs = Db::name('recycle_order_log')->alias('l')->leftJoin('recycle_order o', 'o.id=l.order_id AND o.site_id=l.site_id')
            ->where('l.site_id', '=', $siteId)->where('l.new_status', '=', 2)->whereBetween('l.create_at', [$start, $end]);
        $signedOrderIds = array_values(array_unique(array_map('intval', (clone $signedLogs)->column('l.order_id'))));
        $signedDeviceCount = $signedOrderIds === [] ? 0 : (int)Db::name('recycle_device')->where('site_id', '=', $siteId)->whereIn('order_id', $signedOrderIds)->count();
        $checked = Db::name('recycle_device')->where('site_id', '=', $siteId)->where('check_uid', '>', 0)->whereBetween('check_at', [$start, $end]);
        $priced = Db::name('recycle_device')->where('site_id', '=', $siteId)->where('price_uid', '>', 0)->whereBetween('price_at', [$start, $end]);
        $recycled = Db::name('recycle_device')->alias('d')
            ->where('d.site_id', '=', $siteId)
            ->where('d.dispose_status', '=', 1)
            ->whereBetween('d.confirm_time', [$start, $end]);
        $returned = Db::name('recycle_device')->where('site_id', '=', $siteId)->where('dispose_status', '=', 2)->whereBetween('update_at', [$start, $end]);

        $categories = (clone $recycled)->leftJoin('recycle_category c', 'c.category_id=d.category_id AND c.site_id=d.site_id')
            ->field("d.category_id as `key`,IF(c.category_name IS NULL OR c.category_name='', '未分类', c.category_name) as name,COUNT(*) as in_count")
            ->group('d.category_id,c.category_name')->select()->toArray();
        $categories = array_map(static fn(array $row): array => [
            'key' => (string)$row['key'], 'name' => (string)$row['name'], 'in_count' => (int)$row['in_count'], 'sale_count' => 0, 'stock_count' => 0,
        ], $categories);

        $staff = [];
        $append = static function (array $rows, string $roleKey, string $roleName) use (&$staff): void {
            foreach ($rows as $row) $staff[] = [
                'uid' => (int)$row['uid'], 'name' => (string)$row['name'], 'role_key' => $roleKey, 'role_name' => $roleName,
                'count' => (int)$row['count'], 'amount' => 0, 'profit' => 0,
            ];
        };
        $signRows = (clone $signedLogs)->where('l.operator_id', '>', 0)
            ->field('l.operator_id as uid,MAX(l.operator_name) as name,COUNT(DISTINCT l.order_id) as count')->group('l.operator_id')->select()->toArray();
        $checkRows = (clone $checked)->field('check_uid as uid,COUNT(*) as count')->group('check_uid')->select()->toArray();
        $priceRows = (clone $priced)->field('price_uid as uid,COUNT(*) as count')->group('price_uid')->select()->toArray();
        $names = $this->userNames(array_merge(array_column($checkRows, 'uid'), array_column($priceRows, 'uid')));
        foreach ($checkRows as &$row) $row['name'] = $names[(int)$row['uid']] ?? ('员工#' . (int)$row['uid']);
        unset($row);
        foreach ($priceRows as &$row) $row['name'] = $names[(int)$row['uid']] ?? ('员工#' . (int)$row['uid']);
        unset($row);
        $append($signRows, 'recycle_signer', '签收');
        $append($checkRows, 'recycle_inspector', '质检');
        $append($priceRows, 'recycle_pricer', '回收定价');

        $todoRows = Db::name('recycle_device')->where('site_id', '=', $siteId)->whereIn('status', [1, 2, 3, 4, 7, 8])
            ->field('status,COUNT(*) as count')->group('status')->select()->toArray();
        $todos = ['recycle_pending_sign' => (int)Db::name('recycle_order')->where([['site_id', '=', $siteId], ['status', '=', 1]])->count()];
        foreach ($todoRows as $row) {
            $status = (int)$row['status'];
            if (in_array($status, [1, 2], true)) $todos['recycle_pending_check'] = (int)($todos['recycle_pending_check'] ?? 0) + (int)$row['count'];
            elseif (in_array($status, [3, 4], true)) $todos['recycle_pending_price'] = (int)($todos['recycle_pending_price'] ?? 0) + (int)$row['count'];
        }
        $details = (clone $recycled)->field('d.id as device_id,d.order_id,d.imei,d.sn,d.model,d.category_id,d.final_price,d.check_uid,d.price_uid,d.confirm_time')
            ->order('d.confirm_time desc,d.id desc')->limit(100)->select()->toArray();

        return [
            'provider' => 'hsx_recycle', 'provider_name' => '回收业务', 'available' => true,
            'summary' => [
                'recycle_signed_order_count' => count($signedOrderIds), 'recycle_signed_device_count' => $signedDeviceCount,
                'recycle_checked_count' => (int)(clone $checked)->count(), 'recycle_priced_count' => (int)(clone $priced)->count(),
                'recycle_in_count' => (int)(clone $recycled)->count(), 'recycle_return_count' => (int)(clone $returned)->count(),
            ],
            'categories' => $categories, 'staff' => $staff, 'todos' => $todos, 'details' => ['recycled_devices' => $details],
        ];
    }

    private function userNames(array $uids): array
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
        if ($uids === []) return [];
        $rows = SysUser::whereIn('uid', $uids)->field('uid,username,real_name')->select()->toArray();
        $map = [];
        foreach ($rows as $row) $map[(int)$row['uid']] = (string)($row['real_name'] ?: $row['username'] ?: ('员工#' . $row['uid']));
        return $map;
    }
}
