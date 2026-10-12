<?php

require_once 'phar://../kingdee-webapi-sdk-v8.2.0.phar/index.php';
use kingdee_cdp_webapi_sdk\sdk\K3CloudApi;
use kingdee_cdp_webapi_sdk\entity\IdentifyInfo;

$config=new IdentifyInfo("../config/conf.ini");
$k3api=new K3CloudApi($config);

// 本接口用于实现科目余额表 (GL_RPT_AccountBalance) 的查询报表数据功能功能 
function gl_rpt_accountBalance()
{
    global $Number;
    global $k3api;
    global  $material_id;
    $para_data =  array("FieldKeys"=> "FBALANCEID,FBALANCENAME,FACCTTYPE,FACCTGROUP,FDETAILNUMBER,FDETAILNAME","SchemeId"=> "","StartRow"=> 0,"Limit"=> 2000,"IsVerifyBaseDataField"=> "false","Model"=> array("FACCTBOOKID"=> array("FNumber"=> "001"),"FCURRENCY"=> "1","FSTARTYEAR"=> "2021","FSTARTPERIOD"=> "12","FENDYEAR"=> "2021","FBALANCELEVEL"=> "1","FENDPERIOD"=> "12","FFORBIDBALANCE"=> true,"FBALANCEZERO"=> true,"FPERIODNOBALANCE"=> true,"FYEARNOBALANCE"=> true));
    $response=$k3api->get_sys_report_data("GL_RPT_AccountBalance",$para_data);
    $json_response = json_decode($response);
    if ($json_response ->Result->IsSuccess)
    {
        echo "科目余额表账表查询接口：".$response."\n";
    }
    else{
        assert(false,"科目余额表账表查询接口失败: ".$response."\n");
    }
}


gl_rpt_accountBalance();

?>