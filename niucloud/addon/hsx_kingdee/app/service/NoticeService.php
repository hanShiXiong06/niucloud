<?php
declare(strict_types=1);
namespace addon\hsx_kingdee\app\service;

use think\facade\Db;

final class NoticeService
{
    public function send(int $site, int $id): void
    {
        $where = [['site_id', '=', $site], ['id', '=', $id], ['status', '=', 'blocked']];
        $task = Db::name('hsx_kingdee_task')->where($where)->find();
        if (!$task || !in_array($task['notice_status'], ['pending', 'retry'], true) || (int)$task['notice_next_at'] > time()) return;
        if (!(new ConfigService())->installed($site)) return;
        $config = (new ConfigService())->get($site);
        if ($config['mode'] !== 'preview') return;
        $assignee = (int)$config['assignee_uid'];
        $attempt = (int)$task['notice_attempts'] + 1;
        $state = 'unavailable';
        try {
            $results = $assignee > 0 ? event('HsxBusinessTaskAssigned', [
                'site_id' => $site, 'assignee_uid' => $assignee,
                'event_id' => 'hsx_kingdee:' . $site . ':' . $id . ':blocked:' . (int)$task['notice_round'],
                'source_plugin' => 'hsx_kingdee', 'source_type' => 'sync_task', 'source_id' => $id,
                'title' => '金蝶对接有待处理事项', 'business_no' => $task['business_no'],
                'description' => '请打开金蝶对接工作台核查。当前任务未写入金蝶账套。',
                'target' => ['plugin' => 'hsx_kingdee', 'route_key' => 'hsx_kingdee.task',
                    'web_path' => 'site/hsx_kingdee/workbench?task_id=' . $id],
            ]) : [];
            foreach ($results as $result) {
                if (is_array($result) && !empty($result['queued']) && in_array($result['status'] ?? 'pending', ['pending', 'sending', 'sent'], true)) $state = 'queued';
                elseif (is_array($result) && !empty($result['duplicate'])) $state = 'existing';
            }
        } catch (\Throwable $e) {
            $state = $attempt >= 5 ? 'unavailable' : 'retry';
        }
        Db::transaction(function () use ($where, $site, $id, $state, $attempt, $assignee): void {
            $changed = Db::name('hsx_kingdee_task')->where($where)->update([
                'notice_status' => $state, 'notice_attempts' => $attempt,
                'notice_next_at' => time() + min(1800, 60 * 2 ** min(5, $attempt)), 'assignee_uid' => $assignee,
            ]);
            if ($changed) TaskService::audit($site, $id, 'notice', $state, match ($state) {
                'queued' => '已提交企业微信消息队列，不代表员工已收到或处理',
                'existing' => '已有企业微信通知记录，请在企业微信插件中查看投递结果',
                'retry' => '提醒暂时失败，将自动重试；后台待办仍然保留',
                default => '未能入队，请检查责任人、企业微信安装与绑定；后台待办仍然保留',
            });
        });
    }

    public function request(int $site, int $id, int $uid): void
    {
        $config = (new ConfigService())->get($site);
        if ($config['mode'] !== 'preview' || (int)$config['assignee_uid'] <= 0) throw new \RuntimeException('请先开启预览并设置异常责任人');
        Db::transaction(function () use ($site, $id, $uid): void {
            $where = [['site_id', '=', $site], ['id', '=', $id], ['status', '=', 'blocked']];
            $task = Db::name('hsx_kingdee_task')->where($where)->lock(true)->find();
            if (!$task) throw new \RuntimeException('仅待处理任务允许重新提醒');
            if ((int)$task['notice_next_at'] > time()) throw new \RuntimeException('刚刚已发送或正在重试，请稍后再试');
            Db::name('hsx_kingdee_task')->where($where)->update([
                'notice_status' => 'pending', 'notice_attempts' => 0, 'notice_round' => (int)$task['notice_round'] + 1,
                'notice_next_at' => 0, 'update_at' => time(),
            ]);
            TaskService::audit($site, $id, 'notice_request', 'pending', '人工请求重新提醒责任人', $uid);
        });
        $this->send($site, $id);
    }
}
