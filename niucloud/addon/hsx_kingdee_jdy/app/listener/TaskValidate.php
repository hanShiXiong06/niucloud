<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\listener;
use addon\hsx_kingdee_jdy\app\service\ConfigService;
use think\facade\Db;
final class TaskValidate
{
    public function handle(array $event = []): array
    {
        if (($event['source_plugin'] ?? '') !== 'hsx_kingdee_jdy') return [];
        $site = (int)($event['site_id'] ?? 0);
        $config = new ConfigService();
        $valid = $site > 0 && $config->installed($site) && $config->get($site)['mode'] === 'preview'
            && Db::name('hsx_kingdee_jdy_task')->where('site_id', $site)->where('id', (int)($event['source_id'] ?? 0))->where('status', 'blocked')->count() > 0;
        return ['valid' => $valid, 'reason' => $valid ? '' : '精斗云待办已处理或插件已关闭'];
    }
}
