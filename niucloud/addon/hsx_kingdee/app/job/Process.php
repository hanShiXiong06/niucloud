<?php
declare(strict_types=1);
namespace addon\hsx_kingdee\app\job;

use addon\hsx_kingdee\app\service\ConfigService;
use addon\hsx_kingdee\app\service\NoticeService;
use addon\hsx_kingdee\app\service\TaskService;
use app\model\sys\SysConfig;
use core\base\BaseJob;
use think\facade\Db;
use think\facade\Log;

final class Process extends BaseJob
{
    public function doJob(array $params = []): void
    {
        $sites = SysConfig::where('config_key', 'HSX_KINGDEE_CONNECTION')->column('site_id');
        foreach (array_unique($sites) as $site) {
            try {
                $config = new ConfigService();
                if (!$config->installed((int)$site) || $config->get((int)$site)['mode'] !== 'preview') continue;
                $tasks = Db::name('hsx_kingdee_task')->where('site_id', $site)->whereIn('status', ['pending', 'retry', 'processing'])
                    ->where('next_retry_at', '<=', time())->order('id')->limit(50)->column('id');
                foreach ($tasks as $id) (new TaskService())->process((int)$site, (int)$id);
                $notices = Db::name('hsx_kingdee_task')->where('site_id', $site)->where('status', 'blocked')
                    ->whereIn('notice_status', ['pending', 'retry'])->where('notice_next_at', '<=', time())->order('id')->limit(50)->column('id');
                foreach ($notices as $id) (new NoticeService())->send((int)$site, (int)$id);
            } catch (\Throwable $e) {
                Log::error('Kingdee local preflight failed', ['site_id' => (int)$site, 'error_type' => get_class($e)]);
            }
        }
    }
}
