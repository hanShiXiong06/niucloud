<?php
require_once 'phar://../kingdee-webapi-sdk-v8.2.0.phar/index.php';
use kingdee_cdp_webapi_sdk\sdk\K3CloudApi;
use kingdee_cdp_webapi_sdk\entity\IdentifyInfo;

$Number = "WL".date("Ymdhis",time());
$material_id = "";
$material_groupid = "";
$config=new IdentifyInfo("../config/conf.ini");
$k3api=new K3CloudApi($config);

// 本接口用于实现物料 (BD_Material) 的暂存功能 
function material_draft()
{
    global $Number;
    global $k3api;
    global  $material_id;
    $para_data =  array("NeedUpDateFields"=> [],"NeedReturnFields"=> [],"IsDeleteEntry"=> "true","SubSystemId"=> "","IsVerifyBaseDataField"=> "false","IsEntryBatchFill"=> "true","ValidateFlag"=> "true","NumberSearch"=> "true","IsAutoAdjustField"=> "false","InterationFlags"=> "","IgnoreInterationFlag"=> "","IsControlPrecision"=> "false","ValidateRepeatJson"=> "false","Model"=>array("FName"=> "$Number","FNumber"=> "$Number","FImgStorageType"=> "A","FCreateOrgId"=>array("FNumber"=> "100"),"FUseOrgId"=>array("FNumber"=> "100"),"FSubHeadEntity"=>array("FTimeUnit"=> "H"),"SubHeadEntity"=>array("FErpClsID"=> "1","FFeatureItem"=> "1","FCategoryID"=>array("FNumber"=> "CHLB01_SYS"),"FTaxType"=>array("FNumber"=> "WLDSFL01_SYS"),"FTaxRateId"=>array("FNUMBER"=> "SL02_SYS"),"FBaseUnitId"=>array("FNumber"=> "Pcs"),"FIsPurchase"=> true,"FIsInventory"=> true,"FIsSale"=> true,"FWEIGHTUNITID"=>array("FNUMBER"=> "kg"),"FVOLUMEUNITID"=>array("FNUMBER"=> "m")),"SubHeadEntity1"=>array("FStoreUnitID"=>array("FNumber"=> "Pcs"),"FUnitConvertDir"=> "1","FIsLockStock"=> true,"FCountCycle"=> "1","FCountDay"=> 1,"FCurrencyId"=>array("FNumber"=> "PRE001"),"FSNManageType"=> "1","FSNGenerateTime"=> "1"),"SubHeadEntity2"=>array("FSaleUnitId"=>array("FNumber"=> "Pcs"),"FSalePriceUnitId"=>array("FNumber"=> "Pcs"),"FMaxQty"=> 100000,"FIsReturn"=> true,"FISAFTERSALE"=> true,"FISPRODUCTFILES"=> true,"FWARRANTYUNITID"=> "D","FOutLmtUnit"=> "SAL"),"SubHeadEntity3"=>array("FPurchaseUnitId"=>array("FNumber"=> "Pcs"),"FPurchasePriceUnitId"=>array("FNumber"=> "Pcs"),"FPurchaseOrgId"=>array("FNumber"=> "100"),"FQuotaType"=> "1","FIsReturnMaterial"=> true,"FPOBillTypeId"=>array("FNUMBER"=> "CGSQD01_SYS"),"FPrintCount"=> 1,"FMinPackCount"=> 1),"SubHeadEntity4"=>array("FPlanningStrategy"=> "1","FMfgPolicyId"=>array("FNumber"=> "ZZCL001_SYS"),"FFixLeadTimeType"=> "1","FVarLeadTimeType"=> "1","FCheckLeadTimeType"=> "1","FOrderIntervalTimeType"=> "3","FMaxPOQty"=> 100000,"FEOQ"=> 1,"FVarLeadTimeLotSize"=> 1,"FIsMrpComBill"=> true,"FReserveType"=> "1","FCanDelayDays"=> 999,"FAllowPartDelay"=> true,"FPlanOffsetTimeType"=> "1","FWriteOffQty"=> 1),"SubHeadEntity5"=>array("FProduceUnitId"=>array("FNumber"=> "Pcs"),"FProduceBillType"=>array("FNUMBER"=> "SCDD03_SYS"),"FOrgTrustBillType"=>array("FNUMBER"=> "SCDD06_SYS"),"FBOMUnitId"=>array("FNumber"=> "Pcs"),"FIssueType"=> "1","FOverControlMode"=> "1","FMinIssueQty"=> 1,"FMinIssueUnitId"=>array("FNUMBER"=> "Pcs"),"FStandHourUnitId"=> "3600","FBackFlushType"=> "1"),"SubHeadEntity7"=>array("FSubconUnitId"=>array("FNumber"=> "Pcs"),"FSubconPriceUnitId"=>array("FNumber"=> "Pcs"),"FSubBillType"=>array("FNUMBER"=> "WWDD01_SYS")),"FEntityInvPty"=> [array("FInvPtyId"=>array("FNumber"=> "01"),"FIsEnable"=> true),array("FInvPtyId"=>array("FNumber"=> "02"),"FIsEnable"=> true),array("FInvPtyId"=>array("FNumber"=> "03")),array("FInvPtyId"=>array("FNumber"=> "04")),array("FInvPtyId"=>array("FNumber"=> "06"))]));
    $response=$k3api->draft("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        $material_id = $json_response ->Result->Id;
        echo "物料暂存接口：".$response."\n";
    }
    else{
        assert(false,"物料暂存接口失败: ".$response."\n");
    }
}

// 本接口用于实现物料 (BD_Material) 的提交功能
function material_submit()
{
    global $Number;
    global $k3api;
    $para_data =  array("CreateOrgId"=>0,"Numbers"=>["$Number"],"Ids"=>"","SelectedPostId"=>0,"NetworkCtrl"=>"","IgnoreInterationFlag"=>"");
    $response=$k3api->submit("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料提交接口：".$response."\n";
    }
    else{
        assert(false,"物料提交接口失败: ".$response."\n");
    }
}


// 本接口用于实现物料 (BD_Material) 的撤销功能
function material_cancelassign()
{
    global $Number;
    global $k3api;
    $para_data =  array("CreateOrgId"=>0, "Numbers"=>["$Number"], "Ids"=>"", "NetworkCtrl"=>"");
    $response=$k3api->cancel_assign("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料撤销接口：".$response."\n";
    }
    else{
        assert(false,"物料撤销接口失败: ".$response."\n");
    }
}


// 本接口用于实现物料 (BD_Material) 的审核功能
function material_audit()
{
    global $Number;
    global $k3api;
    $para_data =  array("CreateOrgId"=> 0,"Numbers"=> ["$Number"],"Ids"=> "","InterationFlags"=> "","NetworkCtrl"=> "","IsVerifyProcInst"=> "","IgnoreInterationFlag"=> "","UseBatControlTimes"=> "false");
    $response=$k3api->audit("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料审核接口：".$response."\n";
    }
    else{
        assert(false,"物料审核接口失败: ".$response."\n");
    }
}


// 本接口用于实现物料 (BD_Material) 的弹性域保存功能
function material_flexsave()
{
    global $Number;
    global $k3api;
    $para_data =  array("Model"=>[array("FFLEX8"=>array("FNumber"=> "$Number"))]);
    $response=$k3api->flex_save("BD_FLEXITEMDETAILV",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料弹性域保存接口：".$response."\n";
    }
    else{
        assert(false,"物料弹性域保存接口失败: ".$response."\n");
    }
}

material_draft();
material_submit();
material_cancelassign();
material_submit();
material_audit();
material_flexsave()


?>