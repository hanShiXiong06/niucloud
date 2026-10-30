<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\job;
use addon\hsx_boyuan\app\service\{ConfigService,TaskService,NoticeService};
use app\model\sys\SysConfig;
use core\base\BaseJob;
use think\facade\{Db,Log};
final class Process extends BaseJob
{
    public function doJob($data): bool
    {
        foreach (SysConfig::where('config_key',ConfigService::KEY)->column('site_id') as $site) try {
            if (!(new ConfigService())->installed((int)$site)) continue;
            (new \addon\hsx_boyuan\app\service\ReferenceService())->tick((int)$site,10);
            (new TaskService())->tick((int)$site);
            foreach (Db::name('hsx_boyuan_task')->where('site_id',$site)->whereIn('status',['blocked','rejected','partial','attention'])->where('notice_at','<',time()-900)->order('id')->limit(20)->column('id') as $id) (new NoticeService())->send((int)$site,(int)$id);
        } catch (\Throwable $e) { Log::error('hsx_boyuan worker failure; site='.(int)$site.'; type='.get_class($e)); }
        return true;
    }
}
