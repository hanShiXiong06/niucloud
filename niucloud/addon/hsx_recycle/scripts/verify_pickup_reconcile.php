<?php
declare(strict_types=1);

// 纯 mock：不引入应用 bootstrap、数据库、HTTP、真实账号或通知服务。
namespace core\base { abstract class BaseJob {} }

namespace addon\hsx_recycle\app\model\express {
    class ExpressOrderRecord
    {
        public static array $calls = [];
        public static function whereNotIn($field, $values): MockRecordQuery
        {
            self::$calls = [['whereNotIn', [$field, $values]]];
            return new MockRecordQuery();
        }
    }
    class MockRecordQuery
    {
        public function __call(string $name, array $arguments)
        {
            ExpressOrderRecord::$calls[] = [$name, $arguments];
            return $name === 'toArray' ? [] : $this;
        }
    }
}

namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require dirname(__DIR__) . '/app/job/schedule/PickupReconcile.php';

    use addon\hsx_recycle\app\job\schedule\PickupReconcile;

    final class MockPickupReconcile extends PickupReconcile
    {
        public array $records = [];
        public array $queried = [];
        public array $locks = [];
        public array $logs = [];
        public array $errors = [];
        public array $lockErrors = [];
        public int $clock = 2000000000;
        public $afterQuery;
        public $afterLock;
        public function __construct(array $records) { foreach ($records as $record) $this->records[$record['id']] = $record; }
        public function inspectCandidateQuery(): array { return parent::candidates($this->clock); }
        protected function providerRequirements(): array { return ['kuaidi100' => ['provider_task_id'], 'sf_direct' => ['provider_order_id']]; }
        protected function candidates(int $now): array { return array_values($this->records); }
        protected function now(): int { return $this->clock; }
        protected function readRecord(int $siteId, int $recordId): ?array
        {
            $record = $this->records[$recordId] ?? null;
            return $record && $record['site_id'] === $siteId ? $record : null;
        }
        protected function saveMeta(int $siteId, int $recordId, array $meta, ?int $queryAt = null): void
        {
            $record = $this->readRecord($siteId, $recordId);
            if (!$record) throw new \RuntimeException('missing mock record');
            $this->records[$recordId]['api_response']['pickup_reconcile'] = $meta;
            if ($queryAt !== null) $this->records[$recordId]['api_response']['last_query_at'] = $queryAt;
        }
        protected function queryDetail(int $siteId, string $thirdOrderNo): void
        {
            $this->queried[] = [$siteId, $thirdOrderNo];
            if (isset($this->errors[$thirdOrderNo])) throw $this->errors[$thirdOrderNo];
            if ($this->afterQuery) ($this->afterQuery)($this, $thirdOrderNo);
        }
        protected function withLock(int $siteId, string $key, callable $operation)
        {
            $this->locks[] = [$siteId, $key];
            if (isset($this->lockErrors[$key])) throw new \RuntimeException('mock lock conflict');
            if ($this->afterLock) ($this->afterLock)($this, $key);
            return $operation();
        }
        protected function logFailure(string $message, array $context): void { $this->logs[] = [$message, $context]; }
    }

    $checks = 0;
    function check(bool $condition, string $message): void
    {
        global $checks;
        if (!$condition) throw new \RuntimeException('FAIL: ' . $message);
        $checks++;
    }
    function record(int $id, string $state = 'accepted', array $data = [], int $siteId = 17): array
    {
        return ['id' => $id, 'site_id' => $siteId, 'third_order_no' => 'mock_' . $id,
            'order_no' => 'vendor_' . $id, 'order_status' => $state, 'create_at' => 1900000000,
            'api_response' => array_replace(['provider' => 'kuaidi100', 'provider_task_id' => 'task_' . $id, 'booking_state' => $state], $data)];
    }

    $job = new MockPickupReconcile([record(1)]);
    check($job->inspectCandidateQuery() === [], 'candidate query uses only mock builder');
    $queryCalls = \addon\hsx_recycle\app\model\express\ExpressOrderRecord::$calls;
    $queryMethods = array_column($queryCalls, 0);
    $queryArgs = array_column($queryCalls, 1, 0);
    check($queryArgs['limit'] === [20], 'SQL candidate batch limited to twenty');
    check($queryArgs['order'] === ['update_at asc,id asc'], 'stable oldest-first candidate ordering');
    check($queryArgs['field'] === ['id,site_id,third_order_no,order_status,create_at,api_response'], 'candidate query excludes address and customer columns');
    $rawQueries = array_values(array_filter($queryCalls, static fn(array $call): bool => $call[0] === 'whereRaw'));
    check(count($rawQueries) === 3 && $rawQueries[0][1][1] === ['kuaidi100', 'sf_direct'], 'SQL registered provider snapshot filter is bound, not default-channel fallback');
    check(strpos($rawQueries[1][1][0], 'next_check_at') !== false && $rawQueries[1][1][1] === [$job->clock], 'SQL excludes not-yet-due backoff');
    check(strpos($rawQueries[2][1][0], 'last_query_at') !== false && strpos($rawQueries[2][1][0], 'last_checked_at') !== false && $rawQueries[2][1][1] === [$job->clock - 300], 'SQL excludes recent manual and automatic checks');
    check(strpos($rawQueries[0][1][0], 'JSON_VALID') !== false, 'malformed legacy JSON cannot break candidate scan');
    check($job->doJob()['checked'] === 1, 'existing task queried once');
    check($job->queried === [[17, 'mock_1']], 'query uses original site and thirdOrderNo');
    check($job->locks === [[17, 'mock_1']], 'same record key protected by lock');
    $meta = $job->records[1]['api_response']['pickup_reconcile'];
    check($meta['last_checked_at'] === $job->clock && $meta['next_check_at'] === $job->clock + 300, 'five minute persisted interval');
    check($job->records[1]['api_response']['last_query_at'] === $job->clock, 'manual refresh shares recent query marker');
    check($meta['failure_count'] === 0 && $meta['last_error'] === '', 'successful query clears errors');
    $job->clock += 299;
    check($job->doJob()['skipped'] === 1 && count($job->queried) === 1, 'not queried within five minutes');
    $job->clock++;
    check($job->doJob()['checked'] === 1 && count($job->queried) === 2, 'eligible after five minutes');

    $records = [];
    foreach (['delivered', 'cancelled', 'failed', 'manual', 'not_requested'] as $index => $state) $records[] = record($index + 1, $state);
    $records[] = record(9, 'unknown', ['provider' => 'yisu']);
    $terminal = new MockPickupReconcile($records);
    check($terminal->doJob()['skipped'] === 6 && !$terminal->queried, 'terminal and providers without reconciliation capability not queried');

    $missing = new MockPickupReconcile([record(1, 'unknown', ['provider_task_id' => ''])]);
    check($missing->doJob()['waiting'] === 1 && !$missing->queried, 'orderNo without taskId never triggers query or new booking');
    check($missing->records[1]['api_response']['booking_state'] === 'unknown', 'missing task remains unknown, never marked failed');
    check($missing->records[1]['api_response']['pickup_reconcile']['last_error_code'] === 'missing_query_identifier', 'missing identifier is recorded');
    check($missing->records[1]['api_response']['pickup_reconcile']['next_check_at'] === $missing->clock + 86400, 'missing identifier backs off one day');

    $sf = new MockPickupReconcile([
        record(1, 'unknown', ['provider' => 'sf_direct', 'provider_task_id' => '', 'provider_order_id' => 'SF_LOCAL_17_1']),
        record(2, 'unknown', ['provider' => 'sf_direct', 'provider_task_id' => '', 'provider_order_id' => ''], 18),
    ]);
    $sfCounts = $sf->doJob();
    check($sfCounts['checked'] === 1 && $sfCounts['waiting'] === 1, 'new provider uses its own declared identifiers, not kuaidi100 taskId');
    check($sf->queried === [[17, 'mock_1']], 'SF query retains original tenant and original business order');
    check($sf->records[2]['api_response']['booking_state'] === 'unknown', 'missing SF identifier cannot become failed or trigger another pickup');

    $recent = new MockPickupReconcile([
        record(1, 'unknown', ['last_query_at' => 1999999990]),
        array_replace(record(2), ['create_at' => 1999999990]),
        record(3, 'accepted', ['pickup_reconcile' => ['next_check_at' => 2000009999]]),
    ]);
    check($recent->doJob()['skipped'] === 3 && !$recent->queried, 'recent manual query, newly submitted, and backoff records excluded');

    $account = new MockPickupReconcile([record(1), record(2)]);
    $account->errors['mock_1'] = new \RuntimeException('原账号凭证已变更 secret=never-persist-this');
    $summary = $account->doJob();
    check($summary['failed'] === 1 && $summary['checked'] === 1, 'credential failure isolated from next record');
    $metadata = $account->records[1]['api_response']['pickup_reconcile'];
    check($metadata['last_error_code'] === 'provider_configuration' && $metadata['next_check_at'] === $account->clock + 21600, 'credential failure starts six hour backoff');
    check(strpos(json_encode([$metadata, $account->logs]), 'never-persist-this') === false, 'raw errors and credentials not persisted or logged');
    $account->clock += 300;
    $account->doJob();
    check(count(array_filter($account->queried, static fn(array $query): bool => $query[1] === 'mock_1')) === 1, 'credential mismatch not queried every five minutes');
    $account->clock = $metadata['next_check_at'];
    $account->doJob();
    check($account->records[1]['api_response']['pickup_reconcile']['next_check_at'] === $account->clock + 43200, 'second credential error backs off twelve hours');
    $account->clock += 43200;
    $account->doJob();
    check($account->records[1]['api_response']['pickup_reconcile']['next_check_at'] === $account->clock + 86400, 'credential backoff caps at one day');

    $network = new MockPickupReconcile([record(1)]);
    $network->errors['mock_1'] = new \RuntimeException('query temporarily unavailable');
    $network->doJob();
    check($network->records[1]['api_response']['pickup_reconcile']['next_check_at'] === $network->clock + 300, 'first network failure five minutes');
    $network->clock += 300;
    $network->doJob();
    check($network->records[1]['api_response']['pickup_reconcile']['next_check_at'] === $network->clock + 600, 'second network failure ten minutes');
    $network->clock += 600;
    unset($network->errors['mock_1']);
    $network->doJob();
    check($network->records[1]['api_response']['pickup_reconcile']['failure_count'] === 0, 'recovery resets failures');

    $callback = new MockPickupReconcile([record(1)]);
    $callback->afterLock = static function (MockPickupReconcile $job): void { $job->records[1]['api_response']['booking_state'] = 'delivered'; };
    check($callback->doJob()['skipped'] === 1 && !$callback->queried, 'recheck terminal callback after acquiring lock');
    $callback = new MockPickupReconcile([record(1)]);
    $callback->afterQuery = static function (MockPickupReconcile $job): void {
        $job->records[1]['api_response']['booking_state'] = 'assigned';
        $job->records[1]['api_response']['courier_name'] = 'mock courier';
    };
    $callback->doJob();
    check($callback->records[1]['api_response']['booking_state'] === 'assigned' && $callback->records[1]['api_response']['courier_name'] === 'mock courier', 'metadata save preserves query or callback facts');
    check($callback->records[1]['order_status'] === 'accepted', 'job never directly rewrites shipping or recycle business status');

    $batch = new MockPickupReconcile(array_map(static fn(int $id): array => record($id), range(1, 25)));
    check($batch->doJob()['checked'] === 20 && count($batch->queried) === 20, 'maximum twenty records per run');
    $locked = new MockPickupReconcile([record(1), record(2)]);
    $locked->lockErrors['mock_1'] = true;
    check($locked->doJob()['checked'] === 1 && $locked->queried === [[17, 'mock_2']], 'lock conflict does not block later records');

    $schedule = require dirname(__DIR__) . '/app/dict/schedule/schedule.php';
    $tasks = array_values(array_filter($schedule, static fn(array $item): bool => $item['key'] === 'hsx_recycle_pickup_reconcile'));
    check(count($tasks) === 1 && $tasks[0]['time'] === ['type' => 'min', 'min' => 5], 'one plugin schedule every five minutes');
    check($tasks[0]['class'] === PickupReconcile::class && $tasks[0]['function'] === 'doJob', 'schedule resolves actual job');
    echo "PASS {$checks} checks (mock only: no DB, external shipment query, or notification)\n";
}
