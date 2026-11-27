<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\listener;

use addon\hsx_boyuan\app\service\ConfigService;

final class CostMaintenanceHint
{
    public function handle(array $event): string
    {
        $site=(int)($event['site_id']??0);
        if($site<=0 || $site!==(int)request()->siteId()) return '';
        $svc=new ConfigService();
        if(!$svc->installed($site)) return '';
        return self::message($svc->get($site));
    }
    public static function message(array $c): string
    {
        $p=$c['profiles'][$c['mode']]??[];
        if(empty($c['enabled']) || !in_array($c['mode'],['test','production'],true) || empty($p['probe_at']) || empty($p['connection_key'])) return '';
        return '成本调整不会同步到博远。请在本站保存后，到博远按同一串号调整同一笔成本，不要重复付款。';
    }
}
