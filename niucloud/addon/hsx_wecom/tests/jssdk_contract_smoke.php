<?php
declare(strict_types=1);

$client = file_get_contents(dirname(__DIR__) . '/app/service/core/WecomClient.php');
if ($client === false) throw new RuntimeException('无法读取 WecomClient.php');

$assertContains = static function (string $needle, string $content, string $message): void {
    if (!str_contains($content, $needle)) throw new RuntimeException($message);
};

$assertContains("'config_js_api_list' => \$configJsApiList", $client, 'wx.config 必须使用独立的企业身份 JSAPI 清单');
$assertContains("'agent_js_api_list' => \$agentJsApiList", $client, 'agentConfig 必须使用独立的应用身份 JSAPI 清单');
$assertContains("\$configJsApiList = ['checkJsApi']", $client, '普通 wx.config 不得注册应用级选客或建群能力');
$assertContains("\$agentJsApiList = ['selectExternalContact', 'openEnterpriseChat']", $client, '应用级能力清单必须包含选客与建群');
$assertContains("\$path = '/ticket/get'", $client, 'agentConfig 必须使用应用级 ticket 接口');
$assertContains("\$query['type'] = 'agent_config'", $client, '应用级 ticket 必须声明 agent_config 类型');
$assertContains('hsx_wecom_jsapi_ticket_v2_', $client, '修正 ticket 接口后必须隔离旧缓存');

echo "hsx_wecom jssdk contract smoke passed\n";
