<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;
use addon\hsx_lead\app\service\cooperation\{BusinessService,ConfigService};

/** A curated demand directory uses framework config, not another device-catalog table. */
final class CatalogService
{
    public const KEY = 'HSX_LEAD_DEMAND_CATALOG';
    /** Deployment guard for the explicit one-off catalog reset command. */
    public const BACKEND_ONLY = true;
    public function __construct(private Context $context)
    {
    }

    public function get(array $params = []): array
    {
        $this->context->manager();
        if (!empty($params['business_type'])) {
            $business = (new BusinessService($this->context))->definition(Data::text($params, 'business_type', 16, true), false);
            return ['items' => (new BusinessService($this->context))->options($business), 'business_type' => $business['key'], 'source' => 'business_config', 'supports_preview' => true];
        }
        return $this->directory() + ['supports_preview' => true];
    }

    /** Paginated administrator view; do not send 5000 rows to a table at once. */
    public function items(array $params = []): array
    {
        $this->context->permission('hsx_lead_catalog_items');
        $businessKey = $this->businessKey($params);
        $business = (new BusinessService($this->context))->definition($businessKey, false);
        $items = (new BusinessService($this->context))->options($business);
        $revision = $this->revision($items);
        $keyword = mb_strtolower(Data::text($params, 'keyword', 200));
        if ($keyword !== '') $items = array_values(array_filter($items, static function (array $row) use ($keyword): bool {
            return mb_strpos(mb_strtolower(implode(' ', [$row['id'], $row['title'], $row['brand'], $row['category_name'], ...($row['path'] ?? []), ...($row['aliases'] ?? [])])), $keyword) !== false;
        }));
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
        return ['data' => array_slice($items, ($page - 1) * $limit, $limit), 'total' => count($items), 'per_page' => $limit, 'current_page' => $page,
            'revision' => $revision, 'business_type' => $businessKey, 'business_name' => $business['name'], 'dimension' => $business['dimension'],
            'supports_preview' => true, 'supports_item_management' => true];
    }

    public function add(array $input): array
    {
        return $this->mutate('', $input, 'created');
    }
    public function update(string $id, array $input): array
    {
        return $this->mutate($id, $input, 'updated');
    }
    public function remove(string $id, array $input): array
    {
        return $this->mutate($id, $input, 'deleted');
    }

    private function mutate(string $id, array $input, string $action): array
    {
        $this->context->permission(['created' => 'hsx_lead_catalog_add', 'updated' => 'hsx_lead_catalog_edit', 'deleted' => 'hsx_lead_catalog_delete'][$action]);
        $businessKey = $this->businessKey($input);
        $revision = $this->expectedRevision($input, true);
        if ($id !== '' && !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $id)) throw new CommonException('目录ID格式错误');
        $result = Db::transaction(function () use ($id, $input, $action, $businessKey, $revision): array {
            Db::name('site')->where('site_id', $this->context->site)->lock(true)->field('site_id')->find();
            $service = new BusinessService($this->context);
            $business = $service->definition($businessKey, false);
            $items = $service->options($business);
            if (!hash_equals($this->revision($items), $revision)) throw new CommonException('目录已被其他操作修改，请刷新目录后重新核对；本次未保存');
            $index = null;
            $before = null;
            foreach ($items as $position => $row) if ($row['id'] === $id) {
                $index = $position;
                $before = $this->auditItem($row);
                break;
            }
            if ($action !== 'created' && $index === null) throw new CommonException('目录项不存在或已被移除，请刷新目录');
            $after = null;
            if ($action === 'deleted') array_splice($items, $index, 1);
            else {
                $candidate = $input['item'] ?? null;
                if (!is_array($candidate)) throw new CommonException('请填写完整目录信息');
                if ($action === 'updated') {
                    if (isset($candidate['id']) && (string) $candidate['id'] !== $id) throw new CommonException('已保存目录ID不能修改，请只调整名称和分类');
                    $candidate['id'] = $id;
                } elseif (!isset($candidate['id']) || $candidate['id'] === '') $candidate['id'] = 'cat_'.bin2hex(random_bytes(12));
                $after = $this->item($candidate);
                if ($action === 'created') {
                    if (count($items) >= 5000) throw new CommonException('当前业务目录最多5000项，请先移除不再使用的选项');
                    foreach ($items as $row) if ($row['id'] === $after['id']) throw new CommonException('目录ID已存在，请核对后再新增');
                    $items[] = $after;
                } else $items[$index] = $after;
            }
            $candidate = $business;
            $candidate['options'] = $items;
            BusinessService::validate([$candidate]);
            $this->persistItems($items, $businessKey, $action, ['item_id' => $after['id'] ?? $id, 'before' => $before, 'after' => $after]);
            return ['item' => $after, 'deleted' => $action === 'deleted', 'business_type' => $businessKey, 'count' => count($items)];
        });
        CatalogConfigStorage::evict($this->context->site, ConfigService::KEY);
        CatalogConfigStorage::evict($this->context->site, self::KEY);
        $saved = $this->get(['business_type' => $businessKey]);
        $current = null;
        foreach ($saved['items'] as $row)if ($row['id'] === ($result['item']['id'] ?? $id)) {
            $current = $this->auditItem($row);
            break;
        }
        if (($action === 'deleted' && $current !== null) || ($action !== 'deleted' && $current !== $result['item'])) throw new CommonException('目录已提交，但回读内容已变化，请刷新核对，不要重复提交');
        return $result + ['revision' => $this->revision($saved['items']), 'message' => '目录已保存；已有线索和商户能力记录未改动'];
    }

    /** Whitelisted audit projection; never returns ERP bodies or credentials. */
    public function logs(array $params = []): array
    {
        $this->context->permission('hsx_lead_catalog_logs');
        $businessKey = $this->businessKey($params);
        (new BusinessService($this->context))->definition($businessKey, false);
        $query = Data::query('audit', $this->context->site)->where('entity_type', 'catalog');
        // JSON extraction is parameterized and narrows the existing audit table.
        $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(detail_json, '$.business_type')) = ?", [$businessKey]);
        $action = Data::text($params, 'action', 20);
        if ($action !== '') {
            if (!in_array($action, ['created', 'updated', 'deleted', 'replaced'], true)) throw new CommonException('请选择有效的目录操作类型');
            $query->where('action', $action);
        }
        $page = Data::page($query, $params);
        $uids = array_values(array_unique(array_filter(array_map(static fn (array $row): int => (int) $row['operator_uid'], $page['data']))));
        $users = $uids ? Db::name('sys_user')->whereIn('uid', $uids)->field('uid,username,real_name')->select()->toArray() : [];
        $names = [];
        foreach ($users as $user) $names[(int) $user['uid']] = (string) ($user['real_name'] ?: $user['username']);
        $labels = ['created' => '新增目录项', 'updated' => '修改目录项', 'deleted' => '删除目录项', 'replaced' => '批量导入替换'];
        $page['data'] = array_map(function (array $row) use ($names, $labels): array {
            $detail = Data::decode($row['detail_json'] ?? '');
            $safe = array_intersect_key($detail, array_flip(['business_type', 'business_name', 'item_id', 'before', 'after', 'items_count', 'changes', 'change_counts', 'changes_truncated']));
            return ['id' => (int) $row['id'], 'action' => $row['action'], 'action_label' => $labels[$row['action']] ?? '目录维护',
                'operator_uid' => (int) $row['operator_uid'], 'operator_name' => $names[(int) $row['operator_uid']] ?? '原工作人员',
                'create_time' => (int) $row['create_time'], 'detail' => $safe];
        }, $page['data']);
        return $page + ['business_type' => $businessKey];
    }

    private function businessKey(array $input): string
    {
        return Data::text($input, 'business_type', 16) ?: 'purchase';
    }
    private function expectedRevision(array $input, bool $required = false): ?string
    {
        $value = $input['revision'] ?? null;
        if ($value === null && !$required) return null;
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) throw new CommonException('未取得有效目录版本，请刷新后重试');
        return $value;
    }
    private function revision(array $items): string
    {
        return hash('sha256', Data::json(array_map(fn (array $row): array => $this->auditItem($row), $items)));
    }
    private function auditItem(array $row): array
    {
        return array_intersect_key($row, array_flip(['id', 'title', 'brand', 'category_name', 'path', 'aliases']));
    }

    /** Called only under the site lock, shared by item edits and full import. */
    private function persistItems(array $items, string $businessKey, string $action, array $detail = []): void
    {
        $config = (new ConfigService($this->context))->get();
        $service = new BusinessService($this->context);
        $found = false;
        $businessName = '';
        foreach ($config['businesses'] as &$entry) {
            if ($businessKey === 'purchase' && $entry['key'] !== 'purchase' && $entry['dimension'] === 'model' && !$entry['options'] && empty($entry['catalog_configured'])) {
                $entry['options'] = $service->options($entry);
                $entry['catalog_configured'] = true;
            }
            if ($entry['key'] === $businessKey) {
                $found = true;
                $businessName = $entry['name'];
                $entry['options'] = $businessKey === 'purchase' ? [] : $items;
                $entry['catalog_configured'] = true;
            }
        }unset($entry);
        if (!$found) throw new CommonException('业务已变化，请重新选择目录业务');
        $config['businesses'] = BusinessService::validate($config['businesses']);
        unset($config['membership_levels'],$config['membership_configuration_source']);
        CatalogConfigStorage::write($this->context->site, ConfigService::KEY, $config);
        if ($businessKey === 'purchase') CatalogConfigStorage::write($this->context->site, self::KEY, ['items' => array_map(fn (array $row): array => $this->auditItem($row), $items), 'updated_at' => time()]);
        $this->context->audit('catalog', 0, $action, ['business_type' => $businessKey, 'business_name' => $businessName, 'items_count' => count($items)] + $detail);
    }

    /** Only backend-maintained labels may enter a business directory, never ERP stock. */
    public function directory(): array
    {
        // The plugin store reads persisted data without permanent caching and
        // transparently reconstructs directories larger than a TEXT config row.
        $value = CatalogConfigStorage::read($this->context->site, self::KEY);
        // An explicitly emptied directory must stay empty until the administrator
        // imports it again. Do not silently restore defaults or scan inventory.
        if (is_array($value) && array_key_exists('items', $value)) {
            if (!is_array($value['items']) || !\addon\hsx_lead\app\support\PhpRuntime::isList($value['items'])) throw new CommonException('后台业务目录格式异常，请管理员重新导入');
            return ['items' => $value['items'], 'source' => 'configured', 'updated_at' => (int) ($value['updated_at'] ?? 0)];
        }
        $items = [];
        foreach (require dirname(__DIR__).'/dict/catalog/default.php' as $row) {
            $item = $this->item($row);
            $items[$item['id']] = $item;
        }
        return ['items' => array_values($items), 'source' => 'default', 'updated_at' => 0,
            'notice' => '后台内置常用目录；分类仅由后台维护，不从ERP库存自动补充'];
    }

    public function save(array $input): array
    {
        $this->context->permission('hsx_lead_manage_catalog_save');
        // Do not trim a delimited file: trailing tabs/commas represent empty
        // optional cells, not whitespace that may be discarded.
        $content = $input['content'] ?? '';
        if (!is_string($content)) throw new CommonException('目录文件内容格式错误，请重新选择文件');
        if (strlen($content) > 3000000) throw new CommonException('目录文件内容过大，请将原文件控制在2MB以内');
        $format = Data::text($input, 'format', 10);
        if (!mb_check_encoding($content, 'UTF-8')) throw new CommonException('请使用UTF-8编码的CSV或JSON文件');
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if ($format === 'json') {
            try {
                $rows = json_decode($content, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            } catch (\Throwable $error) {
                throw new CommonException('JSON格式不正确，请上传设备数组或含items的对象');
            }
            if (is_array($rows) && isset($rows['items'])) $rows = $rows['items'];
        } elseif ($format === 'csv') $rows = $this->csv($content);
        elseif ($format === 'xlsx') $rows = $this->xlsx($content);
        else throw new CommonException('请上传XLSX、UTF-8 CSV或JSON');
        if (!is_array($rows) || !\addon\hsx_lead\app\support\PhpRuntime::isList($rows) || count($rows) > 5000) throw new CommonException('清单应为数组，最多5000项');
        if (!$rows) throw new CommonException('文件中未读取到目录数据，请确认表头下有型号记录，且已保存到本次上传的文件；未覆盖已有配置');
        $businessKey = Data::text($input, 'business_type', 16) ?: 'purchase';
        $business = (new BusinessService($this->context))->definition($businessKey, false);
        $items = [];
        $lastHierarchy = ['', ''];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) throw new CommonException('第'.($index + 1).'项不是有效设备');
            if (array_key_exists('一级分类', $row)) {
                if (($row['一级分类'] ?? '') !== '' && $row['一级分类'] !== $lastHierarchy[0]) $lastHierarchy[1] = '';
                foreach (['一级分类', '二级分类'] as $level => $column) if (array_key_exists($column, $row)) {
                    if ($row[$column] === '') $row[$column] = $lastHierarchy[$level];
                    else $lastHierarchy[$level] = $row[$column];
                }
            }
            try {
                $item = $this->item($row);
            } catch (CommonException $error) {
                throw new CommonException('第'.($index + 2).'行：'.$error->getMessage());
            }
            if ($business['dimension'] !== 'model' && (!isset($row['id']) || (is_string($row['id']) && trim($row['id']) === ''))) $item['id'] = 'opt_'.substr(hash('sha256', $businessKey.'|'.implode('|', $item['path']).'|'.$item['title']), 0, 24);
            if (isset($items[$item['id']])) throw new CommonException('目录存在重复选项ID或重复路径，请核对后重新导入');
            $items[$item['id']] = $item;
        }
        $items = array_values($items);
        $candidate = $business;
        $candidate['options'] = $items;
        BusinessService::validate([$candidate]);
        $preview = $input['preview'] ?? false;
        if (!in_array($preview, [true, false, 0, 1, '0', '1'], true)) throw new CommonException('预览参数无效');
        if (in_array($preview, [true, 1, '1'], true)) return ['items' => $items, 'count' => count($items), 'preview' => true, 'business_type' => $businessKey, 'source' => 'preview'];
        $revision = $this->expectedRevision($input);
        Db::transaction(function () use ($items, $businessKey, $revision) {
            Db::name('site')->where('site_id', $this->context->site)->lock(true)->field('site_id')->find();
            $service = new BusinessService($this->context);
            $current = $service->options($service->definition($businessKey, false));
            if ($revision !== null && !hash_equals($this->revision($current), $revision)) throw new CommonException('目录已被其他操作修改，请刷新并重新核对文件；本次未替换');
            $before = array_column($current, null, 'id');
            $after = array_column($items, null, 'id');
            $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0];
            $changes = [];
            foreach ($after as $id => $row) {
                $action = !isset($before[$id]) ? 'created' : ($this->auditItem($before[$id]) !== $this->auditItem($row) ? 'updated' : '');
                if ($action === '') continue;
                $counts[$action]++;
                if (count($changes) < 20) $changes[] = ['action' => $action, 'before' => isset($before[$id]) ? $this->auditItem($before[$id]) : null, 'after' => $this->auditItem($row)];
            }
            foreach ($before as $id => $row) if (!isset($after[$id])) {
                $counts['deleted']++;
                if (count($changes) < 20)$changes[] = ['action' => 'deleted', 'before' => $this->auditItem($row), 'after' => null];
            }
            $this->persistItems($items, $businessKey, 'replaced', ['change_counts' => $counts, 'changes' => $changes, 'changes_truncated' => array_sum($counts) > count($changes)]);
        });
        CatalogConfigStorage::evict($this->context->site, ConfigService::KEY);
        CatalogConfigStorage::evict($this->context->site, self::KEY);
        return $this->get(['business_type' => $businessKey]) + ['count' => count($items), 'message' => '当前业务目录已保存；其他业务和既有工单需求快照未改动'];
    }

    private function item(array $row): array
    {
        $title = $this->firstText($row, ['title', 'model', '名称', '型号', '三级分类'], 'title', 200);
        if ($title === '') throw new CommonException('请填写三级分类或名称，至少一项不能为空');
        $brand = $this->firstText($row, ['brand', '品牌'], 'brand', 100);
        $category = $this->firstText($row, ['category_name', 'category', '分类', '一级分类'], 'category', 100);
        $path = $row['path'] ?? array_values(array_filter([
            $this->firstText($row, ['一级分类'], 'category', 100) ?: $category,
            $this->firstText($row, ['二级分类'], 'brand', 100) ?: $brand,
            $this->firstText($row, ['三级分类'], 'title', 200) ?: $title,
        ], static fn ($v) => $v !== ''));
        if (!is_array($path) || !\addon\hsx_lead\app\support\PhpRuntime::isList($path) || count($path) > 3) throw new CommonException('目录分类最多三级');
        $path = array_map(static fn ($part) => BusinessService::label($part), $path);
        BusinessService::label($title, 200);
        if ($brand !== '') BusinessService::label($brand);
        if ($category !== '') BusinessService::label($category);
        $aliases = $row['aliases'] ?? $row['别名'] ?? [];
        if (is_string($aliases)) $aliases = preg_split('/[|；;\r\n]+/u', $aliases, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($aliases) || count($aliases) > 20) throw new CommonException('每个型号最多20个别名，使用分号或竖线分隔');
        $clean = [];
        foreach ($aliases as $alias) $clean[] = Data::text(['alias' => $alias], 'alias', 200, true);
        $id = $row['id'] ?? '';
        if (is_int($id)) $id = (string) $id;
        if (!is_string($id)) throw new CommonException('目录ID须为文本或整数');
        $id = trim($id);
        if ($id === '') $id = 'cat_'.substr(hash('sha256', $brand.'|'.$title.'|'.$category.(isset($row['一级分类']) || isset($row['path']) ? '|'.implode('|', $path) : '')), 0, 24);
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $id)) throw new CommonException('目录ID格式错误');
        return ['id' => $id, 'title' => $title, 'brand' => $brand,
            'category_name' => $category, 'path' => $path, 'aliases' => array_values(array_unique($clean))];
    }

    /** Optional aliases must not mask a populated lower-priority column. */
    private function firstText(array $row, array $keys, string $label, int $limit): string
    {
        foreach ($keys as $key) {
            if (!isset($row[$key])) continue;
            $value = Data::text([$label => $row[$key]], $label, $limit);
            if ($value !== '') return $value;
        }
        return '';
    }

    private function xlsx(string $content): array
    {
        $binary = base64_decode($content, true);
        if ($binary === false || strlen($binary) > 2097152 || !str_starts_with($binary, 'PK')) throw new CommonException('XLSX内容须为有效的base64，原文件不超过2MB');
        $file = tempnam(sys_get_temp_dir(), 'hsx_lead_catalog_');
        if ($file === false) throw new CommonException('无法准备目录导入');
        $book = null;
        $zip = new \ZipArchive();
        $zipOpen = false;
        try {
            if (file_put_contents($file, $binary) !== strlen($binary) || $zip->open($file) !== true) throw new CommonException('无法读取XLSX文件');
            $zipOpen = true;
            $size = 0;
            if ($zip->numFiles > 150) throw new CommonException('XLSX工作簿结构过于复杂，请使用简单目录模板');
            for ($i = 0;$i < $zip->numFiles;$i++) {
                $stat = $zip->statIndex($i);
                $size += (int) $stat['size'];
                if ($size > 8388608) throw new CommonException('XLSX解压后过大，请减少格式、工作表和内容');
                if (preg_match('/vbaProject|activeX|embeddings|externalLinks/i', $stat['name'])) throw new CommonException('目录XLSX不得包含宏、嵌入对象或外部工作簿');
                if (preg_match('/\.(?:xml|rels)$/i', $stat['name'])) {
                    $xml = $zip->getFromIndex($i);
                    if ($xml === false || preg_match('/<!DOCTYPE|<!ENTITY|TargetMode\s*=\s*["\x27]External/i', $xml)) throw new CommonException('目录XLSX不得包含外部链接或XML实体');
                }
            }
            $zip->close();
            $zipOpen = false;
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(true);
            $reader->setIncludeCharts(false);
            $info = $reader->listWorksheetInfo($file);
            if (count($info) !== 1 || (int) $info[0]['totalRows'] > 5001 || (int) $info[0]['totalColumns'] > 12) throw new CommonException('请使用单工作表，最多5000条目录及12列');
            $book = $reader->load($file);
            $sheet = $book->getSheet(0);
            $header = [];
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $values = [];
                foreach ($row->getCellIterator('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, (int) $info[0]['totalColumns']))) as $cell) {
                    if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) throw new CommonException('目录XLSX不支持公式，请先粘贴为值');
                    $value = $cell->getValue();
                    if (is_object($value)) $value = (string) $value;
                    $values[] = trim((string) ($value ?? ''));
                }
                if (!$header) {
                    $header = $values;
                    if (count(array_unique($header)) !== count($header) || in_array('', $header, true)) throw new CommonException('XLSX表头不得为空或重复');
                    continue;
                }
                if (!array_filter($values, static fn ($v) => $v !== '')) continue;
                $rows[] = array_combine($header, $values);
            }
            return $rows;
        } catch (CommonException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new CommonException('XLSX格式不正确，请使用纯文本目录模板');
        } finally {
            if ($book) $book->disconnectWorksheets();
            if ($zipOpen) $zip->close();
            if (is_file($file)) unlink($file);
        }
    }

    private function csv(string $content): array
    {
        $stream = fopen('php://temp', 'r+');
        if (!$stream) throw new CommonException('无法读取CSV文件');
        try {
            fwrite($stream, $content);
            rewind($stream);
            // Excel exports may use comma, tab or locale-dependent semicolon.
            // Detect only from the header, with CSV quoting still respected.
            $delimiter = ',';
            $header = [];
            foreach ([',', "\t", ';'] as $candidate) {
                rewind($stream);
                while (($cells = fgetcsv($stream, 0, $candidate, '"', '')) !== false) {
                    $cells = array_map(static fn ($value) => trim((string) ($value ?? '')), $cells);
                    if (!array_filter($cells, static fn ($value) => $value !== '')) continue;
                    if (count($cells) > count($header)) {
                        $delimiter = $candidate;
                        $header = $cells;
                    }
                    break;
                }
            }
            if (!$header) return [];
            if (in_array('', $header, true) || count(array_unique($header)) !== count($header)) throw new CommonException('CSV表头不得为空或重复，请核对后重新导入');
            if (!array_intersect($header, ['title', 'model', '名称', '型号', '三级分类'])) throw new CommonException('未找到名称或三级分类列，请使用三级目录模板');
            rewind($stream);
            // Skip the same leading blank lines and then consume the header.
            do {
                $cells = fgetcsv($stream, 0, $delimiter, '"', '');
            } while ($cells !== false && !array_filter($cells, static fn ($value) => trim((string) ($value ?? '')) !== ''));
            $rows = [];
            while (($values = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                $values = array_map(static fn ($value) => trim((string) ($value ?? '')), $values);
                if (!array_filter($values, static fn ($value) => $value !== '')) continue;
                if (count($values) !== count($header)) {
                    // Clipboard TSV often drops its final empty cells. Only
                    // omitted alias columns may be restored; never guess an
                    // omitted ID/brand or mix delimiters within a CSV file.
                    $missing = array_slice($header, count($values));
                    if ($delimiter !== "\t" || count($values) > count($header) || array_diff($missing, ['aliases', '别名'])) throw new CommonException('文件列数与表头不一致，请统一分隔符；空白单元格也需保留分隔符');
                    $values = array_pad($values, count($header), '');
                }
                $rows[] = array_combine($header, $values);
                if (count($rows) > 5000) throw new CommonException('设备清单最多5000项');
            }
            return $rows;
        } finally {
            fclose($stream);
        }
    }

    public function resolve(array $requested, array $previous = []): array
    {
        $this->context->manager();
        if (!$requested || count($requested) > 20 || !\addon\hsx_lead\app\support\PhpRuntime::isList($requested)) throw new CommonException('请勾选1—20项意向设备');
        $directory = array_column($this->get()['items'], null, 'id');
        foreach ($previous as $item) if (isset($item['catalog_id']) && !isset($directory[$item['catalog_id']])) {
            $directory[$item['catalog_id']] = ['id' => $item['catalog_id'], 'title' => $item['title'], 'brand' => $item['brand'] ?? '',
                'category_name' => $item['category_name'] ?? '', 'aliases' => $item['aliases'] ?? []];
        }
        $result = [];
        $seen = [];
        foreach ($requested as $entry) {
            if (!is_array($entry)) throw new CommonException('需求设备格式不正确');
            $id = Data::text($entry, 'catalog_id', 100, true);
            if (!isset($directory[$id])) throw new CommonException('所选型号已不在当前清单，请刷新并重新勾选');
            if (isset($seen[$id])) throw new CommonException('同一型号请修改数量，不要重复勾选');
            $seen[$id] = true;
            $quantity = $entry['quantity'] ?? 1;
            if ((!is_int($quantity) && !is_string($quantity)) || !preg_match('/^[1-9]\d{0,2}$/D', (string) $quantity)) throw new CommonException('每项设备数量请填写1—999的整数');
            $min = Data::amount($entry['budget_min'] ?? null, true);
            $max = Data::amount($entry['budget_max'] ?? null, true);
            if ($min !== null && $max !== null && bccomp($min, $max, 2) > 0) throw new CommonException('每项设备预算下限不能高于上限');
            $item = $directory[$id];
            $result[] = ['catalog_id' => $id, 'title' => $item['title'], 'brand' => $item['brand'], 'category_name' => $item['category_name'],
                'aliases' => $item['aliases'], 'quantity' => (int) $quantity, 'budget_min' => $min, 'budget_max' => $max, 'remark' => Data::text($entry, 'remark', 1000)];
        }
        return $result;
    }
}
