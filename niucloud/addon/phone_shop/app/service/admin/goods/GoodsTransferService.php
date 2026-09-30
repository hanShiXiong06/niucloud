<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\admin\goods;

use addon\phone_shop\app\dict\goods\GoodsDict;
use addon\phone_shop\app\job\GoodsExport;
use addon\phone_shop\app\job\GoodsImport;
use addon\phone_shop\app\model\goods\Brand;
use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\goods\GoodsSpec;
use addon\phone_shop\app\model\goods\GoodsTransferTask;
use addon\phone_shop\app\model\goods\Label;
use addon\phone_shop\app\model\goods\Service;
use addon\phone_shop\app\service\admin\MemberLevelNoService;
use addon\phone_shop\app\service\core\goods\CoreDeviceAttributeService;
use app\model\member\MemberLevel;
use app\service\core\upload\CoreUploadService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\file\UploadedFile;

/**
 * 商城商品 Excel 数据交换。
 *
 * 约束：只导入实物商品；一行默认代表一台一机一物商品；分类必须命中已有末级分类；
 * SN/IMEI 是设备业务编码，允许同一台设备在历史退货后再次入库。
 * Excel 内嵌图片始终经过统一上传服务；外链图片可按任务选择直接引用，或下载后转存到站点存储。
 */
class GoodsTransferService extends BaseAdminService
{
    private const DIR = 'upload/phone_shop/goods_transfer/';
    private const MAX_FILE_SIZE = 50 * 1024 * 1024;
    private const MAX_IMAGE_SIZE = 10 * 1024 * 1024;
    private const ERROR_SAMPLE_LIMIT = 100;

    private GoodsTransferTask $taskModel;

    /**
     * 对外模板以“一机一物”为主，字段顺序尽量贴近商家旧表。
     * 导入时仍兼容旧版通用模板表头，避免已下载文件立即失效。
     */
    private const HEADERS = [
        '一级分类', '二级分类', '三级分类', '品牌', '商品名称', '副标题', '商品等级', '内存规格', '颜色', '电池健康度', '保修到期日',
        '同行价', '售价', 'SN', '标签', '轮播图', '视频', '成本价', '配送类型',
        '是否包邮', '属性', '属性模板', '来源'
    ];

    public function __construct()
    {
        parent::__construct();
        $this->taskModel = new GoodsTransferTask();
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $guide = $spreadsheet->getActiveSheet();
        $guide->setTitle('填写说明');
        $guide->fromArray([
            ['二手机商品批量导入说明'],
            ['1. 请在“商品数据”工作表填写；一行代表一台设备，库存固定为 1，无需填写重量和体积。'],
            ['2. 分类、商品名称、售价、轮播图为必填；分类最多支持三级且必须命中本站末级分类；品牌如填写，必须命中本站已有品牌。'],
            ['3. SN 即商品编码/IMEI，允许同一串号在历史退货后再次入库；SN 暂时未取得时可留空。'],
            ['4. 未开启会员自动加价时，同行价作为“同行”等级指定价。开启后：同行价优先作为最低基准价，同行价留空则以“售价”为基准，各身份按本站规则计算；不要把已加价的普通售价当基准重复导入。'],
            ['5. 轮播图支持多个 http/https URL（用英文逗号、| 或换行分隔）。导入时可选择直接引用 URL，或下载后转存；Excel 内嵌图片始终上传到当前存储。'],
            ['6. 配送类型可填 ["express","store"] 或“快递,自提,同城配送”；留空默认全选。是否包邮留空默认“是”。'],
            ['7. 创建任务时可选择是否把轮播图同步生成商品详情；属性、属性模板、质检报告本期不处理。失败表保留原数据，可修正后直接重新导入。'],
            ['8. 颜色、电池健康度和保修到期日用于前端精准筛选；电池填 0-100（可带%），保修必须填绝对日期，例如 2027-06-01。'],
            ['9. 模板不再填写上架状态；导入任务中统一选择“默认上架/默认下架”，避免同一批数据状态混乱。'],
        ]);
        $guide->mergeCells('A1:F1');
        $guide->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $guide->getColumnDimension('A')->setWidth(110);
        $guide->getStyle('A1:A10')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

        $data = $spreadsheet->createSheet();
        $data->setTitle('商品数据');
        $data->fromArray(self::HEADERS, null, 'A1');
        $this->styleHeader($data, count(self::HEADERS));
        $data->freezePane('A2');
        $data->setAutoFilter('A1:' . Coordinate::stringFromColumnIndex(count(self::HEADERS)) . '1');

        $example = $spreadsheet->createSheet();
        $example->setTitle('填写示例');
        $example->fromArray(self::HEADERS, null, 'A1');
        $example->fromArray([
            ['手机', '苹果', '17 Pro Max', '苹果', '苹果 iPhone 17 Pro Max 256G 原色', '成色靓，在保到26年11月6号，电池100%', '靓机', '256G', '原色', '100%', '2026-11-06', 8580, 8880, '018482', '在保,成色好', 'https://example.com/1.jpg,https://example.com/2.jpg', '', 8480, '["express","store"]', '是', '', '', '100005'],
        ], null, 'A2');
        $this->styleHeader($example, count(self::HEADERS));
        $example->freezePane('A2');
        $this->appendReferenceSheets($spreadsheet, (int)$this->site_id);

        $temp = tempnam(sys_get_temp_dir(), 'phone_shop_goods_template_');
        if ($temp === false) throw new CommonException('导入模板临时文件创建失败');
        (new Xlsx($spreadsheet))->save($temp);
        $spreadsheet->disconnectWorksheets();
        // 当前 ThinkPHP 文件响应不支持发送后删除临时文件。
        // 因此先读取并删除文件，再按内容响应下载，避免临时目录持续堆积。
        $content = (string) file_get_contents($temp);
        @unlink($temp);
        return download($content, '商城商品批量导入模板.xlsx', true);
    }

    public function createImportTask($file, string $imageMode = 'direct', int $defaultStatus = 0, bool $imagesToDesc = true): array
    {
        if (!$file || !$file->isValid()) throw new CommonException('Excel 文件上传失败');
        $ext = strtolower((string)$file->getOriginalExtension());
        if (!in_array($ext, ['xlsx', 'xls'], true)) throw new CommonException('只支持 xlsx、xls 文件');
        if ((int)$file->getSize() > self::MAX_FILE_SIZE) throw new CommonException('Excel 文件不能超过 50MB');
        $relativeDir = self::DIR . 'import/' . date('Y/m/d') . '/';
        $absoluteDir = public_path() . $relativeDir;
        $this->ensureDirectory($absoluteDir);
        $stored = uniqid('goods_', true) . '.' . $ext;
        $file->move($absoluteDir, $stored);
        $imageMode = $this->normalizeImageMode($imageMode);
        $task = $this->taskModel->create([
            'site_id' => (int)$this->site_id,
            'task_type' => 'import',
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'original_name' => (string)$file->getOriginalName(),
            'source_file' => $relativeDir . $stored,
            'status' => 'pending',
            'queue_enabled' => env('queue.state', false) ? 1 : 0,
            'request_json' => [
                'image_mode' => $imageMode,
                'default_status' => $defaultStatus === 1 ? 1 : 0,
                'images_to_desc' => $imagesToDesc ? 1 : 0,
            ],
            'result_json' => [],
            'message' => '文件已上传，等待解析',
        ]);
        return $this->dispatch((int)$task->id, (int)$this->site_id, 'import');
    }

    public function createExportTask(array $request): array
    {
        $scope = ($request['scope'] ?? 'filter') === 'selected' ? 'selected' : 'filter';
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($request['ids'] ?? [])))));
        if ($scope === 'selected' && !$ids) throw new CommonException('请先选择需要导出的商品');
        $payload = [
            'scope' => $scope,
            'is_all' => (int)($request['is_all'] ?? 1),
            'ids' => $ids,
            'where' => (array)($request['where'] ?? []),
            // 队列进程没有浏览器请求上下文，创建任务时保存当前站点域名，
            // 让本地存储图片在导出 Excel 中仍然是可再次导入的完整 URL。
            'base_url' => rtrim((string)request()->domain(), '/'),
        ];
        $task = $this->taskModel->create([
            'site_id' => (int)$this->site_id,
            'task_type' => 'export',
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'status' => 'pending',
            'queue_enabled' => env('queue.state', false) ? 1 : 0,
            'request_json' => $payload,
            'result_json' => [],
            'message' => '等待生成导出文件',
        ]);
        return $this->dispatch((int)$task->id, (int)$this->site_id, 'export');
    }

    public function getPage(array $where = []): array
    {
        $where['site_id'] = (int)$this->site_id;
        $result = $this->pageQuery($this->taskModel->withSearch(['site_id', 'task_type', 'status'], $where)->order('id desc'));
        if (!empty($result['data'])) $result['data'] = array_map([$this, 'formatTask'], $result['data']);
        return $result;
    }

    public function getInfo(int $id): array
    {
        $task = $this->taskModel->where([['id', '=', $id], ['site_id', '=', (int)$this->site_id]])->findOrEmpty()->toArray();
        if (!$task) throw new CommonException('任务不存在');
        return $this->formatTask($task);
    }

    public function retry(int $id): array
    {
        $task = $this->getInfo($id);
        if ($task['task_type'] === \addon\phone_shop\app\service\core\goods\CoreGoodsArrivalService::TASK_TYPE) {
            throw new CommonException('请从导入批次的“上新通知”窗口重试通知');
        }
        if ($task['task_type'] === 'import' && (int)$task['success_count'] > 0) {
            throw new CommonException('已有商品导入成功，请下载错误明细修正后新建批次，避免重复创建商品');
        }
        if (in_array($task['status'], ['queued', 'processing'], true)) throw new CommonException('任务正在执行，请勿重复提交');
        if ($task['task_type'] === 'import' && !is_file($this->absolutePath($task['source_file']))) {
            throw new CommonException('原始 Excel 已失效，请重新上传');
        }
        $this->updateTask($id, (int)$this->site_id, [
            'status' => 'pending', 'processed_rows' => 0, 'success_count' => 0, 'updated_count' => 0,
            'skipped_count' => 0, 'error_count' => 0, 'result_file' => '', 'result_json' => [],
            'message' => '等待重新执行', 'error_message' => '', 'start_time' => 0, 'finish_time' => 0,
        ]);
        return $this->dispatch($id, (int)$this->site_id, $task['task_type']);
    }

    public function downloadResult(int $id)
    {
        $task = $this->getInfo($id);
        if (empty($task['result_file'])) throw new CommonException('任务暂无可下载文件');
        $path = $this->absolutePath($task['result_file']);
        if (!is_file($path)) throw new CommonException('文件已失效，请重新执行任务');
        $name = $task['task_type'] === 'export'
            ? '商城商品导出_' . date('Y-m-d_His', (int)$task['finish_time']) . '.xlsx'
            : '商城商品导入错误明细_' . date('Y-m-d_His', (int)$task['finish_time']) . '.xlsx';
        return download($path, $name);
    }

    public function runImport(int $taskId, int $siteId): void
    {
        $task = $this->rawTask($taskId, $siteId, 'import');
        $imageMode = $this->normalizeImageMode((string)($task['request_json']['image_mode'] ?? 'direct'));
        $defaultStatus = (int)($task['request_json']['default_status'] ?? 0) === 1 ? 1 : 0;
        $imagesToDesc = (int)($task['request_json']['images_to_desc'] ?? 1) === 1;
        $path = $this->absolutePath((string)$task['source_file']);
        if ($task['status'] === 'completed') return;
        if (!is_file($path)) $this->failAndThrow($taskId, $siteId, '原始 Excel 文件不存在');
        $this->updateTask($taskId, $siteId, ['status' => 'processing', 'start_time' => time(), 'message' => '正在解析商品数据与图片']);
        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $this->findDataSheet($spreadsheet);
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $headerValues = $sheet->rangeToArray('A1:' . $highestColumn . '1', '', true, true, false)[0] ?? [];
            $headerMap = $this->headerMap($headerValues);
            if (!isset($headerMap['商品名称'])) throw new CommonException('商品数据缺少必填表头：商品名称');
            if (!isset($headerMap['分类路径']) && !isset($headerMap['一级分类'])) {
                throw new CommonException('商品数据缺少分类表头：请提供一级/二级/三级分类，或完整“分类路径”');
            }
            if (!isset($headerMap['售价']) && !isset($headerMap['零售价'])) throw new CommonException('商品数据缺少必填表头：售价');
            if (!isset($headerMap['轮播图']) && !isset($headerMap['商品图片'])) throw new CommonException('商品数据缺少必填表头：轮播图');
            $embeddedImages = $this->extractEmbeddedImages($sheet);
            $rows = [];
            for ($rowNo = 2; $rowNo <= $highestRow; $rowNo++) {
                $values = $sheet->rangeToArray('A' . $rowNo . ':' . $highestColumn . $rowNo, '', true, true, false)[0] ?? [];
                if (!array_filter($values, static fn($v) => trim((string)$v) !== '') && empty($embeddedImages[$rowNo])) continue;
                $row = ['_row' => $rowNo, '_embedded_images' => $embeddedImages[$rowNo] ?? []];
                foreach ($headerMap as $name => $index) $row[$name] = $values[$index] ?? '';
                $rows[] = $this->normalizeImportRow($row);
            }
            $spreadsheet->disconnectWorksheets();
            if (!$rows) throw new CommonException('商品数据工作表没有可导入数据');
            $groups = [];
            foreach ($rows as $index => $row) {
                $key = trim((string)($row['商品导入编号'] ?? ''));
                if ($key === '') $key = '__row_' . ($row['_row'] ?? $index + 2);
                $groups[$key][] = $row;
            }
            $this->updateTask($taskId, $siteId, ['total_rows' => count($rows), 'message' => '正在创建商品']);
            $stats = ['created' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => [], 'goods_ids' => []];
            $processed = 0;
            foreach ($groups as $groupKey => $groupRows) {
                try {
                    $result = $this->importGroup($groupRows, $siteId, $imageMode, $defaultStatus, $imagesToDesc);
                    $stats['created'] += (int)$result['created'];
                    $stats['skipped'] += (int)$result['skipped'];
                    if (!empty($result['goods_id'])) $stats['goods_ids'][] = (int)$result['goods_id'];
                    foreach ($result['warnings'] as $warning) $stats['warnings'][] = $warning;
                } catch (\Throwable $e) {
                    foreach ($groupRows as $row) {
                        $stats['errors'][] = [
                            'row' => (int)$row['_row'],
                            'field' => $this->errorField($e->getMessage()),
                            'sku_no' => (string)($row['SKU编码/IMEI'] ?? ''),
                            'message' => $e->getMessage(),
                            'data' => $row,
                        ];
                    }
                } finally {
                    $this->cleanupEmbeddedImages($groupRows);
                }
                $processed += count($groupRows);
                $this->updateTask($taskId, $siteId, [
                    'processed_rows' => $processed, 'success_count' => $stats['created'], 'skipped_count' => $stats['skipped'],
                    'error_count' => count($stats['errors']), 'message' => sprintf('已处理 %d / %d 行', $processed, count($rows)),
                    'result_json' => ['imported_goods_ids' => $stats['goods_ids']],
                ]);
            }
            $resultFile = '';
            if ($stats['errors']) $resultFile = $this->writeErrorWorkbook($stats['errors'], $siteId, $taskId);
            $status = $stats['errors'] ? ($stats['created'] > 0 || $stats['skipped'] > 0 ? 'partial' : 'failed') : 'completed';
            $this->updateTask($taskId, $siteId, [
                'status' => $status, 'processed_rows' => count($rows), 'success_count' => $stats['created'],
                'skipped_count' => $stats['skipped'], 'error_count' => count($stats['errors']), 'result_file' => $resultFile,
                'result_json' => ['imported_goods_ids' => $stats['goods_ids'], 'error_samples' => array_slice(array_map(static fn($v) => ['row' => $v['row'], 'field' => $v['field'], 'sku_no' => $v['sku_no'], 'message' => $v['message']], $stats['errors']), 0, self::ERROR_SAMPLE_LIMIT), 'warnings' => array_slice($stats['warnings'], 0, self::ERROR_SAMPLE_LIMIT)],
                'message' => sprintf('导入完成：新增 %d 个商品，跳过 %d 行，失败 %d 行', $stats['created'], $stats['skipped'], count($stats['errors'])),
                'finish_time' => time(),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($taskId, $siteId, $e->getMessage());
            throw $e;
        }
    }

    public function runExport(int $taskId, int $siteId): void
    {
        $task = $this->rawTask($taskId, $siteId, 'export');
        if ($task['status'] === 'completed') return;
        $this->updateTask($taskId, $siteId, ['status' => 'processing', 'start_time' => time(), 'message' => '正在查询商品与 SKU']);
        try {
            $request = (array)($task['request_json'] ?? []);
            $goodsIds = $this->resolveExportGoodsIds($siteId, $request);
            if (!$goodsIds) throw new CommonException('当前条件下没有可导出的商品');
            $this->updateTask($taskId, $siteId, ['total_rows' => count($goodsIds), 'message' => '正在生成 Excel 文件']);
            $categoryMap = (new Category())->where([['site_id', '=', $siteId]])->column('category_full_name', 'category_id');
            $brandMap = (new Brand())->where([['site_id', '=', $siteId]])->column('brand_name', 'brand_id');
            $labelMap = (new Label())->where([['site_id', '=', $siteId]])->column('label_name', 'label_id');
            $serviceMap = (new Service())->where([['site_id', '=', $siteId]])->column('service_name', 'service_id');
            $goodsRows = (new Goods())->where([['site_id', '=', $siteId]])->whereIn('goods_id', $goodsIds)->order('goods_id asc')->select()->toArray();
            $skuRows = (new GoodsSku())->where([['site_id', '=', $siteId]])->whereIn('goods_id', $goodsIds)->order('goods_id asc,sku_id asc')->select()->toArray();
            $specRows = (new GoodsSpec())->whereIn('goods_id', $goodsIds)->order('goods_id asc,spec_id asc')->select()->toArray();
            $skuByGoods = [];
            foreach ($skuRows as $sku) $skuByGoods[(int)$sku['goods_id']][] = $sku;
            $specNamesByGoods = [];
            foreach ($specRows as $spec) $specNamesByGoods[(int)$spec['goods_id']][] = (string)$spec['spec_name'];
            $peerLevelKey = $this->peerMemberLevelKey($siteId, false);
            $deviceAttributes = new CoreDeviceAttributeService();
            $baseUrl = rtrim((string)($request['base_url'] ?? ''), '/');
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('商品数据');
            $sheet->fromArray(array_merge(['商品ID', 'SKU ID'], self::HEADERS), null, 'A1');
            $this->styleHeader($sheet, count(self::HEADERS) + 2);
            $excelRow = 2;
            foreach ($goodsRows as $goods) {
                $goodsSkus = $skuByGoods[(int)$goods['goods_id']] ?? [[]];
                foreach ($goodsSkus as $sku) {
                    $categoryIds = $this->arrayValue($goods['goods_category'] ?? []);
                    $leafCategory = $categoryIds ? end($categoryIds) : 0;
                    $categoryPath = (string)($categoryMap[(int)$leafCategory] ?? '');
                    $categoryParts = preg_split('/\s*[\/>＞]\s*/u', $categoryPath, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $export = [
                        '一级分类' => $categoryParts[0] ?? '',
                        '二级分类' => $categoryParts[1] ?? '',
                        '三级分类' => $categoryParts[2] ?? '',
                        '品牌' => (string)($brandMap[(int)($goods['brand_id'] ?? 0)] ?? ''),
                        '商品名称' => $goods['goods_name'] ?? '',
                        '副标题' => $goods['sub_title'] ?? '',
                        '商品等级' => $goods['condition_grade'] ?? '',
                        '内存规格' => $goods['memory_group'] ?? '',
                        '颜色' => $goods['device_color'] ?? '',
                        '电池健康度' => (int)($goods['battery_health'] ?? -1) >= 0 ? (int)$goods['battery_health'] . '%' : '',
                        '保修到期日' => $deviceAttributes->formatWarrantyDate($goods['warranty_expire_time'] ?? 0),
                        '同行价' => $this->memberPriceFromSku($sku['member_price'] ?? '', $peerLevelKey),
                        '售价' => $sku['price'] ?? 0,
                        'SN' => $sku['sku_no'] ?? '',
                        '标签' => $this->namesFromIds($goods['label_ids'] ?? [], $labelMap),
                        '轮播图' => $this->exportImages((string)($goods['goods_image'] ?? ''), $baseUrl),
                        '视频' => $goods['goods_video'] ?? '',
                        '成本价' => $sku['cost_price'] ?? 0,
                        '配送类型' => json_encode($this->arrayValue($goods['delivery_type'] ?? []), JSON_UNESCAPED_UNICODE),
                        '是否包邮' => (int)($goods['is_free_shipping'] ?? 0) === 1 ? '是' : '否',
                        '属性' => '',
                        '属性模板' => '',
                        '来源' => $goods['source'] ?? '',
                    ];
                    $sheet->fromArray([[ $goods['goods_id'], $sku['sku_id'] ?? 0, ...array_map(static fn($header) => $export[$header] ?? '', self::HEADERS) ]], null, 'A' . $excelRow);
                    $excelRow++;
                }
                $this->updateTask($taskId, $siteId, ['processed_rows' => min(count($goodsIds), $excelRow - 2), 'success_count' => $excelRow - 2]);
            }
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:' . Coordinate::stringFromColumnIndex(count(self::HEADERS) + 2) . '1');
            $relative = self::DIR . 'export/' . date('Y/m/d') . '/goods_export_' . $siteId . '_' . $taskId . '.xlsx';
            $absolute = public_path() . $relative;
            $this->ensureDirectory(dirname($absolute));
            (new Xlsx($spreadsheet))->save($absolute);
            $spreadsheet->disconnectWorksheets();
            $this->updateTask($taskId, $siteId, [
                'status' => 'completed', 'processed_rows' => count($goodsIds), 'success_count' => $excelRow - 2,
                'result_file' => $relative, 'result_json' => ['goods_count' => count($goodsRows), 'sku_count' => $excelRow - 2],
                'message' => sprintf('导出完成：%d 个商品，%d 个 SKU', count($goodsRows), $excelRow - 2), 'finish_time' => time(),
            ]);
        } catch (\Throwable $e) {
            $this->markFailed($taskId, $siteId, $e->getMessage());
            throw $e;
        }
    }

    private function importGroup(array $rows, int $siteId, string $imageMode = 'direct', int $defaultStatus = 0, bool $imagesToDesc = true): array
    {
        $first = $rows[0];
        $deviceAttributes = new CoreDeviceAttributeService();
        $validationErrors = [];
        foreach ($rows as $row) {
            foreach ($this->validateImportRow($row) as $error) $validationErrors[] = $error;
        }
        if ($validationErrors) throw new CommonException(implode('；', array_values(array_unique($validationErrors))));
        $firstFacts = [
            '颜色' => $deviceAttributes->normalizeColor($first['颜色'] ?? ''),
            '电池健康度' => $deviceAttributes->normalizeBattery($first['电池健康度'] ?? ''),
            '保修到期日' => $deviceAttributes->normalizeWarrantyExpire($this->normalizeSpreadsheetDate($first['保修到期日'] ?? '')),
        ];
        foreach (array_slice($rows, 1) as $row) {
            $rowFacts = [
                '颜色' => $deviceAttributes->normalizeColor($row['颜色'] ?? ''),
                '电池健康度' => $deviceAttributes->normalizeBattery($row['电池健康度'] ?? ''),
                '保修到期日' => $deviceAttributes->normalizeWarrantyExpire($this->normalizeSpreadsheetDate($row['保修到期日'] ?? '')),
            ];
            foreach ($firstFacts as $field => $value) {
                if ($rowFacts[$field] !== $value) throw new CommonException('【' . $field . '】同一商品的多规格行必须保持一致');
            }
        }
        $name = trim((string)($first['商品名称'] ?? ''));
        if ($name === '') throw new CommonException('【商品名称】不能为空');
        $categoryIds = $this->resolveCategoryPath($siteId, (string)($first['分类路径'] ?? ''));
        $brandId = $this->resolveBrand($siteId, (string)($first['品牌'] ?? ''));
        $warnings = [];
        $labelIds = $this->resolveNamedIds(new Label(), 'label_name', 'label_id', $siteId, (string)($first['商品标签'] ?? ''), $warnings, '标签');
        $serviceIds = $this->resolveNamedIds(new Service(), 'service_name', 'service_id', $siteId, (string)($first['商品服务'] ?? ''), $warnings, '服务');
        $imageInputs = [];
        foreach ($rows as $row) {
            $embedded = (array)($row['_embedded_images'] ?? []);
            foreach ($this->splitMulti((string)($row['商品图片'] ?? '')) as $imageValue) {
                if (preg_match('#^https?://#i', $imageValue)) {
                    $imageInputs[] = ['type' => 'url', 'value' => $imageValue];
                } elseif ($this->isUsableMediaPath($imageValue)) {
                    $imageInputs[] = ['type' => 'existing', 'value' => $imageValue];
                } elseif (!$embedded) {
                    throw new CommonException('【轮播图】“' . $imageValue . '”只是文件名，无法找到图片；请把图片嵌入该 Excel 行，或填写完整 URL');
                }
            }
            foreach ($embedded as $file) $imageInputs[] = ['type' => 'file', 'value' => $file];
        }
        $imageUrls = [];
        foreach ($imageInputs as $image) {
            if ($image['type'] === 'url') {
                // 默认直接引用外链，避免服务器因 DNS、证书、白名单或本地网络限制而下载失败。
                // 商家明确选择“转存到当前存储”时，才下载并调用站点上传服务。
                $url = $imageMode === 'store'
                    ? $this->uploadRemoteImage($image['value'], $siteId)
                    : trim((string)$image['value']);
            } elseif ($image['type'] === 'file') {
                $url = $this->uploadLocalImage($image['value'], $siteId);
            } else {
                $url = (string)$image['value'];
            }
            if ($url !== '' && !in_array($url, $imageUrls, true)) $imageUrls[] = $url;
            if (count($imageUrls) >= 9) break;
        }
        if (!$imageUrls) throw new CommonException('【轮播图】不能为空；请填写可访问的图片 URL，或把图片嵌入当前 Excel 数据行');
        $specsPerRow = array_map(fn($row) => $this->parseSpec((string)($row['规格'] ?? '')), $rows);
        $multi = count($rows) > 1 || !empty($specsPerRow[0]);
        $skuData = [];
        $specGroups = [];
        $totalStock = 0;
        $autoPricing = (int)(new \addon\phone_shop\app\service\core\goods\CoreTierPricingService())->policy($siteId)['enabled'] === 1;
        $memberPrice = $autoPricing ? [] : $this->resolvePeerMemberPrice($siteId, $first['同行价'] ?? '');
        foreach ($rows as $index => $row) {
            $price = $this->decimal($row['零售价'] ?? '', '零售价', true);
            $basePrice = $autoPricing
                ? (trim((string)($row['同行价'] ?? '')) !== '' ? $this->decimal($row['同行价'], '基准同行价', true) : $price)
                : null;
            $stock = 1;
            if ($memberPrice && (float)reset($memberPrice) > $price) {
                throw new CommonException('【同行价】不能大于售价');
            }
            $totalStock += $stock;
            $specItems = [];
            foreach ($specsPerRow[$index] as $specName => $specValue) {
                $specItems[] = ['spec_name' => $specName, 'spec_value_name' => $specValue];
                $specGroups[$specName][$specValue] = true;
            }
            $skuData[] = [
                'sku_spec' => $specItems,
                'spec_name' => implode(' ', array_values($specsPerRow[$index])),
                'sku_image' => $imageUrls[0], 'sku_no' => trim((string)$row['SKU编码/IMEI']),
                'price' => $price, 'pricing_base_price' => $basePrice, 'market_price' => $this->decimal($row['划线价'] ?? 0, '划线价'),
                'cost_price' => $this->decimal($row['成本价'] ?? 0, '成本价'), 'stock' => $stock,
                'weight' => 0, 'volume' => 0, 'member_price' => $memberPrice,
                'is_unique' => 1, 'condition_grade' => trim((string)($row['成色'] ?? '')),
                'is_default' => $index === 0 ? 1 : 0,
            ];
        }
        if ($multi && !$specGroups) throw new CommonException('【规格】同一商品导入编号包含多行时，必须为每行填写规格');
        $specFormat = [];
        foreach ($specGroups as $specName => $values) {
            $specFormat[] = ['spec_name' => $specName, 'values' => array_map(static fn($value) => ['spec_value_name' => $value], array_keys($values))];
        }
        $goodsDesc = $imagesToDesc ? $this->buildImageDetail($imageUrls) : '';
        $video = trim((string)($first['视频'] ?? ''));
        if ($video !== '' && !$this->isUsableMediaPath($video)) {
            $warnings[] = '视频不是可用 URL 或存储路径，已忽略：' . $video;
            $video = '';
        }
        $data = [
            'goods_name' => $name, 'sub_title' => trim((string)($first['副标题'] ?? '')), 'goods_type' => 'real',
            'goods_cover' => $imageUrls[0], 'goods_image' => implode(',', $imageUrls), 'goods_image_width' => 800, 'goods_image_height' => 800,
            'goods_video' => $video, 'goods_category' => $categoryIds, 'goods_desc' => trim((string)($first['商品详情'] ?? '')) ?: $goodsDesc,
            'memory_group' => trim((string)($first['内存'] ?? '')), 'condition_grade' => trim((string)($first['成色'] ?? '')),
            'device_color' => $deviceAttributes->normalizeColor($first['颜色'] ?? ''),
            'battery_health' => $deviceAttributes->normalizeBattery($first['电池健康度'] ?? ''),
            'warranty_expire_time' => $deviceAttributes->normalizeWarrantyExpire($this->normalizeSpreadsheetDate($first['保修到期日'] ?? '')),
            'qc_report' => '',
            'brand_id' => $brandId, 'label_ids' => $labelIds, 'service_ids' => $serviceIds, 'unit' => trim((string)($first['单位'] ?? '')) ?: '件',
            'stock' => $totalStock, 'virtual_sale_num' => 0, 'is_limit' => 0, 'limit_type' => 1, 'max_buy' => 0, 'min_buy' => 0,
            'is_gift' => 0, 'status' => $defaultStatus, 'sort' => (int)($first['排序'] ?? 0),
            'attr_ids' => [], 'attr_format' => '', 'delivery_type' => $this->deliveryTypes((string)($first['配送方式'] ?? '')),
            'is_free_shipping' => $this->boolValue($first['是否包邮'] ?? 1), 'fee_type' => 'fixed', 'delivery_money' => 0,
            'delivery_template_id' => 0, 'supplier_id' => (int)($first['供应商ID'] ?? 0),
            'member_discount' => $memberPrice ? 'fixed_price' : '', 'poster_id' => 0,
            'form_id' => 0, 'diy_detail_id' => 0, 'spec_type' => $multi ? 'multi' : 'single',
            'sku_no' => $skuData[0]['sku_no'], 'price' => $skuData[0]['price'], 'market_price' => $skuData[0]['market_price'],
            'pricing_base_price' => $skuData[0]['pricing_base_price'],
            'cost_price' => $skuData[0]['cost_price'], 'weight' => $skuData[0]['weight'], 'volume' => $skuData[0]['volume'],
            'member_price' => $memberPrice, 'is_unique' => 1,
            'goods_sku_data' => $skuData, 'goods_spec_format' => $specFormat,
            'source' => trim((string)($first['来源'] ?? '')),
            '_skip_sku_unique_check' => true,
        ];
        $goodsId = (new GoodsService())->addForSite($data, $siteId);
        return ['created' => 1, 'goods_id' => (int)$goodsId, 'skipped' => 0, 'warnings' => $warnings];
    }

    private function resolveExportGoodsIds(int $siteId, array $request): array
    {
        $query = (new Goods())->where([['site_id', '=', $siteId]]);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($request['ids'] ?? [])))));
        $isAll = (int)($request['is_all'] ?? 1) === 1;
        if (($request['scope'] ?? 'filter') === 'selected' && !$isAll) {
            return array_map('intval', $query->whereIn('goods_id', $ids)->column('goods_id'));
        }
        $where = (array)($request['where'] ?? []);
        if (($where['goods_name'] ?? '') !== '') $query->whereLike('goods_name', '%' . trim((string)$where['goods_name']) . '%');
        foreach (['goods_type', 'status', 'memory_group', 'condition_grade', 'device_color', 'battery_health', 'warranty_expire_time', 'sale_status', 'source'] as $field) {
            if (isset($where[$field]) && $where[$field] !== '') $query->where($field, '=', $where[$field]);
        }
        if (isset($where['brand_id']) && $where['brand_id'] !== '') $query->where('brand_id', '=', (int)$where['brand_id']);
        $saleState = (string)($where['sale_state'] ?? '');
        if ($saleState === GoodsDict::SALE_STATE_SOLD) {
            $query->where('sale_status', '=', 'sold');
        } elseif ($saleState === GoodsDict::SALE_STATE_LOCKED) {
            $query->where('sale_status', '=', 'locked');
        } elseif ($saleState === GoodsDict::SALE_STATE_SELLABLE) {
            $query->where([['sale_status', '=', 'available'], ['status', '=', 1], ['is_online_sellable', '=', 1], ['stock', '>', 0]]);
        } elseif ($saleState === GoodsDict::SALE_STATE_UNAVAILABLE) {
            $query->where('sale_status', '=', 'available')->where(function ($child) {
                $child->where('status', '<>', 1)->whereOr('is_online_sellable', '<>', 1)->whereOr('stock', '<=', 0);
            });
        }
        if (!empty($where['goods_category'])) {
            $category = is_array($where['goods_category']) ? end($where['goods_category']) : $where['goods_category'];
            $query->whereLike('goods_category', '%"' . (int)$category . '"%');
        }
        foreach (['label_ids', 'service_ids'] as $jsonField) {
            if (empty($where[$jsonField])) continue;
            $values = is_array($where[$jsonField]) ? $where[$jsonField] : [$where[$jsonField]];
            $query->where(function ($child) use ($jsonField, $values) {
                foreach (array_values($values) as $index => $value) {
                    if ($index === 0) {
                        $child->whereLike($jsonField, '%"' . (int)$value . '"%');
                    } else {
                        $child->whereOr($jsonField, 'like', '%"' . (int)$value . '"%');
                    }
                }
            });
        }
        $this->applyRange($query, 'sale_num', $where['start_sale_num'] ?? '', $where['end_sale_num'] ?? '');
        $now = time();
        if (($where['start_stock_age'] ?? '') !== '') $query->where('create_time', '<=', $now - (int)$where['start_stock_age'] * 86400);
        if (($where['end_stock_age'] ?? '') !== '') $query->where('create_time', '>=', $now - ((int)$where['end_stock_age'] + 1) * 86400);
        $skuQuery = (new GoodsSku())->where([['site_id', '=', $siteId], ['is_default', '=', 1]]);
        $hasSkuFilter = false;
        if (($where['start_price'] ?? '') !== '' || ($where['end_price'] ?? '') !== '') {
            $this->applyRange($skuQuery, 'price', $where['start_price'] ?? '', $where['end_price'] ?? '');
            $hasSkuFilter = true;
        }
        $deviceKeywords = $this->parseDeviceKeywords((string)($where['device_keywords'] ?? ''));
        if ($deviceKeywords) {
            $skuQuery->where(function ($child) use ($deviceKeywords) {
                foreach ($deviceKeywords as $index => $keyword) {
                    $method = $index === 0 ? 'where' : 'whereOr';
                    $child->$method(function ($item) use ($keyword) {
                        $item->whereLike('sku_no', '%' . $keyword . '%');
                        if (is_numeric($keyword)) {
                            $item->whereOr('erp_asset_id', '=', (int)$keyword)->whereOr('sku_id', '=', (int)$keyword);
                        }
                    });
                }
            });
            $hasSkuFilter = true;
        }
        if ($hasSkuFilter) {
            $skuGoodsIds = array_values(array_unique(array_map('intval', $skuQuery->column('goods_id'))));
            $query->whereIn('goods_id', $skuGoodsIds ?: [0]);
        }
        if ($isAll && $ids) $query->whereNotIn('goods_id', $ids);
        return array_map('intval', $query->column('goods_id'));
    }

    private function applyRange($query, string $field, $start, $end): void
    {
        if ($start !== '' && $end !== '') {
            $range = [(float)$start, (float)$end];
            sort($range);
            $query->where($field, 'between', $range);
        } elseif ($start !== '') {
            $query->where($field, '>=', (float)$start);
        } elseif ($end !== '') {
            $query->where($field, '<=', (float)$end);
        }
    }

    private function parseDeviceKeywords(string $value): array
    {
        if (trim($value) === '') return [];
        $parts = preg_split('/[\s,，;；]+/u', trim($value));
        return array_values(array_unique(array_slice(array_filter(array_map('trim', $parts)), 0, 100)));
    }

    private function dispatch(int $taskId, int $siteId, string $type): array
    {
        if (!env('queue.state', false)) {
            $message = '任务已创建，但后台队列未启用；开启队列进程后点击重试。';
            $this->updateTask($taskId, $siteId, ['status' => 'pending', 'queue_enabled' => 0, 'message' => $message]);
            return ['task_id' => $taskId, 'async' => false, 'queue_enabled' => false, 'message' => $message];
        }
        $this->updateTask($taskId, $siteId, ['status' => 'queued', 'queue_enabled' => 1, 'message' => '任务已进入后台队列，可关闭窗口继续工作']);
        $pushed = $type === 'import'
            ? GoodsImport::dispatch(['taskId' => $taskId, 'siteId' => $siteId])
            : GoodsExport::dispatch(['taskId' => $taskId, 'siteId' => $siteId]);
        if ($pushed === false) {
            $message = '队列推送失败，请检查 Redis 和队列进程后重试';
            $this->updateTask($taskId, $siteId, ['status' => 'pending', 'message' => $message]);
            return ['task_id' => $taskId, 'async' => false, 'queue_enabled' => true, 'message' => $message];
        }
        return ['task_id' => $taskId, 'async' => true, 'queue_enabled' => true, 'message' => '任务已进入后台队列'];
    }

    /** 把商家旧表和上一版通用模板统一成内部字段。 */
    private function normalizeImportRow(array $row): array
    {
        $value = static function (array $aliases, $default = '') use ($row) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $row) && trim((string)$row[$alias]) !== '') return $row[$alias];
            }
            return $default;
        };
        $firstCategory = trim((string)$value(['一级分类']));
        $secondCategory = trim((string)$value(['二级分类']));
        $thirdCategory = trim((string)$value(['三级分类']));
        $categoryPath = trim((string)$value(['分类路径']));
        if ($categoryPath === '') $categoryPath = implode('/', array_filter([$firstCategory, $secondCategory, $thirdCategory], static fn($item) => $item !== ''));

        $row['分类路径'] = $categoryPath;
        $row['品牌'] = $value(['品牌']);
        $row['规格'] = $value(['规格']);
        $row['SKU编码/IMEI'] = $value(['SN', 'IMEI', 'SKU编码/IMEI', '商品编码']);
        $row['零售价'] = $value(['售价', '零售价']);
        $row['划线价'] = $value(['划线价'], 0);
        $row['成本价'] = $value(['成本价'], 0);
        $row['库存'] = 1;
        $row['单位'] = $value(['单位'], '台');
        $row['内存'] = $value(['内存规格', '内存']);
        $row['成色'] = $value(['商品等级', '成色']);
        $row['颜色'] = $value(['颜色', '设备颜色']);
        $row['电池健康度'] = $value(['电池健康度', '电池效率', '电池']);
        $row['保修到期日'] = $value(['保修到期日', '保修到期', '保修日期']);
        $row['商品标签'] = $value(['标签', '商品标签']);
        $row['商品服务'] = $value(['商品服务', '服务']);
        $row['商品图片'] = $value(['轮播图', '商品图片']);
        $row['商品详情'] = $value(['商品详情']);
        $row['配送方式'] = $value(['配送类型', '配送方式']);
        $row['是否包邮'] = $value(['是否包邮'], '是');
        // 新模板由任务统一决定默认状态；旧模板中的“上架状态”仅为兼容读取，不参与导入。
        $row['排序'] = $value(['排序'], 0);
        $row['供应商ID'] = $value(['供应商ID'], 0);
        $row['同行价'] = $value(['同行价', '指定会员价']);
        $row['视频'] = $value(['视频']);
        $row['来源'] = $value(['来源']);
        return $row;
    }

    private function errorField(string $message): string
    {
        preg_match_all('/【([^】]+)】/u', $message, $matches);
        $fields = array_values(array_unique(array_filter(array_map('trim', $matches[1] ?? []))));
        return $fields ? implode('、', $fields) : '整行数据';
    }

    private function validateImportRow(array $row): array
    {
        $errors = [];
        if (trim((string)($row['商品名称'] ?? '')) === '') $errors[] = '【商品名称】不能为空';
        if (trim((string)($row['分类路径'] ?? '')) === '') $errors[] = '【分类】分类路径不能为空，请填写到可挂载商品的末级分类';
        $price = trim((string)($row['零售价'] ?? ''));
        if ($price === '') {
            $errors[] = '【售价】不能为空';
        } elseif (!is_numeric($price) || (float)$price <= 0) {
            $errors[] = '【售价】必须是大于 0 的数字，当前值：' . $price;
        }
        foreach ([['同行价', '同行价'], ['成本价', '成本价']] as [$key, $label]) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '' && (!is_numeric($value) || (float)$value < 0)) {
                $errors[] = '【' . $label . '】必须是不小于 0 的数字，当前值：' . $value;
            }
        }
        $deviceAttributes = new CoreDeviceAttributeService();
        foreach ([
            static fn() => $deviceAttributes->normalizeColor($row['颜色'] ?? ''),
            static fn() => $deviceAttributes->normalizeBattery($row['电池健康度'] ?? ''),
            fn() => $deviceAttributes->normalizeWarrantyExpire($this->normalizeSpreadsheetDate($row['保修到期日'] ?? '')),
        ] as $validator) {
            try {
                $validator();
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        if (trim((string)($row['商品图片'] ?? '')) === '' && empty($row['_embedded_images'])) {
            $errors[] = '【轮播图】不能为空，可填写 URL 或在当前行嵌入图片';
        }
        try {
            $this->deliveryTypes((string)($row['配送方式'] ?? ''));
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
        $freeShipping = mb_strtolower(trim((string)($row['是否包邮'] ?? '')));
        if ($freeShipping !== '' && !in_array($freeShipping, ['1', '0', '是', '否', '启用', '禁用', 'true', 'false', 'yes', 'no'], true)) {
            $errors[] = '【是否包邮】只能填“是/否”或 1/0，当前值：' . $freeShipping;
        }
        return $errors;
    }

    private function templateHeaderCanonical(string $header): string
    {
        return [
            '一级分类' => '一级分类', '二级分类' => '二级分类', '三级分类' => '三级分类', '品牌' => '品牌',
            '商品等级' => '成色', '内存规格' => '内存', '售价' => '零售价',
            'SN' => 'SKU编码/IMEI', '标签' => '商品标签', '轮播图' => '商品图片',
            '配送类型' => '配送方式',
        ][$header] ?? $header;
    }

    /** Excel 日期单元格可能是序列号，先转成绝对日期再进入统一校验。 */
    private function normalizeSpreadsheetDate($value)
    {
        if ($value === null || trim((string)$value) === '') return '';
        if (is_numeric($value)) {
            $numeric = (float)$value;
            if ($numeric > 0 && $numeric < 100000) {
                try {
                    return SpreadsheetDate::excelToDateTimeObject($numeric)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return $value;
                }
            }
        }
        return $value;
    }

    private function categoryAncestorIds(int $siteId, int $leafId): array
    {
        $ids = [];
        $current = $leafId;
        $guard = 0;
        while ($current > 0 && $guard++ < 20) {
            $row = (new Category())->where([['site_id', '=', $siteId], ['category_id', '=', $current]])->field('category_id,pid')->findOrEmpty()->toArray();
            if (!$row) break;
            array_unshift($ids, (int)$row['category_id']);
            $current = (int)$row['pid'];
        }
        return $ids;
    }

    private function peerMemberLevelKey(int $siteId, bool $required = true): string
    {
        $level = (new MemberLevel())->where([['site_id', '=', $siteId], ['level_name', '=', '同行']])->field('level_id')->findOrEmpty()->toArray();
        if (!$level) {
            if ($required) throw new CommonException('【同行价】本站尚未创建“同行”会员等级，无法生成指定会员价');
            return '';
        }
        $levelId = (int)$level['level_id'];
        $levelNo = MemberLevelNoService::idToNo($siteId, $levelId);
        return 'level_' . ($levelNo > 0 ? $levelNo : $levelId);
    }

    private function resolvePeerMemberPrice(int $siteId, $value): array
    {
        if (trim((string)$value) === '') return [];
        $price = $this->decimal($value, '同行价', true);
        if ($price <= 0) return [];
        return [$this->peerMemberLevelKey($siteId) => number_format($price, 2, '.', '')];
    }

    private function memberPriceFromSku($value, string $key): string
    {
        if ($key === '') return '';
        $prices = is_array($value) ? $value : json_decode((string)$value, true);
        return is_array($prices) && isset($prices[$key]) ? (string)$prices[$key] : '';
    }

    private function buildImageDetail(array $images): string
    {
        $html = '';
        foreach ($images as $image) {
            $src = htmlspecialchars((string)path_to_url($image), ENT_QUOTES, 'UTF-8');
            $html .= '<p><img src="' . $src . '" style="max-width:100%;height:auto;" /></p>';
        }
        return $html !== '' ? $html : '<p>商品详情请以实物为准</p>';
    }

    private function isUsableMediaPath(string $value): bool
    {
        $value = trim($value);
        return preg_match('#^https?://#i', $value) === 1
            || preg_match('#^(?:/)?(?:upload|uploads|attachment|static)/#i', $value) === 1;
    }

    private function resolveCategoryPath(int $siteId, string $path): array
    {
        $names = preg_split('/\s*[\/\\>＞]\s*/u', trim($path), -1, PREG_SPLIT_NO_EMPTY);
        if (!$names) throw new CommonException('【分类】一级分类和二级分类不能为空');
        $ids = [];
        $pid = 0;
        $directMatched = true;
        foreach ($names as $name) {
            $row = (new Category())->where([['site_id', '=', $siteId], ['pid', '=', $pid], ['category_name', '=', trim($name)]])->findOrEmpty()->toArray();
            if (!$row) {
                $directMatched = false;
                break;
            }
            $pid = (int)$row['category_id'];
            $ids[] = $pid;
        }
        // 商家旧表常只保存“品牌/型号”两级，而商城可能还有“手机”一级根分类。
        // 直接路径匹配不到时，按末级名+父级名唯一反查完整祖先链。
        if (!$directMatched && count($names) >= 2) {
            $leafName = trim((string)$names[count($names) - 1]);
            $parentName = trim((string)$names[count($names) - 2]);
            $candidates = (new Category())->alias('leaf')
                ->join((new Category())->getTable() . ' parent', 'parent.category_id = leaf.pid')
                ->where([['leaf.site_id', '=', $siteId], ['leaf.category_name', '=', $leafName], ['parent.category_name', '=', $parentName]])
                ->field('leaf.category_id')->select()->toArray();
            if (count($candidates) === 1) {
                $ids = $this->categoryAncestorIds($siteId, (int)$candidates[0]['category_id']);
                $pid = (int)end($ids);
                $directMatched = true;
            } elseif (count($candidates) > 1) {
                throw new CommonException('【分类】分类组合“' . $path . '”命中多个目录，请改用完整分类路径');
            }
        }
        if (!$directMatched) throw new CommonException('【分类】分类路径不存在：' . $path);
        if ((new Category())->where([['site_id', '=', $siteId], ['pid', '=', $pid]])->count() > 0) {
            throw new CommonException('【分类】“' . $path . '”不是末级分类，请填写可直接挂载商品的最后一级');
        }
        return $ids;
    }

    private function resolveBrand(int $siteId, string $name): int
    {
        $name = trim($name);
        if ($name === '') return 0;
        $id = (new Brand())->where([['site_id', '=', $siteId], ['brand_name', '=', $name]])->value('brand_id');
        if (!$id) throw new CommonException('【品牌】品牌不存在：' . $name);
        return (int)$id;
    }

    private function resolveNamedIds($model, string $nameField, string $idField, int $siteId, string $value, array &$warnings, string $type): array
    {
        $names = $this->splitMulti($value);
        if (!$names) return [];
        $map = $model->where([['site_id', '=', $siteId]])->whereIn($nameField, $names)->column($idField, $nameField);
        $missing = array_values(array_diff($names, array_keys($map)));
        if ($missing) throw new CommonException('【' . $type . '】本站不存在：' . implode('、', $missing) . '；请先在商城建立后再导入');
        return array_values(array_map('intval', $map));
    }

    private function extractEmbeddedImages($sheet): array
    {
        $result = [];
        foreach ($sheet->getDrawingCollection() as $index => $drawing) {
            $row = (int)preg_replace('/\D+/', '', (string)$drawing->getCoordinates());
            if ($row < 2) continue;
            $temp = tempnam(sys_get_temp_dir(), 'phone_shop_excel_image_');
            $extension = 'png';
            if ($drawing instanceof Drawing) {
                $source = $drawing->getPath();
                $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION)) ?: 'png';
                $target = $temp . '.' . preg_replace('/[^a-z0-9]/', '', $extension);
                if (!copy($source, $target)) continue;
                @unlink($temp);
            } elseif ($drawing instanceof MemoryDrawing) {
                $extension = $drawing->getMimeType() === MemoryDrawing::MIMETYPE_JPEG ? 'jpg' : 'png';
                $target = $temp . '.' . $extension;
                $render = $drawing->getRenderingFunction();
                ob_start();
                $render($drawing->getImageResource());
                file_put_contents($target, (string)ob_get_clean());
                @unlink($temp);
            } else {
                @unlink($temp);
                continue;
            }
            $result[$row][] = $target;
        }
        return $result;
    }

    private function cleanupEmbeddedImages(array $rows): void
    {
        foreach ($rows as $row) {
            foreach ((array)($row['_embedded_images'] ?? []) as $path) {
                if (is_string($path) && is_file($path)) @unlink($path);
            }
        }
    }

    private function uploadRemoteImage(string $url, int $siteId): string
    {
        $url = trim($url);
        if ($url === '') return '';
        $parts = parse_url($url);
        if (!$parts || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) {
            throw new CommonException('图片地址格式错误：' . $url);
        }
        $host = (string)$parts['host'];
        $ips = gethostbynamel($host) ?: [];
        if (!$ips) throw new CommonException('图片域名无法解析：' . $host);
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new CommonException('图片地址不允许访问内网：' . $host);
            }
        }
        $context = stream_context_create(['http' => ['timeout' => 12, 'follow_location' => 0, 'user_agent' => 'NiucloudGoodsImporter/1.0'], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $content = @file_get_contents($url, false, $context, 0, self::MAX_IMAGE_SIZE + 1);
        if ($content === false || $content === '') throw new CommonException('图片下载失败：' . $url);
        if (strlen($content) > self::MAX_IMAGE_SIZE) throw new CommonException('单张图片不能超过 10MB');
        $temp = tempnam(sys_get_temp_dir(), 'phone_shop_url_image_');
        file_put_contents($temp, $content);
        $info = @getimagesize($temp);
        if (!$info) { @unlink($temp); throw new CommonException('远程文件不是有效图片：' . $url); }
        $ext = image_type_to_extension((int)$info[2], false) ?: 'jpg';
        $target = $temp . '.' . $ext;
        rename($temp, $target);
        return $this->uploadLocalImage($target, $siteId);
    }

    private function normalizeImageMode(string $mode): string
    {
        return $mode === 'store' ? 'store' : 'direct';
    }

    private function uploadLocalImage(string $path, int $siteId): string
    {
        if (!is_file($path)) throw new CommonException('内嵌图片读取失败');
        if (filesize($path) > self::MAX_IMAGE_SIZE) { @unlink($path); throw new CommonException('单张图片不能超过 10MB'); }
        $info = @getimagesize($path);
        if (!$info) { @unlink($path); throw new CommonException('Excel 中包含无效图片'); }
        $extension = image_type_to_extension((int)$info[2], false) ?: 'jpg';
        $oldFiles = request()->file() ?: [];
        try {
            request()->withFiles(['goods_import_image' => new UploadedFile($path, 'goods_import_' . uniqid() . '.' . $extension, (string)$info['mime'], UPLOAD_ERR_OK, true)]);
            $dir = 'attachment/image/' . $siteId . '/' . date('Ym') . '/' . date('d');
            $result = (new CoreUploadService(true))->image('goods_import_image', $siteId, $dir, 0);
            return (string)($result['url'] ?? '');
        } finally {
            request()->withFiles($oldFiles);
            if (is_file($path)) @unlink($path);
        }
    }

    /**
     * 附带本站真实分类和品牌。错误行可在同一工作簿中查值、修正并重新导入。
     */
    private function appendReferenceSheets(Spreadsheet $spreadsheet, int $siteId): void
    {
        $categories = (new Category())->where([['site_id', '=', $siteId]])
            ->field('category_id,category_name,category_full_name,pid')
            ->order('sort desc,category_id asc')->select()->toArray();
        $categorySheet = $spreadsheet->createSheet();
        $categorySheet->setTitle('可用末级分类');
        $categorySheet->fromArray(['分类ID', '完整分类路径'], null, 'A1');
        $this->styleHeader($categorySheet, 2);
        $parentIds = array_values(array_unique(array_map('intval', array_filter(array_column($categories, 'pid')))));
        $rowNo = 2;
        foreach ($categories as $category) {
            if (in_array((int)$category['category_id'], $parentIds, true)) continue;
            $categorySheet->fromArray([[(int)$category['category_id'], (string)($category['category_full_name'] ?: $category['category_name'])]], null, 'A' . $rowNo++);
        }
        $categorySheet->getColumnDimension('A')->setWidth(14);
        $categorySheet->getColumnDimension('B')->setWidth(60);
        $categorySheet->freezePane('A2');

        $brandSheet = $spreadsheet->createSheet();
        $brandSheet->setTitle('可用品牌');
        $brandSheet->fromArray(['品牌ID', '品牌名称'], null, 'A1');
        $this->styleHeader($brandSheet, 2);
        $brands = (new Brand())->where([['site_id', '=', $siteId]])->field('brand_id,brand_name')->order('brand_name asc')->select()->toArray();
        if ($brands) {
            $brandSheet->fromArray(array_map(static fn($brand) => [(int)$brand['brand_id'], (string)$brand['brand_name']], $brands), null, 'A2');
        }
        $brandSheet->getColumnDimension('A')->setWidth(14);
        $brandSheet->getColumnDimension('B')->setWidth(30);
        $brandSheet->freezePane('A2');
    }

    private function writeErrorWorkbook(array $errors, int $siteId, int $taskId): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('错误明细');
        $headers = array_merge(['Excel行号', '问题字段', '失败原因'], self::HEADERS);
        $sheet->fromArray($headers, null, 'A1');
        $this->styleHeader($sheet, count($headers));
        $rowNo = 2;
        foreach ($errors as $error) {
            $data = $error['data'];
            $values = [(int)$error['row'], (string)($error['field'] ?? '整行数据'), (string)$error['message']];
            foreach (self::HEADERS as $header) {
                $canonical = $this->templateHeaderCanonical($header);
                $values[] = $data[$header] ?? $data[$canonical] ?? '';
            }
            $sheet->fromArray([$values], null, 'A' . $rowNo++);
        }
        $sheet->freezePane('A2');
        $this->appendReferenceSheets($spreadsheet, $siteId);
        $relative = self::DIR . 'error/' . date('Y/m/d') . '/goods_import_error_' . $siteId . '_' . $taskId . '.xlsx';
        $absolute = public_path() . $relative;
        $this->ensureDirectory(dirname($absolute));
        (new Xlsx($spreadsheet))->save($absolute);
        $spreadsheet->disconnectWorksheets();
        return $relative;
    }

    private function parseSpec(string $value): array
    {
        $result = [];
        foreach ($this->splitMulti($value) as $item) {
            $parts = preg_split('/[:：]/u', $item, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') throw new CommonException('规格格式错误：' . $value);
            $result[trim($parts[0])] = trim($parts[1]);
        }
        return $result;
    }

    private function splitMulti(string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[|｜,，\r\n]+/u', trim($value))))));
    }

    private function deliveryTypes(string $value): array
    {
        $allowed = ['express', 'store', 'local_delivery'];
        if (trim($value) === '') return $allowed;
        $decoded = json_decode(trim($value), true);
        $values = is_array($decoded) ? $decoded : $this->splitMulti($value);
        $aliases = [
            'express' => 'express', '快递' => 'express', '物流快递' => 'express',
            'store' => 'store', '自提' => 'store', '到店自提' => 'store',
            'local_delivery' => 'local_delivery', '同城' => 'local_delivery', '同城配送' => 'local_delivery',
        ];
        $result = [];
        $unknown = [];
        foreach ($values as $item) {
            $item = trim((string)$item, " \t\n\r\0\x0B\"");
            if ($item === '') continue;
            if (isset($aliases[$item])) {
                $result[] = $aliases[$item];
            } else {
                $unknown[] = $item;
            }
        }
        if ($unknown) throw new CommonException('【配送类型】存在不支持的值：' . implode('、', $unknown));
        return array_values(array_unique($result));
    }

    private function decimal($value, string $field, bool $required = false): float
    {
        if ($required && trim((string)$value) === '') throw new CommonException('【' . $field . '】不能为空');
        if ($value !== '' && !is_numeric($value)) throw new CommonException('【' . $field . '】必须是数字，当前值：' . (string)$value);
        return round(max(0, (float)$value), 3);
    }

    private function integer($value, string $field, bool $required = false): int
    {
        if ($required && trim((string)$value) === '') throw new CommonException('【' . $field . '】不能为空');
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false && !ctype_digit((string)$value)) throw new CommonException('【' . $field . '】必须是整数，当前值：' . (string)$value);
        return max(0, (int)$value);
    }

    private function statusValue($value): int
    {
        return in_array(mb_strtolower(trim((string)$value)), ['1', '上架', '启用', '是', 'true'], true) ? 1 : 0;
    }

    private function boolValue($value): int
    {
        return in_array(mb_strtolower(trim((string)$value)), ['1', '是', '启用', 'true', 'yes'], true) ? 1 : 0;
    }

    private function styleHeader($sheet, int $count): void
    {
        $last = Coordinate::stringFromColumnIndex($count);
        $sheet->getStyle('A1:' . $last . '1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:' . $last . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
        $sheet->getStyle('A1:' . $last . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (range(1, $count) as $column) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($column <= 5 ? 20 : 16);
    }

    private function headerMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $header = trim((string)$header);
            if ($header !== '') $map[$header] = (int)$index;
        }
        return $map;
    }

    private function findDataSheet(Spreadsheet $spreadsheet)
    {
        $sheet = $spreadsheet->getSheetByName('商品数据');
        if ($sheet) return $sheet;
        foreach ($spreadsheet->getWorksheetIterator() as $candidate) {
            $headers = $candidate->rangeToArray('A1:AZ1', '', true, true, false)[0] ?? [];
            if (in_array('商品名称', $headers, true)
                && (in_array('SN', $headers, true) || in_array('SKU编码/IMEI', $headers, true))) return $candidate;
        }
        throw new CommonException('未找到“商品数据”工作表');
    }

    private function formatTask(array $task): array
    {
        $total = max(0, (int)($task['total_rows'] ?? 0));
        $processed = max(0, (int)($task['processed_rows'] ?? 0));
        $task['progress'] = $total > 0 ? min(100, round($processed * 100 / $total, 1)) : 0;
        $task['can_retry'] = in_array($task['status'], ['pending', 'partial', 'failed'], true);
        if ($task['task_type'] === 'import' && (int)$task['success_count'] > 0) $task['can_retry'] = false;
        if ($task['task_type'] === \addon\phone_shop\app\service\core\goods\CoreGoodsArrivalService::TASK_TYPE) $task['can_retry'] = false;
        $task['can_notify'] = $task['task_type'] === 'import' && in_array($task['status'], ['completed', 'partial'], true) && !empty($task['result_json']['imported_goods_ids']);
        // 列表只展示统计，完整商品及客户快照保留在后端核验。
        unset($task['request_json']['member_ids'], $task['request_json']['goods_ids'], $task['result_json']['imported_goods_ids']);
        $task['can_download'] = !empty($task['result_file']) && is_file($this->absolutePath((string)$task['result_file']));
        return $task;
    }

    private function rawTask(int $id, int $siteId, string $type): array
    {
        $task = $this->taskModel->where([['id', '=', $id], ['site_id', '=', $siteId], ['task_type', '=', $type]])->findOrEmpty()->toArray();
        if (!$task) throw new CommonException('任务不存在');
        if ($task['status'] === 'completed') return $task;
        return $task;
    }

    private function updateTask(int $id, int $siteId, array $data): void
    {
        foreach (['request_json', 'result_json'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) $data[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE);
        }
        $data['update_time'] = time();
        $this->taskModel->where([['id', '=', $id], ['site_id', '=', $siteId]])->update($data);
    }

    private function markFailed(int $id, int $siteId, string $message): void
    {
        $this->updateTask($id, $siteId, ['status' => 'failed', 'message' => '任务执行失败', 'error_message' => mb_substr($message, 0, 1000), 'finish_time' => time()]);
    }

    private function failAndThrow(int $id, int $siteId, string $message): void
    {
        $this->markFailed($id, $siteId, $message);
        throw new CommonException($message);
    }

    private function absolutePath(string $relative): string
    {
        return public_path() . ltrim($relative, '/');
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new CommonException('无法创建商品数据交换目录');
    }

    private function arrayValue($value): array
    {
        if (is_array($value)) return array_values($value);
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function namesFromIds($ids, array $map): string
    {
        $names = [];
        foreach ($this->arrayValue($ids) as $id) if (isset($map[(int)$id])) $names[] = $map[(int)$id];
        return implode('|', $names);
    }

    private function exportSpec(string $format, array $specNames = []): string
    {
        if ($format === '') return '';
        $values = explode(',', $format);
        return implode('|', array_map(static function ($value, $index) use ($specNames) {
            $name = trim((string)($specNames[$index] ?? '')) ?: '规格' . ($index + 1);
            return $name . ':' . trim($value);
        }, $values, array_keys($values)));
    }

    private function exportImages(string $images, string $baseUrl): string
    {
        $result = [];
        foreach (array_filter(array_map('trim', explode(',', $images))) as $image) {
            if (preg_match('#^https?://#i', $image)) {
                $result[] = $image;
            } else {
                $result[] = $baseUrl !== '' ? $baseUrl . '/' . ltrim(path_to_url($image), '/') : path_to_url($image);
            }
        }
        return implode('|', $result);
    }
}
