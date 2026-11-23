<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\support;

use core\exception\AdminException;
use think\facade\Db;

/** 商品目录归属。未注册外部来源时保持本站原有的维护方式。 */
final class ErpCatalogAuthority
{
    public static function owner(int $site,array $context=[]): array
    {
        $owners=[];
        foreach ((array)event('HsxErpCatalogMasterPolicy',['site_id'=>$site]+$context) as $claim) {
            if ($claim===null || $claim===[]) continue;
            if (!is_array($claim) || !is_string($claim['provider']??null) || !preg_match('/^[a-z][a-z0-9_]*$/D',$claim['provider'])
                || empty($claim['connection']) || empty($claim['label']) || empty($claim['reason'])) {
                throw new AdminException('无法确认商品目录由谁维护，请刷新后重试');
            }
            $owners[$claim['provider']]=$claim;
        }
        if (count($owners)>1) throw new AdminException('本站有多个商品目录来源，请联系管理员确认');
        return $owners ? reset($owners) : [];
    }

    public static function policy(int $site): array
    {
        $owner=self::owner($site);
        return ['managed'=>(bool)$owner,'provider'=>$owner['provider']??'','label'=>$owner['label']??'','reason'=>$owner['reason']??''];
    }

    /** Lock inside the write transaction, using the same site row as connection changes. */
    public static function checkWrite(int $site,bool $lock=false,?array $origin=null): void
    {
        if ($lock && !Db::name('site')->master()->where('site_id',$site)->lock(true)->value('site_id')) throw new AdminException('商品目录站点不存在');
        $owner=self::owner($site);
        if ($origin!==null) {
            if (!$owner || $owner['provider']!==$origin['provider'] || $owner['connection']!==$origin['connection']) throw new AdminException('资料来源或账套已改变，请重新预览后再建档');
        } elseif ($owner) throw new AdminException($owner['reason']);
    }
}
