<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\admin;

use addon\hsx_express\app\service\core\ConfigService;
use addon\hsx_express\app\service\core\LogisticsService;
use addon\hsx_express\app\service\core\TaskRepository;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class ExpressAdminService extends BaseAdminService
{
    private function site(): int
    {
        if ((int)$this->site_id <= 0) throw new CommonException('请进入客户站点管理物流，平台账号不能跨站操作运单');
        return (int)$this->site_id;
    }
    public function config(): array { return (new ConfigService())->get($this->site(), true); }
    public function save(array $data): array { return (new ConfigService())->save($this->site(), $data); }
    public function check(): array { return ConfigService::readiness((new ConfigService())->get($this->site())); }
    public function tasks(array $filters): array
    {
        $page = (new TaskRepository())->page($this->site(), $filters);
        $page['data'] = array_map([LogisticsService::class, 'view'], $page['data']);
        return $page;
    }
    public function detail(int $id): array
    {
        $task = (new TaskRepository())->find($this->site(), $id);
        if (!$task) throw new CommonException('任务不存在或不属于本站');
        $data = LogisticsService::view($task);
        $data['logs'] = array_map(static fn($row) => $row + ['action' => $row['operation'] ?? '', 'create_at' => $row['at'] ?? 0], json_decode((string)($task['logs_json'] ?? '[]'), true) ?: []);
        return $data;
    }
    public function operate(int $id, string $operation, array $input): array
    {
        return (new LogisticsService())->execute($this->site(), $operation, ['task_id' => $id, 'reason' => $input['reason'] ?? '', 'confirm' => $input['confirm'] ?? 0]);
    }
}
