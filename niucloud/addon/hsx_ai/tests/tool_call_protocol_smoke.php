<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/service/core/AiToolCallProtocolService.php';

use addon\hsx_ai\app\service\core\AiToolCallProtocolService;

$service = new AiToolCallProtocolService();
$protocol = "**回收作业助手**\n按全店查询回收工作各环节滞留情况：\nঞ那么多的function<｜tool▁sep｜>hsx_hsx_recycle_workflow_summary\n\n{\"mine\":false,\"filters\":{\"status\":\"pending\"}}\n```<｜tool▁call▁end｜><｜tool▁calls▁end｜>";
$parsed = $service->parse($protocol);
if (count($parsed['tool_calls']) !== 1) throw new RuntimeException('未识别文本工具调用');
if (($parsed['tool_calls'][0]['function']['name'] ?? '') !== 'hsx_hsx_recycle_workflow_summary') throw new RuntimeException('工具名解析错误');
if (json_decode((string)$parsed['tool_calls'][0]['function']['arguments'], true)['mine'] !== false) throw new RuntimeException('工具参数解析错误');
if ($parsed['content'] !== '') throw new RuntimeException('工具协议不应作为用户正文显示');

$state = $service->streamState();
$visible = '';
foreach (["正常的流", "式回答。", "function<｜tool", "▁sep｜>hsx_demo\n{}<｜tool▁call▁end｜>"] as $chunk) {
    $visible .= $service->push($state, $chunk);
}
$visible .= $service->finish($state);
if (str_contains($visible, 'tool') || str_contains($visible, 'hsx_demo')) throw new RuntimeException('流式协议发生泄漏');

$state = $service->streamState();
$visible = $service->push($state, '这是一段普通的中文流式回答，没有工具调用。');
$visible .= $service->finish($state);
if ($visible !== '这是一段普通的中文流式回答，没有工具调用。') throw new RuntimeException('普通流式正文被错误修改');

echo "hsx_ai text tool call protocol smoke passed\n";
