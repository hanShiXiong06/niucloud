<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpPrintDict;
use addon\hsx_erp\app\support\print\ErpPrintProviderManager;
use addon\hsx_erp\app\support\print\ErpPrintRenderer;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/** ERP 内置打印中心。回收插件仍使用自己的打印系统，两者不形成运行时依赖。 */
final class ErpPrintService extends BaseAdminService
{
    public function meta(): array
    {
        $this->ensureDefaults();
        return ['providers' => array_values(ErpPrintDict::providers()), 'scene_defs' => array_values(ErpPrintDict::scenes()),
            'variables' => ErpPrintDict::variables(), 'status_map' => ['queued' => '排队中', 'sending' => '发送中', 'waiting_client' => '等待手机打印', 'success' => '成功', 'failed' => '失败']];
    }

    public function printers(): array
    {
        $rows = Db::name('erp_printer')->where('site_id', $this->site_id)->order('is_default desc,sort asc,id desc')->select()->toArray();
        foreach ($rows as &$row) {
            $row['config'] = $this->maskConfig($this->decode((string)$row['config_json']));
            $row['capabilities'] = $this->decode((string)$row['capabilities_json']);
            unset($row['config_json'], $row['capabilities_json']);
        }
        return $rows;
    }

    public function savePrinter(array $data, int $id = 0): int
    {
        $providers = ErpPrintDict::providers();
        $driver = (string)($data['driver'] ?? '');
        if (!isset($providers[$driver])) throw new CommonException('请选择有效的打印驱动');
        $name = trim((string)($data['printer_name'] ?? ''));
        if ($name === '') throw new CommonException('请填写打印机名称');
        $type = (string)($data['print_type'] ?? 'receipt');
        if (!in_array($type, $providers[$driver]['types'], true)) throw new CommonException('该驱动不支持选择的打印类型');
        $old = $id > 0 ? Db::name('erp_printer')->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->find() : null;
        if ($id > 0 && !$old) throw new CommonException('打印机不存在');
        $config = (array)($data['config'] ?? []);
        if ($old) $config = $this->mergeMaskedConfig($this->decode((string)$old['config_json']), $config);
        $isDefault = (int)($data['is_default'] ?? 0) === 1 ? 1 : 0;
        if ($isDefault) Db::name('erp_printer')->where('site_id', $this->site_id)->update(['is_default' => 0, 'update_at' => time()]);
        $values = ['printer_name' => $name, 'driver' => $driver, 'connection_mode' => $providers[$driver]['modes'][0], 'print_type' => $type,
            'paper_width' => max(20, min(110, (int)($data['paper_width'] ?? ($type === 'label' ? 50 : 58)))),
            'config_json' => $this->encode($config), 'capabilities_json' => $this->encode(['size_mode' => $providers[$driver]['size_mode'], 'types' => $providers[$driver]['types']]),
            'copies' => max(1, min(9, (int)($data['copies'] ?? 1))), 'is_default' => $isDefault, 'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'sort' => (int)($data['sort'] ?? 0), 'remark' => mb_substr(trim((string)($data['remark'] ?? '')), 0, 255), 'update_at' => time()];
        if ($old) { Db::name('erp_printer')->where('id', $id)->update($values); return $id; }
        return (int)Db::name('erp_printer')->insertGetId(array_merge($values, ['site_id' => $this->site_id, 'create_at' => time()]));
    }

    public function deletePrinter(int $id): bool
    {
        if (Db::name('erp_print_scene')->where([['site_id', '=', $this->site_id], ['printer_id', '=', $id], ['enabled', '=', 1]])->count() > 0) {
            throw new CommonException('该打印机仍被启用场景使用，请先调整场景');
        }
        return Db::name('erp_printer')->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->delete() > 0;
    }

    public function templates(): array
    {
        $this->ensureDefaults();
        return Db::name('erp_print_template')->where('site_id', $this->site_id)->order('print_type asc,is_default desc,sort asc,id asc')->select()->toArray();
    }

    public function saveTemplate(array $data, int $id = 0): int
    {
        $name = trim((string)($data['template_name'] ?? ''));
        $type = (string)($data['print_type'] ?? 'receipt');
        if ($name === '' || !in_array($type, ['receipt', 'label'], true)) throw new CommonException('模板名称或类型不正确');
        $content = trim((string)($data['content'] ?? ''));
        if ($content === '') throw new CommonException('模板内容不能为空');
        $old = $id > 0 ? Db::name('erp_print_template')->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->find() : null;
        if ($id > 0 && !$old) throw new CommonException('打印模板不存在');
        $values = ['template_name' => $name, 'print_type' => $type, 'layout_mode' => in_array((string)($data['layout_mode'] ?? 'native'), ['native', 'raster'], true) ? (string)$data['layout_mode'] : 'native',
            'paper_width' => max(20, min(110, (int)($data['paper_width'] ?? ($type === 'label' ? 50 : 58)))), 'content' => $content,
            'is_default' => (int)($data['is_default'] ?? 0) === 1 ? 1 : 0, 'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'sort' => (int)($data['sort'] ?? 0), 'update_at' => time()];
        if ($old) { Db::name('erp_print_template')->where('id', $id)->update($values); return $id; }
        return (int)Db::name('erp_print_template')->insertGetId(array_merge($values, [
            'site_id' => $this->site_id,
            'builtin_key' => null,
            'is_builtin' => 0,
            'create_at' => time(),
        ]));
    }

    public function scenes(): array
    {
        $this->ensureDefaults();
        return Db::name('erp_print_scene')->alias('s')->leftJoin('erp_printer p', 'p.id=s.printer_id AND p.site_id=s.site_id')
            ->leftJoin('erp_print_template t', 't.id=s.template_id AND t.site_id=s.site_id')
            ->where('s.site_id', $this->site_id)->field('s.*,p.printer_name,p.driver,t.template_name,t.print_type')->order('s.sort asc,s.id asc')->select()->toArray();
    }

    public function saveScene(array $data, int $id): bool
    {
        $scene = Db::name('erp_print_scene')->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->find();
        if (!$scene) throw new CommonException('打印场景不存在');
        $printerId = (int)($data['printer_id'] ?? 0); $templateId = (int)($data['template_id'] ?? 0);
        $printer = $printerId > 0 ? Db::name('erp_printer')->where([['site_id', '=', $this->site_id], ['id', '=', $printerId], ['status', '=', 1]])->find() : null;
        $template = $templateId > 0 ? Db::name('erp_print_template')->where([['site_id', '=', $this->site_id], ['id', '=', $templateId], ['status', '=', 1]])->find() : null;
        if ($printerId > 0 && !$printer) throw new CommonException('打印机不可用');
        if ($templateId > 0 && !$template) throw new CommonException('打印模板不可用');
        if ($printer && $template && ((string)$printer['print_type'] !== (string)$scene['template_type'] || (string)$template['print_type'] !== (string)$scene['template_type'])) {
            throw new CommonException('打印机、模板与业务场景的打印类型不一致');
        }
        $enabled = (int)($data['enabled'] ?? 0) === 1 ? 1 : 0;
        if ($enabled && ($printerId <= 0 || $templateId <= 0)) throw new CommonException('启用场景前请先选择打印机和模板');
        $granularity = (string)($data['granularity'] ?? $scene['granularity']);
        $allowedGranularity = $this->granularityOptions((string)$scene['biz_type']);
        if (!in_array($granularity, $allowedGranularity, true)) $granularity = (string)$scene['granularity'];
        Db::name('erp_print_scene')->where('id', $id)->update(['printer_id' => $printerId, 'template_id' => $templateId,
            'auto_print' => (int)($data['auto_print'] ?? 0) === 1 ? 1 : 0, 'enabled' => $enabled,
            'copies' => max(1, min(9, (int)($data['copies'] ?? 1))),
            'granularity' => $granularity,
            'update_at' => time()]);
        return true;
    }

    public function jobs(array $where): array
    {
        $query = Db::name('erp_print_job')->where('site_id', $this->site_id);
        if (!empty($where['status'])) $query->where('status', (string)$where['status']);
        if (!empty($where['scene_key'])) $query->where('scene_key', (string)$where['scene_key']);
        if (!empty($where['keyword'])) $query->whereLike('job_no|biz_no|printer_name|template_name|error_message', '%' . trim((string)$where['keyword']) . '%');
        return $query->order('id desc')->paginate(['list_rows' => max(1, min(100, (int)($where['limit'] ?? 20))), 'page' => max(1, (int)($where['page'] ?? 1))])->toArray();
    }

    public function testPrinter(int $printerId): array
    {
        $printer = $this->printer($printerId);
        $type = (string)$printer['print_type'];
        $content = $type === 'label' ? "SIZE 50 mm,30 mm\nGAP 2 mm,0\nCLS\nTEXT 24,24,\"TSS24.BF2\",0,1,1,\"ERP 标签测试\"\nTEXT 24,62,\"TSS24.BF2\",0,1,1,\"{{occurred_at}}\"\nPRINT 1\n"
            : "<center><FH2>ERP 打印测试</FH2></center>\n------------------------------\n打印机：{{printer_name}}\n时间：{{occurred_at}}\n状态：连接正常\n\n";
        return $this->dispatch($printer, ['id' => 0, 'scene_key' => 'test', 'scene_name' => '测试打印', 'copies' => 1],
            ['id' => 0, 'template_name' => '测试模板', 'print_type' => $type, 'content' => $content],
            ['printer_name' => $printer['printer_name'], 'occurred_at' => date('Y-m-d H:i:s'), 'document_no' => 'TEST-' . date('His')], 'test', 0, 'TEST-' . date('YmdHis'));
    }

    public function print(string $sceneKey, string $bizType, int $bizId, array $extra = [], bool $requireAuto = false): array
    {
        $this->ensureDefaults();
        $scene = Db::name('erp_print_scene')->where([['site_id', '=', $this->site_id], ['scene_key', '=', $sceneKey], ['enabled', '=', 1]])->find();
        if (!$scene || ($requireAuto && (int)$scene['auto_print'] !== 1)) return ['skipped' => true, 'message' => '场景未启用自动打印'];
        $printer = $this->printer((int)$scene['printer_id']);
        $template = Db::name('erp_print_template')->where([['site_id', '=', $this->site_id], ['id', '=', (int)$scene['template_id']], ['status', '=', 1]])->find();
        if (!$template) throw new CommonException('打印场景未配置有效模板');
        $type = $bizType ?: (string)$scene['biz_type'];
        $context = array_merge($this->context($type, $bizId), $extra);
        if (!empty($extra['settlement_id'])) $context = $this->withSettlementContext($context, (int)$extra['settlement_id']);

        $contexts = [['context' => $context, 'biz_no' => (string)($context['document_no'] ?? '')]];
        if ((string)$scene['granularity'] === 'device' && $type === 'sale') {
            $contexts = $this->saleDeviceContexts($bizId, $context);
        }
        $jobs = [];
        foreach ($contexts as $item) {
            $jobs[] = $this->dispatch($printer, $scene, $template, (array)$item['context'], $type, $bizId, (string)$item['biz_no']);
        }
        return count($jobs) === 1 ? $jobs[0] : ['batch' => true, 'count' => count($jobs), 'jobs' => $jobs];
    }

    public function retry(int $jobId): array
    {
        $job = Db::name('erp_print_job')->where([['site_id', '=', $this->site_id], ['id', '=', $jobId]])->find();
        if (!$job) throw new CommonException('打印任务不存在');
        if (!in_array((string)$job['status'], ['failed', 'waiting_client'], true)) throw new CommonException('当前任务无需重试');
        $printer = $this->printer((int)$job['printer_id']);
        $driver = (string)$printer['driver'];
        $rendered = [
            'content' => (string)$job['rendered_content'],
            'format' => $driver === 'bluetooth_tspl' ? 'tspl' : ($driver === 'bluetooth_escpos' ? 'escpos_text' : 'cloud_markup'),
            'encoding' => 'utf-8',
        ];
        $printer['copies'] = max(1, (int)$job['copies']);
        Db::name('erp_print_job')->where('id', $jobId)->update([
            'status' => 'sending', 'attempts' => (int)$job['attempts'] + 1, 'provider_job_no' => '',
            'client_payload' => '', 'error_message' => '', 'start_at' => time(), 'finish_at' => 0, 'update_at' => time(),
        ]);
        try {
            $result = (new ErpPrintProviderManager())->send((int)$this->site_id, $printer, $rendered, (string)$job['job_no']);
            Db::name('erp_print_job')->where('id', $jobId)->update([
                'status' => (string)$result['status'], 'provider_job_no' => (string)$result['provider_job_no'],
                'client_payload' => $this->encode($result['client_payload']),
                'finish_at' => (string)$result['status'] === 'success' ? time() : 0, 'update_at' => time(),
            ]);
            return ['job_id' => $jobId, 'job_no' => (string)$job['job_no'], 'status' => (string)$result['status'], 'client_payload' => $result['client_payload']];
        } catch (\Throwable $e) {
            Db::name('erp_print_job')->where('id', $jobId)->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000), 'finish_at' => time(), 'update_at' => time()]);
            throw $e;
        }
    }

    public function clientComplete(int $jobId, bool $success, string $message = ''): bool
    {
        $job = Db::name('erp_print_job')->where([['site_id', '=', $this->site_id], ['id', '=', $jobId]])->find();
        if (!$job || (string)$job['status'] !== 'waiting_client') throw new CommonException('待手机打印任务不存在或状态已变化');
        Db::name('erp_print_job')->where('id', $jobId)->update(['status' => $success ? 'success' : 'failed', 'error_message' => $success ? '' : mb_substr($message ?: '移动端打印失败', 0, 1000), 'finish_at' => time(), 'update_at' => time()]);
        return true;
    }

    /** 自动打印不允许阻断开单或财务主流程。 */
    public function triggerSafely(string $sceneKey, string $bizType, int $bizId, array $extra = []): void
    {
        try { $this->print($sceneKey, $bizType, $bizId, $extra, true); }
        catch (\Throwable $e) { Log::warning('[hsx_erp][print] 自动打印失败 scene=' . $sceneKey . ' biz=' . $bizType . '#' . $bizId . ' error=' . $e->getMessage()); }
    }

    private function dispatch(array $printer, array $scene, array $template, array $context, string $bizType, int $bizId, string $bizNo): array
    {
        $jobNo = 'EP' . date('YmdHis') . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $rendered = (new ErpPrintRenderer())->render((string)$template['content'], $context, (string)$printer['driver'], (string)$template['print_type']);
        $now = time();
        $jobId = (int)Db::name('erp_print_job')->insertGetId(['site_id' => $this->site_id, 'job_no' => $jobNo, 'request_id' => null,
            'scene_key' => (string)$scene['scene_key'], 'scene_name' => (string)$scene['scene_name'], 'biz_type' => $bizType, 'biz_id' => $bizId, 'biz_no' => $bizNo,
            'printer_id' => (int)$printer['id'], 'printer_name' => (string)$printer['printer_name'], 'driver' => (string)$printer['driver'],
            'template_id' => (int)$template['id'], 'template_name' => (string)$template['template_name'], 'print_type' => (string)$template['print_type'],
            'copies' => max(1, (int)($scene['copies'] ?? $printer['copies'] ?? 1)), 'rendered_content' => (string)$rendered['content'], 'client_payload' => '',
            'status' => 'sending', 'attempts' => 1, 'error_message' => '', 'provider_job_no' => '', 'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username, 'create_at' => $now, 'update_at' => $now, 'start_at' => $now, 'finish_at' => 0]);
        try {
            $printer['copies'] = max(1, (int)($scene['copies'] ?? $printer['copies'] ?? 1));
            $result = (new ErpPrintProviderManager())->send((int)$this->site_id, $printer, $rendered, $jobNo);
            Db::name('erp_print_job')->where('id', $jobId)->update(['status' => $result['status'], 'provider_job_no' => (string)$result['provider_job_no'],
                'client_payload' => $this->encode($result['client_payload']), 'finish_at' => $result['status'] === 'success' ? time() : 0, 'update_at' => time()]);
            return ['job_id' => $jobId, 'job_no' => $jobNo, 'status' => $result['status'], 'client_payload' => $result['client_payload']];
        } catch (\Throwable $e) {
            Db::name('erp_print_job')->where('id', $jobId)->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000), 'finish_at' => time(), 'update_at' => time()]);
            throw $e;
        }
    }

    private function context(string $type, int $id): array
    {
        $siteName = (string)(Db::name('site')->where('site_id', $this->site_id)->value('site_name') ?? '');
        $context = ['site_name' => $siteName, 'document_no' => '', 'occurred_at' => date('Y-m-d H:i:s'), 'party_name' => '', 'operator_name' => (string)$this->username,
            'amount' => '0.00', 'asset_no' => '', 'imei' => '', 'model' => '', 'spec' => '', 'warehouse_name' => '', 'items_text' => ''];
        if ($type === 'sale') {
            $row = Db::name('erp_sale_order')->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->find();
            if (!$row) throw new CommonException('销售订单不存在');
            $items = Db::name('erp_sale_item')->where([['site_id', '=', $this->site_id], ['sale_order_id', '=', $id]])->select()->toArray();
            $lines = []; foreach ($items as $item) $lines[] = trim((string)($item['model'] ?? '设备')) . '  ' . trim((string)($item['imei'] ?? '')) . '  ¥' . number_format((float)($item['sale_price'] ?? 0), 2);
            return array_merge($context, ['document_no' => (string)$row['sale_no'], 'occurred_at' => date('Y-m-d H:i:s', (int)($row['occurred_at'] ?? $row['create_at'])),
                'party_name' => (string)$row['party_name'], 'operator_name' => (string)($row['operator_name'] ?? $this->username), 'amount' => number_format((float)$row['total_amount'], 2), 'items_text' => implode("\n", $lines)]);
        }
        if (in_array($type, ['receivable', 'payable'], true)) {
            $row = Db::name('erp_' . $type)->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->find();
            if (!$row) throw new CommonException($type === 'receivable' ? '应收单不存在' : '应付单不存在');
            return array_merge($context, ['document_no' => (string)($row[$type . '_no'] ?? $row['source_no'] ?? ''), 'party_name' => (string)$row['party_name'],
                'operator_name' => (string)($row['business_operator_name'] ?? $this->username),
                'amount' => number_format((float)($row['settled_amount'] ?? $row['amount']), 2),
                'items_text' => trim((string)($row['business_reason'] ?? $row['remark'] ?? '')),
                'occurred_at' => date('Y-m-d H:i:s', (int)($row['update_at'] ?? time()))]);
        }
        if ($type === 'asset') {
            $row = Db::name('erp_asset')->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->find();
            if (!$row) throw new CommonException('库存设备不存在');
            return array_merge($context, ['document_no' => (string)$row['asset_no'], 'asset_no' => (string)$row['asset_no'], 'imei' => (string)$row['imei'],
                'model' => (string)$row['model'], 'spec' => (string)$row['spec'], 'warehouse_name' => (string)$row['warehouse_name'], 'party_name' => (string)($row['party_name'] ?? '')]);
        }
        return $context;
    }

    /** 按设备打印销售凭证时，一台设备对应一个任务，避免任务重试造成整单重复出纸。 */
    private function saleDeviceContexts(int $saleId, array $base): array
    {
        $items = Db::name('erp_sale_item')->alias('i')
            ->leftJoin('erp_asset a', 'a.id=i.asset_id AND a.site_id=i.site_id')
            ->where([['i.site_id', '=', $this->site_id], ['i.sale_order_id', '=', $saleId]])
            ->field('i.id,i.asset_id,i.imei,i.model,i.sale_price,a.asset_no,a.spec,a.warehouse_name')
            ->order('i.id asc')->select()->toArray();
        if (!$items) return [['context' => $base, 'biz_no' => (string)($base['document_no'] ?? '')]];
        $result = [];
        foreach ($items as $item) {
            $assetNo = (string)($item['asset_no'] ?? '');
            $documentNo = (string)($base['document_no'] ?? '');
            $context = array_merge($base, [
                'document_no' => $documentNo . ($assetNo !== '' ? '-' . $assetNo : '-' . (int)$item['id']),
                'asset_no' => $assetNo, 'imei' => (string)$item['imei'], 'model' => (string)$item['model'],
                'spec' => (string)($item['spec'] ?? ''), 'warehouse_name' => (string)($item['warehouse_name'] ?? ''),
                'amount' => number_format((float)$item['sale_price'], 2),
                'items_text' => trim((string)$item['model']) . '  ' . trim((string)$item['imei']) . '  ¥' . number_format((float)$item['sale_price'], 2),
            ]);
            $result[] = ['context' => $context, 'biz_no' => (string)$context['document_no']];
        }
        return $result;
    }

    /** 财务凭证以实际结算单为事实来源，打印账户、财务经办人和真实结算金额。 */
    private function withSettlementContext(array $context, int $settlementId): array
    {
        $row = Db::name('erp_settlement')->where([['site_id', '=', $this->site_id], ['id', '=', $settlementId]])->find();
        if (!$row) return $context;
        $summary = [];
        if (trim((string)$row['capital_account_name']) !== '') $summary[] = '结算账户：' . trim((string)$row['capital_account_name']);
        if (trim((string)$row['remark']) !== '') $summary[] = '说明：' . trim((string)$row['remark']);
        return array_merge($context, [
            'document_no' => (string)$row['settlement_no'], 'party_name' => (string)$row['party_name'],
            'operator_name' => (string)($row['operator_name'] ?: $context['operator_name']),
            'amount' => number_format((float)$row['amount'], 2),
            'occurred_at' => date('Y-m-d H:i:s', (int)($row['confirmed_at'] ?: $row['create_at'])),
            'items_text' => implode("\n", $summary) ?: (string)$context['items_text'],
        ]);
    }

    private function granularityOptions(string $bizType): array
    {
        if ($bizType === 'sale') return ['order', 'device'];
        if (in_array($bizType, ['receivable', 'payable'], true)) return ['settlement'];
        if ($bizType === 'asset') return ['device'];
        return ['order'];
    }

    private function printer(int $id): array
    {
        $row = Db::name('erp_printer')->where([['site_id', '=', $this->site_id], ['id', '=', $id], ['status', '=', 1]])->find();
        if (!$row) throw new CommonException('打印机不存在或已停用');
        return $row;
    }

    private function ensureDefaults(): void
    {
        $now = time();
        $receipt = Db::name('erp_print_template')->where([['site_id', '=', $this->site_id], ['builtin_key', '=', 'default_receipt']])->find();
        if (!$receipt) {
            $id = (int)Db::name('erp_print_template')->insertGetId(['site_id' => $this->site_id, 'builtin_key' => 'default_receipt', 'template_name' => '标准业务小票',
                'print_type' => 'receipt', 'layout_mode' => 'native', 'paper_width' => 58, 'content' => "<center><FH2>{{site_name}}</FH2></center>\n<center>{{document_no}}</center>\n--------------------------------\n往来主体：{{party_name}}\n业务时间：{{occurred_at}}\n经办人：{{operator_name}}\n--------------------------------\n{{items_text}}\n--------------------------------\n合计：¥{{amount}}\n\n", 'is_builtin' => 1, 'is_default' => 1, 'status' => 1, 'sort' => 10, 'create_at' => $now, 'update_at' => $now]);
            $receipt = ['id' => $id];
        }
        $label = Db::name('erp_print_template')->where([['site_id', '=', $this->site_id], ['builtin_key', '=', 'default_asset_label']])->find();
        if (!$label) {
            $id = (int)Db::name('erp_print_template')->insertGetId(['site_id' => $this->site_id, 'builtin_key' => 'default_asset_label', 'template_name' => '标准设备标签',
                'print_type' => 'label', 'layout_mode' => 'native', 'paper_width' => 50, 'content' => "SIZE 50 mm,30 mm\nGAP 2 mm,0\nDIRECTION 1\nCLS\nTEXT 20,18,\"TSS24.BF2\",0,1,1,\"{{model}}\"\nTEXT 20,52,\"TSS24.BF2\",0,1,1,\"IMEI {{imei}}\"\nTEXT 20,84,\"TSS24.BF2\",0,1,1,\"{{asset_no}} {{warehouse_name}}\"\nPRINT 1\n", 'is_builtin' => 1, 'is_default' => 1, 'status' => 1, 'sort' => 20, 'create_at' => $now, 'update_at' => $now]);
            $label = ['id' => $id];
        }
        $sceneSort = 10;
        foreach (ErpPrintDict::scenes() as $key => $def) {
            if (Db::name('erp_print_scene')->where([['site_id', '=', $this->site_id], ['scene_key', '=', $key]])->count() > 0) continue;
            Db::name('erp_print_scene')->insert(['site_id' => $this->site_id, 'scene_key' => $key, 'scene_name' => $def['name'], 'trigger_key' => $def['trigger'],
                'biz_type' => $def['biz_type'], 'template_type' => $def['template_type'], 'description' => $def['description'],
                'printer_id' => 0, 'template_id' => $def['template_type'] === 'label' ? (int)$label['id'] : (int)$receipt['id'], 'auto_print' => 0,
                'enabled' => 0, 'copies' => 1, 'granularity' => $def['granularity'], 'condition_json' => '{}', 'sort' => $sceneSort,
                'create_at' => $now, 'update_at' => $now]);
            $sceneSort += 10;
        }
    }

    private function decode(string $json): array { $data = json_decode($json, true); return is_array($data) ? $data : []; }
    private function encode(mixed $data): string { return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'; }
    private function maskConfig(array $config): array { foreach (['user_key', 'api_key', 'machine_key'] as $key) if (!empty($config[$key])) $config[$key] = '******'; return $config; }
    private function mergeMaskedConfig(array $old, array $new): array { foreach ($new as $key => $value) if ($value !== '******') $old[$key] = $value; return $old; }
}
