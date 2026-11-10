<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\cooperation\CooperationNoticeOutbox;
use core\base\BaseJob;
final class RecoverCooperationNotices extends BaseJob
{
    public function doJob($data=[]): string
    {
        $result=CooperationNoticeOutbox::recover(5,20);
        return json_encode($result+['message'=>'processed仅代表事件处理完成；渠道受理、拒绝、待配置或结果不明请查看通知记录，不代表用户已收到'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
}
