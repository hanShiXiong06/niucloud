<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\notice\FinancialNoticeService;
use core\base\BaseJob;
final class RecoverFinancialNotices extends BaseJob
{
    public function doJob($data=[]): string
    {
        return json_encode((new FinancialNoticeService())->recover(50)+['message'=>'处理已启用站点的新资金流水及接单提醒计划；受理不代表终端送达，结果不明不自动重发'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
}
