<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\job\ErpOpeningImport;
use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\ErpMoneyLedger;
use addon\hsx_erp\app\model\ErpOpeningBatch;
use addon\hsx_erp\app\model\ErpOpeningItem;
use addon\hsx_erp\app\model\ErpOperationLog;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpPartyMember;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSiteCatalogProduct;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\model\ErpWarehouseLocation;
use app\dict\member\MemberRegisterChannelDict;
use app\dict\member\MemberRegisterTypeDict;
use app\model\member\Member;
use app\service\core\member\CoreMemberService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use think\facade\Db;
use think\facade\Log;

/**
 * ERP 期初建账。
 *
 * 文件解析只生成草稿明细；必须由用户确认后才写库存和财务账，避免上传即入账。
 */
class ErpOpeningService extends BaseAdminService
{
    private const IMPORT_DIR = 'upload/hsx_erp/opening/';
    private const MAX_FILE_SIZE = 30 * 1024 * 1024;
    private const MAX_ROWS = 10000;
    private const DEFAULT_PASSWORD = '123456';

    private const SHEET_TYPES = [
        '客户资料' => 'member',
        '设备库存' => 'device',
        '应收余额' => 'receivable',
        '应付余额' => 'payable',
        '资金账户' => 'capital',
    ];

    public function upload($file, int $openingDate = 0): array
    {
        if (!$file || !$file->isValid()) throw new CommonException('期初建账文件上传失败，请重新选择文件');
        $extension = strtolower((string)$file->getOriginalExtension());
        if (!in_array($extension, ['xls', 'xlsx'], true)) throw new CommonException('只支持上传 xls、xlsx 格式');
        if ((int)$file->getSize() > self::MAX_FILE_SIZE) throw new CommonException('文件不能超过30MB');

        $relativeDir = self::IMPORT_DIR . date('Y/m/d') . '/';
        $saveDir = public_path() . $relativeDir;
        if (!is_dir($saveDir) && !mkdir($saveDir, 0755, true) && !is_dir($saveDir)) {
            throw new CommonException('无法创建期初建账文件目录');
        }
        $originalName = mb_substr((string)$file->getOriginalName(), 0, 255);
        $storedName = uniqid('opening_', true) . '.' . $extension;
        $file->move($saveDir, $storedName);
        $now = time();
        $openingDate = $openingDate > 0 ? $openingDate : strtotime(date('Y-m-d'));
        $batch = ErpOpeningBatch::create([
            'site_id' => (int)$this->site_id,
            'batch_no' => ErpLedgerService::makeNo('QC'),
            'opening_date' => $openingDate,
            'file_name' => $originalName,
            'file_path' => $relativeDir . $storedName,
            'status' => 'pending',
            'queue_enabled' => env('queue.state', false) ? 1 : 0,
            'summary_json' => [],
            'result_json' => [],
            'message' => '文件已上传，等待校验',
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'create_at' => $now,
            'update_at' => $now,
        ]);
        return $this->dispatch((int)$batch->id, (int)$this->site_id, 'parse');
    }

    public function getPage(array $where = []): array
    {
        $query = ErpOpeningBatch::where([['site_id', '=', (int)$this->site_id]]);
        if (trim((string)($where['status'] ?? '')) !== '') {
            $query->where('status', '=', trim((string)$where['status']));
        }
        $result = $query->order('id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $key = isset($result['data']) ? 'data' : 'list';
        if (!empty($result[$key]) && is_array($result[$key])) {
            $result[$key] = array_map([$this, 'formatBatch'], $result[$key]);
        }
        return $result;
    }

    public function getInfo(int $id): array
    {
        $row = $this->findBatch($id)->toArray();
        $row = $this->formatBatch($row);
        $row['type_summary'] = ErpOpeningItem::where([
            ['site_id', '=', (int)$this->site_id],
            ['batch_id', '=', $id],
        ])->field('item_type,status,count(*) as total')->group('item_type,status')->select()->toArray();
        return $row;
    }

    public function getItems(int $id, array $where = []): array
    {
        $this->findBatch($id);
        $query = ErpOpeningItem::where([
            ['site_id', '=', (int)$this->site_id],
            ['batch_id', '=', $id],
        ]);
        foreach (['status', 'item_type', 'member_action', 'party_action'] as $field) {
            $value = trim((string)($where[$field] ?? ''));
            if ($value !== '') $query->where($field, '=', $value);
        }
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') $query->whereLike('mobile|display_name|sheet_name|error_message', '%' . $keyword . '%');
        $result = $query->order('id asc')->paginate([
            'list_rows' => max(1, min(10000, (int)($where['limit'] ?? 50))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        return $result;
    }

    public function retry(int $id): array
    {
        $batch = $this->findBatch($id);
        if (in_array((string)$batch->status, ['posting', 'completed'], true)) {
            throw new CommonException('该批次已入账或正在入账，不能重新校验');
        }
        return $this->dispatch($id, (int)$this->site_id, 'parse');
    }

    public function confirm(int $id): array
    {
        $batch = $this->findBatch($id);
        if ((string)$batch->status !== 'ready') {
            if ((int)$batch->error_rows > 0) throw new CommonException('仍有校验错误，请下载或查看错误明细，修正表格后重新上传');
            throw new CommonException('当前批次尚未完成校验，不能确认入账');
        }
        return $this->dispatch($id, (int)$this->site_id, 'post');
    }

    public function delete(int $id): bool
    {
        $batch = $this->findBatch($id);
        if (in_array((string)$batch->status, ['posting', 'completed', 'partial'], true)) {
            throw new CommonException('已入账批次不能删除，应保留审计留痕');
        }
        ErpOpeningItem::where([
            ['site_id', '=', (int)$this->site_id],
            ['batch_id', '=', $id],
        ])->delete();
        $path = public_path() . ltrim((string)$batch->file_path, '/');
        $batch->delete();
        if (is_file($path)) @unlink($path);
        return true;
    }

    public function run(int $batchId, int $siteId, string $action): void
    {
        if ($action === 'post') {
            $this->postBatch($batchId, $siteId);
            return;
        }
        $this->parseBatch($batchId, $siteId);
    }

    private function dispatch(int $batchId, int $siteId, string $action): array
    {
        $queueEnabled = (bool)env('queue.state', false);
        $status = $action === 'post' ? 'posting' : 'queued';
        $message = $action === 'post' ? '期初数据正在后台入账' : '期初文件正在后台校验';
        $this->updateBatch($batchId, $siteId, [
            'status' => $status,
            'queue_enabled' => $queueEnabled ? 1 : 0,
            'message' => $message,
            'error_message' => '',
        ]);
        if ($queueEnabled) {
            $pushed = ErpOpeningImport::dispatch([
                'batchId' => $batchId,
                'siteId' => $siteId,
                'action' => $action,
            ]);
            if ($pushed !== false) {
                return ['batch_id' => $batchId, 'async' => true, 'message' => $message . '，可以继续其他操作'];
            }
            $this->updateBatch($batchId, $siteId, ['message' => '队列推送失败，已自动切换为当前请求执行']);
        }
        $this->run($batchId, $siteId, $action);
        return [
            'batch_id' => $batchId,
            'async' => false,
            'message' => $action === 'post' ? '期初建账已完成' : '文件校验已完成',
            'batch' => $this->formatBatch($this->findBatchForSite($batchId, $siteId)->toArray()),
        ];
    }

    private function parseBatch(int $batchId, int $siteId): void
    {
        $batch = $this->findBatchForSite($batchId, $siteId);
        if (in_array((string)$batch->status, ['posting', 'completed'], true)) return;
        $this->updateBatch($batchId, $siteId, [
            'status' => 'parsing',
            'start_at' => time(),
            'finish_at' => 0,
            'total_rows' => 0,
            'valid_rows' => 0,
            'error_rows' => 0,
            'posted_rows' => 0,
            'summary_json' => [],
            'result_json' => [],
            'message' => '正在解析并校验文件',
        ]);
        try {
            $path = public_path() . ltrim((string)$batch->file_path, '/');
            if (!is_file($path)) throw new CommonException('原始文件不存在，请删除任务后重新上传');
            $prepared = $this->readWorkbook($path, (int)$batch->opening_date);
            if (!$prepared) throw new CommonException('未读取到有效数据，请使用系统模板并保留工作表名称');
            if (count($prepared) > self::MAX_ROWS) throw new CommonException('单个期初文件最多支持10000行，请拆分为多个批次');

            $prepared = $this->applyCrossValidation($prepared, $siteId);
            $identitySummary = $this->applyIdentityPreview($prepared, $siteId);
            ErpOpeningItem::where([
                ['site_id', '=', $siteId],
                ['batch_id', '=', $batchId],
            ])->delete();
            $now = time();
            $insertRows = [];
            $valid = 0;
            $errors = 0;
            $typeCounts = [];
            foreach ($prepared as $row) {
                $isError = !empty($row['errors']);
                $isError ? $errors++ : $valid++;
                $type = (string)$row['item_type'];
                $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
                $insertRows[] = [
                    'site_id' => $siteId,
                    'batch_id' => $batchId,
                    'item_type' => $type,
                    'sheet_name' => (string)$row['sheet_name'],
                    'row_no' => (int)$row['row_no'],
                    'row_key' => (string)$row['row_key'],
                    'mobile' => (string)($row['primary_mobile'] ?? ''),
                    'display_name' => (string)($row['display_name'] ?? ''),
                    'raw_json' => json_encode($row['raw'], JSON_UNESCAPED_UNICODE),
                    'normalized_json' => json_encode($row['normalized'], JSON_UNESCAPED_UNICODE),
                    'status' => $isError ? 'error' : 'valid',
                    'error_code' => $isError ? 'VALIDATION_FAILED' : '',
                    'error_message' => $isError ? implode('；', array_values(array_unique($row['errors']))) : '',
                    'member_action' => (string)($row['member_action'] ?? 'none'),
                    'member_id' => (int)($row['member_id'] ?? 0),
                    'party_action' => (string)($row['party_action'] ?? 'none'),
                    'party_id' => (int)($row['party_id'] ?? 0),
                    'create_at' => $now,
                    'update_at' => $now,
                ];
            }
            foreach (array_chunk($insertRows, 300) as $chunk) {
                Db::name('erp_opening_item')->insertAll($chunk);
            }
            $summary = array_merge($identitySummary, [
                'type_counts' => $typeCounts,
                'guidance' => [
                    '上传文件只生成校验草稿，不会直接改库存或财务',
                    '所有错误修正并重新上传后，状态变为“待确认入账”',
                    '确认入账后创建的账号用户名为手机号，初始密码为' . self::DEFAULT_PASSWORD,
                    '用户以后使用相同手机号微信授权登录时，框架现有登录流程会把openid绑定到该账号',
                ],
            ]);
            $this->updateBatch($batchId, $siteId, [
                'status' => $errors > 0 ? 'invalid' : 'ready',
                'total_rows' => count($prepared),
                'valid_rows' => $valid,
                'error_rows' => $errors,
                'conflict_count' => (int)($identitySummary['conflict_count'] ?? 0),
                'summary_json' => $summary,
                'message' => $errors > 0
                    ? "校验完成：{$valid}行可导入，{$errors}行需修正"
                    : "校验通过：共{$valid}行，等待确认入账",
                'finish_at' => time(),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($batchId, $siteId, '期初文件校验失败', $e);
            throw $e;
        }
    }

    private function readWorkbook(string $path, int $openingDate): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $prepared = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $sheetName = trim((string)$sheet->getTitle());
            $itemType = self::SHEET_TYPES[$sheetName] ?? '';
            if ($itemType === '') continue;
            $highestRow = (int)$sheet->getHighestDataRow();
            $highestColumn = (string)$sheet->getHighestDataColumn();
            if ($highestRow < 2) continue;
            $rows = $sheet->rangeToArray('A1:' . $highestColumn . $highestRow, '', true, false, false);
            $headers = array_map([$this, 'normalizeHeader'], (array)($rows[0] ?? []));
            foreach ($rows as $index => $values) {
                if ($index === 0) continue;
                $raw = $this->combineRow($headers, (array)$values);
                if (!$this->hasValues($raw)) continue;
                [$normalized, $errors] = $this->normalizeRow($itemType, $raw, $openingDate);
                $rowNo = $index + 1;
                $prepared[] = [
                    'item_type' => $itemType,
                    'sheet_name' => $sheetName,
                    'row_no' => $rowNo,
                    'raw' => $raw,
                    'normalized' => $normalized,
                    'errors' => $errors,
                    'row_key' => hash('sha256', $itemType . '|' . json_encode($normalized, JSON_UNESCAPED_UNICODE)),
                    'primary_mobile' => $this->primaryMobile($itemType, $normalized),
                    'display_name' => (string)($normalized['name'] ?? $normalized['account_name'] ?? $normalized['model'] ?? ''),
                ];
            }
        }
        $book->disconnectWorksheets();
        return $prepared;
    }

    private function normalizeRow(string $type, array $row, int $openingDate): array
    {
        $errors = [];
        if ($type === 'member') {
            $mobile = $this->normalizeMobile($this->pick($row, ['手机号', '手机号码', 'mobile']));
            $name = $this->text($this->pick($row, ['客户姓名', '姓名', '主体名称', 'name']), 100);
            $role = $this->normalizeRole($this->pick($row, ['客户身份', '身份', 'role']));
            if (!$this->validMobile($mobile)) $errors[] = '手机号必须是11位中国大陆手机号';
            if ($name === '') $errors[] = '客户姓名不能为空';
            return [[
                'mobile' => $mobile, 'name' => $name, 'role' => $role,
                'remark' => $this->text($this->pick($row, ['备注', 'remark']), 255),
            ], $errors];
        }
        if ($type === 'device') {
            $imei = $this->text($this->pick($row, ['IMEI', 'imei', '串号']), 64);
            $sn = $this->text($this->pick($row, ['SN', 'sn', '序列号']), 64);
            $model = $this->text($this->pick($row, ['商品型号', '型号', 'model']), 255);
            $cost = $this->number($this->pick($row, ['期初成本', '成本', '采购成本']));
            $ownership = $this->normalizeOwnership($this->pick($row, ['物权', '物权类型']));
            $ownerMobile = $this->normalizeMobile($this->pick($row, ['物权客户手机号', '代卖客户手机号']));
            $sourceMobile = $this->normalizeMobile($this->pick($row, ['来源主体手机号', '供货人手机号']));
            if ($imei === '' && $sn === '') $errors[] = 'IMEI与SN至少填写一个';
            if ($model === '') $errors[] = '商品型号不能为空';
            if ($cost < 0) $errors[] = '期初成本不能小于0';
            if ($ownership === 'consigned' && !$this->validMobile($ownerMobile)) $errors[] = '代卖设备必须填写有效的物权客户手机号';
            if ($sourceMobile !== '' && !$this->validMobile($sourceMobile)) $errors[] = '来源主体手机号格式错误';
            return [[
                'imei' => $imei,
                'sn' => $sn,
                'model' => $model,
                'spec' => $this->text($this->pick($row, ['规格', 'spec']), 255),
                'category_path' => $this->text($this->pick($row, ['分类路径', '品类']), 255),
                'catalog_product_name' => $this->text($this->pick($row, ['商品目录型号', '目录型号']), 150),
                'warehouse_name' => $this->text($this->pick($row, ['仓库', '仓库名称']), 100),
                'location_name' => $this->text($this->pick($row, ['库位', '库位名称']), 100),
                'ownership_type' => $ownership,
                'owner_mobile' => $ownerMobile,
                'owner_name' => $this->text($this->pick($row, ['物权客户姓名', '代卖客户姓名']), 100),
                'source_mobile' => $sourceMobile,
                'source_name' => $this->text($this->pick($row, ['来源主体姓名', '供货人姓名']), 100),
                'purchase_cost' => round($cost, 2),
                'estimate_sale_price' => round(max(0, $this->number($this->pick($row, ['销售底价', '预估售价']))), 2),
                'retail_price' => round(max(0, $this->number($this->pick($row, ['零售价']))), 2),
                'image_urls' => $this->splitUrls($this->pick($row, ['图片URL', '图片', '入库图片'])),
                'stock_in_at' => $this->parseDate($this->pick($row, ['入库日期', '期初日期']), $openingDate),
                'remark' => $this->text($this->pick($row, ['备注', 'remark']), 255),
            ], $errors];
        }
        if (in_array($type, ['receivable', 'payable'], true)) {
            $mobile = $this->normalizeMobile($this->pick($row, ['手机号', '手机号码', 'mobile']));
            $name = $this->text($this->pick($row, ['客户姓名', '供应商姓名', '往来主体', '主体名称', '姓名']), 100);
            $amount = round($this->number($this->pick($row, ['期初未收余额', '期初未付余额', '期初余额', '金额'])), 2);
            if (!$this->validMobile($mobile)) $errors[] = '手机号必须是11位中国大陆手机号';
            if ($name === '') $errors[] = '往来主体名称不能为空';
            if ($amount <= 0) $errors[] = '期初余额必须大于0';
            return [[
                'mobile' => $mobile,
                'name' => $name,
                'amount' => $amount,
                'source_no' => $this->text($this->pick($row, ['原单号', '来源单号']), 40),
                'occurred_at' => $this->parseDate($this->pick($row, ['发生日期', '期初日期']), $openingDate),
                'remark' => $this->text($this->pick($row, ['业务说明', '备注', 'remark']), 255),
            ], $errors];
        }
        $accountName = $this->text($this->pick($row, ['账户名称', 'account_name']), 100);
        $typeKey = $this->normalizeAccountType($this->pick($row, ['账户类型', '类型']));
        $balance = round($this->number($this->pick($row, ['期初余额', '余额'])), 2);
        if ($accountName === '') $errors[] = '账户名称不能为空';
        return [[
            'account_name' => $accountName,
            'account_type' => $typeKey,
            'bank_name' => $this->text($this->pick($row, ['开户行', '银行']), 100),
            'account_no' => $this->text($this->pick($row, ['账号', '卡号']), 100),
            'holder' => $this->text($this->pick($row, ['户名', '账户户名']), 60),
            'balance' => $balance,
            'is_default' => $this->truthy($this->pick($row, ['设为默认', '是否默认'])) ? 1 : 0,
            'remark' => $this->text($this->pick($row, ['备注', 'remark']), 255),
        ], $errors];
    }

    private function applyCrossValidation(array $rows, int $siteId): array
    {
        $warehouses = ErpWarehouse::where([['site_id', '=', $siteId]])->select()->toArray();
        $warehouseByName = [];
        foreach ($warehouses as $row) $warehouseByName[trim((string)$row['warehouse_name'])] = $row;
        $locations = ErpWarehouseLocation::where([['site_id', '=', $siteId]])->select()->toArray();
        $locationByKey = [];
        foreach ($locations as $row) {
            $locationByKey[(int)$row['warehouse_id'] . '|' . trim((string)$row['location_name'])] = $row;
        }
        $seenIdentifiers = [];
        $seenAccounts = [];
        $rowKeys = array_values(array_unique(array_column($rows, 'row_key')));
        $postedKeys = [];
        foreach (array_chunk($rowKeys, 500) as $chunk) {
            $found = ErpOpeningItem::where([
                ['site_id', '=', $siteId],
                ['row_key', 'in', $chunk],
                ['status', '=', 'posted'],
            ])->column('row_key');
            foreach ($found as $key) $postedKeys[(string)$key] = true;
        }
        foreach ($rows as &$row) {
            $data = &$row['normalized'];
            if (isset($postedKeys[(string)$row['row_key']])) $row['errors'][] = '相同数据已在其他期初批次入账，请勿重复导入';
            if ($row['item_type'] === 'device') {
                $warehouseName = trim((string)$data['warehouse_name']);
                if ($warehouseName === '' || !isset($warehouseByName[$warehouseName])) {
                    $row['errors'][] = '仓库不存在，请先在“仓库库位”中创建并保持名称一致';
                } else {
                    $warehouse = $warehouseByName[$warehouseName];
                    $data['warehouse_id'] = (int)$warehouse['id'];
                    $locationName = trim((string)$data['location_name']);
                    if ($locationName !== '') {
                        $key = (int)$warehouse['id'] . '|' . $locationName;
                        if (!isset($locationByKey[$key])) {
                            $row['errors'][] = '库位不属于所选仓库或不存在';
                        } else {
                            $data['location_id'] = (int)$locationByKey[$key]['id'];
                        }
                    } else {
                        $data['location_id'] = 0;
                    }
                }
                foreach (['imei', 'sn'] as $field) {
                    $value = trim((string)$data[$field]);
                    if ($value === '') continue;
                    $key = $field . '|' . mb_strtolower($value);
                    if (isset($seenIdentifiers[$key])) $row['errors'][] = strtoupper($field) . '在文件中重复';
                    $seenIdentifiers[$key] = true;
                    $exists = ErpAsset::where([
                        ['site_id', '=', $siteId],
                        [$field, '=', $value],
                    ])->count();
                    if ($exists > 0) $row['errors'][] = strtoupper($field) . '已存在于ERP库存，不能重复建账';
                }
                $productName = trim((string)$data['catalog_product_name']);
                if ($productName !== '') {
                    $matches = ErpSiteCatalogProduct::where([
                        ['site_id', '=', $siteId],
                        ['product_name', '=', $productName],
                        ['is_enabled', '=', 1],
                    ])->select()->toArray();
                    if (count($matches) === 1) {
                        $data['catalog_product_id'] = (int)$matches[0]['site_product_id'];
                        if ((string)$data['category_path'] === '') $data['category_path'] = (string)$matches[0]['category_path'];
                    } elseif (!$matches) {
                        $row['errors'][] = '商品目录型号不存在或已停用，请先维护商品目录或清空该字段';
                    } elseif (count($matches) > 1) {
                        $row['errors'][] = '商品目录型号匹配到多条记录，请填写更准确的型号';
                    }
                }
            } elseif ($row['item_type'] === 'capital') {
                $name = trim((string)$data['account_name']);
                if (isset($seenAccounts[$name])) $row['errors'][] = '账户名称在文件中重复';
                $seenAccounts[$name] = true;
                $existing = ErpCapitalAccount::where([
                    ['site_id', '=', $siteId],
                    ['account_name', '=', $name],
                ])->findOrEmpty();
                if (!$existing->isEmpty()) {
                    $hasLedger = ErpMoneyLedger::where([
                        ['site_id', '=', $siteId],
                        ['capital_account_id', '=', (int)$existing->id],
                    ])->count();
                    if (round((float)$existing->balance, 2) !== 0.0 || $hasLedger > 0) {
                        $row['errors'][] = '同名资金账户已有余额或流水，不能再次导入期初余额';
                    } else {
                        $data['existing_account_id'] = (int)$existing->id;
                    }
                }
            }
        }
        unset($row, $data);
        return $rows;
    }

    private function applyIdentityPreview(array &$rows, int $siteId): array
    {
        $identities = $this->collectIdentities($rows);
        $mobiles = array_keys($identities);
        $memberGroups = [];
        if ($mobiles) {
            $members = Member::where([
                ['site_id', '=', $siteId],
                ['mobile', 'in', $mobiles],
                ['is_del', '=', 0],
            ])->field('member_id,mobile,nickname,username,member_no')->select()->toArray();
            foreach ($members as $member) $memberGroups[(string)$member['mobile']][] = $member;
        }
        $partyGroups = [];
        if ($mobiles) {
            $parties = ErpParty::where([
                ['site_id', '=', $siteId],
                ['contact_mobile', 'in', $mobiles],
            ])->field('id,party_name,contact_mobile,status')->select()->toArray();
            foreach ($parties as $party) $partyGroups[(string)$party['contact_mobile']][] = $party;
        }
        $relationGroups = [];
        $memberIds = [];
        foreach ($memberGroups as $group) foreach ($group as $member) $memberIds[] = (int)$member['member_id'];
        if ($memberIds) {
            $relations = ErpPartyMember::where([
                ['site_id', '=', $siteId],
                ['member_id', 'in', array_values(array_unique($memberIds))],
                ['status', '=', 1],
            ])->select()->toArray();
            foreach ($relations as $relation) $relationGroups[(int)$relation['member_id']][] = $relation;
        }
        $accounts = [];
        $conflictMobiles = [];
        $nameWarnings = [];
        foreach ($identities as $mobile => &$identity) {
            $memberGroup = $memberGroups[$mobile] ?? [];
            $partyGroup = $partyGroups[$mobile] ?? [];
            if (count($memberGroup) > 1 || count($partyGroup) > 1) {
                $identity['action'] = 'conflict';
                $identity['reason'] = count($memberGroup) > 1 ? '同一手机号对应多个会员账号' : '同一手机号对应多个往来主体';
                $conflictMobiles[$mobile] = $identity['reason'];
            } else {
                $member = $memberGroup[0] ?? null;
                $party = $partyGroup[0] ?? null;
                $memberRelations = $member ? ($relationGroups[(int)$member['member_id']] ?? []) : [];
                if (count($memberRelations) > 1) {
                    $identity['action'] = 'conflict';
                    $identity['reason'] = '同一会员账号关联了多个有效往来主体';
                    $conflictMobiles[$mobile] = $identity['reason'];
                } elseif ($member && isset($memberRelations[0]) && $party
                    && (int)$memberRelations[0]['party_id'] !== (int)$party['id']) {
                    $identity['action'] = 'conflict';
                    $identity['reason'] = '会员已绑定的主体与手机号匹配主体不一致';
                    $conflictMobiles[$mobile] = $identity['reason'];
                } else {
                    $identity['action'] = $member ? 'reuse' : 'create';
                    $identity['member_id'] = (int)($member['member_id'] ?? 0);
                    $identity['party_id'] = (int)($party['id'] ?? ($memberRelations[0]['party_id'] ?? 0));
                    if ($member) {
                        $oldName = trim((string)($member['nickname'] ?? ''));
                        $identity['existing_name'] = $oldName;
                        if ($oldName !== '' && $identity['name'] !== '' && $oldName !== $identity['name']) {
                            $identity['reason'] = "手机号已存在，保留原昵称“{$oldName}”，不自动覆盖为“{$identity['name']}”";
                            $nameWarnings[] = [
                                'mobile' => $mobile,
                                'existing_name' => $oldName,
                                'import_name' => $identity['name'],
                                'message' => '手机号已存在，保留原昵称，不自动覆盖',
                            ];
                        }
                    }
                }
            }
            $importNames = array_values(array_unique(array_filter(array_map(
                'trim',
                (array)($identity['import_names'] ?? [])
            ))));
            if (count($importNames) > 1) {
                $message = '本次表格同一手机号填写了多个姓名，将以首次出现的“'
                    . (string)$identity['name'] . '”作为ERP主体名称';
                if ((string)($identity['reason'] ?? '') === '') $identity['reason'] = $message;
                $nameWarnings[] = [
                    'mobile' => $mobile,
                    'existing_name' => (string)($identity['existing_name'] ?? ''),
                    'import_name' => implode('、', $importNames),
                    'message' => $message,
                ];
            }
            $accounts[] = $identity;
        }
        unset($identity);
        foreach ($rows as &$row) {
            $primaryMobile = (string)($row['primary_mobile'] ?? '');
            $referencedMobiles = $this->itemIdentityRefs((string)$row['item_type'], (array)$row['normalized']);
            foreach ($referencedMobiles as $referencedMobile) {
                if (isset($conflictMobiles[$referencedMobile])) {
                    $row['errors'][] = "手机号{$referencedMobile}：" . $conflictMobiles[$referencedMobile];
                }
            }
            if ($primaryMobile === '' || !isset($identities[$primaryMobile])) {
                $row['member_action'] = 'none';
                $row['party_action'] = 'none';
                continue;
            }
            $identity = $identities[$primaryMobile];
            $row['member_action'] = (string)$identity['action'];
            $row['party_action'] = (string)$identity['action'];
            $row['member_id'] = (int)($identity['member_id'] ?? 0);
            $row['party_id'] = (int)($identity['party_id'] ?? 0);
        }
        unset($row);
        $referenceCount = array_sum(array_map(static fn(array $item): int => count($item['source_rows'] ?? []), $accounts));
        return [
            'identity_count' => count($accounts),
            'consolidated_row_count' => max(0, $referenceCount - count($accounts)),
            'member_reuse_preview_count' => count(array_filter($accounts, static fn(array $item): bool => ($item['action'] ?? '') === 'reuse')),
            'member_create_preview_count' => count(array_filter($accounts, static fn(array $item): bool => ($item['action'] ?? '') === 'create')),
            'conflict_count' => count($conflictMobiles),
            'accounts' => array_values($accounts),
            'name_warnings' => $nameWarnings,
            'default_password' => self::DEFAULT_PASSWORD,
        ];
    }

    private function postBatch(int $batchId, int $siteId): void
    {
        $batch = $this->findBatchForSite($batchId, $siteId);
        if ((string)$batch->status === 'completed') return;
        if ((int)$batch->error_rows > 0) throw new CommonException('批次存在校验错误，不能入账');
        if (!in_array((string)$batch->status, ['ready', 'posting'], true)) throw new CommonException('批次状态不允许入账');
        $this->updateBatch($batchId, $siteId, [
            'status' => 'posting',
            'message' => '正在写入库存、往来账和资金期初余额',
            'start_at' => time(),
            'finish_at' => 0,
        ]);
        $items = ErpOpeningItem::where([
            ['site_id', '=', $siteId],
            ['batch_id', '=', $batchId],
            ['status', '=', 'valid'],
        ])->order('id asc')->select();
        if ($items->isEmpty()) throw new CommonException('没有可入账的有效数据');
        $rows = $items->toArray();
        $identityDefinitions = $this->collectIdentitiesFromItems($rows);
        $identityCache = [];
        $newMemberEvents = [];
        $report = [
            'new_accounts' => [],
            'matched_accounts' => [],
            'created_parties' => [],
            'matched_parties' => [],
            'account_summary' => [
                'source_reference_count' => array_sum(array_map(
                    static fn(array $definition): int => count($definition['source_rows'] ?? []),
                    $identityDefinitions
                )),
                'unique_mobile_count' => count($identityDefinitions),
                'consolidated_row_count' => max(
                    0,
                    array_sum(array_map(
                        static fn(array $definition): int => count($definition['source_rows'] ?? []),
                        $identityDefinitions
                    )) - count($identityDefinitions)
                ),
                'created_count' => 0,
                'reused_count' => 0,
            ],
            'items' => ['member' => 0, 'device' => 0, 'receivable' => 0, 'payable' => 0, 'capital' => 0],
            'default_password' => self::DEFAULT_PASSWORD,
            'completed_at' => date('Y-m-d H:i:s'),
        ];
        try {
            Db::transaction(function () use (
                $rows, $siteId, $batchId, $batch, $identityDefinitions,
                &$identityCache, &$newMemberEvents, &$report
            ) {
                foreach ($rows as $row) {
                    $data = is_array($row['normalized_json'] ?? null)
                        ? $row['normalized_json']
                        : (json_decode((string)($row['normalized_json'] ?? ''), true) ?: []);
                    $type = (string)$row['item_type'];
                    $identityRefs = $this->itemIdentityRefs($type, $data);
                    foreach ($identityRefs as $mobile) {
                        if (!isset($identityCache[$mobile])) {
                            $identityCache[$mobile] = $this->resolveIdentity(
                                $siteId,
                                $mobile,
                                $identityDefinitions[$mobile] ?? ['name' => $mobile, 'roles' => ['sale_customer']],
                                $report,
                                $newMemberEvents
                            );
                        }
                    }
                    $primaryMobile = $this->primaryMobile($type, $data);
                    $primary = $primaryMobile !== '' ? ($identityCache[$primaryMobile] ?? []) : [];
                    $target = $this->postItem($type, $data, [
                        'site_id' => $siteId,
                        'batch_id' => $batchId,
                        'batch_no' => (string)$batch->batch_no,
                        'opening_date' => (int)$batch->opening_date,
                        'operator_uid' => (int)$batch->operator_uid,
                        'operator_name' => (string)$batch->operator_name,
                        'identity_cache' => $identityCache,
                    ]);
                    ErpOpeningItem::where([
                        ['site_id', '=', $siteId],
                        ['id', '=', (int)$row['id']],
                    ])->update([
                        'status' => 'posted',
                        'member_action' => (string)($primary['member_action'] ?? ($primaryMobile === '' ? 'none' : 'reuse')),
                        'member_id' => (int)($primary['member_id'] ?? 0),
                        'party_action' => (string)($primary['party_action'] ?? ($primaryMobile === '' ? 'none' : 'reuse')),
                        'party_id' => (int)($primary['party_id'] ?? 0),
                        'target_type' => (string)$target['type'],
                        'target_id' => (int)$target['id'],
                        'update_at' => time(),
                    ]);
                    $report['items'][$type] = ($report['items'][$type] ?? 0) + 1;
                }
                ErpOperationLog::create([
                    'site_id' => $siteId,
                    'action' => 'opening_posted',
                    'source_type' => 'opening_batch',
                    'source_id' => $batchId,
                    'source_no' => (string)$batch->batch_no,
                    'operator_uid' => (int)$batch->operator_uid,
                    'operator_name' => (string)$batch->operator_name,
                    'remark' => '期初建账确认入账',
                    'extra_json' => json_encode($report['items'], JSON_UNESCAPED_UNICODE),
                    'create_at' => time(),
                ]);
            });
            foreach ($newMemberEvents as $eventData) {
                try {
                    event('MemberRegister', $eventData);
                } catch (\Throwable $eventError) {
                    Log::warning('期初建账会员注册后置事件失败', [
                        'member_id' => $eventData['member_id'] ?? 0,
                        'message' => $eventError->getMessage(),
                    ]);
                }
            }
            $postedRows = array_sum($report['items']);
            $report['account_summary']['created_count'] = count($report['new_accounts']);
            $report['account_summary']['reused_count'] = count($report['matched_accounts']);
            $this->updateBatch($batchId, $siteId, [
                'status' => 'completed',
                'posted_rows' => $postedRows,
                'member_reused_count' => count($report['matched_accounts']),
                'member_created_count' => count($report['new_accounts']),
                'party_reused_count' => count($report['matched_parties']),
                'party_created_count' => count($report['created_parties']),
                'result_json' => $report,
                'message' => "期初建账完成：成功入账{$postedRows}行，新建"
                    . count($report['new_accounts']) . '个账号，复用'
                    . count($report['matched_accounts']) . '个账号',
                'finish_at' => time(),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($batchId, $siteId, '期初数据入账失败，业务事务已回滚', $e);
            throw $e;
        }
    }

    private function postItem(string $type, array $data, array $context): array
    {
        if ($type === 'member') {
            $identity = $context['identity_cache'][(string)$data['mobile']] ?? [];
            return ['type' => 'party', 'id' => (int)($identity['party_id'] ?? 0)];
        }
        if ($type === 'device') return $this->postDevice($data, $context);
        if ($type === 'receivable') return $this->postReceivable($data, $context);
        if ($type === 'payable') return $this->postPayable($data, $context);
        return $this->postCapital($data, $context);
    }

    private function postDevice(array $data, array $context): array
    {
        $siteId = (int)$context['site_id'];
        $source = $context['identity_cache'][(string)($data['source_mobile'] ?? '')] ?? [];
        $owner = $context['identity_cache'][(string)($data['owner_mobile'] ?? '')] ?? [];
        $now = time();
        $cost = round((float)$data['purchase_cost'], 2);
        $asset = ErpAsset::create([
            'site_id' => $siteId,
            'asset_no' => ErpLedgerService::makeNo('AS'),
            'party_id' => (int)($source['party_id'] ?? 0),
            'party_name' => (string)($source['party_name'] ?? ''),
            'ownership_type' => (string)$data['ownership_type'],
            'owner_party_id' => (string)$data['ownership_type'] === 'consigned' ? (int)($owner['party_id'] ?? 0) : 0,
            'owner_party_name' => (string)$data['ownership_type'] === 'consigned' ? (string)($owner['party_name'] ?? '') : '',
            'ownership_source_type' => 'opening',
            'ownership_source_id' => (int)$context['batch_id'],
            'ownership_source_no' => (string)$context['batch_no'],
            'ownership_changed_at' => (int)$data['stock_in_at'],
            'warehouse_id' => (int)($data['warehouse_id'] ?? 0),
            'warehouse_name' => (string)$data['warehouse_name'],
            'location_id' => (int)($data['location_id'] ?? 0),
            'location_name' => (string)$data['location_name'],
            'imei' => (string)$data['imei'],
            'sn' => (string)$data['sn'],
            'model' => (string)$data['model'],
            'spec' => (string)$data['spec'],
            'spec_json' => '{}',
            'catalog_product_id' => (int)($data['catalog_product_id'] ?? 0),
            'category_name' => $this->lastPathName((string)$data['category_path']),
            'category_path' => (string)$data['category_path'],
            'estimate_sale_price' => (float)$data['estimate_sale_price'],
            'retail_price' => (float)$data['retail_price'],
            'image_urls' => json_encode($data['image_urls'] ?? [], JSON_UNESCAPED_UNICODE),
            'purchase_cost' => $cost,
            'adjust_cost' => 0,
            'refurbish_cost' => 0,
            'total_cost' => $cost,
            'sale_target' => 'unset',
            'listing_status' => 'none',
            'status' => 'in_stock',
            'source_plugin' => 'hsx_erp',
            'source_type' => 'opening',
            'source_id' => (string)$context['batch_id'],
            'remark' => (string)$data['remark'],
            'stock_in_at' => (int)$data['stock_in_at'],
            'create_at' => $now,
            'update_at' => $now,
        ]);
        ErpLedgerService::forSite(
            $siteId,
            (int)$context['operator_uid'],
            (string)$context['operator_name']
        )->asset([
            'request_id' => 'opening:' . $context['batch_id'] . ':asset:' . (int)$asset->id,
            'asset_id' => (int)$asset->id,
            'action' => 'inbound',
            'before_status' => '',
            'after_status' => 'in_stock',
            'before_total_cost' => 0,
            'after_total_cost' => $cost,
            'cost_delta' => $cost,
            'source_type' => 'opening',
            'source_id' => (int)$context['batch_id'],
            'source_no' => (string)$context['batch_no'],
            'occurred_at' => (int)$data['stock_in_at'],
            'remark' => '期初库存导入',
            'extra' => ['opening_batch_id' => (int)$context['batch_id']],
        ]);
        return ['type' => 'asset', 'id' => (int)$asset->id];
    }

    private function postReceivable(array $data, array $context): array
    {
        $identity = $context['identity_cache'][(string)$data['mobile']];
        $amount = round((float)$data['amount'], 2);
        $now = time();
        $row = ErpReceivable::create([
            'site_id' => (int)$context['site_id'],
            'receivable_no' => ErpLedgerService::makeNo('AR'),
            'party_id' => (int)$identity['party_id'],
            'party_name' => (string)$identity['party_name'],
            'source_type' => 'opening',
            'source_id' => (int)$context['batch_id'],
            'source_no' => (string)($data['source_no'] !== '' ? $data['source_no'] : $context['batch_no']),
            'origin_plugin' => 'hsx_erp',
            'origin_plugin_name' => '二手机ERP',
            'origin_type' => 'hsx_erp.opening_receivable',
            'origin_name' => '期初应收',
            'origin_id' => (string)$context['batch_id'],
            'origin_no' => (string)$context['batch_no'],
            'biz_scene' => 'opening',
            'category_key' => 'opening_receivable',
            'category_name' => '期初应收',
            'category_statement_group' => 'opening',
            'category_source_plugin' => 'hsx_erp',
            'category_source_key' => 'opening_receivable',
            'channel_code' => 'opening',
            'channel_name' => '期初建账',
            'business_reason' => (string)($data['remark'] ?: '历史业务形成的期初未收余额'),
            'settlement_mode' => 'credit',
            'settlement_mode_name' => '期初挂账',
            'business_operator_uid' => (int)$context['operator_uid'],
            'business_operator_name' => (string)$context['operator_name'],
            'amount' => $amount,
            'settled_amount' => 0,
            'status' => 'pending',
            'occurred_at' => (int)$data['occurred_at'],
            'remark' => (string)$data['remark'],
            'create_at' => $now,
            'update_at' => $now,
        ]);
        ErpLedgerService::forSite(
            (int)$context['site_id'],
            (int)$context['operator_uid'],
            (string)$context['operator_name']
        )->account([
            'biz_type' => 'opening_receivable',
            'direction' => 'increase',
            'amount' => $amount,
            'balance_after' => $amount,
            'party_id' => (int)$identity['party_id'],
            'party_name' => (string)$identity['party_name'],
            'source_type' => 'receivable',
            'source_id' => (int)$row->id,
            'source_no' => (string)$row->receivable_no,
            'occurred_at' => (int)$data['occurred_at'],
            'remark' => '期初应收余额导入',
        ]);
        return ['type' => 'receivable', 'id' => (int)$row->id];
    }

    private function postPayable(array $data, array $context): array
    {
        $identity = $context['identity_cache'][(string)$data['mobile']];
        $amount = round((float)$data['amount'], 2);
        $now = time();
        $row = ErpPayable::create([
            'site_id' => (int)$context['site_id'],
            'payable_no' => ErpLedgerService::makeNo('AP'),
            'party_id' => (int)$identity['party_id'],
            'party_name' => (string)$identity['party_name'],
            'source_type' => 'opening',
            'source_id' => (int)$context['batch_id'],
            'source_no' => (string)($data['source_no'] !== '' ? $data['source_no'] : $context['batch_no']),
            'origin_plugin' => 'hsx_erp',
            'origin_plugin_name' => '二手机ERP',
            'origin_type' => 'hsx_erp.opening_payable',
            'origin_name' => '期初应付',
            'origin_id' => (string)$context['batch_id'],
            'origin_no' => (string)$context['batch_no'],
            'biz_scene' => 'opening',
            'category_key' => 'opening_payable',
            'category_name' => '期初应付',
            'category_statement_group' => 'opening',
            'category_source_plugin' => 'hsx_erp',
            'category_source_key' => 'opening_payable',
            'channel_code' => 'opening',
            'channel_name' => '期初建账',
            'business_reason' => (string)($data['remark'] ?: '历史业务形成的期初未付余额'),
            'settlement_mode' => 'credit',
            'settlement_mode_name' => '期初挂账',
            'business_operator_uid' => (int)$context['operator_uid'],
            'business_operator_name' => (string)$context['operator_name'],
            'amount' => $amount,
            'settled_amount' => 0,
            'status' => 'pending',
            'occurred_at' => (int)$data['occurred_at'],
            'remark' => (string)$data['remark'],
            'create_at' => $now,
            'update_at' => $now,
        ]);
        ErpLedgerService::forSite(
            (int)$context['site_id'],
            (int)$context['operator_uid'],
            (string)$context['operator_name']
        )->account([
            'biz_type' => 'opening_payable',
            'direction' => 'increase',
            'amount' => $amount,
            'balance_after' => $amount,
            'party_id' => (int)$identity['party_id'],
            'party_name' => (string)$identity['party_name'],
            'source_type' => 'payable',
            'source_id' => (int)$row->id,
            'source_no' => (string)$row->payable_no,
            'occurred_at' => (int)$data['occurred_at'],
            'remark' => '期初应付余额导入',
        ]);
        return ['type' => 'payable', 'id' => (int)$row->id];
    }

    private function postCapital(array $data, array $context): array
    {
        $now = time();
        $accountId = (int)($data['existing_account_id'] ?? 0);
        $values = [
            'account_type' => (string)$data['account_type'],
            'bank_name' => (string)$data['bank_name'],
            'account_no' => (string)$data['account_no'],
            'holder' => (string)$data['holder'],
            'balance' => round((float)$data['balance'], 2),
            'is_default' => (int)$data['is_default'],
            'status' => 1,
            'remark' => (string)$data['remark'],
            'update_at' => $now,
        ];
        if ((int)$data['is_default'] === 1) {
            ErpCapitalAccount::where([['site_id', '=', (int)$context['site_id']]])->update([
                'is_default' => 0,
                'update_at' => $now,
            ]);
        }
        if ($accountId > 0) {
            ErpCapitalAccount::where([
                ['site_id', '=', (int)$context['site_id']],
                ['id', '=', $accountId],
            ])->update($values);
        } else {
            $account = ErpCapitalAccount::create(array_merge($values, [
                'site_id' => (int)$context['site_id'],
                'account_name' => (string)$data['account_name'],
                'sort' => 0,
                'create_at' => $now,
            ]));
            $accountId = (int)$account->id;
        }
        return ['type' => 'capital_account', 'id' => $accountId];
    }

    private function resolveIdentity(
        int $siteId,
        string $mobile,
        array $definition,
        array &$report,
        array &$newMemberEvents
    ): array {
        if (!$this->validMobile($mobile)) throw new CommonException('无效手机号：' . $mobile);
        $members = Member::where([
            ['site_id', '=', $siteId],
            ['mobile', '=', $mobile],
            ['is_del', '=', 0],
        ])->lock(true)->select();
        if ($members->count() > 1) throw new CommonException("手机号{$mobile}对应多个会员账号，请先人工合并");
        $name = $this->text((string)($definition['name'] ?? ''), 100);
        if ($name === '') $name = $mobile;
        $memberCreated = false;
        if ($members->isEmpty()) {
            $member = Member::create([
                'site_id' => $siteId,
                'member_no' => '',
                'username' => $mobile,
                'mobile' => $mobile,
                'password' => create_password(self::DEFAULT_PASSWORD),
                'nickname' => $name,
                'member_label' => [],
                'register_type' => MemberRegisterTypeDict::MANUAL,
                'register_channel' => MemberRegisterChannelDict::MANUAL,
                'login_type' => 'h5',
                'status' => 1,
                'remark' => 'ERP期初建账自动创建',
                'create_time' => time(),
                'update_time' => time(),
            ]);
            CoreMemberService::setMemberNo($siteId, (int)$member->member_id);
            $member->refresh();
            $memberCreated = true;
            $newMemberEvents[] = [
                'site_id' => $siteId,
                'member_id' => (int)$member->member_id,
                'member_no' => (string)$member->member_no,
                'username' => $mobile,
                'mobile' => $mobile,
                'nickname' => $name,
                'register_type' => MemberRegisterTypeDict::MANUAL,
                'register_channel' => MemberRegisterChannelDict::MANUAL,
            ];
        } else {
            $member = $members->first();
        }
        $memberId = (int)$member->member_id;
        $relations = ErpPartyMember::where([
            ['site_id', '=', $siteId],
            ['member_id', '=', $memberId],
            ['status', '=', 1],
        ])->lock(true)->select();
        if ($relations->count() > 1) throw new CommonException("手机号{$mobile}的会员关联了多个有效往来主体，请先人工合并");
        $relation = $relations->isEmpty() ? null : $relations->first();
        $parties = ErpParty::where([
            ['site_id', '=', $siteId],
            ['contact_mobile', '=', $mobile],
        ])->lock(true)->select();
        if ($parties->count() > 1) throw new CommonException("手机号{$mobile}对应多个往来主体，请先人工合并");
        $party = null;
        if ($relation !== null) {
            $party = ErpParty::where([
                ['site_id', '=', $siteId],
                ['id', '=', (int)$relation->party_id],
            ])->lock(true)->findOrEmpty();
            if ($party->isEmpty()) $party = null;
        }
        if ($party === null && !$parties->isEmpty()) $party = $parties->first();
        $partyCreated = false;
        $roles = array_values(array_unique((array)($definition['roles'] ?? ['sale_customer'])));
        if ($party === null) {
            $party = ErpParty::create([
                'site_id' => $siteId,
                'party_no' => ErpLedgerService::makeNo('PT'),
                'party_name' => $name,
                'party_type' => $this->partyTypeFromRoles($roles),
                'role_flags' => implode(',', $roles),
                'group_keys' => '',
                'contact_name' => $name,
                'contact_mobile' => $mobile,
                'm_no' => (string)$member->member_no,
                'remark' => 'ERP期初建账自动创建并绑定会员',
                'status' => 1,
                'create_at' => time(),
                'update_at' => time(),
            ]);
            $partyCreated = true;
        } else {
            $party->save([
                'role_flags' => $this->mergeRoles((string)$party->role_flags, $roles),
                'contact_mobile' => (string)$party->contact_mobile !== '' ? (string)$party->contact_mobile : $mobile,
                'contact_name' => (string)$party->contact_name !== '' ? (string)$party->contact_name : $name,
                'm_no' => (string)$party->m_no !== '' ? (string)$party->m_no : (string)$member->member_no,
                'update_at' => time(),
            ]);
        }
        if ($relation === null) {
            ErpPartyMember::create([
                'site_id' => $siteId,
                'party_id' => (int)$party->id,
                'member_id' => $memberId,
                'relation_role' => 'business',
                'is_finance_contact' => 1,
                'status' => 1,
                'remark' => '期初建账手机号关联',
                'create_at' => time(),
                'update_at' => time(),
            ]);
        } elseif ((int)$relation->party_id !== (int)$party->id) {
            throw new CommonException("手机号{$mobile}的会员与往来主体关系冲突");
        }
        $accountResult = [
            'mobile' => $mobile,
            'name' => $name,
            'account_name' => trim((string)$member->nickname) !== '' ? (string)$member->nickname : $name,
            'import_names' => array_values(array_unique(array_filter((array)($definition['import_names'] ?? [$name])))),
            'username' => $mobile,
            'member_id' => $memberId,
            'member_no' => (string)$member->member_no,
            'party_id' => (int)$party->id,
            'party_name' => (string)$party->party_name,
            'source_rows' => array_values((array)($definition['source_rows'] ?? [])),
        ];
        if ($memberCreated) {
            $report['new_accounts'][] = array_merge($accountResult, ['initial_password' => self::DEFAULT_PASSWORD]);
        } else {
            $report['matched_accounts'][] = $accountResult;
        }
        if ($partyCreated) $report['created_parties'][] = $accountResult;
        else $report['matched_parties'][] = $accountResult;
        return array_merge($accountResult, [
            'member_action' => $memberCreated ? 'create' : 'reuse',
            'party_action' => $partyCreated ? 'create' : 'reuse',
        ]);
    }

    private function collectIdentities(array $rows): array
    {
        $identities = [];
        foreach ($rows as $row) {
            $data = (array)$row['normalized'];
            $type = (string)$row['item_type'];
            foreach ($this->identityDefinitionsForRow($type, $data, (string)$row['sheet_name'] . '第' . (int)$row['row_no'] . '行') as $definition) {
                $mobile = (string)$definition['mobile'];
                if (!$this->validMobile($mobile)) continue;
                if (!isset($identities[$mobile])) {
                    $identities[$mobile] = [
                        'mobile' => $mobile,
                        'name' => (string)$definition['name'],
                        'import_names' => [],
                        'roles' => [],
                        'source_rows' => [],
                    ];
                }
                if ($identities[$mobile]['name'] === '' && $definition['name'] !== '') {
                    $identities[$mobile]['name'] = (string)$definition['name'];
                }
                if (trim((string)$definition['name']) !== '') {
                    $identities[$mobile]['import_names'][] = trim((string)$definition['name']);
                    $identities[$mobile]['import_names'] = array_values(array_unique($identities[$mobile]['import_names']));
                }
                $identities[$mobile]['roles'] = array_values(array_unique(array_merge(
                    $identities[$mobile]['roles'],
                    (array)$definition['roles']
                )));
                $identities[$mobile]['source_rows'][] = (string)$definition['source_row'];
            }
        }
        return $identities;
    }

    private function collectIdentitiesFromItems(array $rows): array
    {
        $normalizedRows = [];
        foreach ($rows as $row) {
            $data = is_array($row['normalized_json'] ?? null)
                ? $row['normalized_json']
                : (json_decode((string)($row['normalized_json'] ?? ''), true) ?: []);
            $normalizedRows[] = [
                'normalized' => $data,
                'item_type' => (string)$row['item_type'],
                'sheet_name' => (string)$row['sheet_name'],
                'row_no' => (int)$row['row_no'],
            ];
        }
        return $this->collectIdentities($normalizedRows);
    }

    private function identityDefinitionsForRow(string $type, array $data, string $sourceRow): array
    {
        if ($type === 'member') {
            return [[
                'mobile' => (string)$data['mobile'],
                'name' => (string)$data['name'],
                'roles' => $this->rolesFromInput((string)$data['role']),
                'source_row' => $sourceRow,
            ]];
        }
        if ($type === 'receivable' || $type === 'payable') {
            return [[
                'mobile' => (string)$data['mobile'],
                'name' => (string)$data['name'],
                'roles' => [$type === 'receivable' ? 'sale_customer' : 'purchase_supplier'],
                'source_row' => $sourceRow,
            ]];
        }
        if ($type !== 'device') return [];
        $definitions = [];
        if ((string)($data['source_mobile'] ?? '') !== '') {
            $definitions[] = [
                'mobile' => (string)$data['source_mobile'],
                'name' => (string)($data['source_name'] ?: $data['source_mobile']),
                'roles' => ['purchase_supplier'],
                'source_row' => $sourceRow . '（来源主体）',
            ];
        }
        if ((string)($data['owner_mobile'] ?? '') !== '') {
            $definitions[] = [
                'mobile' => (string)$data['owner_mobile'],
                'name' => (string)($data['owner_name'] ?: $data['owner_mobile']),
                'roles' => ['sale_customer'],
                'source_row' => $sourceRow . '（物权客户）',
            ];
        }
        return $definitions;
    }

    private function itemIdentityRefs(string $type, array $data): array
    {
        $refs = [];
        foreach ($this->identityDefinitionsForRow($type, $data, '') as $definition) {
            $mobile = (string)$definition['mobile'];
            if ($this->validMobile($mobile)) $refs[] = $mobile;
        }
        return array_values(array_unique($refs));
    }

    private function primaryMobile(string $type, array $data): string
    {
        if (in_array($type, ['member', 'receivable', 'payable'], true)) return (string)($data['mobile'] ?? '');
        if ($type === 'device') {
            return (string)(($data['ownership_type'] ?? '') === 'consigned'
                ? ($data['owner_mobile'] ?? '')
                : ($data['source_mobile'] ?? ''));
        }
        return '';
    }

    private function formatBatch(array $row): array
    {
        $status = (string)($row['status'] ?? 'pending');
        $names = [
            'pending' => '待执行', 'queued' => '排队中', 'parsing' => '校验中',
            'ready' => '待确认入账', 'invalid' => '校验未通过', 'posting' => '入账中',
            'completed' => '已完成', 'partial' => '部分完成', 'failed' => '失败',
        ];
        $types = [
            'pending' => 'info', 'queued' => 'primary', 'parsing' => 'primary',
            'ready' => 'warning', 'invalid' => 'danger', 'posting' => 'primary',
            'completed' => 'success', 'partial' => 'warning', 'failed' => 'danger',
        ];
        $row['status_name'] = $names[$status] ?? $status;
        $row['status_type'] = $types[$status] ?? 'info';
        $total = (int)($row['total_rows'] ?? 0);
        $row['progress'] = $status === 'completed'
            ? 100
            : ($total > 0 ? min(100, (int)floor(((int)($row['valid_rows'] ?? 0) + (int)($row['error_rows'] ?? 0)) * 100 / $total)) : 0);
        foreach (['opening_date', 'create_at', 'start_at', 'finish_at'] as $field) {
            $row[$field . '_text'] = $this->formatTime($row[$field] ?? 0, $field === 'opening_date');
        }
        return $row;
    }

    private function updateBatch(int $batchId, int $siteId, array $data): void
    {
        $data['update_at'] = time();
        ErpOpeningBatch::where([
            ['site_id', '=', $siteId],
            ['id', '=', $batchId],
        ])->update($data);
    }

    private function markFailed(int $batchId, int $siteId, string $title, \Throwable $e): void
    {
        $message = mb_substr($e->getMessage(), 0, 1000);
        $this->updateBatch($batchId, $siteId, [
            'status' => 'failed',
            'message' => $title,
            'error_message' => $message,
            'finish_at' => time(),
        ]);
        Log::error($title, ['batch_id' => $batchId, 'site_id' => $siteId, 'message' => $message]);
    }

    private function findBatch(int $id): ErpOpeningBatch
    {
        return $this->findBatchForSite($id, (int)$this->site_id);
    }

    private function findBatchForSite(int $id, int $siteId): ErpOpeningBatch
    {
        $batch = ErpOpeningBatch::where([
            ['site_id', '=', $siteId],
            ['id', '=', $id],
        ])->findOrEmpty();
        if ($batch->isEmpty()) throw new CommonException('期初建账批次不存在');
        return $batch;
    }

    private function combineRow(array $headers, array $values): array
    {
        $row = [];
        $count = max(count($headers), count($values));
        for ($index = 0; $index < $count; $index++) {
            $header = trim((string)($headers[$index] ?? ''));
            if ($header === '') continue;
            $row[$header] = $values[$index] ?? '';
        }
        return $row;
    }

    private function hasValues(array $row): bool
    {
        foreach ($row as $value) if (trim((string)$value) !== '') return true;
        return false;
    }

    private function normalizeHeader($value): string
    {
        return trim(str_replace(["\n", "\r", '*'], '', (string)$value));
    }

    private function pick(array $row, array $keys)
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader($key);
            if (array_key_exists($normalized, $row)) return $row[$normalized];
        }
        return '';
    }

    private function text($value, int $limit): string
    {
        return mb_substr(trim((string)$value), 0, $limit);
    }

    private function number($value): float
    {
        if (is_numeric($value)) return (float)$value;
        $value = str_replace([',', '¥', '￥', '元', ' '], '', trim((string)$value));
        return is_numeric($value) ? (float)$value : 0.0;
    }

    private function normalizeMobile($value): string
    {
        $mobile = preg_replace('/\D+/', '', (string)$value) ?: '';
        if (str_starts_with($mobile, '86') && strlen($mobile) === 13) $mobile = substr($mobile, 2);
        return $mobile;
    }

    private function validMobile(string $mobile): bool
    {
        return (bool)preg_match('/^1[3-9]\d{9}$/', $mobile);
    }

    private function parseDate($value, int $fallback): int
    {
        if ($value === '' || $value === null) return $fallback;
        if (is_numeric($value)) {
            $number = (float)$value;
            if ($number > 200000000) return (int)$number;
            if ($number > 1) {
                try {
                    return ExcelDate::excelToTimestamp($number);
                } catch (\Throwable $e) {
                    return $fallback;
                }
            }
        }
        $timestamp = strtotime(trim((string)$value));
        return $timestamp !== false ? $timestamp : $fallback;
    }

    private function normalizeRole($value): string
    {
        $value = trim((string)$value);
        if (in_array($value, ['供应商', '供货商', 'supplier'], true)) return 'supplier';
        if (in_array($value, ['客户兼供应商', '客户/供应商', 'both'], true)) return 'both';
        return 'customer';
    }

    private function rolesFromInput(string $role): array
    {
        if ($role === 'supplier') return ['purchase_supplier'];
        if ($role === 'both') return ['sale_customer', 'purchase_supplier'];
        return ['sale_customer'];
    }

    private function normalizeOwnership($value): string
    {
        $value = trim((string)$value);
        return in_array($value, ['代卖', '寄售', 'consigned'], true) ? 'consigned' : 'owned';
    }

    private function normalizeAccountType($value): string
    {
        $value = trim((string)$value);
        $map = ['现金' => 'cash', '微信' => 'wechat', '支付宝' => 'alipay', '银行卡' => 'bank', '银行' => 'bank', '其他' => 'other'];
        $key = $map[$value] ?? strtolower($value);
        return in_array($key, ['cash', 'wechat', 'alipay', 'bank', 'other'], true) ? $key : 'other';
    }

    private function truthy($value): bool
    {
        return in_array(mb_strtolower(trim((string)$value)), ['1', '是', 'yes', 'true', 'y'], true);
    }

    private function splitUrls($value): array
    {
        $parts = preg_split('/[\s,，;；\n\r]+/u', trim((string)$value)) ?: [];
        return array_values(array_unique(array_filter(array_map('trim', $parts))));
    }

    private function partyTypeFromRoles(array $roles): string
    {
        return in_array('purchase_supplier', $roles, true) && !in_array('sale_customer', $roles, true)
            ? 'supplier'
            : 'customer';
    }

    private function mergeRoles(string $current, array $roles): string
    {
        $existing = array_values(array_filter(array_map('trim', explode(',', $current))));
        return mb_substr(implode(',', array_values(array_unique(array_merge($existing, $roles)))), 0, 255);
    }

    private function lastPathName(string $path): string
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('#[/\\\\>]#', $path) ?: [])));
        return mb_substr((string)($parts ? end($parts) : ''), 0, 100);
    }

    private function formatTime($value, bool $dateOnly = false): string
    {
        if ($value === '' || $value === null || $value === 0 || $value === '0') return '-';
        $timestamp = is_numeric($value) ? (int)$value : (strtotime((string)$value) ?: 0);
        return $timestamp > 0 ? date($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s', $timestamp) : '-';
    }
}
