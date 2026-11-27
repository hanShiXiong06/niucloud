<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;

use addon\hsx_erp_integration\app\support\{BridgeTarget,ConnectionFields,OriginalEventIdentity};
use app\model\sys\{SysUser,SysUserRole};
use think\facade\Db;

/** A single original recycle purchase, never a site-wide release or a finance permission. */
final class ProductionPurchasePilot
{
    public const SITE=100004, ACCOUNT='89096', IMEI='990000261121901';
    public const OP='PUR_PILOT_100004_89096_990000261121901';
    public const NEXT_IMEI='990000261121902', NEXT_OP='PUR_PILOT_100004_89096_990000261121902';
    public const FIRST_SCOPE='purchase_first_100004_89096', NEXT_SCOPE='purchase_pricer_100004_89096_990000261121902';
    public const THIRD_IMEI='990000261121903', THIRD_OP='PUR_PILOT_100004_89096_990000261121903',
        THIRD_SCOPE='purchase_pricer_100004_89096_990000261121903', THIRD_RECYCLE_NO='R202611212319294498';
    public const BUYER='fa558859-7590-45a2-9006-7a560c357cdf', BUYER_NAME='韩世雄';
    public const COMPANY='e8776587-0a6d-4949-9bdd-30069f8a3842', WAREHOUSE='6e73a303-b63a-4976-a512-5fbfff691a8a',
        SUPPLIER='a649d6a5-efd5-4682-a85e-44c0e3e1a4a8', PRODUCT='3e5b43a2-3a88-49fe-819b-b60889ae0070';
    public static function operation($imei): ?string
    {
        return match($imei){self::IMEI=>self::OP,self::NEXT_IMEI=>self::NEXT_OP,self::THIRD_IMEI=>self::THIRD_OP,default=>null};
    }
    public static function pricerOperation($op): bool
    {
        return in_array($op,[self::NEXT_OP,self::THIRD_OP],true);
    }
    public static function pricerIntent(array $request,$imei): bool
    {
        $op=self::operation($imei);
        return self::pricerOperation($op) && ($request['pilot_operation_id']??null)===$op &&
            ($op!==self::THIRD_OP || ($request['pilot_recycle_no']??null)===self::THIRD_RECYCLE_NO);
    }
    public static function scoped(string $scope,$imei): bool
    {
        return match($imei){self::NEXT_IMEI=>$scope===self::NEXT_SCOPE,self::THIRD_IMEI=>$scope===self::THIRD_SCOPE,
            self::IMEI=>in_array($scope,['',self::FIRST_SCOPE],true),default=>false};
    }
    /** Only locally classified failures from the read-only buyer query, before the create transport. */
    public static function buyerPreflightFailure(\Throwable $e): bool
    {
        return $e instanceof Problem && $e->effect==='none' &&
            in_array($e->reason,['PILOT_BUYER_NOT_ELIGIBLE','PILOT_BUYER_READ_UNAVAILABLE'],true);
    }
    /** Never pass a provider's free-text rejection, URI, credentials or other documents into an ERP summary. */
    public static function prepareIssue(\Throwable $error,string $checkpoint): array
    {
        $issue=ReceiptFacts::issue($error,'prepare');
        $code=$issue['code'];
        $detail=match($code) {
            'PILOT_BUYER_NOT_ELIGIBLE'=>'博远没有确认定价人的采购员资格，请核对员工档案。',
            'PILOT_BUYER_READ_UNAVAILABLE'=>'未能读取博远采购员资格，请检查员工档案和连接。',
            'ORIGINAL_PURCHASE_REMOTE_EXISTS'=>'博远的采购或库存记录已有此串号，请核对原单。',
            'PURCHASE_PAGE_INCOMPLETE','PURCHASE_PAGE_INVALID','PURCHASE_PAGE_DUPLICATE','PURCHASE_PAGE_LIMIT'=>'排重查询返回的采购明细不完整或重复，请人工核对。',
            'PURCHASE_IDENTITY_INVALID'=>'排重查询缺少有效的原始单据或明细编号，无法确认没有重复。',
            'ORIGINAL_PURCHASE_REMOTE_UNKNOWN'=>'采购明细、串号或当前库存未能相互核实，不能排除重复。',
            default=>$checkpoint==='post_check'?'排重后原单、连接或处理权限发生变化，请核对本张原单。':'未能取得有效的博远排重查询结果，请核对连接及本张原单。',
        };
        if($code==='ORIGINAL_PURCHASE_REMOTE_UNKNOWN') {
            // Only allow our fixed labels, not arbitrary upstream/root exception text.
            foreach(['有效状态异常','串号不一致','仓库或商品与采购行不一致','明细有行未出现在串号清单','串号缺失、被遮盖','没有精确关联到指定日期','未找全当日该单的串号明细','无法识别日期、单据或串号','超出单笔试单核对范围','未返回可核对的实时库存结果','单据信息与采购行矛盾','原单身份冲突','不属于当前账套','不属于当日该单','原始串号缺失或互相矛盾'] as $label)
                if(str_contains($error->getMessage(),$label)) { $detail='排重核对未通过：'.$label.'。请人工核对博远原单。'; break; }
        }
        if(str_starts_with($code,'OPENAPI_')) $issue['code']='PREFLIGHT_REMOTE_UNAVAILABLE';
        return $issue+['checkpoint'=>$checkpoint,'notice'=>'未发送采购单：'.$detail];
    }

    public static function unsentNotice(array $task,array $result): string
    {
        $i=$result['last_issue']??[];
        if(!self::pricerOperation($task['operation_id']??'') || ($task['status']??'')!=='attention' || ($task['side_effect']??'')!=='none' ||
            !empty($task['external_no']) || !empty($result['created']) || !empty($result['external_no']) || ($i['stage']??'')!=='prepare' ||
            !in_array($i['checkpoint']??'',['duplicate_check','post_check','buyer_check'],true)) return '';
        return is_string($i['notice']??null) && str_starts_with($i['notice'],'未发送采购单：') ? $i['notice']:'';
    }
    public static function requireNewSelection(array $head,array $row,array $pick): void
    {
        if(($head['sales_id']??null)!==self::BUYER || ($head['sales_name']??null)!==self::BUYER_NAME ||
            ($head['warehouse_id']??null)!==self::WAREHOUSE || ($head['supplier_id']??null)!==self::SUPPLIER ||
            ($row['product_id']??null)!==self::PRODUCT || ($pick['company_id']??null)!==self::COMPANY)
            throw new Problem('PILOT_SELECTION_CHANGED','本次试单只允许已确认的定价员韩世雄、大花仓、原供方和白色128GB商品，未发送');
    }
    public const REMARK='100004接口联调测试，非实物交收，单台1元挂账，不付款';
    public static function profile(int $site,array $c,array $p): bool
    {
        return $site===self::SITE && ($c['mode']??null)==='production' && ($c['purchase_mode']??null)==='manual'
            && empty($c['enabled']) && empty($c['purchase_enabled']) && empty($c['finance_enabled']) && empty($c['uat_payment_enabled'])
            && empty($p['voucher_release']['at']) && ($p['protocol']??null)==='openapi_v153'
            && ($p['endpoint']??null)===ProductionVoucher::ENDPOINT && ($p['gateway_path']??null)==='/gateway.do'
            && ($p['account_id']??null)===self::ACCOUNT && !empty($p['app_id']) && !empty($p['connection_key'])
            && (!empty($p['app_secret_cipher']) || ($p['app_secret_configured']??false)===true)
            && !empty($p['probe_at']) && !empty($p['contract_confirmed']);
    }
    public static function event(int $site,array $e): bool
    {
        $s=$e['payload']??[]; $l=$s['lines']??[];
        return $site===self::SITE && ($e['site_id']??0)===$site && ($e['event_version']??0)===1
            && ($e['event_name']??'')==='erp.integration.document.confirmed.v1' && ($e['aggregate_type']??'')==='purchase'
            && (int)($e['aggregate_id']??0)>0 && ($s['source_document_id']??'')==='purchase:'.$e['aggregate_id']
            && ($s['kind']??'')==='purchase' && ($s['purchase_source']??'')==='ordinary_customer'
            && is_array($l) && array_keys($l)===[0] && self::operation($l[0]['imei']??null)!==null
            && (!self::pricerOperation(self::operation($l[0]['imei'])) || ($l[0]['pricing_operator']['ref']??null)==='staff:1')
            && in_array($l[0]['quantity']??null,[1,'1','1.00','1.000'],true) && ($l[0]['amount']??'')==='1.00'
            && ($l[0]['imei']!==self::THIRD_IMEI || Db::name('erp_purchase_order')->where(['site_id'=>$site,'id'=>(int)$e['aggregate_id'],
                'origin_plugin'=>'hsx_recycle','origin_type'=>'hsx_recycle.recycle_purchase','origin_id'=>'34','origin_no'=>self::THIRD_RECYCLE_NO])->count()===1);
    }
    public static function admin(int $site,int $uid): void
    {
        if((int)request()->uid()!==$uid || (int)request()->siteId()!==$site || !self::activeAdmin($site,$uid))
            throw new Problem('PILOT_ADMIN_REQUIRED','仅允许该站点的在职管理员明确确认这一笔正式采购');
    }
    public static function activeAdmin(int $site,int $uid): bool
    {
        return $site===self::SITE && $uid>0 && SysUser::where(['uid'=>$uid,'status'=>1])->count()>0 &&
            SysUserRole::where(['site_id'=>$site,'uid'=>$uid,'status'=>1,'is_admin'=>1])->count()>0;
    }
    /** The caller holds the site lock. Read independently from the ERP outbox and from the *one* live bridge. */
    public static function assertBinding(int $site,array $p,int $bridge,string $eventId,array $snapshot,int $vendor=0): void
    {
        $q=Db::name('erp_integration_sync_task')->where(['site_id'=>$site,'id'=>$bridge,'origin_event_id'=>$eventId,'business_type'=>'purchase','source_type'=>'erp_purchase'])->find();
        $routes=Db::name('erp_integration_connection')->where(['site_id'=>$site,'status'=>'enabled'])->select()->toArray();
        $routes=array_values(array_filter($routes,static fn($r)=>in_array('purchase',ConnectionFields::values($r['business_types']),true)));
        $imei=$snapshot['lines'][0]['imei']??'';
        if(count($routes)===2 && self::pricerOperation(self::operation($imei))) {
            // Only these two immutable, mutually exclusive scopes may share the account. Never pick the first route.
            $scopeRoutes=[];
            foreach($routes as $route) {
                $rc=ConnectionFields::values($route['config']??null);$scope=$rc['manual_scope']??'';
                if(!in_array($scope,[self::NEXT_SCOPE,self::THIRD_SCOPE],true) || isset($scopeRoutes[$scope]) ||
                    $route['adapter_code']!=='boyuan' || $route['sync_mode']!=='manual' || $route['last_test_result']!=='success' || (int)$route['bridge_started_at']<=0 ||
                    ConnectionFields::values($route['business_types'])!==['purchase'] || ($rc['mode']??'')!=='production' || ($rc['account_code']??'')!==self::ACCOUNT ||
                    ($rc['app_id']??'')!==$p['app_id'] || ($rc['api_url']??'')!==$p['endpoint'] || ($rc['gateway_path']??'')!==$p['gateway_path']) break;
                $scopeRoutes[$scope]=$route;
            }
            if(count($scopeRoutes)===2) $routes=[$scopeRoutes[$imei===self::THIRD_IMEI?self::THIRD_SCOPE:self::NEXT_SCOPE]];
        }
        $c=$routes[0]??[]; $cfg=ConnectionFields::values($c['config']??null);
        if(!$q || !empty($q['external_doc_no']) || !empty($q['external_doc_id']) || count($routes)!==1 || ($c['adapter_code']??'')!=='boyuan' || ($c['sync_mode']??'')!=='manual' ||
            ($c['last_test_result']??'')!=='success' || (int)($c['bridge_started_at']??0)<1 || (int)$q['connection_id']!==(int)$c['id'] ||
            ConnectionFields::values($c['business_types'])!==['purchase'] || BridgeTarget::key($c)!==$q['target_key'] ||
            ($cfg['mode']??'')!=='production' || ($cfg['account_code']??'')!==self::ACCOUNT || ($cfg['app_id']??'')!==$p['app_id'] ||
            ($cfg['api_url']??'')!==$p['endpoint'] || ($cfg['gateway_path']??'')!==$p['gateway_path'] || !empty($q['error_code']))
            throw new Problem('PILOT_BRIDGE_REQUIRED','未找到唯一且已核验的本站正式采购桥，或目标账套、原事件不一致');
        $r=Db::name('erp_outbox_event')->where(['site_id'=>$site,'event_id'=>$eventId,'event_name'=>'erp.integration.document.confirmed.v1'])->field('occurred_at,payload_json')->find();
        $e=ConnectionFields::values($r['payload_json']??null); $copy=ConnectionFields::values($q['payload']??null);
        if(!$r || (int)$r['occurred_at']<(int)$c['bridge_started_at'] || !self::event($site,$e) || !self::scoped($cfg['manual_scope']??'',$imei) ||
            !OriginalEventIdentity::same($e,$copy) || !OriginalEventIdentity::same($snapshot,$e['payload']) ||
            (int)$q['source_id']!==(int)$e['aggregate_id'] || $q['source_no']!==$snapshot['source_document_no'] ||
            $q['fact_key']!==$snapshot['source_document_id'].':'.$snapshot['batch_ref'] ||
            ($vendor>0 && (int)(ConnectionFields::values($q['receipt_json'])['vendor_task_id']??0)!==$vendor))
            throw new Problem('PILOT_SOURCE_CHANGED','试单必须由同一站点、同一回收原采购及其桥任务发起');
        $id=(int)$e['aggregate_id']; $asset=(int)substr($snapshot['lines'][0]['device_ref'],6);
        $h=Db::name('erp_purchase_order')->where(['site_id'=>$site,'id'=>$id,'status'=>'completed','origin_plugin'=>'hsx_recycle'])->find();
        if($imei===self::THIRD_IMEI) {
            $row=Db::name('erp_purchase_item')->where(['site_id'=>$site,'purchase_order_id'=>$id,'asset_id'=>$asset])->field('id,spec_json')->find();
            $spec=Contract::decode($row['spec_json']??'');
            if(!$h || $h['origin_type']!=='hsx_recycle.recycle_purchase' || (string)$h['origin_id']!=='34' || $h['origin_no']!==self::THIRD_RECYCLE_NO ||
                !$row || $snapshot['lines'][0]['source_line_id']!=='purchase_line:'.$row['id'] || !in_array($spec['source_device_id']??null,[45,'45'],true) ||
                ($spec['source_order_no']??null)!==self::THIRD_RECYCLE_NO)
                throw new Problem('PILOT_SOURCE_CHANGED','本次授权仅适用于指定回收单的原设备、入库明细和串号，未发送');
        }
        $ap=Db::name('erp_payable')->where(['site_id'=>$site,'source_type'=>'purchase_asset','source_id'=>$asset])->limit(2)->select()->toArray();
        if(!$h || Db::name('erp_purchase_item')->where(['site_id'=>$site,'purchase_order_id'=>$id])->count()!=1 ||
            !Db::name('erp_asset')->where(['site_id'=>$site,'id'=>$asset,'purchase_order_id'=>$id,'imei'=>$imei,'status'=>'in_stock'])->count() ||
            count($ap)!==1 || (int)$ap[0]['party_id']!==(int)$h['party_id'] || (string)$ap[0]['amount']!=='1.00' ||
            (string)$ap[0]['settled_amount']!=='0.00' || ($ap[0]['status']??'')!=='pending')
            throw new Problem('PILOT_UNPAID_DEVICE_REQUIRED','只允许该串号一台、已入库、未付款且尚欠1元的回收原采购');
    }
}
