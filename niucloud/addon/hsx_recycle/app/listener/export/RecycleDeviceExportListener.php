<?php

namespace addon\hsx_recycle\app\listener\export;

use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceSummaryHelper;

/**
 * 回收设备导出监听器
 */
class RecycleDeviceExportListener
{
    /**
     * 获取导出数据
     * @param array|null $param
     * @return array
     */
    public function handle($param = [])
    {
        // 确保参数是数组类型
        if (!is_array($param)) {
            $param = [];
        }
        
        $data = [];
        if (isset($param['type']) && $param['type'] == 'recycle_device') {
            $model = new RecycleDevice();
            $field = 'id, imei,imei2,sn,member_id, model, check_result, info, category_id, color,package_type,capacity,warranty_info,system_version,check_template_id, status, final_price, sell_price, update_at, order_id, price_uid, dispose_type, dispose_status, settlement_mode, consignment_order_id';

            $where = $param['where'] ?? [];

            // 查询导出数据 - 使用与列表页相同的逻辑
            $search_model = $model->where([['site_id', '=', $param['site_id'] ?? 0]])
                ->withSearch(['imei', 'model', 'status', 'update_at','export_status', 'device_ids', 'warehouse_type'], $where)
                ->whereIn('status', !empty($where['status']) ? [(int)$where['status']] : [
                    RecycleOrderDict::DEVICE_STATUS_RECYCLED,
                    RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
                ])
                ->with([
                    'order',
                    'priceUser' => function($query) {
                        $query->field('uid,username,real_name');
                    },
                    'consignmentOrder' => function($query) {
                        $query->field('id,consignment_no,source_device_id,status,listing_price,sold_price,settlement_amount');
                    }
                ])
                ->field($field)
                ->append(['status_name', 'category_name', 'nickname','code', 'dispose_type_name', 'dispose_status_name'])
                ->order('update_at desc');
            
            // 筛选分类
            if (!empty($where['category_id'])) {
                $search_model->where('category_id', $where['category_id']);
            }

            // 处理分页参数，确保是整数类型
            $page = isset($param['page']['page']) ? (int)$param['page']['page'] : 0;
            $limit = isset($param['page']['limit']) ? (int)$param['page']['limit'] : 0;

            if ($page > 0 && $limit > 0) {
                $data = $search_model->page($page, $limit)->select()->toArray();
            } else {
                $data = $search_model->select()->toArray();
            }
            
            // 处理导出数据格式
             // 先收集所有设备 ID，用于批量更新 export_time
            $deviceIds = array_column($data, 'id');

            // 批量更新这些设备的 export_time
            if (!empty($deviceIds)) {
                (new RecycleDevice())->whereIn('id', $deviceIds)->update(['export_time' => time()]);
            }

            // 质检模板保留列(颜色/内存等)存的是选项值(数字 id),按各设备所属模板回译成文案,避免导出偏差
            $reservedKeys = ['color', 'package_type', 'capacity', 'system_version', 'warranty_info'];
            $templateIds = array_column($data, 'check_template_id');
            $optionLabelMap = DeviceSummaryHelper::buildOptionLabelMap($templateIds, $reservedKeys, (int)($param['site_id'] ?? 0));

            foreach ($data as $key => $value) {
                // “包装”是导出展示字段：不要求额外建表或补历史列。
                // 新数据优先使用设备列，历史数据为空时从 info JSON / 质检结果中派生。
                if (trim((string)($data[$key]['package_type'] ?? '')) === '') {
                    $data[$key]['package_type'] = $this->extractPackageType(
                        $value['info'] ?? [],
                        (string)($value['check_result'] ?? '')
                    );
                }

                // 选项值 → 文案回译(input/number 等自由输入字段不命中映射,原样保留)
                $templateId = (int)($value['check_template_id'] ?? 0);
                if ($templateId > 0 && !empty($optionLabelMap[$templateId])) {
                    foreach ($reservedKeys as $rk) {
                        if (isset($optionLabelMap[$templateId][$rk]) && isset($data[$key][$rk]) && $data[$key][$rk] !== '') {
                            $data[$key][$rk] = DeviceSummaryHelper::resolveReservedValue((string)$data[$key][$rk], $optionLabelMap[$templateId][$rk]);
                        }
                    }
                }

                $data[$key]['order_no'] = $value['order']['order_no'] ?? '';
                $data[$key]['create_at'] = !empty($value['update_at']) ? $value['update_at'] : '';
                $isConsign = ($value['dispose_type'] ?? '') === RecycleOrderDict::DISPOSE_TYPE_CONSIGN
                    || (int)($value['status'] ?? 0) === RecycleOrderDict::DEVICE_STATUS_CONSIGNED;
                $data[$key]['warehouse_type_name'] = $isConsign ? '代卖入库' : '回收入库';
                $data[$key]['is_merchant_owned'] = $isConsign ? '否' : '是';
                $data[$key]['consignment_no'] = $value['consignment_order']['consignment_no'] ?? $value['consignmentOrder']['consignment_no'] ?? '';

                // 获取报价人姓名 - 兼容多种键名
                $data[$key]['quoter_name'] = '';

                // 尝试从 price_user 或 priceUser 获取
                $priceUser = $value['price_user'] ?? $value['priceUser'] ?? null;

                if (!empty($priceUser)) {
                    $data[$key]['quoter_name'] = $priceUser['real_name'] ?? $priceUser['username'] ?? '';
                }

                // 如果还是空，尝试直接从 sys_user 表查询
                if (empty($data[$key]['quoter_name']) && !empty($value['price_uid'])) {
                    try {
                        $sysUser = \app\model\sys\SysUser::where('uid', $value['price_uid'])
                            ->field('uid,username,real_name')
                            ->find();
                        if ($sysUser) {
                            $data[$key]['quoter_name'] = $sysUser['real_name'] ?? $sysUser['username'] ?? '';
                        }
                    } catch (\Exception $e) {
                        // 查询失败，保持为空
                    }
                }

                // 将数字类型的字段强制转换为字符串，防止Excel显示为科学计数法
                // 使用制表符前缀强制Excel识别为文本
                if (!empty($value['imei'])) {
                    $data[$key]['imei'] = "\t" . $value['imei'];
                }
                if (!empty($value['imei2'])) {
                    $data[$key]['imei2'] = "\t" . $value['imei2'];
                }
                if (!empty($value['sn'])) {
                    $data[$key]['sn'] = "\t" . $value['sn'];
                }
                if (!empty($value['code'])) {
                    $data[$key]['code'] = "\t" . $value['code'];
                }

                unset($data[$key]['order'], $data[$key]['price_user'], $data[$key]['priceUser'], $data[$key]['consignment_order'], $data[$key]['consignmentOrder'], $data[$key]['id'], $data[$key]['info'], $data[$key]['category_id'], $data[$key]['status'], $data[$key]['order_id'], $data[$key]['update_at'], $data[$key]['price_uid'], $data[$key]['dispose_type'], $data[$key]['dispose_status'], $data[$key]['settlement_mode'], $data[$key]['consignment_order_id'], $data[$key]['check_template_id'], $data[$key]['system_version']);
            }
        }
        return $data;
    }

    /**
     * 从设备 info JSON 中提取包装展示值，兼容签收摘要、质检结果项和历史文本。
     * 这里只生成导出值，不回写设备表。
     *
     * @param mixed $info
     */
    private function extractPackageType($info, string $checkResult = ''): string
    {
        $info = $this->normalizeArray($info);
        $checkMeta = $this->normalizeArray($info['check_meta'] ?? []);
        $signSummary = $this->normalizeArray($info['sign_summary'] ?? []);

        $directCandidates = [
            $info['package_type'] ?? null,
            $info['packageType'] ?? null,
            $info['package'] ?? null,
            $info['packing'] ?? null,
            $signSummary['package_type'] ?? null,
            $signSummary['package'] ?? null,
            $checkMeta['package_type'] ?? null,
            $checkMeta['package'] ?? null,
        ];
        foreach ($directCandidates as $candidate) {
            $display = $this->displayValue($candidate);
            if ($display !== '') {
                return $display;
            }
        }

        foreach ((array)($checkMeta['result_items'] ?? []) as $item) {
            $item = $this->normalizeArray($item);
            if (empty($item)) {
                continue;
            }
            $fieldKey = trim((string)($item['field_key'] ?? $item['key'] ?? ''));
            $fieldName = trim((string)($item['field_name'] ?? $item['name'] ?? ''));
            if (!in_array($fieldKey, ['package_type', 'packageType', 'package', 'packing'], true)
                && mb_strpos($fieldName, '包装') === false) {
                continue;
            }
            foreach (['labels', 'label', 'values', 'value', 'text'] as $valueKey) {
                $display = $this->displayValue($item[$valueKey] ?? null);
                if ($display !== '') {
                    return $display;
                }
            }
        }

        // 兼容历史记录：质检 JSON 已被整理成“包装：全套;”文本。
        if ($checkResult !== '' && preg_match('/(?:^|[;；\r\n])\s*包装\s*[:：]\s*([^;；\r\n]+)/u', $checkResult, $matches)) {
            return trim((string)($matches[1] ?? ''));
        }
        return '';
    }

    /** @param mixed $value */
    private function normalizeArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /** @param mixed $value */
    private function displayValue($value): string
    {
        if (is_array($value)) {
            if (isset($value['label']) || isset($value['name'])) {
                return trim((string)($value['label'] ?? $value['name'] ?? ''));
            }
            $parts = array_values(array_filter(array_map(static function ($item): string {
                if (is_array($item)) {
                    return trim((string)($item['label'] ?? $item['name'] ?? $item['value'] ?? ''));
                }
                return is_scalar($item) ? trim((string)$item) : '';
            }, $value), static fn(string $item): bool => $item !== ''));
            return implode('、', $parts);
        }
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
