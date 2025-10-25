<?php
// addon/recycle/app/service/admin/stats/StatsService.php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\stats;

use addon\recycle\app\model\RecycleDevice;
use app\model\sys\SysUser; // 确认 SysUser 模型路径
use core\base\BaseAdminService;
use think\db\Query;

class StatsService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDevice();
    }

    /**
     * 统计质检员绩效
     * @param array $params
     * @return array
     */
    public function getInspectorPerformanceStats(array $params = []): array
    {
        return $this->getPerformanceStats($params, 'check_uid', 'check_at');
    }

    /**
     * 统计价格确认人绩效
     * @param array $params
     * @return array
     */
    public function getPriceConfirmerPerformanceStats(array $params = []): array
    {
        // 注意：recycle_device 表中没有直接的 price_confirmed_at 字段，
        // 可能需要使用 update_at 配合 final_status=1 的时间，或者依赖 price_uid 被赋值的时间点。
        // 这里暂时使用 update_at 作为示例，你可能需要根据业务逻辑调整时间字段。
        // 如果价格确认人确认操作有特定时间戳字段，请替换 'update_at'
        return $this->getPerformanceStats($params, 'price_uid', 'update_at');
    }

    /**
     * 核心绩效统计方法
     * @param array $params
     * @param string $uidField 'check_uid' 或 'price_uid'
     * @param string $dateField 用于时间筛选的日期字段名 ('check_at', 'update_at', etc.)
     * @return array
     */
    private function getPerformanceStats(array $params, string $uidField, string $dateField): array
    {
        $start_time_str = $params['start_time'] ?? date('Y-m-d 00:00:00');
        $end_time_str = $params['end_time'] ?? date('Y-m-d 23:59:59');
        $specific_uid = $params[$uidField] ?? null; // e.g., $params['check_uid']

        $query = $this->model->alias('rd')
            ->leftJoin((new SysUser())->getTable().' su', "su.uid = rd.{$uidField}")
            ->field([
                "rd.{$uidField} as uid",
                'IFNULL(su.real_name, su.username) as user_name', // 优先 real_name
                'COUNT(rd.id) as total_processed_devices',
                'SUM(CASE WHEN rd.final_status = 1 THEN 1 ELSE 0 END) as successfully_recycled_devices'
            ]);

        // 时间筛选
        if ($start_time_str) {
            $query->whereTime("rd.{$dateField}", '>=', strtotime($start_time_str));
        }
        if ($end_time_str) {
            $query->whereTime("rd.{$dateField}", '<=', strtotime($end_time_str));
        }

        // 特定 UID 筛选
        if ($specific_uid !== null && (int)$specific_uid > 0) {
            $query->where("rd.{$uidField}", '=', (int)$specific_uid);
        }
        
        // 确保只统计有效的 uid (大于0)
        $query->where("rd.{$uidField}", '>', 0);

        // 对于质检员，确保是已质检的设备
        if ($uidField === 'check_uid') {
            $query->where('rd.check_status', '=', 2); // 2 = 已质检
        }
        // 对于价格确认人，确保是已产生最终价格的设备 (或者已有 final_status)
        if ($uidField === 'price_uid') {
             $query->where('rd.final_status', '<>', 0); // 确保设备已进入最终状态决策阶段 (final_status is 1 or 2)
        }


        $stats = $query->group("rd.{$uidField}, user_name") // user_name is already an alias from IFNULL
                       ->order('successfully_recycled_devices', 'desc')
                       ->select()
                       ->toArray();

        $total_successfully_recycled = array_sum(array_column($stats, 'successfully_recycled_devices'));
        $total_processed = array_sum(array_column($stats, 'total_processed_devices'));


        return [
            'total_successfully_recycled_devices_in_period' => (int)$total_successfully_recycled,
            'total_processed_devices_in_period' => (int)$total_processed,
            'user_stats' => $stats,
            'params_echo' => [ // Echo back processed parameters for clarity
                'start_time_used' => $start_time_str,
                'end_time_used' => $end_time_str,
                'specific_uid_field' => $uidField,
                'specific_uid_value' => $specific_uid,
                'date_field_used' => $dateField
            ]
        ];
    }
} 