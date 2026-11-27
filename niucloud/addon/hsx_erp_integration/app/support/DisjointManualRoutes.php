<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\support;

use addon\hsx_erp_integration\app\contract\{DisjointManualScopeInterface,ManualDocumentAdapterInterface};

final class DisjointManualRoutes
{
    /** Fail closed on general connections, duplicate scopes, different credentials, purposes or adapter. */
    public static function permits(int $site,array $a,array $b,$driver): bool
    {
        if(!$driver instanceof DisjointManualScopeInterface || !$driver instanceof ManualDocumentAdapterInterface || $site<1 ||
            (int)($a['site_id']??0)!==$site || (int)($b['site_id']??0)!==$site || empty($a['adapter_code']) ||
            $a['adapter_code']!==($b['adapter_code']??null) || ($a['sync_mode']??null)!=='manual' || ($b['sync_mode']??null)!=='manual' ||
            ($a['last_test_result']??null)!=='success' || ($b['last_test_result']??null)!=='success') return false;
        $kinds=ConnectionFields::values($a['business_types']??null);
        if(array_keys($kinds)!==[0] || $kinds!==ConnectionFields::values($b['business_types']??null)) return false;
        $ca=ConnectionFields::values($a['config']??null); $cb=ConnectionFields::values($b['config']??null);
        foreach(['mode','account_code','app_id','api_url','gateway_path'] as $k)
            if(!is_string($ca[$k]??null) || $ca[$k]==='' || $ca[$k]!==($cb[$k]??null)) return false;
        $x=$ca['manual_scope']??null; $y=$cb['manual_scope']??null;
        if(!is_string($x) || !is_string($y) || $x===$y || !preg_match('/^[a-zA-Z0-9._:-]{1,128}$/D',$x) || !preg_match('/^[a-zA-Z0-9._:-]{1,128}$/D',$y)) return false;
        try {
            if(BridgeTarget::key($a)!==BridgeTarget::key($b)) return false;
            $pairs=$driver->manualDisjointScopes($site);
        } catch(\Throwable $e) { return false; }
        foreach($pairs as $pair) if($pair===[$x,$y] || $pair===[$y,$x]) return true;
        return false;
    }

    /** Zero matched scopes means unrelated event; ambiguous or withdrawn authority creates no tasks. */
    public static function forEvent(int $site,array $routes,$driver,array $event): ?array
    {
        $routes=array_values($routes);
        if(count($routes)!==2 || !self::permits($site,$routes[0],$routes[1],$driver))
            throw new \DomainException('同一业务存在未明确区分范围的启用连接；未生成或发送任务');
        $matches=[];
        foreach($routes as $r) {
            if((int)($r['bridge_started_at']??0)<1) throw new \DomainException('限定连接缺少启用时间，未接管');
            // Match scope first: time cutoffs must not make an overlapping scope look unambiguous.
            if($driver->admitsManualFact($site,ConnectionFields::values($r['config']),$event)) $matches[]=$r;
        }
        if(count($matches)>1) throw new \DomainException('原单命中多个范围；请核对授权，未生成或发送任务');
        return $matches[0]??null;
    }
}
