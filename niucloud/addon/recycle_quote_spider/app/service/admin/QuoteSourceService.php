<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\admin;

use addon\recycle_quote_spider\app\model\QuoteSource;
use addon\recycle_quote_spider\app\job\QuoteSpiderManualSync;
use addon\recycle_quote_spider\app\service\core\QuoteSpiderClient;
use addon\recycle_quote_spider\app\service\core\QuoteSyncService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Log;

class QuoteSourceService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new QuoteSource();
    }

    public function getPage(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $search = $this->model
            ->withSearch(['site_id', 'status', 'keyword'], $where)
            ->order('id desc');
        return $this->pageQuery($search);
    }

    public function getAll(): array
    {
        return $this->model->where('site_id', $this->site_id)->where('status', 1)->order('id desc')->select()->toArray();
    }

    public function getInfo(int $id): array
    {
        $info = $this->model->where('site_id', $this->site_id)->where('id', $id)->findOrEmpty()->toArray();
        if (empty($info)) {
            throw new CommonException('报价源不存在');
        }
        $info['safe_request_config'] = (new QuoteSpiderClient())->sanitizeConfig($info['request_config'] ?? []);
        return $info;
    }

    public function add(array $data): int
    {
        $data = $this->filterData($data);
        $data['site_id'] = $this->site_id;
        $record = $this->model->create($data);
        return (int)$record->id;
    }

    public function edit(int $id, array $data): bool
    {
        $this->getInfo($id);
        $this->model->where('site_id', $this->site_id)->where('id', $id)->update($this->filterData($data));
        return true;
    }

    public function del(int $id): bool
    {
        $this->getInfo($id);
        $this->model->where('site_id', $this->site_id)->where('id', $id)->delete();
        return true;
    }

    public function sync(int $id): array
    {
        $this->getInfo($id);
        $service = new QuoteSyncService();
        $task = $service->createLog($id, 'manual');

        if (env('queue.state', false)) {
            $pushed = QuoteSpiderManualSync::dispatch([
                'source_id' => $id,
                'log_id' => $task['log_id'],
            ]);
            if ($pushed === false) {
                Log::error('回收报价爬虫手动同步队列推送失败，source_id=' . $id . ', log_id=' . $task['log_id']);
                return [
                    'async' => false,
                    'queue_enabled' => true,
                    'message' => '同步任务已创建，但队列推送失败，请检查Redis和队列服务。',
                ] + $task;
            }
            return ['async' => true] + $task;
        }

        return [
            'async' => false,
            'queue_enabled' => false,
            'message' => '同步任务已创建，请开启队列后执行。当前队列未启用，直接同步可能导致504。',
        ] + $task;
    }

    public function createDefault(): int
    {
        $existing = $this->model->where('site_id', $this->site_id)->where('source_key', 'dongxu')->findOrEmpty()->toArray();
        if (!empty($existing)) {
            return (int)$existing['id'];
        }
        return $this->add([
            'source_key' => 'dongxu',
            'source_name' => '东旭报价爬虫',
            'provider' => 'dongxu',
            'base_url' => 'https://xcx1416.ycdongxu.com',
            'list_path' => '/index.php/Api/index/newimage',
            'detail_path' => '/index.php/Api/index/bj',
            'request_config' => [
                'query' => [],
                'headers' => [],
                'cookies' => [],
            ],
            'sync_enabled' => 0,
            'sync_interval' => 86400,
            'timeout' => 15,
            'rate_limit' => 300,
            'retry_times' => 1,
            'status' => 1,
        ]);
    }

    private function filterData(array $data): array
    {
        return [
            'source_key' => (string)($data['source_key'] ?? ''),
            'source_name' => (string)($data['source_name'] ?? ''),
            'provider' => (string)($data['provider'] ?? ''),
            'base_url' => (string)($data['base_url'] ?? ''),
            'list_path' => (string)($data['list_path'] ?? ''),
            'detail_path' => (string)($data['detail_path'] ?? ''),
            'request_config' => is_array($data['request_config'] ?? null) ? $data['request_config'] : [],
            'sync_enabled' => (int)($data['sync_enabled'] ?? 0),
            'sync_interval' => max(60, (int)($data['sync_interval'] ?? 86400)),
            'timeout' => max(3, (int)($data['timeout'] ?? 15)),
            'rate_limit' => max(0, (int)($data['rate_limit'] ?? 300)),
            'retry_times' => max(0, (int)($data['retry_times'] ?? 1)),
            'status' => (int)($data['status'] ?? 1),
        ];
    }
}
