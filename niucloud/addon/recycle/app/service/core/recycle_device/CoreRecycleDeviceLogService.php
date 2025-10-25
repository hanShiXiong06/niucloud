<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\recycle_device;

use addon\recycle\app\dict\order\RecycleOrderDict;
use addon\recycle\app\model\RecycleDeviceLog;
use app\service\core\sys\CoreSysConfigService;
use core\base\BaseAdminService;

/**
 * 设备日志核心服务类
 */
class CoreRecycleDeviceLogService extends BaseAdminService
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeviceLog();
    }

    /**
     * 添加设备日志
     * @param array $data 日志数据
     * @return int 日志ID
     */
    public function addDeviceLog(array $data): int
    {
        $logData = [
            'device_id' => $data['device_id'] ?? 0,
            'order_id' => $data['order_id'] ?? 0,
            'operator_id' => $this->uid,
            'operator_name' => $this->username,
            'operation_type' => $data['operation_type'] ?? '',
            'old_status' => $data['old_status'] ?? 0,
            'new_status' => $data['new_status'] ?? 0,
            'create_at' => time(),
            'remark' => $this->buildDetailedRemark($data)
        ];

        return $this->model->insertGetId($logData);
    }

    /**
     * 记录设备签收日志
     * @param int $deviceId 设备ID
     * @param array $data 设备数据
     * @return int 日志ID
     */
    public function logDeviceSign(int $deviceId, array $data): int
    {
        return $this->addDeviceLog([
            'device_id' => $deviceId,
            'action' => 'sign',
            'old_status' => 0,
            'new_status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
            'operation_type' => 'sign',
            'device_info' => $data,
            'custom_message' => '设备签收',
            'order_id' => $data['order_id'] ?? 0
        ]);
    }

    /**
     * 记录质检开始日志
     * @param int $deviceId 设备ID
     * @param string $remark 备注
     * @return int 日志ID
     */
    public function logDeviceCheckStart(int $deviceId, string $remark = ''): int
    {
        return $this->addDeviceLog([
            'device_id' => $deviceId,
            'action' => 'check_start',
            'old_status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
            'new_status' => RecycleOrderDict::DEVICE_STATUS_CHECKING,
            'operation_type' => 'check_start',
            'custom_message' => '开始质检',
            'user_remark' => $remark
        ]);
    }

    /**
     * 记录质检完成日志
     * @param int $deviceId 设备ID
     * @param array $checkData 质检数据
     * @param string $remark 备注
     * @return int 日志ID
     */
    public function logDeviceCheckComplete(int $deviceId, array $checkData, string $remark = ''): int
    {
        return $this->addDeviceLog([
            'device_id' => $deviceId,
            'action' => 'check_complete',
            'old_status' => RecycleOrderDict::DEVICE_STATUS_CHECKING,
            'new_status' => RecycleOrderDict::DEVICE_STATUS_CHECKED,
            'operation_type' => 'check_complete',
            'check_data' => $checkData,
            'custom_message' => '完成质检',
            'user_remark' => $remark
        ]);
    }

    /**
     * 记录设备定价日志
     * @param int $deviceId 设备ID
     * @param array $priceData 定价数据
     * @param string $remark 备注
     * @return int 日志ID
     */
    public function logDevicePrice(int $deviceId, array $priceData, string $remark = ''): int
    {
        return $this->addDeviceLog([
            'device_id' => $deviceId,
            'action' => 'price',
            'old_status' => $priceData['old_status'] ?? 0,
            'new_status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
            'operation_type' => 'price',
            'price_data' => $priceData,
            'custom_message' => '设备定价',
            'user_remark' => $remark
        ]);
    }

    /**
     * 记录价格确认日志
     * @param int $deviceId 设备ID
     * @param float $finalPrice 最终价格
     * @param string $remark 备注
     * @return int 日志ID
     */
    public function logDevicePriceConfirm(int $deviceId, float $finalPrice, string $remark = ''): int
    {
        return $this->addDeviceLog([
            'device_id' => $deviceId,
            'action' => 'confirm_price',
            'old_status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
            'new_status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
            'operation_type' => 'confirm_price',
            'price_data' => ['final_price' => $finalPrice],
            'custom_message' => '价格确认',
            'user_remark' => $remark
        ]);
    }

    /**
     * 记录设备回收日志
     * @param int $deviceId 设备ID
     * @param string $remark 备注
     * @return int 日志ID
     */
    public function logDeviceRecycle(int $deviceId, string $remark = ''): int
    {
        return $this->addDeviceLog([
            'device_id' => $deviceId,
            'action' => 'recycle',
            'old_status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
            'new_status' => RecycleOrderDict::DEVICE_STATUS_RECYCLED,
            'operation_type' => 'recycle',
            'custom_message' => '设备回收',
            'user_remark' => $remark
        ]);
    }

    /**
     * 记录设备退回日志
     * @param int $deviceId 设备ID
     * @param string $reason 退回原因
     * @param string $remark 备注
     * @return int 日志ID
     */
    public function logDeviceReturn(int $deviceId, string $reason = '', string $remark = ''): int
    {
        return $this->addDeviceLog([
            'device_id' => $deviceId,
            'action' => 'return',
            'old_status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CONFIRM,
            'new_status' => RecycleOrderDict::DEVICE_STATUS_RETURNED,
            'operation_type' => 'return',
            'return_reason' => $reason,
            'custom_message' => '设备退回',
            'user_remark' => $remark
        ]);
    }

    /**
     * 记录设备添加日志
     * @param int $deviceId 设备ID
     * @param array $deviceData 设备数据
     * @return int 日志ID
     */
    public function logDeviceAdd(int $deviceId, array $deviceData): int
    {
        return $this->addDeviceLog([
            'device_id' => $deviceId,
            'action' => 'add',
            'old_status' => 0,
            'new_status' => RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK,
            'operation_type' => 'add',
            'device_info' => $deviceData,
            'custom_message' => '设备添加',
            'order_id' => $deviceData['order_id'] ?? 0
        ]);
    }

    /**
     * 记录设备移除日志
     * @param int $deviceId 设备ID
     * @param string $reason 移除原因
     * @return int 日志ID
     */
    public function logDeviceRemove(int $deviceId, string $reason = ''): int
    {
        return $this->addDeviceLog([
            'device_id' => $deviceId,
            'action' => 'remove',
            'old_status' => -1,
            'new_status' => -2,
            'operation_type' => 'remove',
            'remove_reason' => $reason,
            'custom_message' => '设备移除'
        ]);
    }

    /**
     * 构建详细的备注信息
     * @param array $data 数据
     * @return string 备注信息
     */
    private function buildDetailedRemark(array $data): string
    {
        $remarks = [];
        
        // 基础信息
        if (!empty($data['custom_message'])) {
            $remarks[] = $data['custom_message'];
        }
        
        // 状态变更信息
        if (isset($data['old_status']) && isset($data['new_status'])) {
            $oldStatusText = $this->getStatusText($data['old_status']);
            $newStatusText = $this->getStatusText($data['new_status']);
            $remarks[] = "状态变更: {$oldStatusText} → {$newStatusText}";
        }

        // 根据操作类型添加具体信息
        $action = $data['action'] ?? '';
        switch ($action) {
            case 'sign':
                $this->addSignRemarks($remarks, $data);
                break;
            case 'check_start':
                $this->addCheckStartRemarks($remarks, $data);
                break;
            case 'check_complete':
                $this->addCheckCompleteRemarks($remarks, $data);
                break;
            case 'price':
                $this->addPriceRemarks($remarks, $data);
                break;
            case 'confirm_price':
                $this->addPriceConfirmRemarks($remarks, $data);
                break;
            case 'recycle':
                $this->addRecycleRemarks($remarks, $data);
                break;
            case 'return':
                $this->addReturnRemarks($remarks, $data);
                break;
            case 'add':
                $this->addDeviceAddRemarks($remarks, $data);
                break;
            case 'remove':
                $this->addDeviceRemoveRemarks($remarks, $data);
                break;
        }

        // 用户备注
        if (!empty($data['user_remark'])) {
            $remarks[] = "备注: " . $data['user_remark'];
        }

        return implode(' | ', $remarks);
    }

    /**
     * 添加签收相关备注
     */
    private function addSignRemarks(array &$remarks, array $data): void
    {
        if (!empty($data['device_info'])) {
            $device = $data['device_info'];
            if (!empty($device['imei'])) {
                $remarks[] = "IMEI: " . $device['imei'];
            }
            if (!empty($device['model'])) {
                $remarks[] = "型号: " . $device['model'];
            }
            if (!empty($device['initial_price'])) {
                $remarks[] = "初始价格: ¥" . number_format((float)$device['initial_price'], 2);
            }
        }
    }

    /**
     * 添加质检开始相关备注
     */
    private function addCheckStartRemarks(array &$remarks, array $data): void
    {
        $remarks[] = "质检开始时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 添加质检完成相关备注
     */
    private function addCheckCompleteRemarks(array &$remarks, array $data): void
    {
        if (!empty($data['check_data'])) {
            $checkData = $data['check_data'];
            
            if (!empty($checkData['check_result'])) {
                $remarks[] = "质检结果: " . $checkData['check_result'];
            }
            
            if (isset($checkData['final_price'])) {
                $remarks[] = "质检定价: ¥" . number_format((float)$checkData['final_price'], 2);
            }
            
            if (isset($checkData['return_device']) && $checkData['return_device']) {
                $remarks[] = "处理方式: 退回设备";
            } else {
                $remarks[] = "处理方式: 正常回收";
            }
        }
        $remarks[] = "质检完成时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 添加定价相关备注
     */
    private function addPriceRemarks(array &$remarks, array $data): void
    {
        if (!empty($data['price_data'])) {
            $priceData = $data['price_data'];
            
            if (isset($priceData['initial_price'])) {
                $remarks[] = "初始价格: ¥" . number_format((float)$priceData['initial_price'], 2);
            }
            
            if (isset($priceData['final_price'])) {
                $remarks[] = "定价金额: ¥" . number_format((float)$priceData['final_price'], 2);
            }
            
            // 检查是否有旧价格（重新定价）
            if (isset($priceData['old_price']) && $priceData['old_price'] > 0) {
                $remarks[] = "原价格: ¥" . number_format((float)$priceData['old_price'], 2);
                if (isset($priceData['price_change'])) {
                    $change = (float)$priceData['price_change'];
                    $remarks[] = "价格调整: " . ($change >= 0 ? '+' : '') . "¥" . number_format($change, 2);
                }
                if (isset($priceData['is_repricing']) && $priceData['is_repricing']) {
                    $remarks[] = "操作类型: 重新定价";
                }
            } else {
                // 首次定价
                if (isset($priceData['initial_price']) && isset($priceData['final_price'])) {
                    $diff = (float)$priceData['final_price'] - (float)$priceData['initial_price'];
                    $remarks[] = "价格调整: " . ($diff >= 0 ? '+' : '') . "¥" . number_format($diff, 2);
                }
                $remarks[] = "操作类型: 首次定价";
            }
            
            if (!empty($priceData['price_reason'])) {
                $remarks[] = "定价原因: " . $priceData['price_reason'];
            }
        }
        $remarks[] = "定价时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 添加价格确认相关备注
     */
    private function addPriceConfirmRemarks(array &$remarks, array $data): void
    {
        if (!empty($data['price_data'])) {
            $priceData = $data['price_data'];
            
            if (isset($priceData['final_price'])) {
                $remarks[] = "确认价格: ¥" . number_format((float)$priceData['final_price'], 2);
            }
        }
        $remarks[] = "确认时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 添加回收相关备注
     */
    private function addRecycleRemarks(array &$remarks, array $data): void
    {
        $remarks[] = "回收完成时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 添加退回相关备注
     */
    private function addReturnRemarks(array &$remarks, array $data): void
    {
        if (!empty($data['return_reason'])) {
            $remarks[] = "退回原因: " . $data['return_reason'];
        }
        $remarks[] = "退回时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 添加设备添加相关备注
     */
    private function addDeviceAddRemarks(array &$remarks, array $data): void
    {
        if (!empty($data['device_info'])) {
            $device = $data['device_info'];
            if (!empty($device['imei'])) {
                $remarks[] = "IMEI: " . $device['imei'];
            }
            if (!empty($device['model'])) {
                $remarks[] = "型号: " . $device['model'];
            }
            if (!empty($device['initial_price'])) {
                $remarks[] = "初始价格: ¥" . number_format((float)$device['initial_price'], 2);
            }
        }
        $remarks[] = "添加时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 添加设备移除相关备注
     */
    private function addDeviceRemoveRemarks(array &$remarks, array $data): void
    {
        if (!empty($data['remove_reason'])) {
            $remarks[] = "移除原因: " . $data['remove_reason'];
        }
        $remarks[] = "移除时间: " . date('Y-m-d H:i:s');
    }

    /**
     * 获取状态文本
     * @param int $status 状态值
     * @return string
     */
    private function getStatusText(int $status): string
    {
        if ($status == 0) return '初始状态';
        if ($status == -1) return '存在状态';
        if ($status == -2) return '已删除';
        
        return RecycleOrderDict::getDeviceStatus($status) ?? '未知状态';
    }
} 