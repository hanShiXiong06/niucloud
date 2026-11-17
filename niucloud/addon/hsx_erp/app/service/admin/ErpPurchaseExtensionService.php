<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\service\admin;

use think\facade\Log;

/** Optional purchase actions supplied by installed business integrations. */
final class ErpPurchaseExtensionService
{
    public static function actions(int $site, array $order): array
    {
        $actions=[];
        try {
            foreach ((array)event('HsxErpPurchaseActions', ['site_id'=>$site,'purchase_id'=>(int)$order['id'],
                'status'=>(string)($order['status']??''),'items'=>$order['items']??[]]) as $result) {
                foreach ((array)($result['actions']??[]) as $action) {
                    if (!is_array($action) || !preg_match('/^[a-z0-9_]{1,64}$/D',(string)($action['key']??''))) continue;
                    if (!preg_match('#^/[A-Za-z0-9_/-]+$#D',(string)($action['pc_route']??''))
                        || !preg_match('#^/addon/[A-Za-z0-9_/-]+$#D',(string)($action['mobile_path']??''))) continue;
                    $actions[$action['key']]=['key'=>$action['key'],'label'=>mb_substr((string)($action['label']??'外部同步'),0,30),
                        'pc_route'=>$action['pc_route'],'mobile_path'=>$action['mobile_path']];
                }
            }
        } catch (\Throwable $e) {
            // Optional extensions must never prevent reading an established purchase.
            Log::warning('ERP采购扩展入口暂不可用', ['site_id'=>$site,'purchase_id'=>(int)$order['id'],'type'=>get_class($e)]);
        }
        return array_values($actions);
    }
}
