<?php
require_once 'phar://../kingdee-webapi-sdk-v8.2.0.phar/index.php';
use kingdee_cdp_webapi_sdk\sdk\K3CloudApi;
use kingdee_cdp_webapi_sdk\entity\IdentifyInfo;

$Number = "CGDD".date("Ymdhis",time());
$chaseorderid = '';
$config=new IdentifyInfo("../config/conf.ini");
$k3api=new K3CloudApi($config);

// 本接口用于实现采购订单 (PUR_PurchaseOrder)的保存功能 
function PurchaseOrder_save()
{
    global $Number;
    global $k3api;
    global $chaseorderid;
    $para_data = array("NeedUpDateFields"=>[],"NeedReturnFields"=>[],"IsDeleteEntry"=> "true", "SubSystemId"=> "", "IsVerifyBaseDataField"=> "false", "IsEntryBatchFill"=> "true", "ValidateFlag"=> "true", "NumberSearch"=> "true", "IsAutoAdjustField"=> "false", "InterationFlags"=> "", "IgnoreInterationFlag"=> "", "IsControlPrecision"=> "false", "ValidateRepeatJson"=> "false", "Model"=> array( "FBillTypeID"=> array( "FNUMBER"=> "CGDD01_SYS" ), "FDate"=> "2022-09-29 ", "FSupplierId"=> array( "FNumber"=> "FINGYS000100" ), "FPurchaseOrgId"=> array( "FNumber"=> "100" ), "FProviderId"=> array( "FNumber"=> "FINGYS000100" ), "FSettleId"=> array( "FNumber"=> "FINGYS000100" ), "FChargeId"=> array( "FNumber"=> "FINGYS000100" ), "FChangeStatus"=> "A", "FACCTYPE"=> "Q", "FPOOrderFinance"=> array( "FSettleCurrId"=> array( "FNumber"=> "PRE001" ), "FExchangeTypeId"=> array( "FNumber"=> "HLTX01_SYS" ), "FExchangeRate"=> 1, "FPriceTimePoint"=> "1", "FFOCUSSETTLEORGID"=> array( "FNumber"=> "100" ), "FIsIncludedTax"=> true, "FISPRICEEXCLUDETAX"=> true, "FLocalCurrId"=> array( "FNumber"=> "PRE001" ), "FSupToOderExchangeBusRate"=> 1 ), "FPOOrderEntry"=> [ array( "FProductType"=> "1", "FMaterialId"=> array( "FNumber"=> "BOSTEST001" ), "FMaterialDesc"=> "BOSTEST001", "FUnitId"=> array( "FNumber"=> "Pcs" ), "FQty"=> 1, "FPriceUnitId"=> array( "FNumber"=> "Pcs" ), "FPriceUnitQty"=> 1, "FPriceBaseQty"=> 1, "FDeliveryDate"=> "2022-09-29 ", "FEntryTaxRate"=> 13, "FRequireOrgId"=> array( "FNumber"=> "100" ), "FReceiveOrgId"=> array( "FNumber"=> "100" ), "FEntrySettleOrgId"=> array( "FNumber"=> "100" ), "FStockUnitID"=> array( "FNumber"=> "Pcs" ), "FStockQty"=> 1, "FStockBaseQty"=> 1, "FDeliveryMaxQty"=> 1, "FDeliveryMinQty"=> 1, "FDeliveryEarlyDate"=> "2022-09-29 ", "FDeliveryLastDate"=> "2022-09-29 ", "FPriceCoefficient"=> 1, "FPlanConfirm"=> true, "FSalUnitID"=> array( "FNumber"=> "Pcs" ), "FSalQty"=> 1, "FCentSettleOrgId"=> array( "FNumber"=> "100" ), "FDispSettleOrgId"=> array( "FNumber"=> "100" ), "FDeliveryStockStatus"=> array( "FNumber"=> "KCZT02_SYS" ), "FSalBaseQty"=> 1, "FEntryPayOrgId"=> array( "FNumber"=> "100" ), "FEntryDeliveryPlan"=> [ array( "FDeliveryDate_Plan"=> "2022-09-29 ", "FPlanQty"=> 1, "FPREARRIVALDATE"=> "2022-09-29 " ) ] ) ], "FBillNo"=>"$Number"));
     $response=$k3api->save("PUR_PurchaseOrder",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        $chaseorderid = $json_response ->Result->Id;
        echo "采购订单保存接口：".$response."\n";
    }
    else{
        assert(false,"采购订单保存接口失败: ".$response."\n");
    }
}

// 本接口用于实现采购订单 (PUR_PurchaseOrder)的提交功能
function purchaseorder_submit()
{
    global $Number;
    global $k3api;
    $para_data = array("CreateOrgId"=>0,"Numbers"=>["$Number"],"Ids"=>"","SelectedPostId"=>0,"NetworkCtrl"=>"","IgnoreInterationFlag"=>"");
    $response=$k3api->submit("PUR_PurchaseOrder",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "采购订单提交接口：".$response."\n";
    }
    else{
        assert(false,"采购订单提交接口失败: ".$response."\n");
    }
}


// 本接口用于实现采购订单 (PUR_PurchaseOrder)的审核功能
function purchaseorder_audit()
{
    global $Number;
    global $k3api;
    $para_data = array("CreateOrgId"=> 0,"Numbers"=> ["$Number"],"Ids"=> "","InterationFlags"=> "","NetworkCtrl"=> "","IsVerifyProcInst"=> "","IgnoreInterationFlag"=> "","UseBatControlTimes"=> "false");
    $response=$k3api->audit("PUR_PurchaseOrder",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "采购订单审核接口：".$response."\n";
    }
    else{
        assert(false,"采购订单审核接口失败: ".$response."\n");
    }
}

// 本接口用于实现采购订单 (PUR_PurchaseOrder)下推采购订单变更单(PUR_POChange)功能
function PurchaseOrder_push()
{
    global $Number;
    global $k3api;
    global $chaseorderid;
    $para_data =  "{'Ids': '$chaseorderid','Numbers': [],'EntryIds': '','RuleId': 'PUR_PurchaseOrder-PUR_POChange','TargetBillTypeId': 'd588ab5aeb23490aadbb665874b1eabf','TargetOrgId': 0,'TargetFormId': 'PUR_POChange','IsEnableDefaultRule': false,'IsDraftWhenSaveFail': true,'CustomParams': {}}";
    $response=$k3api->push("PUR_PurchaseOrder",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "采购订单下推变更单接口：".$response."\n";
    }
    else{
        assert(false,"采购订单下推变更单接口失败: ".$response."\n");
    }
}



PurchaseOrder_save();
PurchaseOrder_submit();
PurchaseOrder_audit();
PurchaseOrder_push()
?>