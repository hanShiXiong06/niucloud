<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\adminapi\middleware;

use addon\hsx_erp\app\support\ErpRecycleOperationAudit as Audit;
use app\Request;
use Closure;
use think\facade\{Event, Log};
use think\Response;

/** Runs AFTER authorization. Read-only routes never produce request rows; no raw payloads. */
final class ErpOperationFailureAudit
{
    // Authoritative vouchers/stock facts already report success (also skip idempotent success replays).
    private const DOMAIN = [
        'ErpPurchase@create'=>'采购入库','ErpPurchase@cancel'=>'撤销采购','ErpPurchase@adjustCost'=>'调整采购成本',
        'ErpFinance@confirmPayment'=>'登记付款凭证','ErpFinance@confirmPartyPayment'=>'登记付款凭证',
        'ErpFinance@confirmPayableItemsPayment'=>'登记付款凭证','ErpFinance@confirmReceipt'=>'登记收款凭证',
        'ErpFinance@offset'=>'折账','ErpFinance@supplementReceivableSaleDetails'=>'补充应收销售明细',
        'ErpStock@transfer'=>'库存调拨','ErpStock@buyoutConsignment'=>'代卖买断','ErpStock@adjustCost'=>'调整库存成本',
        'ErpStock@adjustRetailPrice'=>'调整零售价','ErpStock@sendRefurbish'=>'送整备','ErpStock@completeRefurbish'=>'整备完工',
        'ErpSale@create'=>'销售出库','ErpSale@cancel'=>'撤销销售单','ErpSale@cancelItem'=>'撤销销售明细',
        'CapitalAccount@entry'=>'登记资金流水','ErpOperatingFinance@create'=>'登记经营收支',
    ];

    // Some alternate paths additionally emit a domain log: a fallback row is created only if none did.
    private const FALLBACK = [
        'ErpGoodsCatalog@saveProduct'=>'保存商品目录','ErpGoodsCatalog@deleteProduct'=>'删除商品目录项',
        'ErpGoodsCatalog@sortNode'=>'调整目录排序','ErpGoodsCatalog@importUpload'=>'上传目录导入文件',
        'ErpGoodsCatalog@importTaskRetry'=>'重试目录导入任务','ErpGoodsCatalog@importTaskDelete'=>'删除目录导入任务',
        'ErpGoodsSpec@saveGroup'=>'保存规格分组','ErpGoodsSpec@deleteGroup'=>'删除规格分组',
        'ErpGoodsSpec@saveItem'=>'保存规格选项','ErpGoodsSpec@deleteItem'=>'删除规格选项',
        'ErpGoodsSpec@saveGrade'=>'保存成色等级','ErpGoodsSpec@deleteGrade'=>'删除成色等级',
        'ErpConfig@save'=>'保存 ERP 配置','ErpConfig@saveTaskAssignmentSettings'=>'保存任务分配设置',
        'ErpConfig@dismissRefurbishReminder'=>'关闭整备提醒','ErpConfig@dismissTurnoverReminder'=>'关闭周转提醒',
        'ErpConfig@saveSaleChannels'=>'保存销售渠道','ErpConfig@saveSaleChannelOptions'=>'保存渠道选项',
        'ErpConfig@saveFinanceCategories'=>'保存财务分类','ErpKpi@saveRules'=>'保存绩效规则',
        'ErpPrint@savePrinter'=>'保存打印机','ErpPrint@deletePrinter'=>'删除打印机','ErpPrint@testPrinter'=>'请求测试打印',
        'ErpPrint@saveTemplate'=>'保存打印模板','ErpPrint@saveScene'=>'保存打印场景',
        'ErpPrint@manual'=>'提交打印请求','ErpPrint@retry'=>'重试打印请求','ErpPrint@clientComplete'=>'登记客户端打印反馈',
        'ErpCounterparty@quickContact'=>'保存往来联系人','ErpCounterparty@quickParty'=>'保存往来单位',
        'ErpCounterparty@updateParty'=>'更新往来单位','ErpCounterparty@updateCredit'=>'更新信用资料',
        'ErpCounterparty@resolveContact'=>'维护往来人关联','ErpSale@saveProfitReportView'=>'保存销售报表视图',
        'ErpWarehouse@save'=>'保存仓库','ErpWarehouse@delete'=>'删除仓库',
        'ErpWarehouse@saveLocation'=>'保存库位','ErpWarehouse@deleteLocation'=>'删除库位',
        'ErpStock@createQuantityProduct'=>'创建数量商品','ErpStock@updateQuantityProductCategory'=>'调整数量商品分类',
        'ErpStock@flow'=>'处理设备流转','ErpStock@syncListing'=>'同步上架资料','ErpStock@handoffListing'=>'交接上架任务',
        'ErpStocktake@create'=>'创建盘点','ErpStocktake@scan'=>'登记盘点扫码','ErpStocktake@resolve'=>'处理盘点差异',
        'ErpStocktake@submit'=>'提交盘点','ErpStocktake@complete'=>'确认盘点','ErpStocktake@cancel'=>'撤销盘点',
        'CapitalAccount@save'=>'保存资金账户','CapitalAccount@delete'=>'删除资金账户',
        'ErpOpening@mallInventoryConfirm'=>'确认商城期初库存','ErpOpening@upload'=>'上传期初资料',
        'ErpOpening@retry'=>'重试期初任务','ErpOpening@confirm'=>'确认期初资料','ErpOpening@delete'=>'删除期初资料',
        'ErpPurchaseReturn@create'=>'提交采购退货申请','ErpPurchaseReturn@confirm'=>'确认采购退货','ErpPurchaseReturn@cancel'=>'撤销采购退货申请',
        'ErpSaleReturn@create'=>'提交销售退货申请','ErpSaleReturn@cancel'=>'撤销销售退货申请','ErpSaleReturn@confirm'=>'确认销售退货',
        'ErpSaleReturn@createAndConfirm'=>'登记销售退货','ErpSaleReturn@compensate'=>'处理销售退货补偿',
    ];

    public function handle(Request $request, Closure $next)
    {
        $action = ''; $state = null;
        try {
            if (in_array($request->method(), ['POST','DELETE'], true) && $request->siteId() > 0 && $request->uid() > 0 && Event::hasListener(Audit::EVENT)) {
                $route = (string)$request->rule()->getRoute();
                $prefix = 'addon\\hsx_erp\\app\\adminapi\\controller\\';
                $candidate = str_starts_with($route,$prefix) ? substr($route,strlen($prefix)) : '';
                if (isset(self::DOMAIN[$candidate]) || isset(self::FALLBACK[$candidate])) {
                    $state = Audit::beginRequest($request); $action = $candidate;
                }
            }
        } catch (\Throwable $err) { self::warn($err); }
        if (!$state) return $next($request);
        try {
            try { $response = $next($request); }
            catch (\Throwable $cause) {
                $this->note($request, $action, true);
                throw $cause;
            }
            try {
                // The framework also returns HTTP 200 with code=0 on business failure.
                if ($response instanceof Response) {
                    $envelope = $response->getData();
                    $ok = $response->getCode() >= 200 && $response->getCode() < 300
                        && is_array($envelope) && in_array($envelope['code'] ?? null, [1,'1'], true);
                    if (!$ok) $this->note($request, $action, true);
                    elseif (!$state->domain && isset(self::FALLBACK[$action]) &&
                        ($action !== 'ErpCounterparty@resolveContact' || $state->changed || ($envelope['data']['auto_created'] ?? false) === true)) {
                        $this->note($request, $action, false);
                    }
                }
            } catch (\Throwable $err) { self::warn($err); }
            return $response;
        } finally { Audit::endRequest($request); }
    }

    private function note(Request $request,string $action,bool $uncertain): void
    {
        try {
            event(Audit::EVENT, [
                'source'=>'erp','event_id'=>'request:'.bin2hex(random_bytes(16)),'site_id'=>(int)$request->siteId(),
                'action'=>'request.'.strtolower(str_replace('@','.',$action)),'result'=>$uncertain ? 'review' : 'recorded',
                'label'=>(self::DOMAIN[$action] ?? self::FALLBACK[$action]).($uncertain ? '请求异常，请核对' : ''),
                'operator_id'=>(int)$request->uid(),'operator_name'=>(string)$request->username(),
                'occurred_at'=>time(),'order_ids'=>[],'device_ids'=>[],
            ]);
        } catch (\Throwable $err) { self::warn($err); }
    }

    private static function warn(\Throwable $error): void
    {
        try { Log::warning('ERP 请求摘要未记录', ['error_type'=>get_class($error)]); } catch (\Throwable $ignored) {}
    }
}
