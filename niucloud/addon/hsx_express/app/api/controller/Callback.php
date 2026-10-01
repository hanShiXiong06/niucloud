<?php
declare(strict_types=1);
namespace addon\hsx_express\app\api\controller;

use addon\hsx_express\app\service\core\LogisticsService;

final class Callback
{
    public function receive(int $site_id, string $task_no)
    {
        try {
            if ($site_id <= 0 || !preg_match(LogisticsService::TASK_NUMBER_PATTERN, $task_no)) throw new \RuntimeException('invalid task');
            return json((new LogisticsService())->callback($site_id, $task_no, request()->post()));
        } catch (\Throwable $e) {
            // 响应不暴露数据库、凭据或地址，失败ACK供服务商按协议重试。
            return json(['result' => false, 'returnCode' => '500', 'message' => '任务校验或处理失败，请稍后重试'], 503);
        }
    }
}
