<?php
declare(strict_types=1);

// 离线隔离：真实 EasyWeChat 加解密，内存通道/授权模型；不加载应用、不读环境密钥、不访问数据库或网络。
namespace TestWecomCallback {
    final class State
    {
        public static array $suite = [], $authorizations = [], $events = [];
        public static string $aes = '';
        public static int $queries = 0;
    }

    class Record
    {
        public function __construct(private array $data = []) {}
        public function isEmpty(): bool { return $this->data === []; }
        public function __get(string $key): mixed { return $this->data[$key] ?? null; }
    }

    final class Query
    {
        public function __construct(private array $conditions) {}
        public function findOrEmpty(): Record
        {
            State::$queries++;
            foreach (State::$authorizations as $row) {
                foreach ($this->conditions as [$key, $operator, $value]) {
                    if ($operator !== '=') throw new \LogicException('Unexpected query operator');
                    if (($row[$key] ?? null) != $value) continue 2;
                }
                return new Record($row);
            }
            return new Record();
        }
    }
}

namespace core\exception { class CommonException extends \RuntimeException {} }

namespace addon\hsx_wecom\app\model {
    final class WecomProviderSuite extends \TestWecomCallback\Record {}
    final class WecomCorpAuthorization
    {
        public static function where(array $conditions): \TestWecomCallback\Query { return new \TestWecomCallback\Query($conditions); }
    }
}

namespace addon\hsx_wecom\app\support {
    final class WecomProviderCipher
    {
        public static function decrypt(string $value): string
        {
            if ($value !== 'synthetic-cipher-marker') throw new \RuntimeException('Unexpected credential access');
            return \TestWecomCallback\State::$aes;
        }
    }
}

namespace addon\hsx_wecom\app\service\core {
    final class WecomProviderConfigService
    {
        public function byChannel(string $channel): \addon\hsx_wecom\app\model\WecomProviderSuite
        {
            return new \addon\hsx_wecom\app\model\WecomProviderSuite($channel === 'default' ? \TestWecomCallback\State::$suite : []);
        }
    }
    final class WecomProviderAuthorizationService
    {
        public function handleCallbackEvent(\addon\hsx_wecom\app\model\WecomProviderSuite $suite, array $payload): void
        {
            \TestWecomCallback\State::$events[] = $payload;
        }
    }
}

namespace {
    use addon\hsx_wecom\app\service\core\WecomProviderCallbackService;
    use EasyWeChat\Kernel\Encryptor;
    use EasyWeChat\Kernel\Support\Xml;
    use TestWecomCallback\State;

    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    require dirname(__DIR__) . '/app/service/core/WecomProviderCallbackService.php';

    const PROVIDER = 'synthetic-provider-corp';
    const SUITE = 'synthetic-suite';
    const CORP = 'synthetic-customer-corp';
    const TOKEN = 'synthetic-callback-token';
    $passed = 0;

    function check(string $name, bool $condition): void
    {
        global $passed;
        if (!$condition) throw new \RuntimeException('FAIL: ' . $name);
        $passed++;
        echo "PASS: {$name}\n";
    }

    function rejects(string $name, callable $call): void
    {
        $before = count(State::$events);
        try { $call(); } catch (\Throwable $error) {
            check($name, count(State::$events) === $before);
            return;
        }
        check($name, false);
    }

    /** 只在内存中生成模拟密钥；不输出任何密钥、签名或原始消息。 */
    function encrypted(string $plain, string $receiver): array
    {
        $cipher = new Encryptor($receiver, TOKEN, State::$aes, $receiver);
        return Xml::parse($cipher->encrypt($plain, 'synthetic-nonce', '1790000000')) ?? [];
    }

    function url(array $packet, bool $echo = false): string
    {
        $query = ['msg_signature' => $packet['MsgSignature'], 'nonce' => $packet['Nonce'], 'timestamp' => $packet['TimeStamp']];
        if ($echo) $query['echostr'] = $packet['Encrypt'];
        return 'https://example.test/api/wecom/provider/callback/default?' . http_build_query($query);
    }

    function post(WecomProviderCallbackService $service, bool $data, array $payload, string $receiver, array $outer = []): array
    {
        $packet = encrypted(Xml::build($payload), $receiver);
        $envelope = array_replace(['ToUserName' => $receiver, 'Encrypt' => $packet['Encrypt']], $outer);
        return $data
            ? $service->serveData('default', 'POST', url($packet), Xml::build($envelope))
            : $service->serve('default', 'POST', url($packet), Xml::build($envelope));
    }

    State::$aes = rtrim(base64_encode(random_bytes(32)), '=');
    State::$suite = [
        'id' => 7, 'status' => 'preparing', 'provider_corp_id' => PROVIDER,
        'suite_id' => '', 'suite_secret_cipher' => '', 'callback_token' => TOKEN,
        'encoding_aes_key_cipher' => 'synthetic-cipher-marker',
    ];
    $service = new WecomProviderCallbackService();
    $echo = encrypted('synthetic-challenge', PROVIDER);
    foreach (['serve', 'serveData'] as $method) {
        $response = $service->{$method}('default', 'GET', url($echo, true), '');
        check("preparing {$method} 无 SuiteID/Secret 可完成严格 GET 验证", $response['status'] === 200 && $response['body'] === 'synthetic-challenge');
        rejects("preparing {$method} 拒绝业务 POST", fn() => $service->{$method}('default', 'POST', url($echo), '<xml/>'));
        rejects("preparing {$method} 拒绝 POST 伪装 echostr", fn() => $service->{$method}('default', 'POST', url($echo, true), ''));
        rejects("preparing {$method} 拒绝 HEAD", fn() => $service->{$method}('default', 'HEAD', url($echo, true), ''));
        rejects("{$method} GET 缺少 echostr 拒绝", fn() => $service->{$method}('default', 'GET', url($echo), ''));
        $wrong = encrypted('synthetic-challenge', 'other-provider');
        rejects("{$method} GET 拒绝错误接收方", fn() => $service->{$method}('default', 'GET', url($wrong, true), ''));
        $bad = $echo;
        $bad['MsgSignature'] = str_repeat('0', 40);
        rejects("{$method} GET 拒绝坏签名", fn() => $service->{$method}('default', 'GET', url($bad, true), ''));
    }
    check('预配置 GET 不自动启用、不写事件、不查询企业授权', State::$suite['status'] === 'preparing' && State::$events === [] && State::$queries === 0);
    rejects('GET 数组型参数拒绝', fn() => $service->serve('default', 'GET', url($echo, true) . '&echostr[]=x', ''));
    rejects('GET 缺少 nonce 拒绝', fn() => $service->serve('default', 'GET', str_replace('nonce=synthetic-nonce&', '', url($echo, true)), ''));
    rejects('未知通道拒绝', fn() => $service->serve('unknown', 'GET', url($echo, true), ''));
    foreach (['provider_corp_id', 'callback_token'] as $field) {
        $saved = State::$suite[$field];
        State::$suite[$field] = '';
        rejects("GET 缺少 {$field} 拒绝", fn() => $service->serve('default', 'GET', url($echo, true), ''));
        State::$suite[$field] = $saved;
    }
    $savedAes = State::$aes;
    State::$aes = 'invalid';
    rejects('GET 非法 AES 配置拒绝', fn() => $service->serve('default', 'GET', url($echo, true), ''));
    State::$aes = $savedAes;
    State::$suite['status'] = 'disabled';
    foreach (['serve', 'serveData'] as $method) {
        rejects("disabled {$method} GET 始终拒绝", fn() => $service->{$method}('default', 'GET', url($echo, true), ''));
        rejects("disabled {$method} POST 始终拒绝", fn() => $service->{$method}('default', 'POST', url($echo), '<xml/>'));
    }

    State::$suite['status'] = 'enabled';
    State::$suite['suite_id'] = SUITE;
    $event = ['SuiteId' => SUITE, 'InfoType' => 'suite_ticket', 'SuiteTicket' => 'synthetic-ticket', 'TimeStamp' => '1790000000'];
    $result = post($service, false, $event, SUITE);
    check('enabled 指令 POST 继续交给原业务服务', $result['body'] === 'success' && State::$events === [$event]);
    $result = $service->serve('default', 'GET', url($echo, true), '');
    check('enabled GET 仍以服务商 CorpID 验证', $result['body'] === 'synthetic-challenge');
    rejects('指令回调错误密文接收方拒绝', fn() => post($service, false, $event, 'other-suite'));
    rejects('指令回调错误内层 SuiteID 拒绝', fn() => post($service, false, array_replace($event, ['SuiteId' => 'other-suite']), SUITE));
    rejects('指令回调错误外层 SuiteID 拒绝', fn() => post($service, false, $event, SUITE, ['ToUserName' => 'other-suite']));
    rejects('指令回调无 InfoType 拒绝', fn() => post($service, false, ['SuiteId' => SUITE], SUITE));
    rejects('指令回调重复 InfoType 标签拒绝', fn() => post($service, false, array_replace($event, ['InfoType' => ['a', 'b']]), SUITE));
    rejects('指令回调夹带用户消息拒绝', fn() => post($service, false, array_replace($event, ['MsgType' => 'text']), SUITE));
    $bad = encrypted(Xml::build($event), SUITE);
    $bad['MsgSignature'] = str_repeat('f', 40);
    rejects('指令回调坏签名拒绝', fn() => $service->serve('default', 'POST', url($bad), Xml::build(['ToUserName' => SUITE, 'Encrypt' => $bad['Encrypt']])));
    rejects('指令 POST 携带 echostr 拒绝', fn() => $service->serve('default', 'POST', url($echo, true), ''));
    State::$suite['suite_id'] = '';
    rejects('enabled 但 SuiteID 缺失仍拒绝业务 POST', fn() => post($service, false, $event, SUITE));
    State::$suite['suite_id'] = SUITE;

    State::$authorizations = [['id' => 10, 'provider_suite_id' => 7, 'auth_corpid' => CORP, 'status' => 'authorized', 'agent_id' => 1000001]];
    $data = ['ToUserName' => CORP, 'FromUserName' => 'synthetic-user', 'CreateTime' => '1790000000', 'MsgType' => 'text', 'Content' => 'synthetic-body-do-not-store', 'AgentID' => '1000001'];
    $before = count(State::$events);
    $result = post($service, true, $data, CORP, ['AgentID' => '1000001']);
    check('已授权企业数据回调安全 ACK，正文不写入事件业务', $result['status'] === 200 && $result['body'] === 'success' && count(State::$events) === $before);
    $noAgent = $data;
    unset($noAgent['AgentID']);
    check('无 AgentID 数据仍由严格企业授权约束', post($service, true, $noAgent, CORP)['body'] === 'success');
    check('只含外层 AgentID 也按企业授权校验', post($service, true, $noAgent, CORP, ['AgentID' => '1000001'])['body'] === 'success');
    rejects('数据回调外层 AgentID 不匹配拒绝', fn() => post($service, true, $data, CORP, ['AgentID' => '1000002']));
    rejects('数据回调内层 AgentID 不匹配拒绝', fn() => post($service, true, array_replace($data, ['AgentID' => '1000002']), CORP));
    rejects('数据回调 AgentID 非数字拒绝', fn() => post($service, true, array_replace($data, ['AgentID' => '1e6']), CORP));
    rejects('数据回调密文接收方与外层企业不符拒绝', fn() => post($service, true, $data, 'other-corp', ['ToUserName' => CORP]));
    rejects('数据回调内外层企业不符拒绝', fn() => post($service, true, array_replace($data, ['ToUserName' => 'other-corp']), CORP));
    rejects('数据回调内层企业缺失拒绝', fn() => post($service, true, ['MsgType' => 'text'], CORP));
    rejects('数据回调缺少 MsgType 拒绝', fn() => post($service, true, ['ToUserName' => CORP], CORP));
    rejects('数据回调不得处理指令 InfoType', fn() => post($service, true, array_replace($data, ['InfoType' => 'suite_ticket']), CORP));
    rejects('数据回调可选 SuiteID 不匹配拒绝', fn() => post($service, true, array_replace($data, ['SuiteId' => 'other-suite']), CORP));
    rejects('数据回调可选 AuthCorpId 不匹配拒绝', fn() => post($service, true, array_replace($data, ['AuthCorpId' => 'other-corp']), CORP));
    rejects('用户数据错投指令回调拒绝', fn() => post($service, false, $data, CORP));
    rejects('指令错投数据回调拒绝', fn() => post($service, true, $event, SUITE));
    foreach (['changed', 'cancelled', 'error'] as $status) {
        State::$authorizations[0]['status'] = $status;
        rejects("数据企业 {$status} 状态拒绝", fn() => post($service, true, $data, CORP));
    }
    State::$authorizations[0]['status'] = 'authorized';
    State::$authorizations[0]['provider_suite_id'] = 8;
    rejects('同企业只授权其他 Suite 时拒绝', fn() => post($service, true, $data, CORP));
    State::$authorizations[0]['provider_suite_id'] = 7;
    rejects('未授权企业即使共享 Token/AES 也拒绝', fn() => post($service, true, array_replace($data, ['ToUserName' => 'other-corp']), 'other-corp'));
    $bad = encrypted(Xml::build($data), CORP);
    $bad['MsgSignature'] = str_repeat('0', 40);
    $queries = State::$queries;
    rejects('数据坏签名拒绝', fn() => $service->serveData('default', 'POST', url($bad), Xml::build(['ToUserName' => CORP, 'Encrypt' => $bad['Encrypt']])));
    check('坏签名在企业授权查询前被拒绝', State::$queries === $queries);
    rejects('外层 XML 实体声明拒绝', fn() => $service->serveData('default', 'POST', url($bad), '<!DOCTYPE xml [<!ENTITY x "test">]><xml/>'));
    $unsafeXml = encrypted('<!DOCTYPE xml [<!ENTITY x "test">]><xml><ToUserName>' . CORP . '</ToUserName></xml>', CORP);
    rejects('解密后的 XML 实体声明也拒绝', fn() => $service->serveData('default', 'POST', url($unsafeXml), Xml::build(['ToUserName' => CORP, 'Encrypt' => $unsafeXml['Encrypt']])));
    check('数据回调所有场景未落消息正文或误标事件 processed', count(State::$events) === $before);
    $routes = file_get_contents(dirname(__DIR__) . '/app/api/route/route.php');
    check('数据与指令为独立公开回调路由', str_contains($routes, "wecom/provider/data/:channel") && str_contains($routes, 'ProviderCallback@data') && str_contains($routes, 'ProviderCallback@event'));
    $controller = file_get_contents(dirname(__DIR__) . '/app/api/controller/ProviderCallback.php');
    check('数据路由对应独立控制器方法和数据服务入口', str_contains($controller, 'function data(string $channel)') && str_contains($controller, "'serveData'"));
    echo "OK: {$passed} checks; synthetic SDK crypto only, no database or network.\n";
}
