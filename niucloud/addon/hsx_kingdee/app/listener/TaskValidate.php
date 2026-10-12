<?php
declare(strict_types=1);
namespace addon\hsx_kingdee\app\listener;
use addon\hsx_kingdee\app\service\ConfigService;
use think\facade\Db;
final class TaskValidate
{
    public function handle(array $event = []): array
    {
        if (($event['source_plugin'] ?? '') !== 'hsx_kingdee') return [];
        $site = (int)($event['site_id'] ?? 0);
        $config = new ConfigService();
        $valid = $site > 0 && $config->installed($site) && $config->get($site)['mode'] === 'preview'
            && Db::name('hsx_kingdee_task')->where([
                ['site_id', '=', $site], ['id', '=', (int)($event['source_id'] ?? 0)], ['status', '=', 'blocked'],
            ])->count() > 0;
        return ['valid' => $valid, 'reason' => $valid ? '' : '金蝶待办已处理或插件已关闭'];
    }
}
