<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\listener;

use addon\hsx_boyuan\app\service\{ConfigService,PermissionService};

final class PurchaseActions
{
    public function handle(array $event): array
    {
        $site=(int)($event['site_id']??0);
        if ($site<=0 || (int)($event['purchase_id']??0)<=0 || ($event['status']??'')==='void') return [];
        foreach ($event['items']??[] as $item) if (($item['item_type']??'device')==='standard') return [];
        try {
            $configService=new ConfigService();
            if (!$configService->installed($site)) return [];
            $config=$configService->get($site); $p=$config['profiles'][$config['mode']];
            if ($config['mode']==='mock' || ($p['protocol']??'')!=='openapi_v153') return [];
            (new PermissionService())->assertApi('get','boyuan/tasks/<id>');
            (new PermissionService())->assertApi('post','boyuan/tasks/<id>/action');
            return ['actions'=>[['key'=>'boyuan_purchase','label'=>'核对并同步博远',
                'pc_route'=>'/hsx_boyuan/purchase','mobile_path'=>'/addon/hsx_boyuan/pages/workbench/index']]];
        } catch (\Throwable $e) { return []; }
    }
}
