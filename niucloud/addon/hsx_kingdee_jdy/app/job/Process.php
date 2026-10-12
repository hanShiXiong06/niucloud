<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\job;
use addon\hsx_kingdee_jdy\app\service\ConfigService;
use addon\hsx_kingdee_jdy\app\service\TaskService;
use addon\hsx_kingdee_jdy\app\service\NoticeService;
use app\model\sys\SysConfig;
use core\base\BaseJob;
use think\facade\Db;
use think\facade\Log;
final class Process extends BaseJob
{
    public function doJob($data): bool
    {
        foreach (SysConfig::where('config_key', ConfigService::KEY)->column('site_id') as $site) {
            try {
                $config = new ConfigService();
                if (!$config->installed((int)$site) || $config->get((int)$site)['mode'] !== 'preview') continue;
                $ids = Db::name('hsx_kingdee_jdy_task')->where('site_id', $site)->whereIn('status', ['pending', 'retry', 'processing'])
                    ->where('next_retry_at', '<=', time())->order('id')->limit(50)->column('id');
                foreach ($ids as $id) (new TaskService())->process((int)$site, (int)$id);
                $ids = Db::name('hsx_kingdee_jdy_task')->where('site_id', $site)->where('status', 'blocked')
                    ->whereIn('notice_status', ['pending', 'retry'])->where('notice_next_at', '<=', time())->order('id')->limit(50)->column('id');
                foreach ($ids as $id) (new NoticeService())->send((int)$site, (int)$id);
            } catch (\Throwable $error) {
                Log::error('hsx_kingdee_jdy local queue failure; site=' . (int)$site . '; type=' . get_class($error));
            }
        }
        return true;
    }
}
