<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\service;

use addon\hsx_kingdee_jdy\app\support\Problem;
use think\facade\Db;

final class NoticeService
{
    public function send(int $site, int $id): void
    {
        $service = new ConfigService();
        if (!$service->installed($site)) return;
        $config = $service->get($site);
        if ($config['mode'] !== 'preview') return;
        $where = [['site_id', '=', $site], ['id', '=', $id], ['status', '=', 'blocked']];
        $task = Db::name('hsx_kingdee_jdy_task')->where($where)->find();
        if (!$task || !in_array($task['notice_status'], ['pending', 'retry'], true) || (int)$task['notice_next_at'] > time()) return;
        $claim = Db::name('hsx_kingdee_jdy_task')->where($where)->where('notice_next_at', $task['notice_next_at'])
            ->where('notice_status', $task['notice_status'])->update(['notice_next_at' => time() + 120]);
        if (!$claim) return;
        $state = 'unavailable';
        $attempt = (int)$task['notice_attempts'] + 1;
        try {
            $results = (int)$config['assignee_uid'] > 0 ? event('HsxBusinessTaskAssigned', [
                'site_id' => $site, 'assignee_uid' => (int)$config['assignee_uid'],
                'event_id' => 'hsx_kingdee_jdy:' . $site . ':' . $id . ':' . $task['notice_round'],
                'source_plugin' => 'hsx_kingdee_jdy', 'source_type' => 'sync_task', 'source_id' => $id,
                'title' => '精斗云对接待处理', 'business_no' => $task['business_no'],
                'description' => '请在精斗云工作台检查资料与业务规则。当前任务未向账套写单。',
                'target' => ['plugin' => 'hsx_kingdee_jdy', 'route_key' => 'hsx_kingdee_jdy.task',
                    'web_path' => 'site/hsx_kingdee_jdy/workbench?task_id=' . $id],
            ]) : [];
            foreach ($results as $result) {
                if (is_array($result) && !empty($result['queued']) && in_array($result['status'] ?? 'pending', ['pending', 'sending', 'sent'], true)) $state = 'queued';
                elseif (is_array($result) && !empty($result['duplicate'])) $state = 'existing';
            }
        } catch (\Throwable $e) { $state = $attempt < 5 ? 'retry' : 'unavailable'; }
        Db::transaction(function () use ($where, $site, $id, $state, $attempt): void {
            $changed = Db::name('hsx_kingdee_jdy_task')->where($where)->update(['notice_status' => $state,
                'notice_attempts' => $attempt, 'notice_next_at' => time() + min(1800, 60 * 2 ** min(5, $attempt))]);
            if ($changed) TaskService::audit($site, $id, 'notice', $state, match ($state) {
                'queued' => '已提交企业微信通知队列，不代表员工已收到或处理',
                'existing' => '已有通知记录，请在企业微信插件查看投递结果',
                'retry' => '通知暂时失败，将重试；后台待办仍保留',
                default => '通知未入队，请检查责任人、企业微信插件及员工绑定；后台待办仍保留',
            });
        });
    }

    public function request(int $site, int $id, int $uid): void
    {
        $config = (new ConfigService())->get($site);
        if ($config['mode'] !== 'preview' || (int)$config['assignee_uid'] <= 0) throw new Problem('NOTICE_CONFIG', '请开启本地预检并设置异常责任人');
        Db::transaction(function () use ($site, $id, $uid): void {
            $where = [['site_id', '=', $site], ['id', '=', $id], ['status', '=', 'blocked']];
            $task = Db::name('hsx_kingdee_jdy_task')->where($where)->lock(true)->find();
            if (!$task) throw new Problem('TASK_STATE', '只有待处理任务可以提醒责任人');
            if ((int)$task['notice_next_at'] > time()) throw new Problem('NOTICE_THROTTLED', '刚刚已请求提醒，请稍后再试');
            Db::name('hsx_kingdee_jdy_task')->where($where)->update(['notice_status' => 'pending', 'notice_next_at' => 0,
                'notice_round' => (int)$task['notice_round'] + 1, 'notice_attempts' => 0]);
            TaskService::audit($site, $id, 'notice_request', 'pending', '人工请求提醒责任人', $uid);
        });
        $this->send($site, $id);
    }
}
