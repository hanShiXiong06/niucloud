<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\listener;

use addon\hsx_boyuan\app\service\ConfigService;
use addon\hsx_boyuan\app\service\CategoryCatalog;
use addon\hsx_boyuan\app\service\OpeningMasterService;
use addon\hsx_boyuan\app\service\PurchaseDeviceCatalog;
use think\facade\Db;

final class CatalogMasterPolicy
{
    public function handle(array $event): array
    {
        $site=(int)($event['site_id']??0);
        $config=new ConfigService();
        if ($site<1 || !$config->installed($site)) return [];
        $c=$config->get($site,false); $mode=$c['mode']; $p=$c['profiles'][$mode];
        if (!in_array($mode,['test','production'],true) || $p['protocol']!=='openapi_v153' || $p['connection_key']==='' || $p['account_id']==='') return [];
        // 暂停制单不交还主档。已取回过分类的同一账套换密钥后也不开放本地修改。
        if (empty($p['probe_at']) && empty($c['enabled']) && !Db::name('hsx_boyuan_reference_sync')->master()
            ->where(['site_id'=>$site,'mode'=>$mode,'connection_key'=>$p['connection_key'],'resource'=>'categories'])->count()) return [];
        $claim=['provider'=>'hsx_boyuan','connection'=>$p['connection_key'],'label'=>'博远 ERP',
            'reason'=>'本站商品目录由博远 ERP 维护。请在博远新增、修改或删除分类，保存后回到商品目录，点击「同步最新分类」。'];
        if (($event['action']??'')==='categories' || ($event['action']??'')==='leaf')
            $claim+=CategoryCatalog::selection($site,$p['connection_key'],$event);
        if (($event['action']??'')==='purchase_catalog')
            $claim['picker']=PurchaseDeviceCatalog::browse($site,$p['connection_key'],$event);
        if (($event['action']??'')==='resolve_purchase_device')
            $claim['resolution']=PurchaseDeviceCatalog::resolve($site,$p['connection_key'],$event);
        if (($event['action']??'')==='product_category' && ($event['source_key']??'')===OpeningMasterService::productSourceKey($site,$p['connection_key']))
            $claim['current_path']=CategoryCatalog::currentPath($site,$p['connection_key'],(string)$event['category_source_id']);
        return $claim;
    }
}
