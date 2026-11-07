<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\admin\check\RecycleCheckTemplateService;
use addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelDictService;
use addon\hsx_recycle\app\service\admin\template\RecycleTemplateBindingService;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceEntryImei;
use addon\hsx_recycle\app\service\core\recycle_order\DeviceReadingArchive;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpIntegrationService;
use app\model\sys\SysUser;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/** 确认前的基础资料纠错。独立于质检、定价、打印与入库操作，不新增数据表。 */
class RecycleDeviceInfoCorrectionService extends BaseAdminService
{
    private const FIELDS = ['model', 'category_id', 'imei', 'imei2', 'sn', 'user_sn', 'capacity', 'color', 'system_version', 'warranty_info', 'summary', 'correction_reason'];
    private const LABELS = ['model' => '设备型号', 'category_id' => '型号分类', 'imei' => 'IMEI', 'imei2' => 'IMEI2', 'sn' => 'SN', 'user_sn' => '用户串号', 'capacity' => '容量', 'color' => '颜色', 'system_version' => '系统版本', 'warranty_info' => '保修信息', 'check_template_id' => '质检模板'];
    private const SUMMARY_COLUMNS = ['capacity', 'color', 'system_version', 'warranty_info', 'battery', 'package_type', 'condition_grade'];

    public function describe(array $device): array
    {
        $empty = ['initial_entry' => $this->unknownActor(), 'checker' => $this->unknownActor()];
        try {
            $logs = $this->loadLogs((int)($device['id'] ?? 0));
            return ['allowed' => ($reason = $this->guardReason($device)) === '', 'reason' => $reason,
                'provenance' => $this->provenance($device, $logs), 'summary' => $this->currentSummary($device)];
        } catch (\Throwable $e) {
            return ['allowed' => false, 'reason' => '基础资料修改条件暂时无法核对，请稍后重试或联系管理员。',
                'provenance' => $empty, 'summary' => $this->currentSummary($device)];
        }
    }

    public function correct(int $id, array $input): array
    {
        $unexpected = array_diff(array_keys($input), self::FIELDS);
        if ($unexpected !== []) throw new CommonException('基础资料修改不能提交价格、状态、人员或其他业务字段：' . implode('、', $unexpected));
        if (isset($input['correction_reason']) && (!is_string($input['correction_reason']) || $this->length($input['correction_reason']) > 2000)) throw new CommonException('修改说明格式不正确或内容过长');
        return Db::transaction(function () use ($id, $input): array {
            $unlocked = $this->loadDevice($id, false);
            if (!$unlocked) throw new CommonException('DEVICE_NOT_FOUND');
            $order = $this->loadOrder((int)$unlocked['order_id'], true);
            if (!$order) throw new CommonException('ORDER_NOT_FOUND');
            $before = $this->loadDevice($id, true);
            if (!$before || (int)$before['order_id'] !== (int)$order['id']) throw new CommonException('设备归属已变化，请刷新后操作');
            $reason = $this->guardReason($before);
            if ($reason !== '') throw new CommonException($reason);
            $provenance = $this->provenance($before, $this->loadLogs($id));
            $info = DeviceReadingArchive::decode($before['info'] ?? []);
            $categoryId = array_key_exists('category_id', $input) ? (int)$input['category_id'] : (int)($before['category_id'] ?? 0);
            $categoryChanged = $categoryId !== (int)($before['category_id'] ?? 0);
            $updates = [];
            foreach (array_diff(self::FIELDS, ['summary', 'correction_reason']) as $key) {
                if (!array_key_exists($key, $input)) continue;
                if ($key === 'category_id') {
                    if ($categoryId <= 0) throw new CommonException('请选择具体型号分类');
                    $updates[$key] = $categoryId;
                    continue;
                }
                if (!is_scalar($input[$key]) && $input[$key] !== null) throw new CommonException('基础资料格式不正确：' . $key);
                $value = trim((string)$input[$key]);
                $max = in_array($key, ['imei', 'imei2', 'capacity', 'color'], true) ? 50 : 100;
                if ($this->length($value) > $max) throw new CommonException((self::LABELS[$key] ?? $key) . '内容过长');
                if ($key === 'imei') DeviceEntryImei::assertValid($value);
                if ($key === 'imei2' && $value !== '') DeviceEntryImei::assertValid($value);
                $updates[$key] = $value;
            }
            if ($categoryId > 0 && (array_key_exists('category_id', $input) || array_key_exists('model', $input))) {
                $node = $this->selectableLeaf($categoryId);
                $updates['model'] = (string)$node['node_name'];
                $info['goods_category'] = array_map('strval', (array)($node['category_path'] ?? [$categoryId]));
            }
            if (array_key_exists('model', $updates) && $updates['model'] === '') throw new CommonException('请填写设备型号');
            $templateId = (int)(($before['check_template_id'] ?? 0) ?: ($info['check_meta']['template_id'] ?? 0));
            $previousTemplateId = $templateId;
            if ($categoryChanged) {
                $binding = $this->resolveTemplate($categoryId);
                $nextTemplateId = (int)($binding['check_template_id'] ?? 0);
                if ($nextTemplateId <= 0) throw new CommonException('新型号尚未明确关联质检模板，请先配置型号模板后再修改');
                // 原模板和答案留作证据，不把旧选项按新模板重新解释，也不递归复制审计归档。
                if ($templateId > 0 && $nextTemplateId !== $templateId) {
                    $archiveKey = (string)$templateId;
                    if (!isset($info['info_correction_audit']['original_templates'][$archiveKey])) {
                        $info['info_correction_audit']['original_templates'][$archiveKey] = [
                            'template_id' => $templateId, 'category_id' => (int)($before['category_id'] ?? 0),
                            'check_uid' => (int)($before['check_uid'] ?? 0), 'check_at' => (int)($before['check_at'] ?? 0),
                            'summary' => $this->currentSummary($before),
                            'sign_summary' => DeviceReadingArchive::decode($info['sign_summary'] ?? []),
                            'check_meta' => DeviceReadingArchive::decode($info['check_meta'] ?? []),
                            'archived_at' => time(), 'operator_uid' => (int)$this->uid,
                        ];
                    }
                    foreach (array_keys($this->currentSummary($before)) as $oldKey) unset($info[$oldKey]);
                    unset($info['sign_summary']);
                    $info['check_meta'] = ['template_id' => $nextTemplateId, 'custom_fields' => [], 'result_items' => []];
                    foreach (self::SUMMARY_COLUMNS as $key) {
                        if (array_key_exists($key, $before) && !array_key_exists($key, $input)) $updates[$key] = '';
                    }
                }
                $templateId = $nextTemplateId;
                $updates['check_template_id'] = $templateId;
            }
            $previousSummary = $this->currentSummary($before);
            $current = $categoryChanged && $templateId !== $previousTemplateId ? [] : $previousSummary;
            $summaryPatch = $input['summary'] ?? [];
            if (!is_array($summaryPatch) || ($summaryPatch !== [] && array_keys($summaryPatch) === range(0, count($summaryPatch) - 1))) throw new CommonException('设备摘要格式应为字段和值的对应关系');
            $schemaFields = [];
            $allFields = [];
            $topSummary = array_intersect_key($updates, array_flip(['capacity', 'color', 'system_version', 'warranty_info']));
            if ($summaryPatch !== [] || $categoryChanged || ($topSummary !== [] && $templateId > 0)) {
                if ($templateId <= 0) throw new CommonException('设备尚未关联有效质检模板，不能修改动态摘要');
                foreach (($this->schema($templateId)['groups'] ?? []) as $group) {
                    foreach (($group['fields'] ?? []) as $field) {
                        $allFields[(string)$field['field_key']] = $field;
                        if ((int)($field['extra_config']['summary_visible'] ?? 0) === 1) $schemaFields[(string)$field['field_key']] = $field;
                    }
                }
                foreach ($summaryPatch as $key => $value) {
                    if (!isset($schemaFields[$key])) throw new CommonException('该字段不是当前模板启用的设备摘要：' . $key);
                    $summaryPatch[$key] = $this->normalizeValue($schemaFields[$key], $value);
                }
            }
            foreach (['capacity', 'color', 'system_version', 'warranty_info'] as $key) {
                if (array_key_exists($key, $updates) && !array_key_exists($key, $summaryPatch)) {
                    $schemaFields[$key] = $allFields[$key] ?? ['field_key' => $key, 'field_name' => self::LABELS[$key], 'component' => 'input', 'is_required' => 0];
                    $summaryPatch[$key] = $this->normalizeValue($schemaFields[$key], $updates[$key]);
                }
            }
            foreach ($schemaFields as $key => $field) {
                $value = array_key_exists($key, $summaryPatch) ? $summaryPatch[$key] : ($current[$key] ?? null);
                if (($categoryChanged || array_key_exists($key, $summaryPatch)) && (int)($field['is_required'] ?? 0) === 1 && $this->emptyValue($value)) throw new CommonException('请填写「' . (string)$field['field_name'] . '」');
            }
            $changes = [];
            $beforeFields = $schemaFields;
            if ($templateId !== $previousTemplateId && $previousTemplateId > 0) {
                $beforeFields = [];
                try {
                    foreach (($this->schema($previousTemplateId)['groups'] ?? []) as $group) foreach (($group['fields'] ?? []) as $field) $beforeFields[(string)$field['field_key']] = $field;
                } catch (\Throwable $e) { /* 已删除的历史模板不猜选项名称；保留原始值。 */ }
            }
            foreach ($updates as $key => $value) {
                if (!$this->same($before[$key] ?? '', $value)) $changes[$key] = ['label' => self::LABELS[$key] ?? $key, 'before' => $before[$key] ?? '', 'after' => $value];
            }
            foreach ($summaryPatch as $key => $value) {
                if (!$this->same($previousSummary[$key] ?? '', $value)) $changes['summary.' . $key] = [
                    'label' => (string)$schemaFields[$key]['field_name'], 'before' => $previousSummary[$key] ?? '', 'after' => $value,
                    'before_display' => $this->fieldDisplay($beforeFields[$key] ?? [], $previousSummary[$key] ?? ''),
                    'after_display' => $this->fieldDisplay($schemaFields[$key], $value),
                ];
                $info[$key] = $value;
                $info['check_meta']['custom_fields'][$key] = $value;
                $info['check_meta'][$key] = $value;
                $this->replaceResultItem($info, $schemaFields[$key], $value);
                if (in_array($key, self::SUMMARY_COLUMNS, true)) {
                    $columnValue = is_array($value) ? implode(',', $value) : (string)$value;
                    $max = $key === 'condition_grade' ? 20 : (in_array($key, ['system_version', 'warranty_info'], true) ? 100 : 50);
                    if ($this->length($columnValue) > $max) throw new CommonException('摘要内容过长：' . (string)$schemaFields[$key]['field_name']);
                    $updates[$key] = $columnValue;
                }
            }
            if ($changes === []) return ['changed' => false, 'changed_fields' => [], 'info_correction' => ['allowed' => true, 'reason' => '', 'provenance' => $provenance, 'summary' => $current]];
            $updates['info'] = $info;
            $updates['update_at'] = time();
            if (!$this->saveDevice($id, $updates)) throw new CommonException('设备基础资料保存失败');
            $batch = 'IC' . date('YmdHis') . bin2hex(random_bytes(4));
            $context = ['batch' => $batch, 'initial_entry' => $provenance['initial_entry'], 'checker' => $provenance['checker'], 'reason' => (string)($input['correction_reason'] ?? '')];
            $this->writeChunks($before, '__context', $context, $batch);
            foreach ($changes as $key => $change) $this->writeChunks($before, $key, $change, $batch);
            return ['changed' => true, 'changed_fields' => array_keys($changes), 'info_correction' => ['allowed' => true, 'reason' => '', 'provenance' => $provenance, 'summary' => array_replace($current, $summaryPatch)]];
        });
    }

    public function currentSummary(array $device): array
    {
        $info = DeviceReadingArchive::decode($device['info'] ?? []);
        $meta = DeviceReadingArchive::decode($info['check_meta'] ?? []);
        $values = DeviceReadingArchive::decode($info['sign_summary'] ?? []);
        $custom = DeviceReadingArchive::decode($meta['custom_fields'] ?? []);
        $keys = array_unique(array_merge(array_keys($values), array_keys($custom), self::SUMMARY_COLUMNS, ['battery_num']));
        foreach ($keys as $key) {
            if (array_key_exists($key, $device)) $values[$key] = $device[$key];
            if (array_key_exists($key, $info)) $values[$key] = $info[$key];
            if (array_key_exists($key, $meta)) $values[$key] = $meta[$key];
        }
        $values = array_replace($values, $custom);
        foreach ((array)($meta['result_items'] ?? []) as $item) {
            if (!is_array($item) || empty($item['field_key'])) continue;
            if (array_key_exists('value', $item)) $value = $item['value'];
            elseif (array_key_exists('values', $item)) {
                $value = $item['values'];
                if (is_array($value) && !in_array((string)($item['component'] ?? ''), ['checkbox'], true) && count($value) <= 1) $value = $value[0] ?? '';
            } else continue;
            $values[(string)$item['field_key']] = $value;
        }
        return $values;
    }

    /** 只在真实新增设备处调用；以后纠错不得覆盖首录快照，历史缺口不在此补造。 */
    public function recordInitialEntry(int $id, string $source = 'staff_entry', int $trustedSiteId = 0): void
    {
        $previousSite = $this->site_id;
        if ($trustedSiteId > 0) $this->site_id = $trustedSiteId;
        try { $this->captureInitialEntry($id, $source); }
        finally { $this->site_id = $previousSite; }
    }

    private function captureInitialEntry(int $id, string $source): void
    {
        $device = $this->loadDevice($id, true);
        if (!$device) throw new CommonException('首录记录失败：设备不存在');
        $info = DeviceReadingArchive::decode($device['info'] ?? []);
        if (is_array($info['entry_audit']['initial_entry'] ?? null)) return;
        if ((int)$this->uid > 0) {
            $actor = $this->staffActor((int)$this->uid);
        } elseif ($source === 'customer_order' && $this->isAuthenticatedMemberEntry($device)) {
            $actor = ['type' => 'member', 'uid' => 0, 'member_id' => (int)$device['member_id'], 'name' => '客户自行录入', 'known' => true, 'source' => 'customer_order', 'at' => time()];
        } else $actor = $this->unknownActor();
        $actor['source'] = $source;
        $actor['at'] = time();
        $hasInfo = $this->hasEnteredBasicInfo($device);
        $isCreation = in_array($source, ['staff_entry', 'customer_order', 'staff_order'], true);
        if ($isCreation && !isset($info['entry_audit']['record_creator'])) $info['entry_audit']['record_creator'] = $actor;
        if ($hasInfo) $info['entry_audit']['initial_entry'] = $actor;
        elseif (!$isCreation) return;
        if (!$this->saveDevice($id, ['info' => $info])) throw new CommonException('首录人员记录失败');
        $this->writeLog(['site_id' => (int)$this->site_id, 'device_id' => $id, 'order_id' => (int)$device['order_id'],
            'operator_id' => (int)$actor['uid'], 'operator_name' => (string)$actor['name'], 'operation_type' => $hasInfo ? 'initial_entry' : 'record_creator', 'action' => $hasInfo ? 'initial_entry' : 'record_creator',
            'old_status' => (int)$device['status'], 'new_status' => (int)$device['status'], 'remark' => ($hasInfo ? '首次设备基础资料录入：' : '仅创建设备占位，尚未录入型号：') . (string)$actor['name'] . '；来源：' . $source, 'create_at' => time()]);
    }

    /** 仅空型号首次填入才捕获；已有型号的历史空缺不能补记到当前员工头上。 */
    public function recordFirstModelFill(int $id, array $before, string $source, int $trustedSiteId): void
    {
        $previousSite = $this->site_id;
        if ($trustedSiteId > 0) $this->site_id = $trustedSiteId;
        try {
            if ($this->hasEnteredBasicInfo($before)) return;
            $this->recordInitialEntry($id, $source, $trustedSiteId);
        } finally { $this->site_id = $previousSite; }
    }

    protected function hasEnteredBasicInfo(array $device): bool
    {
        if (trim((string)($device['model'] ?? '')) !== '') return true;
        if ((int)($device['category_id'] ?? 0) <= 0) return false;
        try { return trim((string)($this->selectableLeaf((int)$device['category_id'])['node_name'] ?? '')) !== ''; }
        catch (\Throwable $e) { return false; }
    }

    protected function isAuthenticatedMemberEntry(array $device): bool
    {
        return isset($this->request) && method_exists($this->request, 'memberId') && method_exists($this->request, 'siteId')
            && (int)$this->request->siteId() === (int)$device['site_id'] && (int)$this->request->memberId() > 0
            && (int)$this->request->memberId() === (int)($device['member_id'] ?? 0);
    }

    /** 分片仅是既有255字符列的存储方式；详情按完整批次还原，不把分片JSON交给用户。 */
    public function groupTimelineLogs(array $rows, int $deviceId): array
    {
        $result = [];
        $seen = [];
        foreach ($rows as $row) {
            if (($row['operation_type'] ?? '') !== 'info_correction' || !preg_match('/^\[(IC[0-9a-f]+)\]\[/i', (string)($row['remark'] ?? ''), $match)) {
                $result[] = $row;
                continue;
            }
            $batch = $match[1];
            if (isset($seen[$batch])) continue;
            $seen[$batch] = true;
            $parts = [];
            $bad = false;
            foreach ($this->loadCorrectionBatch($deviceId, $batch) as $piece) {
                if (!preg_match('/^\[' . preg_quote($batch, '/') . '\]\[([^\]]+)\]\[([0-9]+)\/([0-9]+)\] ([\s\S]*)$/u', (string)($piece['remark'] ?? ''), $m)) { $bad = true; continue; }
                $key = $m[1]; $index = (int)$m[2]; $total = (int)$m[3];
                if ($index <= 0 || $total <= 0 || $index > $total || isset($parts[$key]['pieces'][$index]) || (isset($parts[$key]['total']) && $parts[$key]['total'] !== $total)) { $bad = true; continue; }
                $parts[$key]['total'] = $total;
                $parts[$key]['pieces'][$index] = $m[4];
            }
            $decoded = [];
            foreach ($parts as $key => $set) {
                if (count($set['pieces']) !== $set['total']) { $bad = true; continue; }
                ksort($set['pieces'], SORT_NUMERIC);
                $value = json_decode(implode('', $set['pieces']), true);
                if (!is_array($value)) { $bad = true; continue; }
                $decoded[$key] = $value;
            }
            $context = $decoded['__context'] ?? [];
            unset($decoded['__context']);
            if (!$context || !$decoded) $bad = true;
            $row['status_name'] = '基础资料修改';
            $row['batch_no'] = $batch;
            $row['integrity'] = $bad ? 'incomplete' : 'complete';
            $row['changes'] = [];
            $row['provenance'] = ['initial_entry' => $context['initial_entry'] ?? $this->unknownActor(), 'checker' => $context['checker'] ?? $this->unknownActor()];
            $remarks = ['最初录入：' . (string)$row['provenance']['initial_entry']['name'], '质检人：' . (string)$row['provenance']['checker']['name']];
            foreach ($decoded as $key => $change) {
                $row['changes'][] = ['field' => $key, 'label' => (string)($change['label'] ?? $key), 'before' => $change['before'] ?? '', 'after' => $change['after'] ?? '', 'before_display' => $change['before_display'] ?? $this->displayValue($change['before'] ?? ''), 'after_display' => $change['after_display'] ?? $this->displayValue($change['after'] ?? '')];
                $remarks[] = (string)($change['label'] ?? $key) . '：' . ($change['before_display'] ?? $this->displayValue($change['before'] ?? '')) . ' → ' . ($change['after_display'] ?? $this->displayValue($change['after'] ?? ''));
            }
            if (($context['reason'] ?? '') !== '') $remarks[] = '说明：' . $context['reason'];
            $row['remark'] = $bad ? '该次资料修改日志分片不完整，请联系管理员核对原始日志；不展示不完整的前后值。' : implode('；', $remarks);
            if ($bad) $row['changes'] = [];
            $result[] = $row;
        }
        return $result;
    }

    protected function loadCorrectionBatch(int $id, string $batch): array
    {
        return Db::name('recycle_device_log')->where('site_id', $this->site_id)->where('device_id', $id)->where('operation_type', 'info_correction')->whereLike('remark', '[' . $batch . ']%')->order('id asc')->select()->toArray();
    }
    private function displayValue($value): string
    {
        if ($value === null || $value === '') return '空';
        if (is_bool($value)) return $value ? '是' : '否';
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$value;
    }
    private function fieldDisplay(array $field, $value): string
    {
        if ($this->emptyValue($value)) return '空';
        if (($field['component'] ?? '') === 'switch') return in_array($value, [true, 1, '1'], true) ? '是' : '否';
        if (!in_array((string)($field['component'] ?? ''), ['radio', 'select', 'checkbox'], true)) return $this->displayValue($value);
        $labels = [];
        foreach (is_array($value) ? $value : [$value] as $one) {
            $label = (string)$one;
            foreach ((array)($field['options'] ?? []) as $option) if ((string)($option['value'] ?? '') === (string)$one) { $label = (string)($option['label'] ?? $option['name'] ?? $one); break; }
            $labels[] = $label;
        }
        return implode('、', $labels);
    }

    protected function guardReason(array $device): string
    {
        if (!$device || (int)($device['site_id'] ?? 0) !== (int)$this->site_id) return '设备不存在或不属于当前站点';
        if ((int)($device['confirm_status'] ?? 0) !== 0) return '客户已确认或拒绝，不能再修改回收基础资料';
        if ((int)($device['pay_status'] ?? 0) === 1 || (float)($device['pay_amount'] ?? 0) > 0 || (int)($device['pay_time'] ?? 0) > 0) return '设备已形成付款，不能再修改回收基础资料';
        if (!in_array((int)($device['status'] ?? 0), [3, 4, 7, 8], true)) return '仅已质检、已定价且客户未确认的设备支持基础资料修改';
        if ((int)($device['downstream_erp_asset_id'] ?? 0) > 0 || (int)($device['downstream_stage'] ?? 0) > 0) return '设备已关联 ERP 或已进入下游，不能在回收端修改基础资料';
        return $this->erpReason($device);
    }

    protected function erpReason(array $device): string
    {
        if (!(new RecycleErpIntegrationService())->isInstalled((int)$this->site_id)) {
            $hasOwner = Db::name('recycle_device_log')->where('site_id', $this->site_id)->where('device_id', (int)$device['id'])->where('operation_type', 'erp_payment_owner')->count();
            return $hasOwner ? '设备已交接 ERP，不能在回收端修改基础资料' : '';
        }
        $responses = (array)event('RecycleErpPaymentOwnershipRequested', ['site_id' => (int)$this->site_id, 'device_ids' => [(int)$device['id']], 'purpose' => 'info_correction']);
        $matches = array_values(array_filter($responses, static fn($row): bool => is_array($row) && ($row['consumer'] ?? '') === 'hsx_erp'));
        if (count($matches) !== 1 || ($matches[0]['status'] ?? '') !== 'processed' || !empty($matches[0]['error'])) return 'ERP 资料关联核对未响应，请核对服务后重试';
        $fact = $matches[0]['devices'][(int)$device['id']] ?? null;
        if (!is_array($fact) || !is_bool($fact['has_asset'] ?? null) || !is_bool($fact['has_purchase'] ?? null) || !is_bool($fact['ambiguous'] ?? null)) return 'ERP 资料关联核对不完整，请更新配套后端后重试';
        return $fact['has_asset'] || $fact['has_purchase'] || $fact['ambiguous'] ? '设备已存在 ERP 采购或资产关联，不能在回收端修改基础资料' : '';
    }

    private function provenance(array $device, array $logs): array
    {
        $info = DeviceReadingArchive::decode($device['info'] ?? []);
        $initial = is_array($info['entry_audit']['initial_entry'] ?? null) ? $info['entry_audit']['initial_entry'] : $this->unknownActor();
        $checker = $this->unknownActor();
        foreach ($logs as $log) {
            $type = (string)(($log['operation_type'] ?? '') ?: ($log['action'] ?? ''));
            $hasHistoricalModel = in_array($type, ['add', 'sign'], true) && preg_match('/(?:^|\|)\s*型号[:：]\s*\S/u', (string)($log['remark'] ?? ''));
            if (empty($initial['known']) && ($type === 'initial_entry' || $hasHistoricalModel) && (int)($log['operator_id'] ?? 0) > 0) $initial = $this->logActor($log);
            if ($type === 'check_complete' && (int)($device['check_uid'] ?? 0) > 0 && (int)($log['operator_id'] ?? 0) === (int)$device['check_uid']) $checker = $this->logActor($log);
        }
        if ((int)($device['check_uid'] ?? 0) > 0) {
            if (empty($checker['known'])) $checker = $this->staffActor((int)$device['check_uid']);
            $checker['source'] = $checker['source'] === 'device_log' ? 'device_check_uid_log' : 'device_check_uid';
            $checker['at'] = (int)($device['check_at'] ?? 0);
        }
        return ['initial_entry' => $initial, 'checker' => $checker];
    }

    private function logActor(array $log): array
    {
        return ['type' => 'staff', 'uid' => (int)$log['operator_id'], 'member_id' => 0, 'name' => (string)(($log['operator_name'] ?? '') ?: ('历史员工#' . (int)$log['operator_id'])), 'known' => true, 'source' => 'device_log', 'at' => (int)($log['create_at'] ?? 0)];
    }
    private function unknownActor(): array { return ['type' => 'unknown', 'uid' => 0, 'member_id' => 0, 'name' => '历史未记录', 'known' => false, 'source' => 'unknown', 'at' => 0]; }
    protected function staffActor(int $uid): array
    {
        $row = SysUser::withTrashed()->where('uid', $uid)->field('uid,username,real_name')->findOrEmpty()->toArray();
        return ['type' => 'staff', 'uid' => $uid, 'member_id' => 0, 'name' => (string)($row['real_name'] ?? '') ?: ((string)($row['username'] ?? '') ?: ('历史员工#' . $uid)), 'known' => true, 'source' => 'staff', 'at' => time()];
    }
    protected function loadDevice(int $id, bool $lock): array { return RecycleDevice::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock($lock)->findOrEmpty()->toArray(); }
    protected function loadOrder(int $id, bool $lock): array { return RecycleOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock($lock)->findOrEmpty()->toArray(); }
    protected function loadLogs(int $id): array { return Db::name('recycle_device_log')->where('site_id', $this->site_id)->where('device_id', $id)->order('id asc')->select()->toArray(); }
    protected function saveDevice(int $id, array $values): bool { $device = RecycleDevice::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty(); return !$device->isEmpty() && $device->save($values) !== false; }
    protected function selectableLeaf(int $id): array { return (new RecycleDeviceModelDictService())->selectableLeaf($id); }
    protected function resolveTemplate(int $id): array { return (new RecycleTemplateBindingService())->resolveByTarget('model_dict', $id, 'manual_device_label', false); }
    protected function schema(int $id): array { return (new RecycleCheckTemplateService())->schema(['template_id' => $id]); }

    private function normalizeValue(array $field, $value)
    {
        $component = (string)($field['component'] ?? 'input');
        $multiple = $component === 'checkbox' || ($component === 'select' && ($field['selection_mode'] ?? '') === 'multiple');
        if ($multiple) {
            if ($value === null || $value === '') $value = [];
            if (!is_array($value)) throw new CommonException('多选摘要必须提交选项列表');
            $value = array_values(array_unique(array_map('strval', $value)));
        } elseif (is_array($value) || is_object($value)) throw new CommonException('输入或单选摘要不能提交数组');
        elseif ($value === null) $value = '';
        if (in_array($component, ['radio', 'select', 'checkbox'], true)) {
            $allowed = array_map(static fn($row): string => (string)$row['value'], (array)($field['options'] ?? []));
            foreach ($multiple ? $value : ($value === '' ? [] : [$value]) as $selected) if (!in_array((string)$selected, $allowed, true)) throw new CommonException('摘要选项已变化，请重新选择「' . $field['field_name'] . '」');
        } elseif ($component === 'number' && $value !== '') {
            if (!is_numeric($value) || !is_finite((float)$value)) throw new CommonException('请输入有效数字：' . $field['field_name']);
            $value = 0 + $value;
        } elseif ($component === 'switch') {
            if (!in_array($value, [true, false, 0, 1, '0', '1', ''], true)) throw new CommonException('开关摘要值不正确');
            $value = in_array($value, [true, 1, '1'], true);
        } else $value = (string)$value;
        return $value;
    }
    private function replaceResultItem(array &$info, array $field, $value): void
    {
        $key = (string)$field['field_key'];
        $items = array_values(array_filter((array)($info['check_meta']['result_items'] ?? []), static fn($row): bool => !is_array($row) || (string)($row['field_key'] ?? '') !== $key));
        if (!$this->emptyValue($value)) {
            $values = is_array($value) ? $value : [$value];
            $labels = [];
            $options = [];
            foreach ($values as $one) {
                $label = is_bool($one) ? ($one ? '开启' : '关闭') : (string)$one;
                if (in_array((string)($field['component'] ?? ''), ['radio', 'select', 'checkbox'], true)) foreach ((array)($field['options'] ?? []) as $option) if ((string)$option['value'] === (string)$one) { $label = (string)($option['label'] ?? $option['name'] ?? $one); break; }
                $labels[] = $label;
                $options[] = ['value' => $one, 'label' => $label];
            }
            $items[] = ['field_key' => $key, 'field_name' => (string)$field['field_name'], 'component' => (string)($field['component'] ?? 'input'), 'value' => $value, 'values' => $values, 'labels' => $labels, 'option_items' => $options, 'text' => (string)$field['field_name'] . '：' . implode('、', $labels)];
        }
        $info['check_meta']['result_items'] = $items;
    }
    private function writeChunks(array $device, string $field, array $data, string $batch): void
    {
        if (preg_match('/[\[\]\r\n]/', $field)) throw new CommonException('摘要字段标识包含不支持的字符，未保存本次修改');
        $text = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) throw new CommonException('修改日志文字编码无效，未保存本次修改');
        $chunks = array_chunk($characters, 130);
        foreach ($chunks as $index => $chunk) {
            $prefix = '[' . $batch . '][' . $field . '][' . ($index + 1) . '/' . count($chunks) . '] ';
            if ($this->length($prefix) > 110) throw new CommonException('摘要字段标识过长，无法完整记录修改日志');
            $this->writeLog(['site_id' => (int)$this->site_id, 'device_id' => (int)$device['id'], 'order_id' => (int)$device['order_id'], 'operator_id' => (int)$this->uid, 'operator_name' => (string)$this->username, 'operation_type' => 'info_correction', 'action' => 'info_correction', 'old_status' => (int)$device['status'], 'new_status' => (int)$device['status'], 'remark' => $prefix . implode('', $chunk), 'create_at' => time()]);
        }
    }
    protected function writeLog(array $row): void { if (!Db::name('recycle_device_log')->insert($row)) throw new CommonException('修改日志写入失败，未保存本次修改'); }
    private function length(string $value): int { return preg_match_all('/./us', $value, $unused) ?: 0; }
    private function emptyValue($value): bool { return $value === null || $value === '' || (is_array($value) && $value === []); }
    private function same($left, $right): bool { return is_array($left) || is_array($right) ? $left === $right : (string)$left === (string)$right; }
}
