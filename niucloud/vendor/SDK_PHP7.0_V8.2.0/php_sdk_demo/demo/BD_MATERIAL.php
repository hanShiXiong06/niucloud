<?php
require_once 'phar://../kingdee-webapi-sdk-v8.2.0.phar/index.php';
use kingdee_cdp_webapi_sdk\sdk\K3CloudApi;
use kingdee_cdp_webapi_sdk\entity\IdentifyInfo;

$Number = "WL".date("Ymdhis",time());
$material_id = "";
$material_groupid = "";
$material_fileid  = "";
$config=new IdentifyInfo("../config/conf.ini");
$k3api=new K3CloudApi($config);

// 本接口用于实现物料 (BD_Material) 的保存功能 
function material_save()
{
    global $Number;
    global $k3api;
    global  $material_id;
    $para_data = array("NeedUpDateFields"=> [],"NeedReturnFields"=> [],"IsDeleteEntry"=> "true","SubSystemId"=> "","IsVerifyBaseDataField"=> "false","IsEntryBatchFill"=> "true","ValidateFlag"=> "true","NumberSearch"=> "true","IsAutoAdjustField"=> "false","InterationFlags"=> "","IgnoreInterationFlag"=> "","IsControlPrecision"=> "false","ValidateRepeatJson"=> "false","Model"=>array("FName"=> "$Number","FNumber"=> "$Number","FImgStorageType"=> "A","FCreateOrgId"=>array("FNumber"=> "100"),"FUseOrgId"=>array("FNumber"=> "100"),"FSubHeadEntity"=>array("FTimeUnit"=> "H"),"SubHeadEntity"=>array("FErpClsID"=> "1","FFeatureItem"=> "1","FCategoryID"=>array("FNumber"=> "CHLB01_SYS"),"FTaxType"=>array("FNumber"=> "WLDSFL01_SYS"),"FTaxRateId"=>array("FNUMBER"=> "SL02_SYS"),"FBaseUnitId"=>array("FNumber"=> "Pcs"),"FIsPurchase"=> true,"FIsInventory"=> true,"FIsSale"=> true,"FWEIGHTUNITID"=>array("FNUMBER"=> "kg"),"FVOLUMEUNITID"=>array("FNUMBER"=> "m")),"SubHeadEntity1"=>array("FStoreUnitID"=>array("FNumber"=> "Pcs"),"FUnitConvertDir"=> "1","FIsLockStock"=> true,"FCountCycle"=> "1","FCountDay"=> 1,"FCurrencyId"=>array("FNumber"=> "PRE001"),"FSNManageType"=> "1","FSNGenerateTime"=> "1"),"SubHeadEntity2"=>array("FSaleUnitId"=>array("FNumber"=> "Pcs"),"FSalePriceUnitId"=>array("FNumber"=> "Pcs"),"FMaxQty"=> 100000,"FIsReturn"=> true,"FISAFTERSALE"=> true,"FISPRODUCTFILES"=> true,"FWARRANTYUNITID"=> "D","FOutLmtUnit"=> "SAL"),"SubHeadEntity3"=>array("FPurchaseUnitId"=>array("FNumber"=> "Pcs"),"FPurchasePriceUnitId"=>array("FNumber"=> "Pcs"),"FPurchaseOrgId"=>array("FNumber"=> "100"),"FQuotaType"=> "1","FIsReturnMaterial"=> true,"FPOBillTypeId"=>array("FNUMBER"=> "CGSQD01_SYS"),"FPrintCount"=> 1,"FMinPackCount"=> 1),"SubHeadEntity4"=>array("FPlanningStrategy"=> "1","FMfgPolicyId"=>array("FNumber"=> "ZZCL001_SYS"),"FFixLeadTimeType"=> "1","FVarLeadTimeType"=> "1","FCheckLeadTimeType"=> "1","FOrderIntervalTimeType"=> "3","FMaxPOQty"=> 100000,"FEOQ"=> 1,"FVarLeadTimeLotSize"=> 1,"FIsMrpComBill"=> true,"FReserveType"=> "1","FCanDelayDays"=> 999,"FAllowPartDelay"=> true,"FPlanOffsetTimeType"=> "1","FWriteOffQty"=> 1),"SubHeadEntity5"=>array("FProduceUnitId"=>array("FNumber"=> "Pcs"),"FProduceBillType"=>array("FNUMBER"=> "SCDD03_SYS"),"FOrgTrustBillType"=>array("FNUMBER"=> "SCDD06_SYS"),"FBOMUnitId"=>array("FNumber"=> "Pcs"),"FIssueType"=> "1","FOverControlMode"=> "1","FMinIssueQty"=> 1,"FMinIssueUnitId"=>array("FNUMBER"=> "Pcs"),"FStandHourUnitId"=> "3600","FBackFlushType"=> "1"),"SubHeadEntity7"=>array("FSubconUnitId"=>array("FNumber"=> "Pcs"),"FSubconPriceUnitId"=>array("FNumber"=> "Pcs"),"FSubBillType"=>array("FNUMBER"=> "WWDD01_SYS")),"FEntityInvPty"=> [array("FInvPtyId"=>array("FNumber"=> "01"),"FIsEnable"=> true),array("FInvPtyId"=>array("FNumber"=> "02"),"FIsEnable"=> true),array("FInvPtyId"=>array("FNumber"=> "03")),array("FInvPtyId"=>array("FNumber"=> "04")),array("FInvPtyId"=>array("FNumber"=> "06"))]));
    $response=$k3api->save("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        $material_id = $json_response ->Result->Id;
        echo "物料保存接口：".$response."\n";
    }
    else{
        assert(false,"物料保存接口失败: ".$response."\n");
    }
}

// 本接口用于实现物料 (BD_Material) 的提交功能
function material_submit()
{
    global $Number;
    global $k3api;
    $para_data = array("CreateOrgId"=>0,"Numbers"=>["$Number"],"Ids"=>"","SelectedPostId"=>0,"NetworkCtrl"=>"","IgnoreInterationFlag"=>"");
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


// 本接口用于实现物料 (BD_Material) 的审核功能
function material_audit()
{
    global $Number;
    global $k3api;
    $para_data = array("CreateOrgId"=> 0,"Numbers"=> ["$Number"],"Ids"=> "","InterationFlags"=> "","NetworkCtrl"=> "","IsVerifyProcInst"=> "","IgnoreInterationFlag"=> "","UseBatControlTimes"=> "false");
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

// 本接口用于实现物料 (BD_Material) 的分配功能
function material_allocate()
{
    global $Number;
    global $k3api;
    global $material_id;
    $para_data = array("PkIds" => "$material_id", "TOrgIds"=> "100002");
    $response=$k3api->allocate("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料分配接口：".$response."\n";
    }
    else{
        assert(false,"物料分配接口失败: ".$response."\n");
    }
}


// 本接口用于实现物料 (BD_Material) 的取消分配功能
function material_cancelallocate()
{
    global $Number;
    global $k3api;
    global $material_id;
    $para_data = array("PkIds" => "$material_id", "TOrgIds"=> "100002");
    $response=$k3api->cancel_allocate("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料取消分配接口：".$response."\n";
    }
    else{
        assert(false,"物料取消分配接口失败: ".$response."\n");
    }
}

// 本接口用于实现物料 (BD_Material) 的反审核功能
function material_unaudit()
{
    global $Number;
    global $k3api;
    $para_data = array("CreateOrgId"=> 0,"Numbers"=> ["$Number"],"Ids"=> "","InterationFlags"=> "","NetworkCtrl"=> "","IsVerifyProcInst"=> "","IgnoreInterationFlag"=> "","UseBatControlTimes"=> "false");
    $response=$k3api->unaudit("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料反审核接口：".$response."\n";
    }
    else{
        assert(false,"物料反审核接口失败: ".$response."\n");
    }
}

// 本接口用于实现物料 (BD_Material) 的附件上传功能
function material_attachment_upload()
{
    global $Number;
    global $k3api;
    global $material_fileid;
    global $material_id;
    $para_data =  array("FileName"=>"1016.txt","FEntryKey"=>"","FormId"=> "BD_MATERIAL","IsLast"=> true,"InterId"=> "$material_id", "BillNO"=> "$Number","AliasFileName"=> "test","SendByte"=> "6L+Z5piv5rWL6K+V5paH5Lu25ZKMYmFzZTY05a2X56ym5Liy5LqS6L2s");
    
    $response=$k3api->attachment_upload($para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        $material_fileid=$json_response ->Result->FileId;
        echo "物料附件上传接口：".$response."\n";
    }
    else{
        assert(false,"物料附件上传接口失败: ".$response."\n");
    }
}

// 本接口用于实现物料 (BD_Material) 的附件下载功能
function material_attachment_downLoad()
{
    global $Number;
    global $k3api;
    global $material_fileid;
    $para_data = array("FileId"=> "$material_fileid", "StartIndex"=> 0);
    $response=$k3api->attachment_downLoad($para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料附件下载接口：".$response."\n";
    }
    else{
        assert(false,"物料附件下载失败: ".$response."\n");
    }
}


// 本接口用于实现物料 (BD_Material) 的禁用功能
function material_Forbid()
{
    global $Number;
    global $k3api;
    $para_data =  array("CreateOrgId"=> 0,"Numbers"=> ["$Number"],"Ids"=> "","PkEntryIds"=>[],"NetworkCtrl"=> "","IgnoreInterationFlag"=> "");
    $response=$k3api->excute_operation("BD_MATERIAL","Forbid",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料禁用接口：".$response."\n";
    }
    else{
        assert(false,"物料禁用接口失败: ".$response."\n");
    }
}


// 本接口用于实现物料 (BD_Material) 的反禁用功能
function material_enable()
{
    global $Number;
    global $k3api;
    $para_data =  array("CreateOrgId"=> 0,"Numbers"=> ["$Number"],"Ids"=> "","PkEntryIds"=>[],"NetworkCtrl"=> "","IgnoreInterationFlag"=> "");
    $response=$k3api->excute_operation("BD_MATERIAL","enable",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料反禁用接口：".$response."\n";
    }
    else{
        assert(false,"物料反禁用接口失败: ".$response."\n");
    }
}

// 本接口用于实现物料 (BD_Material) 的查看功能
function material_view()
{
    global $Number;
    global $k3api;
    $para_data =  array("CreateOrgId"=> 0,"Number"=> "$Number","Id"=> "","IsSortBySeq"=> "false");
    $response=$k3api->view("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料查看接口：".$response."\n";
    }
    else{
        assert(false,"物料查看接口失败: ".$response."\n");
    }
}


// 本接口用于实现物料 (BD_Material) 的单据查询功能
function material_execute_bill_query()
{
    global $Number;
    global $k3api;
    $para_data =  array("FormId"=> "BD_Material", 
        "FieldKeys"=> "FMATERIALID,FNumber,FName,FCreateOrgId,FUseOrgId.Fnumber", 
        "FilterString"=> [array(
            "Left"=> "(",
            "FieldName"=> "FNumber",
            "Compare"=> "=",
            "Value"=> "$Number",
            "Right"=> ")",
            "Logic"=> "AND"
          )], 
        "OrderString"=> "",
        "TopRowCount"=> 0, 
        "StartRow"=> 0, 
        "Limit"=> 2000, 
        "SubSystemId"=> "");
    $response=$k3api->execute_bill_query($para_data);
    $json_response = json_decode($response);
    echo "物料单据查询接口：".$response."\n";
}

// 本接口用于实现物料 (BD_Material) 的单据查询(json)功能
function material_billBillQuery()
{
    global $Number;
    global $k3api;
    $para_data =  array("FormId"=> "BD_Material", 
        "FieldKeys"=> "FMATERIALID,FNumber,FName,FCreateOrgId,FUseOrgId.Fnumber", 
        "FilterString"=> [array(
            "Left"=> "(",
            "FieldName"=> "FNumber",
            "Compare"=> "=",
            "Value"=> "$Number",
            "Right"=> ")",
            "Logic"=> "AND"
          )], 
        "OrderString"=> "",
        "TopRowCount"=> 0, 
        "StartRow"=> 0, 
        "Limit"=> 2000, 
        "SubSystemId"=> "");
    $response=$k3api->bill_query($para_data);
    $json_response = json_decode($response);
    echo "物料单据查询接口：".$response."\n";
}

// 本接口用于实现物料 (BD_Material) 的删除功能
function material_delete()
{
    global $Number;
    global $k3api;
    $para_data = array("CreateOrgId"=> 0,"Numbers"=> "$Number","Id"=> "","IsSortBySeq"=> "false");
    $response=$k3api->delete("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料删除接口：".$response."\n";
    }
    else{
        assert(false,"物料删除接口失败: ".$response."\n");
    }
}

// 本接口用于实现物料 (BD_Material) 的分组保存功能
function material_groupsave()
{
    global $Number;
    global $k3api;
    global $material_groupid;
    $para_data =  array("GroupFieldKey"=> "","GroupPkId"=> 0,"FParentId"=> 0,"FNumber"=> "$Number","FName"=> "$Number","FDescription"=> "");
    $response=$k3api->group_save("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
     
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料分组保存接口：".$response."\n";
        $SuccessEntitys = $json_response->Result->ResponseStatus->SuccessEntitys;
        $material_groupid = $SuccessEntitys[0]->Id;

    }
    else{
        assert(false,"物料分组保存接口失败: ".$response."\n");
    }
}



// 本接口用于实现物料 (BD_Material) 的分组查询功能
function material_querygroupinfo()
{
    global $Number;
    global $k3api;
    global $material_groupid;
    $para_data =  array("FormId"=> "BD_MATERIAL", "GroupFieldKey"=> "FMaterialGroup", "GroupPkIds"=> "$material_groupid", "Ids"=> "");
    $response=$k3api->query_group_info($para_data);
    $json_response = json_decode($response);
     
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料分组查询接口：".$response."\n";
    }
    else{
        assert(false,"物料分组查询接口失败: ".$response."\n");
    }
}


// 本接口用于实现物料 (BD_Material) 的分组删除功能
function material_groupdelete()
{
    global $Number;
    global $k3api;
    global $material_groupid;
    $para_data = array("FormId"=> "BD_MATERIAL", "GroupFieldKey"=> "FMaterialGroup", "GroupPkIds"=> "$material_groupid");
    $response=$k3api->group_delete($para_data);
    $json_response = json_decode($response);
     
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料分组删除接口：".$response."\n";
    }
    else{
        assert(false,"物料分组删除接口失败: ".$response."\n");
    }
}



// 本接口用于实现物料 (BD_Material) 的切换组织功能
function material_switch_org()
{
    global $Number;
    global $k3api;
    $para_data =  array("OrgNumber"=> "200");
    $response=$k3api->switch_org($para_data);
    // 切换组织后重新切换回来
    $para_data2 = array("OrgNumber"=> "100");
    $response=$k3api->switch_org($para_data2);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料切换组织接口：".$response."\n";
    }
    else{
        assert(false,"物料切换组织接口失败: ".$response."\n");
    }
}



// 本接口用于实现物料 (BD_Material) 的批量保存功能
function material_batch_save()
{
    global $Number;
    global $k3api;
    // 
    $para_data =  array("NumberSearch"=>"true","ValidateFlag"=>"true","IsDeleteEntry"=>"true","IsEntryBatchFill"=>"true","NeedUpDateFields"=>[],"NeedReturnFields"=>[],"SubSystemId"=>"","InterationFlags"=>"","Model"=>[array("FName"=> "$Number","FNumber"=> "$Number","FImgStorageType"=> "A","FCreateOrgId"=> array("FNumber"=> "100"),"FUseOrgId"=> array("FNumber"=> "100"),"FSubHeadEntity"=> array("FTimeUnit"=> "H"),"SubHeadEntity"=> array("FErpClsID"=> "1","FFeatureItem"=> "1","FCategoryID"=> array("FNumber"=> "CHLB01_SYS"),"FTaxType"=> array("FNumber"=> "WLDSFL01_SYS"),"FTaxRateId"=> array("FNUMBER"=> "SL02_SYS"),"FBaseUnitId"=> array("FNumber"=> "Pcs"),"FIsPurchase"=> true,"FIsInventory"=> true,"FIsSale"=> true,"FWEIGHTUNITID"=> array("FNUMBER"=> "kg"),"FVOLUMEUNITID"=> array("FNUMBER"=> "m")),"SubHeadEntity1"=> array("FStoreUnitID"=> array("FNumber"=> "Pcs"),"FUnitConvertDir"=> "1","FIsLockStock"=> true,"FCountCycle"=> "1","FCountDay"=> 1,"FCurrencyId"=> array("FNumber"=> "PRE001"),"FSNManageType"=> "1","FSNGenerateTime"=> "1"),"SubHeadEntity2"=> array("FSaleUnitId"=> array("FNumber"=> "Pcs"),"FSalePriceUnitId"=> array("FNumber"=> "Pcs"),"FMaxQty"=> 100000,"FIsReturn"=> true,"FISAFTERSALE"=> true,"FISPRODUCTFILES"=> true,"FWARRANTYUNITID"=> "D","FOutLmtUnit"=> "SAL"),"SubHeadEntity3"=> array("FPurchaseUnitId"=> array("FNumber"=> "Pcs"),"FPurchasePriceUnitId"=> array("FNumber"=> "Pcs"),"FPurchaseOrgId"=> array("FNumber"=> "100"),"FQuotaType"=> "1","FIsReturnMaterial"=> true,"FPOBillTypeId"=> array("FNUMBER"=> "CGSQD01_SYS"),"FPrintCount"=> 1,"FMinPackCount"=> 1),"SubHeadEntity4"=> array("FPlanningStrategy"=> "1","FMfgPolicyId"=> array("FNumber"=> "ZZCL001_SYS"),"FFixLeadTimeType"=> "1","FVarLeadTimeType"=> "1","FCheckLeadTimeType"=> "1","FOrderIntervalTimeType"=> "3","FMaxPOQty"=> 100000,"FEOQ"=> 1,"FVarLeadTimeLotSize"=> 1,"FIsMrpComBill"=> true,"FReserveType"=> "1","FCanDelayDays"=> 999,"FAllowPartDelay"=> true,"FPlanOffsetTimeType"=> "1","FWriteOffQty"=> 1),"SubHeadEntity5"=> array("FProduceUnitId"=> array("FNumber"=> "Pcs"),"FProduceBillType"=> array("FNUMBER"=> "SCDD03_SYS"),"FOrgTrustBillType"=> array("FNUMBER"=> "SCDD06_SYS"),"FBOMUnitId"=> array("FNumber"=> "Pcs"),"FIssueType"=> "1","FOverControlMode"=> "1","FMinIssueQty"=> 1,"FMinIssueUnitId"=> array("FNUMBER"=> "Pcs"),"FStandHourUnitId"=> "3600","FBackFlushType"=> "1"),"SubHeadEntity7"=> array("FSubconUnitId"=> array("FNumber"=> "Pcs"),"FSubconPriceUnitId"=> array("FNumber"=> "Pcs"),"FSubBillType"=> array("FNUMBER"=> "WWDD01_SYS")),"FEntityInvPty"=> [array("FInvPtyId"=> array("FNumber"=> "01"),"FIsEnable"=> true),array("FInvPtyId"=> array("FNumber"=> "02"),"FIsEnable"=> true),array("FInvPtyId"=> array("FNumber"=> "03")),array("FInvPtyId"=> array("FNumber"=> "04")),array("FInvPtyId"=> array("FNumber"=> "06"))])],"BatchCount"=>"1","IsVerifyBaseDataField"=>"false","IsAutoAdjustField"=>"false","IgnoreInterationFlag"=>"false","IsControlPrecision"=>"false","ValidateRepeatJson"=>"false");
    $response=$k3api->batch_save("BD_MATERIAL",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料批量保存接口：".$response."\n";
    }
    else{
        assert(false,"物料批量保存接口失败: ".$response."\n");
    }
}



// 本接口用于实现发送消息功能
function material_send_msg()
{
    global $Number;
    global $k3api;
    $para_data = array("Model"=>[array("FTitle"=>"我是标题","FContent"=>"我是内容","FReceivers"=>"demo","FType"=>"1")]);
    $response=$k3api->send_msg($para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "发送消息接口：".$response."\n";
    }
    else{
        assert(false,"发送消息接口失败: ".$response."\n");
    }
}


// 本接口用于实现元数据查询功能
function material_querybusinessinfo()
{
    global $Number;
    global $k3api;
    $para_data = array("FormId"=>"BD_MATERIAL");
    $response=$k3api->query_business_info($para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->ResponseStatus->IsSuccess)
    {
        echo "物料元数据查询接口：".$response."\n";
    }
    else{
        assert(false,"物料元数据查询接口失败: ".$response."\n");
    }
}




material_save();
material_submit();
material_allocate();
material_cancelallocate();
material_audit();
material_unaudit();
material_attachment_upload();
material_attachment_downLoad();
material_Forbid();
material_enable();
// material_view();
material_execute_bill_query();
material_billBillQuery();
material_delete();
material_groupsave();
material_querygroupinfo();
material_groupdelete();
material_switch_org();
material_send_msg();
material_batch_save();
material_querybusinessinfo();


?> 