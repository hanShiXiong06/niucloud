<?php
require_once 'phar://../kingdee-webapi-sdk-v8.2.0.phar/index.php';
use kingdee_cdp_webapi_sdk\sdk\K3CloudApi;
use kingdee_cdp_webapi_sdk\entity\IdentifyInfo;

$Number = "XSTD".date("Ymdhis",time());
$saleorderid = "";
$config=new IdentifyInfo("../config/conf.ini");
$k3api=new K3CloudApi($config);

// 本接口用于实现销售订单 (SAL_SaleOrder)的保存功能 
function saleorder_save()
{
    global $Number;
    global $k3api;
	global $saleorderid;
    $para_data= array("NeedUpDateFields"=> [],"NeedReturnFields"=> [],"IsDeleteEntry"=> "true","SubSystemId"=> "","IsVerifyBaseDataField"=> "False","IsEntryBatchFill"=> "true","ValidateFlag"=> "true","NumberSearch"=> "true","IsAutoAdjustField"=> "False","InterationFlags"=> "","IgnoreInterationFlag"=> "","IsControlPrecision"=> "False","Model"=> array("FBillTypeID"=> array("FNUMBER"=> "XSDD01_SYS"),"FDate"=> "2022-04-27 ","FSaleOrgId"=> array("FNumber"=> "100"),"FCustId"=> array("FNumber"=> "SCMKH100001"),"FReceiveId"=> array("FNumber"=> "SCMKH100001"),"FSaleDeptId"=> array("FNumber"=> "SCMBM000001"),"FSalerId"=> array("FNumber"=> "SCMYG000001_SCMGW000001_1"),"FSettleId"=> array("FNumber"=> "SCMKH100001"),"FChargeId"=> array("FNumber"=> "SCMKH100001"),"FSaleOrderFinance"=> array("FSettleCurrId"=> array("FNumber"=> "PRE001"),"FIsPriceExcludeTax"=> 'true',"FIsIncludedTax"=> 'true',"FExchangeTypeId"=> array("FNumber"=> "HLTX01_SYS")),"FSaleOrderEntry"=> [array("FRowType"=> "Standard","FMaterialId"=> array("FNumber"=> "SCMWL100002"),"FUnitID"=> array("FNumber"=> "Pcs"),"FQty"=> 10,"FPriceUnitId"=> array("FNumber"=> "Pcs"),"FPrice"=> 8.849558,"FTaxPrice"=> 10,"FEntryTaxRate"=> 13,"FDeliveryDate"=> "2022-04-27 ","FStockOrgId"=> array("FNumber"=> "100"),"FSettleOrgIds"=> array("FNumber"=> "100"),"FSupplyOrgId"=> array("FNumber"=> "100"),"FOwnerTypeId"=> "BD_OwnerOrg","FOwnerId"=> array("FNumber"=> "100"),"FReserveType"=> "1","FPriceBaseQty"=> 10,"FStockUnitID"=> array("FNumber"=> "Pcs"),"FStockQty"=> 10,"FStockBaseQty"=> 10,"FOUTLMTUNIT"=> "SAL","FOutLmtUnitID"=> array("FNumber"=> "Pcs"),"FAllAmountExceptDisCount"=> 100,"FOrderEntryPlan"=> [array("FPlanDate"=> "2022-04-27 ","FPlanQty"=> 10)])],"FSaleOrderPlan"=> [array("FRecAdvanceRate"=> 100,"FRecAdvanceAmount"=> 100)],"FBillNo"=>"$Number",));
    $response=$k3api->save("SAL_SaleOrder",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
		$saleorderid = $json_response ->Result->Id;
        echo "销售订单保存接口：".$response."\n";
    }
    else{
        assert(false,"销售订单保存接口失败: ".$response."\n");
    }
}

// 本接口用于实现销售订单 (SAL_SaleOrder)的提交功能
function saleorder_submit()
{
    global $Number;
    global $k3api;
    $para_data = array("CreateOrgId"=>0,"Numbers"=>["$Number"],"Ids"=>"","SelectedPostId"=>0,"NetworkCtrl"=>"","IgnoreInterationFlag"=>"");
    $response=$k3api->submit("SAL_SaleOrder",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "销售订单提交接口：".$response."\n";
    }
    else{
        assert(false,"销售订单提交接口失败: ".$response."\n");
    }
}


// 本接口用于实现销售订单 (SAL_SaleOrder)的审核功能
function saleorder_audit()
{
    global $Number;
    global $k3api;
    $para_data = array("CreateOrgId"=> 0,"Numbers"=> ["$Number"],"Ids"=> "","InterationFlags"=> "","NetworkCtrl"=> "","IsVerifyProcInst"=> "","IgnoreInterationFlag"=> "","UseBatControlTimes"=> "false");
    $response=$k3api->audit("SAL_SaleOrder",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "销售订单审核接口：".$response."\n";
    }
    else{
        assert(false,"销售订单审核接口失败: ".$response."\n");
    }
}


// 本接口用于实现自定义销售订单新变更单 (SAL_SaleOrder)的功能
function savexsaleorder()
{
    global $Number;
    global $k3api;
	global $saleorderid;
	$uri = "Kingdee.K3.SCM.WebApi.ServicesStub.SaveXSaleOrderWebApi.SaveXSaleOrder";
    $post_data_str = "{'SaleOrderBillNo':'$Number','SaleOrderBillId':$saleorderid}";
    $response = $k3api->execute($uri, $post_data_str);
    $json_response = json_decode($response);
    if ($json_response ->IsSuccess)
    {
        echo "销售订单新变更单接口：".$response."\n";
    }
    else{
        assert(false,"销售订单新变更单接口失败: ".$response."\n");
    }
}


saleorder_save();
saleorder_submit();
saleorder_audit();
savexsaleorder();


?> 